<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.linknacional.com/
 * @since      1.0.0
 *
 * @package    Wc_Payment_Invoice
 * @subpackage Wc_Payment_Invoice/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Wc_Payment_Invoice
 * @subpackage Wc_Payment_Invoice/public
 * @author     Link Nacional
 */
class Wc_Payment_Invoice_Public {
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Undocumented function
     *
     * @param  [type] $orderId
     *
     * @return void
     */
    public function check_invoice_exp_date() {
        $orderId = sanitize_text_field(get_query_var('order-pay'));
        $order = wc_get_order($orderId);
        $defaultPaymethod = esc_attr($order->get_payment_method());
        $dueDate = esc_attr($order->get_meta('lkn_exp_date'));

        $html = <<<HTML
        <input id="lkn_wcip_default_paymethod" type="hidden" value="$defaultPaymethod">
        <input id="lkn_wcip_due_date" type="hidden" value="$dueDate">
HTML;
        echo $html;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wc_Payment_Invoice_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wc_Payment_Invoice_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        $checkoutPage = get_option('woocommerce_checkout_page_id');

        if (is_page($checkoutPage) === true) {
            wp_enqueue_style($this->plugin_name . '-public-style', plugin_dir_url(__FILE__) . 'css/wc-invoice-payment-public.css', [], $this->version, 'all');
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wc_Payment_Invoice_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wc_Payment_Invoice_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        $checkoutPage = get_option('woocommerce_checkout_page_id');

        if (is_page($checkoutPage) === true) {
            wp_enqueue_script($this->plugin_name . '-public-js', plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-public.js', ['jquery'], $this->version, false);
        }
    }
}
