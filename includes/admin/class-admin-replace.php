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

final class DCCM_Admin_Replace {
    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tables = DCCM_Replacer::get_tables();
        $report = get_transient('dccm_replace_report');

        DCCM_Admin_Helpers::page_start(__('Zamiana na żywo', 'design-cart-migration'));
        DCCM_Admin_Helpers::render_hero(
            __('Zamiana na żywo', 'design-cart-migration'),
            [
                [
                    'form' => 'dccm-replace-form',
                    'label' => __('Uruchom', 'design-cart-migration'),
                    'icon' => 'fa fa-play',
                    'class' => 'dc-btn dc-btn--light',
                ],
                [
                    'href' => DCCM_Admin_Helpers::page_url(DCCM_Admin::PAGE_DASH),
                    'label' => '',
                    'icon' => 'fa fa-arrow-left',
                    'class' => 'dc-btn dc-btn--ghost',
                    'title' => __('Wróć', 'design-cart-migration'),
                ],
            ],
            [
                ['href' => DCCM_Admin_Helpers::page_url(DCCM_Admin::PAGE_DASH), 'label' => __('Migration', 'design-cart-migration')],
                ['label' => __('Zamiana na żywo', 'design-cart-migration')],
            ],
            'fa fa-exchange'
        );

        echo '<div class="dc-page__wrap"><div class="dc-form-card"><div class="dc-interface">';
        echo '<form class="dc-form dc-form--full" id="dccm-replace-form" method="post" action="' .
            esc_url(admin_url('admin-post.php')) . '" data-dccm-live-confirm="1">';
        echo '<input type="hidden" name="action" value="dccm_live_replace">';
        wp_nonce_field('dccm_live_replace', 'dccm_nonce');

        echo '<div class="dc-form-card__body">';

        echo '<div class="dc-alert dc-alert--warn"><i class="fa fa-exclamation-triangle"></i><span>';
        echo esc_html__(
            'Operacja zmienia dane w bieżącej bazie. Zrób kopię zapasową. Najpierw możesz włączyć dry-run (symulacja).',
            'design-cart-migration'
        );
        echo '</span></div>';

        if (is_array($report)) {
            self::render_report($report);
            delete_transient('dccm_replace_report');
        }

        DCCM_Admin_Helpers::section_start(
            'fa fa-exchange',
            __('Stary → nowy adres', 'design-cart-migration'),
            __('Zamiana uwzględnia dane serializowane PHP, JSON oraz typowe warianty URL', 'design-cart-migration')
        );

        echo '<div class="dc-row dc-row--2">';
        DCCM_Admin_Helpers::text_field(
            'search_for',
            'dccm-replace-search',
            __('Szukaj (stary URL)', 'design-cart-migration'),
            '',
            'https://stary-adres.example',
            true
        );
        DCCM_Admin_Helpers::text_field(
            'replace_with',
            'dccm-replace-replace',
            __('Zamień na (nowy URL)', 'design-cart-migration'),
            untrailingslashit(home_url()),
            'https://www.przyklad.pl',
            true
        );
        echo '</div>';

        echo '<div class="dc-row dc-row--2" style="margin-top:1rem;">';
        echo '<div class="dc-field">';
        DCCM_Admin_Helpers::toggle(
            'dry_run',
            'dccm-replace-dry',
            __('Dry-run — tylko podgląd, bez zapisu', 'design-cart-migration'),
            true
        );
        echo '</div><div class="dc-field">';
        DCCM_Admin_Helpers::toggle(
            'smart_urls',
            'dccm-replace-smart',
            __('Inteligentne warianty URL', 'design-cart-migration'),
            true
        );
        echo '</div></div>';

        echo '<div class="dc-row dc-row--2" style="margin-top:0.75rem;">';
        echo '<div class="dc-field">';
        DCCM_Admin_Helpers::toggle(
            'case_insensitive',
            'dccm-replace-ci',
            __('Ignoruj wielkość liter', 'design-cart-migration'),
            false
        );
        echo '</div><div class="dc-field">';
        DCCM_Admin_Helpers::toggle(
            'replace_guids',
            'dccm-replace-guids',
            __('Zamień także kolumnę guid (zalecane przy migracji)', 'design-cart-migration'),
            true
        );
        echo '</div></div>';

        DCCM_Admin_Helpers::section_end();

        DCCM_Admin_Helpers::section_start(
            'fa fa-table',
            __('Tabele', 'design-cart-migration'),
            __('Posortowane od największych. Przy testach możesz odznaczyć duże tabele.', 'design-cart-migration')
        );

        echo '<div class="dccm-tables-toolbar">';
        echo '<button type="button" class="dc-btn dc-btn--secondary" data-dccm-tables="all"><i class="fa fa-check-square-o"></i> ' .
            esc_html__('Zaznacz wszystkie', 'design-cart-migration') . '</button>';
        echo '<button type="button" class="dc-btn dc-btn--secondary" data-dccm-tables="none"><i class="fa fa-square-o"></i> ' .
            esc_html__('Odznacz wszystkie', 'design-cart-migration') . '</button>';
        echo '<button type="button" class="dc-btn dc-btn--secondary" data-dccm-tables="skip-large" data-dccm-min-bytes="' .
            esc_attr((string) (10 * MB_IN_BYTES)) . '"><i class="fa fa-filter"></i> ' .
            esc_html__('Odznacz duże (≥ 10 MB)', 'design-cart-migration') . '</button>';
        echo '</div>';

        DCCM_Admin_Helpers::tables_checklist($tables);

        DCCM_Admin_Helpers::section_end();

        echo '<div class="dc-actions">';
        echo '<button type="submit" class="dc-btn dc-btn--primary" id="dccm-replace-submit"><i class="fa fa-play"></i> ' .
            esc_html__('Uruchom zamianę', 'design-cart-migration') . '</button>';
        echo '</div>';

        echo '<div class="dccm-progress" id="dccm-replace-progress" aria-hidden="true">';
        echo '<div class="dccm-progress__bar"><div class="dccm-progress__fill" id="dccm-replace-fill"></div></div>';
        echo '<p class="dccm-progress__text" id="dccm-replace-status"></p>';
        echo '</div>';

        echo '</div></form></div></div></div>';
        DCCM_Admin_Helpers::page_end();
    }

    /**
     * @param array<string, mixed> $report
     */
    private static function render_report(array $report): void {
        $dry = !empty($report['dry_run']);
        $class = $dry ? 'dc-alert--ok' : 'dc-alert--ok';
        $title = $dry
            ? __('Wynik dry-run (bez zapisu)', 'design-cart-migration')
            : __('Zamiana zakończona', 'design-cart-migration');

        echo '<div class="dc-alert ' . esc_attr($class) . '"><i class="fa fa-check-circle"></i><span>';
        echo esc_html($title) . ' — ';
        echo esc_html(sprintf(
            /* translators: 1: cells changed, 2: rows updated */
            __('zmienione komórki: %1$d, zaktualizowane wiersze: %2$d', 'design-cart-migration'),
            (int) ($report['change'] ?? 0),
            (int) ($report['updates'] ?? 0)
        ));
        echo '</span></div>';

        if (!empty($report['tables']) && is_array($report['tables'])) {
            echo '<div class="dccm-results is-active">';
            echo '<table class="dccm-results__table"><thead><tr>';
            echo '<th>' . esc_html__('Tabela', 'design-cart-migration') . '</th>';
            echo '<th>' . esc_html__('Zmiany', 'design-cart-migration') . '</th>';
            echo '<th>' . esc_html__('UPDATE', 'design-cart-migration') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($report['tables'] as $table => $stats) {
                echo '<tr>';
                echo '<td><code>' . esc_html((string) $table) . '</code></td>';
                echo '<td>' . esc_html((string) (int) ($stats['change'] ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) (int) ($stats['updates'] ?? 0)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }

        if (!empty($report['errors']) && is_array($report['errors'])) {
            echo '<div class="dc-alert dc-alert--err"><i class="fa fa-times-circle"></i><span>';
            echo esc_html(implode(' ', array_map('strval', $report['errors'])));
            echo '</span></div>';
        }
    }
}
