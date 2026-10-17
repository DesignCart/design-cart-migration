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

final class DCCM_Admin {
    public const PAGE_DASH = 'design-cart-migration';
    public const PAGE_EXPORT = 'design-cart-migration-export';
    public const PAGE_REPLACE = 'design-cart-migration-replace';

    /** @var self|null */
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('admin_body_class', [$this, 'admin_body_class']);
        DCCM_Admin_Handlers::boot();
    }

    public function register_menu(): void {
        add_menu_page(
            __('Migration', 'design-cart-migration'),
            __('Migration', 'design-cart-migration'),
            'manage_options',
            self::PAGE_DASH,
            ['DCCM_Admin_Dashboard', 'render'],
            'dashicons-migrate',
            59
        );

        add_submenu_page(
            self::PAGE_DASH,
            __('Kokpit', 'design-cart-migration'),
            __('Kokpit', 'design-cart-migration'),
            'manage_options',
            self::PAGE_DASH,
            ['DCCM_Admin_Dashboard', 'render']
        );

        add_submenu_page(
            self::PAGE_DASH,
            __('Eksport SQL', 'design-cart-migration'),
            __('Eksport SQL', 'design-cart-migration'),
            'manage_options',
            self::PAGE_EXPORT,
            ['DCCM_Admin_Export', 'render']
        );

        add_submenu_page(
            self::PAGE_DASH,
            __('Zamiana na żywo', 'design-cart-migration'),
            __('Zamiana na żywo', 'design-cart-migration'),
            'manage_options',
            self::PAGE_REPLACE,
            ['DCCM_Admin_Replace', 'render']
        );
    }

    public function admin_body_class(string $classes): string {
        if ($this->is_plugin_page()) {
            $classes .= ' dccm-admin';
        }

        return $classes;
    }

    public function enqueue_assets(string $hook): void {
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        $base = DCCM_URL . 'assets/admin/';
        $css = $base . 'css/';
        $js = $base . 'js/';
        $ver = DCCM_VERSION;

        wp_enqueue_style(
            'dccm-font-awesome',
            $base . 'vendor/font-awesome/font-awesome.min.css',
            [],
            '4.7.0'
        );

        // Absolute @font-face so icons survive CSS concatenation / relative-path breakage.
        $fa_woff2 = esc_url($base . 'vendor/font-awesome/fonts/fontawesome-webfont.woff2');
        $fa_woff = esc_url($base . 'vendor/font-awesome/fonts/fontawesome-webfont.woff');
        $fa_ttf = esc_url($base . 'vendor/font-awesome/fonts/fontawesome-webfont.ttf');
        wp_add_inline_style(
            'dccm-font-awesome',
            "@font-face{font-family:'FontAwesome';src:url('{$fa_woff2}') format('woff2')," .
            "url('{$fa_woff}') format('woff'),url('{$fa_ttf}') format('truetype');" .
            "font-weight:normal;font-style:normal;font-display:block;}"
        );

        $sheets = [
            'dc-tokens',
            'dc-hero',
            'dc-layout',
            'dc-tabs',
            'dc-accordion',
            'dc-switch',
            'dc-dimension',
            'dc-forms',
            'dc-colorpicker',
        ];
        $prev = 'dccm-font-awesome';
        foreach ($sheets as $sheet) {
            $handle = 'dccm-' . $sheet;
            wp_enqueue_style($handle, $css . $sheet . '.css', [$prev], $ver);
            $prev = $handle;
        }

        wp_enqueue_style('dccm-admin-overrides', $css . 'admin-overrides.css', [$prev], $ver);
        wp_enqueue_style('dccm-admin', $css . 'admin.css', ['dccm-admin-overrides'], $ver);

        wp_enqueue_script('dccm-interface', $js . 'dc-interface.js', [], $ver, true);
        wp_enqueue_script('dccm-admin', $js . 'admin.js', ['jquery', 'dccm-interface'], $ver, true);

        wp_localize_script('dccm-admin', 'dccmAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dccm_admin'),
            'i18n' => [
                'running' => __('Przetwarzanie…', 'design-cart-migration'),
                'done' => __('Gotowe — pobieranie pliku…', 'design-cart-migration'),
                'doneTitle' => __('Dump gotowy', 'design-cart-migration'),
                'doneHint' => __(
                    'Plik jest gotowy. Jeśli pobieranie nie wystartowało automatycznie, kliknij przycisk poniżej.',
                    'design-cart-migration'
                ),
                'downloadAgain' => __('Pobierz plik SQL', 'design-cart-migration'),
                'close' => __('Zamknij', 'design-cart-migration'),
                'error' => __('Wystąpił błąd.', 'design-cart-migration'),
                'exportTitle' => __('Generowanie dumpa SQL', 'design-cart-migration'),
                'exportHint' => __(
                    'Trwa generowanie pliku. Nie zamykaj i nie przełączaj tej karty przeglądarki.',
                    'design-cart-migration'
                ),
                'exportTable' => __('Tabela', 'design-cart-migration'),
                'replaceTitle' => __('Zamiana na żywo', 'design-cart-migration'),
                'replaceHint' => __(
                    'Trwa przetwarzanie bazy. Nie zamykaj i nie przełączaj tej karty przeglądarki.',
                    'design-cart-migration'
                ),
                'confirmLive' => __(
                    'Zamiana na żywo zmieni dane w bieżącej bazie. Zrób kopię zapasową. Kontynuować?',
                    'design-cart-migration'
                ),
                'selectAll' => __('Zaznacz wszystkie', 'design-cart-migration'),
                'selectNone' => __('Odznacz wszystkie', 'design-cart-migration'),
            ],
        ]);
    }

    private function is_plugin_page(string $hook = ''): bool {
        $page = DCCM_Admin_Helpers::query_text('page');
        if (in_array($page, [self::PAGE_DASH, self::PAGE_EXPORT, self::PAGE_REPLACE], true)) {
            return true;
        }

        if ($hook === '') {
            return false;
        }

        return strpos($hook, self::PAGE_DASH) !== false;
    }
}
