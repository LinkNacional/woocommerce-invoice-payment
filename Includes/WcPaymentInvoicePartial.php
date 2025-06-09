<?php

namespace LknWc\WcInvoicePayment\Includes;

final class WcPaymentInvoicePartial
{
    public function enqueueCheckoutScripts(){
        if (is_checkout()) {
            wp_enqueue_script( 'wcInvoicePaymentPartialScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
            wp_localize_script('wcInvoicePaymentPartialScript', 'wcInvoicePaymentPartialVariables', array(
                'minPartialAmount' => get_option('lkn_wcip_partial_interval_minimum', 0),
                'cart' => WC()->cart
            ));
        }
    }

    public function registerStatus( $order_statuses ) {
        $order_statuses['wc-partial-pend'] = array(
            'label' => __('Partial payment pending', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
         );
         $order_statuses['wc-partial-comp'] = array(
             'label' => __('Partial payment completed', 'wc-invoice-payment'),
             'public' => true,
             'exclude_from_search' => false,
             'show_in_admin_all_list' => true,
             'show_in_admin_status_list' => true
         );
		return $order_statuses;
	}

    public function createStatus($order_statuses){
        $order_statuses['wc-partial-pend'] = __('Partial payment pending', 'fraud-detection-for-woocommerce');
        $order_statuses['wc-partial-comp'] = __('Partial payment completed', 'fraud-detection-for-woocommerce');
        return $order_statuses;
    }

    public function allowStatusPayment($statuses) {
        $statuses[] = 'partial-pend';
        return $statuses;
    }

    public function allowStatusCancel($statuses) {
        $statuses[] = 'partial-pend';
        return $statuses;
    }

    public function showPartialFields($orderId): void {
        $partialOrder = wc_get_order( $orderId );
        if($partialOrder->get_meta('_wc_lkn_partial_is_order') == 'yes'){
            $order = wc_get_order( $partialOrder->get_meta('_wc_lkn_parent_id') );
            $totalToPay = $order->get_total() - floatval($order->get_meta('_wc_lkn_total_confirmed')) - floatval($order->get_meta('_wc_lkn_total_peding'));
    
            wc_get_template(
                '/partialTables.php',
                array(
                    'donationId' => $partialOrder->get_id(),
                    'totalPeding' => number_format(floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0, 2, ',', '.'),
                    'totalConfirmed' => number_format(floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0, 2, ',', '.'),
                    'total' => number_format(floatval($order->get_total()) ?: 0.0, 2, ',', '.'),
                    'partialsOrdersIds' => $order->get_meta('_wc_lkn_partials_id'),
                ),
                'woocommerce/pix/',
                plugin_dir_path( __FILE__ ) . 'templates/'
            );
            
            wp_enqueue_script( 'wcInvoicePaymentPartialScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial-table.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-table.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
            wp_localize_script('wcInvoicePaymentPartialScript', 'wcInvoicePaymentPartialTableVariables', array(
                'orderId' => $order->get_id(),
                'totalToPay' => $totalToPay
            ));
        }
    }
}