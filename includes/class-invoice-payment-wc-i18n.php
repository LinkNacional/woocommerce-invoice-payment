<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.linknacional.com/
 * @since      1.0.0
 *
 * @package    Invoice_Payment_Wc
 * @subpackage Invoice_Payment_Wc/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Invoice_Payment_Wc
 * @subpackage Invoice_Payment_Wc/includes
 * @author     Link Nacional
 */
class Invoice_Payment_Wc_i18n {
    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'invoice-payment-wc',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
