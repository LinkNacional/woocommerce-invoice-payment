<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.linknacional.com/
 * @since             1.0.0
 * @package           Wc_Payment_Invoice
 *
 * @wordpress-plugin
 * Plugin Name:       Invoice Payment for WooCommerce
 * Plugin URI:        https://www.linknacional.com/wordpress/
 * Description:       Invoice payment generation and management for WooCommerce.
 * Version:           1.0.0
 * Author:            Link Nacional
 * Author URI:        https://www.linknacional.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wc-invoice-payment
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
require_once __DIR__ . '/plugin-updater/plugin-update-checker.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WC_PAYMENT_INVOICE_VERSION', '1.0.0');
define('WC_PAYMENT_INVOICE_TRANSLATION_PATH', plugin_dir_path(__FILE__) . 'languages/');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wc-invoice-payment-activator.php
 */
function activate_Wc_Payment_Invoice() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-invoice-payment-activator.php';
    Wc_Payment_Invoice_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wc-invoice-payment-deactivator.php
 */
function deactivate_Wc_Payment_Invoice() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-invoice-payment-deactivator.php';
    Wc_Payment_Invoice_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wc_payment_invoice');
register_deactivation_hook(__FILE__, 'deactivate_wc_payment_invoice');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-wc-invoice-payment.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wc_payment_invoice() {
    $plugin = new Wc_Payment_Invoice();
    $plugin->run();

    add_action('admin_notices', 'lkn_wcip_woocommerce_missing_notice');
}
run_wc_payment_invoice();

/**
 * Instance for the Plugin Updater lib
 *
 * @return Lkn_Puc_Plugin_UpdateChecker
 */
function lkn_wc_invoice_payment_updater() {
    return new Lkn_Puc_Plugin_UpdateChecker(
        'https://api.linknacional.com.br/v2/u/slug=woocommerce-invoice-payment',
        __FILE__,
        'woocommerce-invoice-payment'
    );
}

lkn_wc_invoice_payment_updater();

/**
 * WooCommerce missing notice
 *
 * @return void
 */
function lkn_wcip_woocommerce_missing_notice() {
    include_once dirname(__FILE__) . '/admin/partials/wc-invoice-payment-admin-missing-woocommerce.php';
}
