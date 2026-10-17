<?php
/**
 * Design Cart Migration
 * Code: design-cart-migration
 * Autor: Paweł Nosko — https://www.designcart.pl/pawel-nosko.html
 * Firma: Design Cart — https://www.designcart.pl/
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serialized-safe search/replace with automatic URL variant pairs.
 */
final class DCCM_Replacer {
    /** @var array<int, array{0:string,1:string}> */
    private $pairs = [];

    /** @var bool */
    private $case_insensitive = false;

    /** @var bool */
    private $replace_guids = false;

    /**
     * @param string $search  Old URL / string.
     * @param string $replace New URL / string.
     * @param bool   $smart_urls Expand common URL variants (protocol, slash, escaped).
     * @param bool   $case_insensitive
     * @param bool   $replace_guids
     */
    public function __construct(
        string $search,
        string $replace,
        bool $smart_urls = true,
        bool $case_insensitive = false,
        bool $replace_guids = false
    ) {
        $this->case_insensitive = $case_insensitive;
        $this->replace_guids = $replace_guids;
        $this->pairs = $smart_urls
            ? self::build_url_pairs($search, $replace)
            : [[$search, $replace]];
    }

    /**
     * Build search/replace pairs covering common URL encodings.
     *
     * @return array<int, array{0:string,1:string}>
     */
    public static function build_url_pairs(string $from, string $to): array {
        $from = trim($from);
        $to = trim($to);

        if ($from === '' || $from === $to) {
            return $from === '' ? [] : [[$from, $to]];
        }

        $variants = [];

        $add = static function (string $a, string $b) use (&$variants): void {
            if ($a === '' || $a === $b) {
                return;
            }
            $variants[$a] = $b;
        };

        $bases = [
            [$from, $to],
        ];

        // Protocol flip http <-> https.
        foreach ([[$from, $to]] as [$a, $b]) {
            if (stripos($a, 'https://') === 0) {
                $bases[] = ['http://' . substr($a, 8), self::flip_protocol($b, 'http')];
            } elseif (stripos($a, 'http://') === 0) {
                $bases[] = ['https://' . substr($a, 7), self::flip_protocol($b, 'https')];
            }
        }

        foreach ($bases as [$a, $b]) {
            $a_slash = rtrim($a, '/');
            $b_slash = rtrim($b, '/');
            $a_trail = $a_slash . '/';
            $b_trail = $b_slash . '/';

            // Longer first so trailing-slash forms win before shorter ones.
            $add($a_trail, $b_trail);
            $add($a_slash, $b_slash);

            // Protocol-relative.
            if (preg_match('#^https?://(.+)$#i', $a_slash, $m)) {
                $to_host = preg_replace('#^https?://#i', '', $b_slash);
                $add('//' . $m[1] . '/', '//' . $to_host . '/');
                $add('//' . $m[1], '//' . $to_host);
            }

            // JSON / JS escaped slashes.
            $add(str_replace('/', '\\/', $a_trail), str_replace('/', '\\/', $b_trail));
            $add(str_replace('/', '\\/', $a_slash), str_replace('/', '\\/', $b_slash));

            // Double-escaped (rare in DB dumps / options).
            $add(str_replace('/', '\\\\/', $a_trail), str_replace('/', '\\\\/', $b_trail));
            $add(str_replace('/', '\\\\/', $a_slash), str_replace('/', '\\\\/', $b_slash));
        }

        // Sort longest search first to avoid partial collisions.
        uksort($variants, static function ($x, $y) {
            return strlen($y) <=> strlen($x);
        });

        $pairs = [];
        foreach ($variants as $search => $replace) {
            $pairs[] = [$search, $replace];
        }

        return $pairs;
    }

    private static function flip_protocol(string $url, string $scheme): string {
        if (preg_match('#^https?://(.*)$#i', $url, $m)) {
            return $scheme . '://' . $m[1];
        }

        return $url;
    }

    /**
     * @return array<int, array{0:string,1:string}>
     */
    public function get_pairs(): array {
        return $this->pairs;
    }

    public function replace_guids(): bool {
        return $this->replace_guids;
    }

    /**
     * Apply all pairs to a single DB cell value.
     *
     * Walks JSON and PHP-serialized structures by decoding first, so escaped
     * forms like http:\/\/host are replaced correctly after json_decode().
     *
     * @param mixed $data
     * @return mixed
     */
    public function replace_value($data) {
        if ($this->pairs === []) {
            return $data;
        }

        try {
            return $this->deep_replace($data);
        } catch (Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            return $data;
        }
    }

    /**
     * Count how many cells would change in a table page (dry preview helper).
     *
     * @param array<string, mixed> $row
     * @param array<int, string>   $primary_keys
     * @param string               $table
     * @return array{changed:bool,row:array<string,mixed>}
     */
    public function replace_row(array $row, array $primary_keys, string $table): array {
        global $wpdb;

        $changed = false;
        $out = $row;

        foreach ($row as $column => $value) {
            if (in_array($column, $primary_keys, true)) {
                continue;
            }

            if (!$this->replace_guids && strtolower((string) $column) === 'guid') {
                continue;
            }

            // Skip this plugin's own transient/option keys when touching options.
            if ($wpdb->options === $table && is_string($value)) {
                if (strpos($value, 'dccm_') === 0 || strpos($value, '_transient_dccm_') === 0) {
                    continue;
                }
            }

            $edited = $this->replace_value($value);
            if ($edited !== $value) {
                $out[$column] = $edited;
                $changed = true;
            }
        }

        return ['changed' => $changed, 'row' => $out];
    }

    /**
     * Deep walk: decode JSON / serialized once, replace leaves with all pairs.
     *
     * @param mixed $data
     * @return mixed
     */
    private function deep_replace($data) {
        if (is_string($data)) {
            if ($this->looks_like_json($data)) {
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                    $replaced = $this->deep_replace($decoded);
                    // Keep Woo-style escaped slashes when the original had them.
                    $flags = JSON_UNESCAPED_UNICODE;
                    if (strpos($data, '\\/') === false) {
                        $flags |= JSON_UNESCAPED_SLASHES;
                    }
                    $encoded = wp_json_encode($replaced, $flags);
                    if (is_string($encoded)) {
                        return $encoded;
                    }
                }
            }

            $unserialized = $this->safe_unserialize($data);
            if ($unserialized !== false) {
                return serialize($this->deep_replace($unserialized));
            }

            return $this->replace_plain_string($data);
        }

        if (is_array($data)) {
            $tmp = [];
            foreach ($data as $key => $value) {
                $new_key = $key;
                if (is_string($key) && $key !== '') {
                    $new_key = $this->replace_plain_string($key);
                }
                $tmp[$new_key] = $this->deep_replace($value);
            }

            return $tmp;
        }

        if (is_object($data) && $this->is_cloneable($data)) {
            $tmp = clone $data;
            foreach (get_object_vars($data) as $key => $value) {
                if (!is_string($key) || preg_match('/^\x00/', $key)) {
                    continue;
                }
                $tmp->$key = $this->deep_replace($value);
            }

            return $tmp;
        }

        return $data;
    }

    /**
     * Apply every search/replace pair to a plain (non-structured) string.
     */
    private function replace_plain_string(string $data): string {
        if (!$this->string_may_contain_search($data)) {
            return $data;
        }

        foreach ($this->pairs as [$from, $to]) {
            $data = $this->str_replace($from, $to, $data);
        }

        return $data;
    }

    /**
     * Fast reject: true if any pair needle (or its slash-unescaped form) appears.
     */
    private function string_may_contain_search(string $data): bool {
        foreach ($this->pairs as [$from]) {
            if ($from === '') {
                continue;
            }
            if ($this->case_insensitive) {
                if (stripos($data, $from) !== false) {
                    return true;
                }
            } elseif (strpos($data, $from) !== false) {
                return true;
            }
        }

        return false;
    }

    private function looks_like_json(string $data): bool {
        $trim = ltrim($data);
        if ($trim === '') {
            return false;
        }
        $first = $trim[0];

        return $first === '{' || $first === '[';
    }

    /**
     * @return mixed|false
     */
    private function safe_unserialize(string $data) {
        if (!is_serialized($data)) {
            return false;
        }

        $data = trim($data);

        return @unserialize($data, ['allowed_classes' => false]); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
    }

    private function str_replace(string $from, string $to, string $data): string {
        if ($this->case_insensitive) {
            return str_ireplace($from, $to, $data);
        }

        return str_replace($from, $to, $data);
    }

    private function is_cloneable(object $object): bool {
        try {
            return (new ReflectionClass($object))->isCloneable();
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    public static function get_tables(): array {
        global $wpdb;

        if (function_exists('is_multisite') && is_multisite()) {
            if (is_main_site()) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                return $wpdb->get_col('SHOW TABLES');
            }

            $blog_id = get_current_blog_id();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_col(
                $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->base_prefix . $blog_id) . '\_%')
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col('SHOW TABLES');
    }

    /**
     * Approximate on-disk size per table (data + indexes), keyed by table name.
     *
     * @return array<string, int>
     */
    public static function get_table_sizes(): array {
        global $wpdb;

        $tables = self::get_tables();
        if ($tables === []) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT TABLE_NAME AS name, (DATA_LENGTH + INDEX_LENGTH) AS bytes
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()",
            ARRAY_A
        );

        $sizes = array_fill_keys($tables, 0);
        if (!is_array($rows)) {
            return $sizes;
        }

        $allowed = array_fill_keys($tables, true);
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '' || !isset($allowed[$name])) {
                continue;
            }
            $sizes[$name] = max(0, (int) ($row['bytes'] ?? 0));
        }

        return $sizes;
    }

    /**
     * @return array{0:?array<int,string>,1:array<int,string>}
     */
    public static function get_columns(string $table): array {
        global $wpdb;

        $primary = [];
        $columns = [];

        if (!in_array($table, self::get_tables(), true)) {
            return [null, $columns];
        }

        $safe = str_replace('`', '``', $table);
        // Table name allowlisted via get_tables(); backtick-escaped.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $fields = $wpdb->get_results("DESCRIBE `{$safe}`");

        if (is_array($fields)) {
            foreach ($fields as $column) {
                $columns[] = $column->Field;
                if ($column->Key === 'PRI') {
                    $primary[] = $column->Field;
                }
            }
        }

        return [$primary === [] ? null : $primary, $columns];
    }

    /**
     * Run live replace on one table page.
     *
     * @param array<string, mixed> $args
     * @return array{table_complete:bool,change:int,updates:int,errors:array<int,string>}
     */
    public function process_table_page(string $table, int $page, int $page_size, bool $dry_run): array {
        global $wpdb;

        $report = [
            'table_complete' => true,
            'change' => 0,
            'updates' => 0,
            'errors' => [],
        ];

        if (!in_array($table, self::get_tables(), true)) {
            return $report;
        }

        list($primary_keys, $columns) = self::get_columns($table);
        if (empty($primary_keys) || $columns === []) {
            return $report;
        }

        $safe = str_replace('`', '``', $table);
        $offset = $page * $page_size;

        // Table name is allowlisted via get_tables(); only LIMIT uses placeholders.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- dynamic table name
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$safe}` LIMIT %d, %d", $offset, $page_size),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$safe}`");
        $pages = (int) ceil($total / max(1, $page_size));
        $report['table_complete'] = $page >= max(0, $pages - 1);

        if (!is_array($rows)) {
            return $report;
        }

        foreach ($rows as $row) {
            $result = $this->replace_row($row, $primary_keys, $table);
            if (!$result['changed']) {
                continue;
            }

            $report['change']++;

            if ($dry_run) {
                continue;
            }

            $set_sql = [];
            $set_values = [];
            foreach ($columns as $column) {
                if (in_array($column, $primary_keys, true)) {
                    continue;
                }
                if ($result['row'][$column] !== $row[$column]) {
                    $set_sql[] = '`' . str_replace('`', '``', $column) . '` = %s';
                    $set_values[] = $result['row'][$column];
                }
            }

            $where_sql = [];
            $where_values = [];
            foreach ($primary_keys as $pk) {
                $where_sql[] = '`' . str_replace('`', '``', $pk) . '` = %s';
                $where_values[] = $row[$pk];
            }

            if ($set_sql === [] || $where_sql === []) {
                continue;
            }

            $sql = "UPDATE `{$safe}` SET " . implode(', ', $set_sql) . ' WHERE ' . implode(' AND ', $where_sql);
            $prepare_args = array_merge([$sql], $set_values, $where_values);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $ok = $wpdb->query(call_user_func_array([$wpdb, 'prepare'], $prepare_args));

            if ($ok === false) {
                $report['errors'][] = sprintf(
                    /* translators: %s: table name */
                    __('Błąd aktualizacji wiersza w tabeli %s.', 'design-cart-migration'),
                    $table
                );
            } else {
                $report['updates']++;
            }
        }

        return $report;
    }
}
