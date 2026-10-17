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

final class DCCM_Admin_Helpers {
    /**
     * @param array<int, array<string, string>> $actions
     * @param array<int, array<string, string>> $crumbs
     */
    public static function render_hero(string $title, array $actions, array $crumbs, string $icon = 'fa fa-database'): void {
        $logo = DCCM_URL . 'assets/admin/images/dc-logo-white.png';

        echo '<div class="dc-hero">';
        echo '<div class="dc-hero__mesh"></div>';
        echo '<img class="dc-hero__logo" src="' . esc_url($logo) . '" alt="" aria-hidden="true" width="384" height="384">';
        echo '<div class="dc-hero__orb dc-hero__orb--1"></div>';
        echo '<div class="dc-hero__orb dc-hero__orb--2"></div>';
        echo '<div class="dc-hero__inner"><div class="dc-hero__row">';
        echo '<div class="dc-hero__brand">';
        echo '<span class="dc-hero__icon"><i class="' . esc_attr($icon) . '"></i></span>';
        echo '<div><p class="dc-hero__eyebrow">Design Cart</p>';
        echo '<h1 class="dc-hero__title">' . esc_html($title) . '</h1></div></div>';
        echo '<div class="dc-hero__actions">';

        foreach ($actions as $action) {
            $class = $action['class'] ?? 'dc-btn dc-btn--light';
            $icon_cls = $action['icon'] ?? '';
            $label = $action['label'] ?? '';
            $title_attr = $action['title'] ?? '';

            if (!empty($action['form'])) {
                echo '<button type="submit" form="' . esc_attr($action['form']) . '" class="' . esc_attr($class) . '"';
                if ($title_attr !== '') {
                    echo ' title="' . esc_attr($title_attr) . '"';
                }
                echo '>';
                if ($icon_cls !== '') {
                    echo '<i class="' . esc_attr($icon_cls) . '"></i> ';
                }
                echo esc_html($label) . '</button>';
            } else {
                echo '<a class="' . esc_attr($class) . '" href="' . esc_url($action['href'] ?? '') . '"';
                if ($title_attr !== '') {
                    echo ' title="' . esc_attr($title_attr) . '"';
                }
                echo '>';
                if ($icon_cls !== '') {
                    echo '<i class="' . esc_attr($icon_cls) . '"></i> ';
                }
                echo esc_html($label) . '</a>';
            }
        }

        echo '</div></div><ul class="dc-hero__bc">';
        foreach ($crumbs as $crumb) {
            if (!empty($crumb['href'])) {
                echo '<li><a href="' . esc_url($crumb['href']) . '">' . esc_html($crumb['label']) . '</a></li>';
            } else {
                echo '<li>' . esc_html($crumb['label']) . '</li>';
            }
        }
        echo '</ul></div></div>';
    }

    public static function page_start(string $title): void {
        echo '<div class="wrap dccm-wrap"><h1>' . esc_html($title) . '</h1>';
        echo '<div class="dc-page dccm-page">';
    }

    public static function page_end(): void {
        echo '</div></div>';
    }

    public static function section_start(string $icon, string $title, string $sub = ''): void {
        echo '<div class="dc-section-card dc-section">';
        echo '<div class="dc-section-card__head">';
        echo '<span class="dc-section-card__icon"><i class="' . esc_attr($icon) . '"></i></span>';
        echo '<div><h3 class="dc-section-card__title">' . esc_html($title) . '</h3>';
        if ($sub !== '') {
            echo '<p class="dc-section-card__sub">' . esc_html($sub) . '</p>';
        }
        echo '</div></div>';
    }

    public static function section_end(): void {
        echo '</div>';
    }

    public static function text_field(string $name, string $id, string $label, string $value, string $placeholder = '', bool $required = false): void {
        $req = $required ? ' dc-label--required' : '';
        echo '<div class="dc-field">';
        echo '<label class="dc-label' . esc_attr($req) . '" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        echo '<input class="dc-input" type="url" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"';
        if ($placeholder !== '') {
            echo ' placeholder="' . esc_attr($placeholder) . '"';
        }
        if ($required) {
            echo ' required';
        }
        echo '>';
        echo '</div>';
    }

    public static function toggle(string $name, string $id, string $label, bool $checked): void {
        echo '<label class="dc-toggle">';
        echo '<input class="dc-toggle__input" type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . checked($checked, true, false) . '>';
        echo '<span class="dc-toggle__track"></span>';
        echo '<span>' . esc_html($label) . '</span>';
        echo '</label>';
    }

    /**
     * @param array<int, string> $tables
     * @param array<int, string> $selected
     */
    public static function tables_checklist(array $tables, array $selected = [], string $name = 'tables[]'): void {
        if ($selected === []) {
            $selected = $tables;
        }

        $sizes = DCCM_Replacer::get_table_sizes();
        uasort(
            $tables,
            static function (string $a, string $b) use ($sizes): int {
                $sa = $sizes[$a] ?? 0;
                $sb = $sizes[$b] ?? 0;
                if ($sa === $sb) {
                    return strcasecmp($a, $b);
                }

                return $sb <=> $sa;
            }
        );
        // uasort keeps keys; reindex for foreach order.
        $tables = array_values($tables);

        echo '<div class="dccm-tables-list">';
        foreach ($tables as $table) {
            $id = 'dccm-table-' . md5($table);
            $bytes = (int) ($sizes[$table] ?? 0);
            $size_label = $bytes > 0 ? size_format($bytes, 1) : '—';
            $tone = 'is-sm';
            if ($bytes >= 50 * MB_IN_BYTES) {
                $tone = 'is-xl';
            } elseif ($bytes >= 10 * MB_IN_BYTES) {
                $tone = 'is-lg';
            } elseif ($bytes >= 1 * MB_IN_BYTES) {
                $tone = 'is-md';
            }

            echo '<label for="' . esc_attr($id) . '" data-dccm-bytes="' . esc_attr((string) $bytes) . '">';
            echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($table) . '"' .
                checked(in_array($table, $selected, true), true, false) . '>';
            echo '<code class="dccm-tables-list__name">' . esc_html($table) . '</code>';
            echo '<span class="dccm-tables-list__size ' . esc_attr($tone) . '">' . esc_html($size_label) . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }

    public static function query_text(string $key): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (!is_string($value)) {
            return '';
        }

        return sanitize_text_field(wp_unslash($value));
    }

    public static function posted_text(string $key): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (!is_string($value)) {
            return '';
        }

        return sanitize_text_field(wp_unslash($value));
    }

    public static function posted_bool(string $key): bool {
        return self::posted_text($key) === '1' || self::posted_text($key) === 'on';
    }

    /**
     * @return array<int, string>
     */
    public static function posted_tables(): array {
        if (
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            !isset($_POST['tables']) || !is_array($_POST['tables'])
        ) {
            return [];
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw = wp_unslash($_POST['tables']);
        $out = [];
        foreach ($raw as $table) {
            if (!is_string($table)) {
                continue;
            }
            $table = sanitize_text_field($table);
            if ($table !== '') {
                $out[] = $table;
            }
        }

        return $out;
    }

    public static function page_url(string $page): string {
        return admin_url('admin.php?page=' . $page);
    }

    public static function normalize_url(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // Allow pasting without scheme for convenience.
        if (!preg_match('#^https?://#i', $url) && strpos($url, '//') !== 0) {
            $url = 'https://' . $url;
        }

        return untrailingslashit(esc_url_raw($url));
    }
}
