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

final class DCCM_Admin_Export {
    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current = untrailingslashit(home_url());
        $tables = DCCM_Replacer::get_tables();

        DCCM_Admin_Helpers::page_start(__('Eksport SQL', 'design-cart-migration'));
        DCCM_Admin_Helpers::render_hero(
            __('Eksport SQL', 'design-cart-migration'),
            [
                [
                    'form' => 'dccm-export-form',
                    'label' => __('Pobierz dump', 'design-cart-migration'),
                    'icon' => 'fa fa-download',
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
                ['label' => __('Eksport SQL', 'design-cart-migration')],
            ],
            'fa fa-download'
        );

        echo '<div class="dc-page__wrap"><div class="dc-form-card"><div class="dc-interface">';
        echo '<form class="dc-form dc-form--full" id="dccm-export-form" method="post" action="' .
            esc_url(admin_url('admin-post.php')) . '" data-dccm-ajax-export="1">';
        echo '<input type="hidden" name="action" value="dccm_export_sql">';
        wp_nonce_field('dccm_export_sql', 'dccm_nonce');

        echo '<div class="dc-form-card__body">';

        echo '<div class="dc-alert dc-alert--warn"><i class="fa fa-exclamation-triangle"></i><span>';
        echo esc_html__(
            'Dump zawiera pełną bazę ze zmienionymi adresami. Pliki mediów (uploads) skopiuj osobno.',
            'design-cart-migration'
        );
        echo '</span></div>';

        DCCM_Admin_Helpers::section_start(
            'fa fa-globe',
            __('Adresy URL', 'design-cart-migration'),
            __('Stary adres zostanie zamieniony na nowy już w pliku SQL (dane serializowane + warianty URL)', 'design-cart-migration')
        );

        echo '<div class="dc-row dc-row--2">';
        DCCM_Admin_Helpers::text_field(
            'search_for',
            'dccm-export-search',
            __('Stary adres (źródło)', 'design-cart-migration'),
            $current,
            'https://stary-adres.example',
            true
        );
        DCCM_Admin_Helpers::text_field(
            'replace_with',
            'dccm-export-replace',
            __('Nowy adres (cel)', 'design-cart-migration'),
            '',
            'https://www.przyklad.pl',
            true
        );
        echo '</div>';

        echo '<div class="dc-row dc-row--2" style="margin-top:1rem;">';
        echo '<div class="dc-field">';
        DCCM_Admin_Helpers::toggle(
            'smart_urls',
            'dccm-export-smart',
            __('Inteligentne warianty URL (http/https, ukośnik, escaped \\/)', 'design-cart-migration'),
            true
        );
        echo '</div><div class="dc-field">';
        DCCM_Admin_Helpers::toggle(
            'replace_guids',
            'dccm-export-guids',
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
        echo '<button type="submit" class="dc-btn dc-btn--primary" id="dccm-export-submit"><i class="fa fa-download"></i> ' .
            esc_html__('Pobierz dump SQL', 'design-cart-migration') . '</button>';
        echo '</div>';

        echo '<div class="dccm-progress" id="dccm-export-progress" aria-hidden="true">';
        echo '<div class="dccm-progress__bar"><div class="dccm-progress__fill" id="dccm-export-fill"></div></div>';
        echo '<p class="dccm-progress__text" id="dccm-export-status"></p>';
        echo '<a class="dc-btn dc-btn--primary dccm-export-download" id="dccm-export-download-link" href="#" hidden>';
        echo '<i class="fa fa-download"></i> ' . esc_html__('Pobierz plik SQL', 'design-cart-migration');
        echo '</a>';
        echo '</div>';

        $ready = DCCM_Exporter::find_ready_job_for_user(get_current_user_id());
        if ($ready !== null) {
            echo '<div class="dc-alert dc-alert--ok" style="margin-top:1rem;">';
            echo '<i class="fa fa-check-circle"></i><span>';
            echo esc_html__(
                'Masz gotowy dump z poprzedniego eksportu. Możesz go pobrać bez ponownego generowania.',
                'design-cart-migration'
            );
            echo ' <strong>' . esc_html((string) $ready['filename']) . '</strong>';
            echo ' (' . esc_html(size_format((int) $ready['bytes'])) . ').';
            echo '</span></div>';
            echo '<p><a class="dc-btn dc-btn--primary" href="' . esc_url((string) $ready['download_url']) . '">';
            echo '<i class="fa fa-download"></i> ' . esc_html__('Pobierz gotowy dump', 'design-cart-migration');
            echo '</a></p>';
        }

        echo '</div></form></div></div></div>';
        DCCM_Admin_Helpers::page_end();
    }
}
