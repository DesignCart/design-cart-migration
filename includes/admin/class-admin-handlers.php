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

final class DCCM_Admin_Handlers {
    public static function boot(): void {
        add_action('admin_post_dccm_export_sql', [self::class, 'handle_export']);
        add_action('admin_post_dccm_export_download', [self::class, 'handle_export_download']);
        add_action('admin_post_dccm_live_replace', [self::class, 'handle_live_replace']);
        add_action('wp_ajax_dccm_export_start', [self::class, 'ajax_export_start']);
        add_action('wp_ajax_dccm_export_batch', [self::class, 'ajax_export_batch']);
        add_action('wp_ajax_dccm_live_replace_batch', [self::class, 'ajax_live_replace_batch']);
    }

    public static function handle_export_download(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Brak uprawnień.', 'design-cart-migration'));
        }

        $token = sanitize_text_field(wp_unslash((string) ($_GET['token'] ?? '')));
        if ($token === '') {
            wp_die(esc_html__('Brak tokenu eksportu.', 'design-cart-migration'));
        }

        check_admin_referer('dccm_export_download_' . $token);
        DCCM_Exporter::download_job($token);
    }

    public static function ajax_export_start(): void {
        check_ajax_referer('dccm_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Brak uprawnień.', 'design-cart-migration')]);
        }

        $search = DCCM_Admin_Helpers::normalize_url(DCCM_Admin_Helpers::posted_text('search_for'));
        $replace = DCCM_Admin_Helpers::normalize_url(DCCM_Admin_Helpers::posted_text('replace_with'));

        if ($search === '' || $replace === '') {
            wp_send_json_error(['message' => __('Podaj stary i nowy adres URL.', 'design-cart-migration')]);
        }

        $smart = DCCM_Admin_Helpers::posted_bool('smart_urls');
        $guids = DCCM_Admin_Helpers::posted_bool('replace_guids');
        $tables = DCCM_Admin_Helpers::posted_tables();

        try {
            $exporter = new DCCM_Exporter();
            $job = $exporter->start_job($search, $replace, $smart, $guids, $tables === [] ? null : $tables);
            wp_send_json_success($job);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_export_batch(): void {
        check_ajax_referer('dccm_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Brak uprawnień.', 'design-cart-migration')]);
        }

        $token = sanitize_text_field(wp_unslash((string) ($_POST['token'] ?? '')));
        if ($token === '') {
            wp_send_json_error(['message' => __('Brak tokenu eksportu.', 'design-cart-migration')]);
        }

        try {
            $exporter = new DCCM_Exporter();
            $progress = $exporter->process_job_batch($token);
            wp_send_json_success($progress);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function handle_export(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Brak uprawnień.', 'design-cart-migration'));
        }

        check_admin_referer('dccm_export_sql', 'dccm_nonce');

        $search = DCCM_Admin_Helpers::normalize_url(DCCM_Admin_Helpers::posted_text('search_for'));
        $replace = DCCM_Admin_Helpers::normalize_url(DCCM_Admin_Helpers::posted_text('replace_with'));

        if ($search === '' || $replace === '') {
            wp_die(esc_html__('Podaj stary i nowy adres URL.', 'design-cart-migration'));
        }

        $smart = DCCM_Admin_Helpers::posted_bool('smart_urls');
        $guids = DCCM_Admin_Helpers::posted_bool('replace_guids');
        $tables = DCCM_Admin_Helpers::posted_tables();

        // Raise limits for large dumps.
        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged,WordPress.PHP.NoSilencedErrors.Discouraged -- full DB export
            @set_time_limit(0);
        }
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $replacer = new DCCM_Replacer($search, $replace, $smart, false, $guids);
        $exporter = new DCCM_Exporter($replacer);
        $exporter->download($tables === [] ? null : $tables);
    }

    public static function handle_live_replace(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Brak uprawnień.', 'design-cart-migration'));
        }

        check_admin_referer('dccm_live_replace', 'dccm_nonce');

        $search = DCCM_Admin_Helpers::normalize_url(DCCM_Admin_Helpers::posted_text('search_for'));
        $replace = DCCM_Admin_Helpers::normalize_url(DCCM_Admin_Helpers::posted_text('replace_with'));

        if ($search === '' || $replace === '') {
            wp_safe_redirect(
                add_query_arg(
                    ['dccm_err' => 'urls'],
                    DCCM_Admin_Helpers::page_url(DCCM_Admin::PAGE_REPLACE)
                )
            );
            exit;
        }

        $dry_run = DCCM_Admin_Helpers::posted_bool('dry_run');
        $smart = DCCM_Admin_Helpers::posted_bool('smart_urls');
        $ci = DCCM_Admin_Helpers::posted_bool('case_insensitive');
        $guids = DCCM_Admin_Helpers::posted_bool('replace_guids');
        $tables = DCCM_Admin_Helpers::posted_tables();

        if ($tables === []) {
            $tables = DCCM_Replacer::get_tables();
        } else {
            $tables = array_values(array_intersect($tables, DCCM_Replacer::get_tables()));
        }

        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged,WordPress.PHP.NoSilencedErrors.Discouraged -- live DB replace
            @set_time_limit(0);
        }
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $replacer = new DCCM_Replacer($search, $replace, $smart, $ci, $guids);
        $page_size = 500;
        $report = [
            'dry_run' => $dry_run,
            'change' => 0,
            'updates' => 0,
            'errors' => [],
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $page = 0;
            $table_change = 0;
            $table_updates = 0;

            do {
                $result = $replacer->process_table_page($table, $page, $page_size, $dry_run);
                $table_change += (int) $result['change'];
                $table_updates += (int) $result['updates'];
                if (!empty($result['errors'])) {
                    $report['errors'] = array_merge($report['errors'], $result['errors']);
                }
                $done = !empty($result['table_complete']);
                $page++;
            } while (!$done && $page < 100000);

            $report['tables'][$table] = [
                'change' => $table_change,
                'updates' => $table_updates,
            ];
            $report['change'] += $table_change;
            $report['updates'] += $table_updates;
        }

        set_transient('dccm_replace_report', $report, 10 * MINUTE_IN_SECONDS);

        wp_safe_redirect(DCCM_Admin_Helpers::page_url(DCCM_Admin::PAGE_REPLACE));
        exit;
    }

    /**
     * Optional AJAX batch endpoint (for future progress UI).
     */
    public static function ajax_live_replace_batch(): void {
        check_ajax_referer('dccm_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Brak uprawnień.', 'design-cart-migration')]);
        }

        $search = DCCM_Admin_Helpers::normalize_url(
            sanitize_text_field(wp_unslash((string) ($_POST['search_for'] ?? '')))
        );
        $replace = DCCM_Admin_Helpers::normalize_url(
            sanitize_text_field(wp_unslash((string) ($_POST['replace_with'] ?? '')))
        );
        $table = sanitize_text_field(wp_unslash((string) ($_POST['table'] ?? '')));
        $page = absint($_POST['page'] ?? 0);
        $dry_run = !empty($_POST['dry_run']);
        $smart = !empty($_POST['smart_urls']);
        $ci = !empty($_POST['case_insensitive']);
        $guids = !empty($_POST['replace_guids']);

        if ($search === '' || $replace === '' || $table === '') {
            wp_send_json_error(['message' => __('Nieprawidłowe parametry.', 'design-cart-migration')]);
        }

        if (!in_array($table, DCCM_Replacer::get_tables(), true)) {
            wp_send_json_error(['message' => __('Nieznana tabela.', 'design-cart-migration')]);
        }

        $replacer = new DCCM_Replacer($search, $replace, $smart, $ci, $guids);
        $result = $replacer->process_table_page($table, $page, 500, $dry_run);

        wp_send_json_success($result);
    }
}
