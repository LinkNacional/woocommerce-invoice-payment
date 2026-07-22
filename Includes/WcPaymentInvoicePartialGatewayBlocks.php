<?php
namespace LknWc\WcInvoicePayment\Includes;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if (! defined('ABSPATH')) {
    exit;
}

final class WcPaymentInvoicePartialGatewayBlocks extends AbstractPaymentMethodType
{
    public $gateway;
    public $settings;

    public function get_name()
    {
        return 'lkn_wcip_partial_gateway';
    }

    public function initialize()
    {
        $this->settings = get_option('woocommerce_lkn_wcip_partial_gateway_settings', []);
        $this->gateway = new WcPaymentInvoicePartialGateway();
    }

    public function is_active()
    {
        return true;
    }

    public function get_payment_method_script_handles()
    {
        $script_asset = array(
            'dependencies' => array(
                'wc-blocks-registry',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            'version' => '1.0.0',
        );
        $script_url = WC_PAYMENT_INVOICE_ROOT_URL . '/Public/js/wc-invoice-partial-gateway-blocks.js';

        wp_register_script(
            'wc-invoice-partial-gateway-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-invoice-partial-gateway-blocks', 'wc-invoice-payment');
        }

        return ['wc-invoice-partial-gateway-blocks'];
    }

    public function get_payment_method_data()
    {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => $this->gateway->supports ?? [],
        ];
    }

    public function get_setting($key, $default = '')
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}
