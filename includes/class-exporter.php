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
 * Streams a full SQL dump with optional URL replacement applied to cell values.
 */
final class DCCM_Exporter {
    public const JOB_TTL = HOUR_IN_SECONDS;

    /** @var DCCM_Replacer|null */
    private $replacer;

    /** @var int */
    private $chunk = 200;

    /** @var resource|null */
    private $output_handle = null;

    public function __construct(?DCCM_Replacer $replacer = null) {
        $this->replacer = $replacer;
    }

    /**
     * Start an AJAX export job. Returns token + progress seed.
     *
     * @param array<int, string>|null $tables
     * @return array{token:string,tables_total:int,filename:string}
     */
    public function start_job(
        string $search,
        string $replace,
        bool $smart,
        bool $guids,
        ?array $tables = null
    ): array {
        $all = DCCM_Replacer::get_tables();
        if ($tables === null || $tables === []) {
            $tables = $all;
        } else {
            $tables = array_values(array_intersect($tables, $all));
        }

        if ($tables === []) {
            throw new RuntimeException(esc_html__('Brak tabel do eksportu.', 'design-cart-migration'));
        }

        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $slug = is_string($host) ? preg_replace('/[^a-z0-9\-]+/i', '-', $host) : 'wordpress';
        $filename = sanitize_file_name('dccm-' . $slug . '-' . gmdate('Ymd-His') . '.sql');
        if ($filename === '') {
            $filename = 'dccm-export.sql';
        }

        $tmp = wp_tempnam($filename);
        if (!is_string($tmp) || $tmp === '') {
            throw new RuntimeException(esc_html__('Nie udało się utworzyć pliku tymczasowego.', 'design-cart-migration'));
        }

        $token = wp_generate_password(32, false, false);
        $job = [
            'user_id' => get_current_user_id(),
            'file' => $tmp,
            'filename' => $filename,
            'tables' => $tables,
            'table_index' => 0,
            'offset' => 0,
            'header_done' => false,
            'footer_done' => false,
            'complete' => false,
            'search' => $search,
            'replace' => $replace,
            'smart' => $smart,
            'guids' => $guids,
            'bytes' => 0,
            'created' => time(),
        ];

        set_transient(self::job_key($token), $job, self::JOB_TTL);

        return [
            'token' => $token,
            'tables_total' => count($tables),
            'filename' => $filename,
        ];
    }

    /**
     * Process one batch of the export job.
     *
     * @return array<string, mixed>
     */
    public function process_job_batch(string $token): array {
        $job = get_transient(self::job_key($token));
        if (!is_array($job) || (int) ($job['user_id'] ?? 0) !== get_current_user_id()) {
            throw new RuntimeException(esc_html__('Sesja eksportu wygasła. Uruchom ponownie.', 'design-cart-migration'));
        }

        if (!empty($job['complete'])) {
            return $this->job_progress_payload($token, $job);
        }

        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged,WordPress.PHP.NoSilencedErrors.Discouraged -- long export batches
            @set_time_limit(120);
        }
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $file = (string) ($job['file'] ?? '');
        if ($file === '' || !file_exists($file)) {
            throw new RuntimeException(esc_html__('Plik tymczasowy eksportu nie istnieje.', 'design-cart-migration'));
        }

        $this->replacer = new DCCM_Replacer(
            (string) $job['search'],
            (string) $job['replace'],
            !empty($job['smart']),
            false,
            !empty($job['guids'])
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming append for large SQL dumps
        $handle = fopen($file, 'ab');
        if ($handle === false) {
            throw new RuntimeException(esc_html__('Nie udało się otworzyć pliku eksportu.', 'design-cart-migration'));
        }

        $this->output_handle = $handle;

        try {
            if (empty($job['header_done'])) {
                $this->write_header();
                $job['header_done'] = true;
            }

            $tables = is_array($job['tables'] ?? null) ? $job['tables'] : [];
            $index = (int) ($job['table_index'] ?? 0);
            $offset = (int) ($job['offset'] ?? 0);

            if ($index < count($tables)) {
                $table = (string) $tables[$index];
                $result = $this->export_table_chunk($table, $offset);
                if (!empty($result['complete'])) {
                    $job['table_index'] = $index + 1;
                    $job['offset'] = 0;
                } else {
                    $job['offset'] = (int) $result['next_offset'];
                }
            }

            if ((int) $job['table_index'] >= count($tables) && empty($job['footer_done'])) {
                $this->write_footer();
                $job['footer_done'] = true;
                $job['complete'] = true;
            }
        } finally {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- paired with fopen above
            fflush($handle);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- paired with fopen above
            fclose($handle);
            $this->output_handle = null;
        }

        clearstatcache(true, $file);
        $size = filesize($file);
        $job['bytes'] = is_int($size) ? $size : 0;

        set_transient(self::job_key($token), $job, self::JOB_TTL);

        return $this->job_progress_payload($token, $job);
    }

    /**
     * Serve a completed job file as download and clean up.
     */
    public static function download_job(string $token): void {
        $job = get_transient(self::job_key($token));
        if (!is_array($job) || (int) ($job['user_id'] ?? 0) !== get_current_user_id()) {
            wp_die(esc_html__('Sesja eksportu wygasła.', 'design-cart-migration'));
        }

        if (empty($job['complete'])) {
            wp_die(esc_html__('Eksport nie jest jeszcze gotowy.', 'design-cart-migration'));
        }

        $file = (string) ($job['file'] ?? '');
        $filename = sanitize_file_name((string) ($job['filename'] ?? 'dccm-export.sql'));
        if ($filename === '') {
            $filename = 'dccm-export.sql';
        }

        if ($file === '' || !is_readable($file)) {
            delete_transient(self::job_key($token));
            wp_die(esc_html__('Plik eksportu nie istnieje.', 'design-cart-migration'));
        }

        $size = filesize($file);
        if ($size === false || $size < 1) {
            wp_delete_file($file);
            delete_transient(self::job_key($token));
            wp_die(esc_html__('Wygenerowany dump jest pusty.', 'design-cart-migration'));
        }

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged,WordPress.PHP.NoSilencedErrors.Discouraged -- disable gzip for binary download
        @ini_set('zlib.output_compression', 'Off');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) $size);
        header('Content-Transfer-Encoding: binary');
        header('X-Content-Type-Options: nosniff');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: public');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($file);
        wp_delete_file($file);
        delete_transient(self::job_key($token));
        exit;
    }

    /**
     * Legacy one-shot download (kept for fallback / CLI).
     *
     * @param array<int, string>|null $tables Null = all tables.
     */
    public function download(?array $tables = null, string $filename = ''): void {
        if ($filename === '') {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
            $slug = is_string($host) ? preg_replace('/[^a-z0-9\-]+/i', '-', $host) : 'wordpress';
            $filename = 'dccm-' . $slug . '-' . gmdate('Ymd-His') . '.sql';
        }

        $filename = sanitize_file_name($filename);
        if ($filename === '') {
            $filename = 'dccm-export.sql';
        }

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged,WordPress.PHP.NoSilencedErrors.Discouraged -- disable gzip for binary download
        @ini_set('zlib.output_compression', 'Off');

        $tmp = wp_tempnam($filename);
        if (!is_string($tmp) || $tmp === '') {
            wp_die(esc_html__('Nie udało się utworzyć pliku tymczasowego.', 'design-cart-migration'));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming write for large SQL dumps
        $handle = fopen($tmp, 'wb');
        if ($handle === false) {
            wp_delete_file($tmp);
            wp_die(esc_html__('Nie udało się otworzyć pliku tymczasowego.', 'design-cart-migration'));
        }

        $this->output_handle = $handle;
        try {
            $this->stream($tables);
        } catch (Throwable $e) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- paired with fopen above
            fclose($handle);
            wp_delete_file($tmp);
            wp_die(esc_html__('Błąd podczas generowania dumpa SQL.', 'design-cart-migration'));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- paired with fopen above
        fclose($handle);
        $this->output_handle = null;

        $size = filesize($tmp);
        if ($size === false || $size < 1) {
            wp_delete_file($tmp);
            wp_die(esc_html__('Wygenerowany dump jest pusty.', 'design-cart-migration'));
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) $size);
        header('Content-Transfer-Encoding: binary');
        header('X-Content-Type-Options: nosniff');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: public');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($tmp);
        wp_delete_file($tmp);
        exit;
    }

    /**
     * @param array<int, string>|null $tables
     */
    public function stream(?array $tables = null): void {
        $all = DCCM_Replacer::get_tables();
        if ($tables === null || $tables === []) {
            $tables = $all;
        } else {
            $tables = array_values(array_intersect($tables, $all));
        }

        $this->write_header();
        foreach ($tables as $table) {
            $this->export_table($table);
        }
        $this->write_footer();
    }

    public static function job_key(string $token): string {
        return 'dccm_export_' . $token;
    }

    /**
     * Find newest completed export job for a user (for resume download UI).
     *
     * @return array{token:string,filename:string,bytes:int,download_url:string}|null
     */
    public static function find_ready_job_for_user(int $user_id): ?array {
        global $wpdb;

        if ($user_id <= 0) {
            return null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 30",
                $wpdb->esc_like('_transient_dccm_export_') . '%'
            )
        );

        if (!is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            $name = (string) $row->option_name;
            if (strpos($name, '_transient_timeout_') === 0) {
                continue;
            }
            $token = substr($name, strlen('_transient_dccm_export_'));
            if ($token === '') {
                continue;
            }
            $job = maybe_unserialize($row->option_value);
            if (!is_array($job) || (int) ($job['user_id'] ?? 0) !== $user_id) {
                continue;
            }
            if (empty($job['complete'])) {
                continue;
            }
            $file = (string) ($job['file'] ?? '');
            if ($file === '' || !is_readable($file)) {
                continue;
            }
            $size = filesize($file);
            if ($size === false || $size < 1) {
                continue;
            }

            return [
                'token' => $token,
                'filename' => (string) ($job['filename'] ?? 'dccm-export.sql'),
                'bytes' => (int) $size,
                'download_url' => add_query_arg(
                    [
                        'action' => 'dccm_export_download',
                        'token' => $token,
                        '_wpnonce' => wp_create_nonce('dccm_export_download_' . $token),
                    ],
                    admin_url('admin-post.php')
                ),
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function job_progress_payload(string $token, array $job): array {
        $tables = is_array($job['tables'] ?? null) ? $job['tables'] : [];
        $total = max(1, count($tables));
        $index = min((int) ($job['table_index'] ?? 0), $total);
        $complete = !empty($job['complete']);
        $percent = $complete ? 100 : (int) floor(($index / $total) * 100);
        $current = '';
        if (!$complete && isset($tables[$index])) {
            $current = (string) $tables[$index];
        } elseif ($complete && $tables !== []) {
            $current = (string) $tables[count($tables) - 1];
        }

        return [
            'token' => $token,
            'complete' => $complete,
            'percent' => max(0, min(100, $percent)),
            'tables_done' => $index,
            'tables_total' => $total,
            'current_table' => $current,
            'bytes' => (int) ($job['bytes'] ?? 0),
            'filename' => (string) ($job['filename'] ?? ''),
            'download_url' => $complete
                ? add_query_arg(
                    [
                        'action' => 'dccm_export_download',
                        'token' => $token,
                        '_wpnonce' => wp_create_nonce('dccm_export_download_' . $token),
                    ],
                    admin_url('admin-post.php')
                )
                : '',
        ];
    }

    private function write_header(): void {
        $this->out("-- Design Cart Migration SQL dump\n");
        $this->out('-- Code: design-cart-migration' . "\n");
        $this->out('-- Generated: ' . gmdate('c') . " UTC\n");
        $this->out('-- Site: ' . home_url() . "\n");
        if ($this->replacer !== null) {
            $this->out("-- Search/replace pairs applied during export:\n");
            foreach ($this->replacer->get_pairs() as [$from, $to]) {
                $this->out('--   ' . $this->comment_safe($from) . ' => ' . $this->comment_safe($to) . "\n");
            }
        }
        $this->out("\nSET NAMES utf8mb4;\n");
        $this->out("SET FOREIGN_KEY_CHECKS=0;\n");
        $this->out("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
    }

    private function write_footer(): void {
        $this->out("\nSET FOREIGN_KEY_CHECKS=1;\n");
        $this->out("-- End of dump\n");
    }

    private function export_table(string $table): void {
        $offset = 0;
        $first = true;
        while (true) {
            $result = $this->export_table_chunk($table, $offset, $first);
            $first = false;
            if (!empty($result['complete'])) {
                break;
            }
            $offset = (int) $result['next_offset'];
        }
    }

    /**
     * @return array{complete:bool,next_offset:int}
     */
    private function export_table_chunk(string $table, int $offset, bool $write_create = true): array {
        global $wpdb;

        $safe = str_replace('`', '``', $table);

        if ($offset === 0 && $write_create) {
            // Table name is allowlisted via DCCM_Replacer::get_tables(); backtick-escaped.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$safe}`", ARRAY_N);
            if (!is_array($create) || empty($create[1])) {
                $this->out("-- SKIP (no CREATE): {$table}\n\n");
                return ['complete' => true, 'next_offset' => 0];
            }

            $this->out("-- ----------------------------\n");
            $this->out("-- Table: {$table}\n");
            $this->out("-- ----------------------------\n");
            $this->out("DROP TABLE IF EXISTS `{$safe}`;\n");
            $this->out($create[1] . ";\n\n");
        }

        list($primary_keys) = DCCM_Replacer::get_columns($table);

        // Table name is allowlisted via get_tables(); only LIMIT uses placeholders.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- dynamic table name
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$safe}` LIMIT %d, %d", $offset, $this->chunk),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!is_array($rows) || $rows === []) {
            if ($offset === 0) {
                $this->out("\n");
            } else {
                $this->out("\n");
            }
            return ['complete' => true, 'next_offset' => $offset];
        }

        foreach ($rows as $row) {
            if ($this->replacer !== null) {
                $keys = is_array($primary_keys) ? $primary_keys : [];
                $result = $this->replacer->replace_row($row, $keys, $table);
                $row = $result['row'];
            }

            $this->out($this->build_insert($safe, $row) . "\n");
        }

        $next = $offset + $this->chunk;
        if (count($rows) < $this->chunk) {
            $this->out("\n");
            return ['complete' => true, 'next_offset' => $next];
        }

        return ['complete' => false, 'next_offset' => $next];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function build_insert(string $safe_table, array $row): string {
        $cols = [];
        $vals = [];

        foreach ($row as $column => $value) {
            $cols[] = '`' . str_replace('`', '``', (string) $column) . '`';
            $vals[] = $this->sql_value($value);
        }

        return 'INSERT INTO `' . $safe_table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ');';
    }

    /**
     * @param mixed $value
     */
    private function sql_value($value): string {
        global $wpdb;

        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($wpdb->dbh && $wpdb->use_mysqli) {
            // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_real_escape_string
            return "'" . mysqli_real_escape_string($wpdb->dbh, (string) $value) . "'";
        }

        return "'" . str_replace(
            ["\\", "\0", "\n", "\r", "'", '"', "\x1a"],
            ["\\\\", '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
            (string) $value
        ) . "'";
    }

    private function out(string $chunk): void {
        if ($this->output_handle !== null) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
            fwrite($this->output_handle, $chunk);
            return;
        }

        echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw SQL stream
    }

    private function comment_safe(string $text): string {
        return str_replace(["\n", "\r"], ' ', $text);
    }
}
