<?php

namespace LknWc\WcInvoicePayment\Includes;

final class WcPaymentInvoiceQuote
{
    function lknWcInvoiceHidePrice( $price, $product ) {
        $showPrice = get_option(  'lkn_wcip_show_products_price', 'no' );

        if ( $showPrice === 'no' && !is_admin() ) {
            $this->lknWcInvoiceHidePriceFrontend();
            return ''; // esconde completamente o preço
        }

        return $price; // mantém o preço normal
    }

    function lknWcInvoiceHidePriceFrontend() {
        $showPrice = get_option(  'lkn_wcip_show_products_price', 'no' );
        $quoteMode = get_option(  'lkn_wcip_quote_mode', 'no' );
        wp_enqueue_style('wcInvoiceHidePrice', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-hide-price.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        wp_enqueue_script( 'wcInvoiceHidePrice', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-hide-price.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
        wp_localize_script( 'wcInvoiceHidePrice', 'wcInvoiceHidePrice', array(
            'quoteMode' => $quoteMode,
            'showPrice' => $showPrice,
        ));
    }

    public function enqueueCheckoutScripts(){
        if ( is_checkout() ){
            $currency_code =  get_woocommerce_currency();
            $currency_symbol = get_woocommerce_currency_symbol( $currency_code );
            wp_enqueue_script( 'wcInvoicePaymentPartialScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
            wp_localize_script('wcInvoicePaymentPartialScript', 'wcInvoicePaymentPartialVariables', array(
                'minPartialAmount' => get_option('lkn_wcip_partial_interval_minimum', 0),
                'cart' => WC()->cart,
                'userId' => get_current_user_id(),
                'symbol' => $currency_symbol,
            ));
        }
    }

    public function createQuote($request) {
        $parameters = $request->get_params();
        $cart = isset($parameters['cart']) ? $parameters['cart'] : null;
        $user_id = isset($parameters['userId']) ? intval($parameters['userId']) : 0;

        if (!$cart || empty($cart['cart_contents'])) {
            return new WP_REST_Response(['error' => 'Carrinho vazio ou inválido'], 400);
        }

        // Lógica para criar a cotação com base no carrinho
        // ...

        return new WP_REST_Response(['message' => 'Cotação criada com sucesso'], 200);
    }
}
