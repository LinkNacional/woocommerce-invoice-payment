<?php
namespace LknWc\WcInvoicePayment\Includes;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WcPaymentInvoiceQuoteGatewayBlocks extends AbstractPaymentMethodType {
    
    /**
     * The gateway instance.
     *
     * @var WcPaymentInvoiceQuoteGateway
     */
    public $gateway;

    /**
     * Payment method settings.
     *
     * @var array
     */
    public $settings;

    /**
     * Payment method name/id/slug.
     *
     * @return string
     */
    public function get_name() {
        return 'lkn_invoice_quote_gateway';
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_lkn_invoice_quote_gateway_settings', []);
        $this->gateway = new WcPaymentInvoiceQuoteGateway();
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        $quote_mode = get_option('lkn_wcip_quote_mode', 'no');
        return $quote_mode === 'yes' && !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_asset      = array(
            'dependencies' => array(
                'wc-blocks-registry',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            'version' => '1.0.0',
        );
        $script_url        = WC_PAYMENT_INVOICE_ROOT_URL . '/Public/js/wc-invoice-quote-blocks.js';

        wp_register_script(
            'wc-invoice-quote-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-invoice-quote-blocks', 'wc-invoice-payment');
        }

        return ['wc-invoice-quote-blocks'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */ 
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => $this->gateway->supports ?? []
        ];
    }

    /**
     * Get payment method setting or default.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}
