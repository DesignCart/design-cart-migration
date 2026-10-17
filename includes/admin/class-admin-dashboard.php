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

final class DCCM_Admin_Dashboard {
    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        DCCM_Admin_Helpers::page_start(__('Design Cart Migration — Kokpit', 'design-cart-migration'));
        DCCM_Admin_Helpers::render_hero(
            __('Migration', 'design-cart-migration'),
            [
                [
                    'href' => DCCM_Admin_Helpers::page_url(DCCM_Admin::PAGE_EXPORT),
                    'label' => __('Eksport SQL', 'design-cart-migration'),
                    'icon' => 'fa fa-download',
                    'class' => 'dc-btn dc-btn--light',
                ],
                [
                    'href' => DCCM_Admin_Helpers::page_url(DCCM_Admin::PAGE_REPLACE),
                    'label' => __('Zamiana na żywo', 'design-cart-migration'),
                    'icon' => 'fa fa-exchange',
                    'class' => 'dc-btn dc-btn--ghost',
                ],
            ],
            [
                ['href' => admin_url('plugins.php'), 'label' => __('Wtyczki', 'design-cart-migration')],
                ['label' => __('Design Cart Migration', 'design-cart-migration')],
                ['label' => __('Kokpit', 'design-cart-migration')],
            ],
            'fa fa-database'
        );

        echo '<div class="dc-page__wrap"><div class="dc-form-card"><div class="dc-interface">';
        echo '<div class="dc-form-card__body">';

        DCCM_Admin_Helpers::section_start(
            'fa fa-th-large',
            __('Wybierz funkcję', 'design-cart-migration'),
            __('Dwie osobne ścieżki — eksport zrzutu SQL albo zamiana adresów w bieżącej bazie', 'design-cart-migration')
        );

        $tiles = [
            [
                'url' => DCCM_Admin_Helpers::page_url(DCCM_Admin::PAGE_EXPORT),
                'icon' => 'fa-download',
                'label' => __('Eksport SQL', 'design-cart-migration'),
                'desc' => __('Dump bazy ze zmienionymi adresami — wgrywasz na nowy serwer', 'design-cart-migration'),
                'color' => '#1fa28c',
            ],
            [
                'url' => DCCM_Admin_Helpers::page_url(DCCM_Admin::PAGE_REPLACE),
                'icon' => 'fa-exchange',
                'label' => __('Zamiana na żywo', 'design-cart-migration'),
                'desc' => __('Stary → nowy URL w aktualnej bazie (dane serializowane)', 'design-cart-migration'),
                'color' => '#1ca7ca',
            ],
        ];

        echo '<div class="dc-tiles dc-tiles--wide">';
        foreach ($tiles as $tile) {
            echo '<a class="dc-tile dc-tile--wide" href="' . esc_url($tile['url']) . '" style="--tile-color:' . esc_attr($tile['color']) . '">';
            echo '<span class="dc-tile__icon"><i class="fa ' . esc_attr($tile['icon']) . '"></i></span>';
            echo '<span class="dc-tile__text">';
            echo '<span class="dc-tile__label">' . esc_html($tile['label']) . '</span>';
            echo '<span class="dc-tile__desc">' . esc_html($tile['desc']) . '</span>';
            echo '</span></a>';
        }
        echo '</div>';

        DCCM_Admin_Helpers::section_end();

        DCCM_Admin_Helpers::section_start(
            'fa fa-info-circle',
            __('Jak to działa', 'design-cart-migration'),
            __('Migracja localhost → produkcja bez Better Search Replace', 'design-cart-migration')
        );

        echo '<ol class="dccm-steps">';
        echo '<li>' . esc_html__('Na localhost: Eksport SQL — podajesz adres nowej strony, pobierasz plik .sql ze zmienionymi URL-ami (także warianty http/https, ukośniki, escaped).', 'design-cart-migration') . '</li>';
        echo '<li>' . esc_html__('Na serwerze: importujesz dump do bazy WordPress.', 'design-cart-migration') . '</li>';
        echo '<li>' . esc_html__('Gdy baza jest już na produkcji: Zamiana na żywo — wpisujesz stary i nowy adres (opcjonalnie dry-run).', 'design-cart-migration') . '</li>';
        echo '</ol>';

        echo '<p class="dc-hint">' . esc_html__('Aktualny adres witryny:', 'design-cart-migration') .
            ' <code>' . esc_html(home_url('/')) . '</code></p>';

        DCCM_Admin_Helpers::section_end();
        echo '</div></div></div></div>';
        DCCM_Admin_Helpers::page_end();
    }
}
