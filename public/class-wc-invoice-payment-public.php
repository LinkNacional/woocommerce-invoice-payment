<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @see       https://www.linknacional.com/
 * @since      1.0.0
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @author     Link Nacional
 */
final class Wc_Payment_Invoice_Public
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     *
     * @var string the ID of this plugin
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     *
     * @var string the current version of this plugin
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param string $plugin_name the name of the plugin
     * @param string $version     the version of this plugin
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Undocumented function.
     *
     * @param  [type] $orderId
     */
    public function check_invoice_exp_date(): void
    {
        $orderId = sanitize_text_field(get_query_var('order-pay'));
        $order = wc_get_order($orderId);
        $defaultPaymethod = esc_attr($order->get_payment_method());
        $dueDate = esc_attr($order->get_meta('lkn_exp_date'));

        $html = <<<HTML
        <input id="lkn_wcip_default_paymethod" type="hidden" value="{$defaultPaymethod}">
        <input id="lkn_wcip_due_date" type="hidden" value="{$dueDate}">
HTML;

        echo wp_kses($html, array(
            'input' => array(
                'id' => true,
                'type' => true,
                'value' => true,
            ),
        ));
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles(): void
    {
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
            wp_enqueue_style($this->plugin_name . '-public-style', plugin_dir_url(__FILE__) . 'css/wc-invoice-payment-public.css', array(), $this->version, 'all');
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts(): void
    {
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
            wp_enqueue_script($this->plugin_name . '-public-js', plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-public.js', array('jquery'), $this->version, false);
        }
    }
}
