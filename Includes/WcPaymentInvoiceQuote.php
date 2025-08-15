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
        if($showPrice == 'no'){
            wp_enqueue_style('wcInvoiceHidePrice', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-hide-price.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        }
        wp_enqueue_script( 'wcInvoiceHidePrice', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-quote.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
        wp_localize_script( 'wcInvoiceHidePrice', 'wcInvoiceHidePrice', array(
            'quoteMode' => $quoteMode,
            'showPrice' => $showPrice,
            'cart' => WC()->cart,
            'userId' => get_current_user_id()
        ));
    }

    public function registerQuoteStatus( $order_statuses ) {
        $order_statuses['wc-quote-draft'] = array(
            'label' => __('Orçamento Rascunho', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-pending'] = array(
            'label' => __('Orçamento Pendente', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-awaiting'] = array(
            'label' => __('Orçamento Aguardando Aprovação', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-approved'] = array(
            'label' => __('Orçamento Aprovado', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-cancelled'] = array(
            'label' => __('Orçamento Cancelado', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-expired'] = array(
            'label' => __('Orçamento Vencido', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        return $order_statuses;
    }

    public function createQuoteStatus($order_statuses){
        $order_statuses['wc-quote-draft'] = __('Orçamento Rascunho', 'wc-invoice-payment');
        $order_statuses['wc-quote-pending'] = __('Orçamento Pendente', 'wc-invoice-payment');
        $order_statuses['wc-quote-awaiting'] = __('Orçamento Aguardando Aprovação', 'wc-invoice-payment');
        $order_statuses['wc-quote-approved'] = __('Orçamento Aprovado', 'wc-invoice-payment');
        $order_statuses['wc-quote-cancelled'] = __('Orçamento Cancelado', 'wc-invoice-payment');
        $order_statuses['wc-quote-expired'] = __('Orçamento Vencido', 'wc-invoice-payment');
        return $order_statuses;
    }

    public function allowQuoteStatusPayment($statuses) {
        $statuses[] = 'quote-approved';
        return $statuses;
    }

    public function allowQuoteStatusCancel($statuses) {
        $statuses[] = 'quote-awaiting';
        $statuses[] = 'quote-approved';
        return $statuses;
    }
}
