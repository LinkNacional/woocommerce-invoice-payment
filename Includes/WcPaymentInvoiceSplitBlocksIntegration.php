<?php

namespace LknWc\WcInvoicePayment\Includes;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Integration class for WooCommerce Checkout Blocks.
 * Injeta o step de split de pagamento via API oficial do WC Blocks.
 */
class WcPaymentInvoiceSplitBlocksIntegration implements IntegrationInterface {

    public function get_name() {
        return 'lkn-wcip-partial-split';
    }

    public function initialize() {
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
    }

    public function get_script_handles() {
        return array('wcInvoicePaymentPartialSplitBlocks');
    }

    public function get_editor_script_handles() {
        return array();
    }

    public function get_script_data() {
        return array();
    }

    private function register_block_frontend_scripts() {
        $currency_symbol = get_woocommerce_currency_symbol(get_woocommerce_currency());

        wp_register_script(
            'wcInvoicePaymentPartialSplitBlocks',
            WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial-split-blocks.js',
            array('wp-element', 'wp-data', 'wp-i18n', 'wp-api-fetch'),
            WC_PAYMENT_INVOICE_VERSION,
            true
        );

        wp_localize_script('wcInvoicePaymentPartialSplitBlocks', 'lknWcipSplitBlocksConfig', array(
            'ajaxUrl'             => admin_url('admin-ajax.php'),
            'nonce'               => wp_create_nonce('lkn_wcip_partial_split'),
            'minPartialAmount'    => get_option('lkn_wcip_partial_interval_minimum', 0),
            'symbol'              => $currency_symbol,
            'splitTitle'          => __('Pagamento Parcial', 'wc-invoice-payment'),
            'splitDescription'    => __('Marque para dividir o pagamento.', 'wc-invoice-payment'),
            'calcButtonText'      => __('Split pagamento', 'wc-invoice-payment'),
            'paidNowLabel'        => __('Você pagará agora:', 'wc-invoice-payment'),
            'paidLaterLabel'      => __('Restante para depois:', 'wc-invoice-payment'),
            'gatewayLockedText'   => __('Indisponivel para pagamento parcial', 'wc-invoice-payment'),
            'maxValueLabel'       => __('Valor máximo permitido:', 'wc-invoice-payment'),
            'feesAddedLabel'      => __('Taxas/Descontos adicionais:', 'wc-invoice-payment'),
            'initialBaseMax'      => WC()->cart ? (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_shipping_total() - (float) WC()->cart->get_discount_total() : 0,
            'currencyCode'        => get_woocommerce_currency(),
            'priceFormat'         => array(
                'decimal_sep'   => wc_get_price_decimal_separator(),
                'thousand_sep'  => wc_get_price_thousand_separator(),
                'decimals'      => wc_get_price_decimals(),
                'currency_pos'  => get_option('woocommerce_currency_pos', 'left'),
            ),
        ));

        wp_register_style(
            'wcInvoicePaymentPartialSplitBlocksStyle',
            WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-split.css',
            array(),
            WC_PAYMENT_INVOICE_VERSION,
            'all'
        );
    }

    private function register_block_editor_scripts() {
        // Sem scripts de editor necessarios
    }
}
