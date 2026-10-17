<?php
/**
 * Design Cart Migration
 * Code: design-cart-migration
 * Autor: Paweł Nosko — https://www.designcart.pl/pawel-nosko.html
 * Firma: Design Cart — https://www.designcart.pl/
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('dccm_last_export');
delete_transient('dccm_replace_report');
