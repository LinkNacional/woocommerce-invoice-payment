<?php

namespace LknWc\WcInvoicePayment\Includes;

final class WcPaymentInvoicePartial
{
    public function enqueueCheckoutScripts(){
        if (is_checkout()) {
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

    public function registerStatus( $order_statuses ) {
        $order_statuses['wc-partial-pend'] = array(
            'label' => __('Pagamento parcial pendente', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
         );
         $order_statuses['wc-partial-comp'] = array(
             'label' => __('Pagamento parcial completo', 'wc-invoice-payment'),
             'public' => true,
             'exclude_from_search' => false,
             'show_in_admin_all_list' => true,
             'show_in_admin_status_list' => true
         );
		return $order_statuses;
	}

    public function createStatus($order_statuses){
        $order_statuses['wc-partial-pend'] = __('Pagamento parcial pendente', 'wc-invoice-payment');
        $order_statuses['wc-partial-comp'] = __('Pagamento parcial completo', 'wc-invoice-payment');
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
        if($partialOrder->get_meta('_wc_lkn_is_partial_order') == 'yes'){
            $order = wc_get_order( $partialOrder->get_meta('_wc_lkn_parent_id') );
            $totalToPay = $order->get_total() - floatval($order->get_meta('_wc_lkn_total_confirmed')) - floatval($order->get_meta('_wc_lkn_total_peding'));
    
            wc_get_template(
                '/partialTablesClient.php',
                array(
                    'donationId' => $partialOrder->get_id(),
                    'orderStatus' => $order->get_status(),
                    'totalPeding' => number_format(floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0, 2, ',', '.'),
                    'totalConfirmed' => number_format(floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0, 2, ',', '.'),
                    'total' => number_format(floatval($order->get_total()) ?: 0.0, 2, ',', '.'),
                    'partialsOrdersIds' => $order->get_meta('_wc_lkn_partials_id'),
                    'symbol' => get_woocommerce_currency_symbol( $order->get_currency() ),
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

    public function statusChanged($orderId, $oldStatus, $newStatus, $order) {
        $order = wc_get_order( $orderId );
        if($order->get_meta('_wc_lkn_is_partial_order') == 'yes'){
            $parentOrder = wc_get_order( $order->get_meta('_wc_lkn_parent_id') );
            
            if($parentOrder){
                $paymentMethod = $order->get_payment_method();
                $savedStatus = get_option('lkn_wcip_partial_payment_methods_statuses', array());
                $totalPending = floatval($parentOrder->get_meta('_wc_lkn_total_peding')) ?: 0.0;
                $totalConfirmed = floatval($parentOrder->get_meta('_wc_lkn_total_confirmed')) ?: 0.0;
                $orderTotal = floatval($order->get_total()) ?: 0.0;
                $successStatuses = $savedStatus[$paymentMethod] ?? 'wc-completed';
                $newStatus = 'wc-' . $newStatus;
        
                switch ($newStatus) {
                    case 'wc-cancelled':
                        $parentOrder->update_meta_data('_wc_lkn_total_peding', $totalPending - $orderTotal);
                        break;
                    case $successStatuses:
                        $parentOrder->update_meta_data('_wc_lkn_total_peding', $totalPending - $orderTotal);
                        $parentOrder->update_meta_data('_wc_lkn_total_confirmed', $totalConfirmed + $orderTotal);
                        if(($totalConfirmed + $orderTotal) >= $parentOrder->get_total()) {
                            $parentOrder->update_status(get_option('lkn_wcip_partial_complete_status', 'wc-partial-comp'));
                        }
                        break;
                }
                
                $parentOrder->save();
                $order->save();
            }
        }
    }

    public function showPartialsPayments($order){
        $orderId = get_the_id();
        $order = wc_get_order( $orderId );
        if($order && $order->get_meta('_wc_lkn_is_partial_main_order') == 'yes'){
            $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';
            
            add_meta_box(
                'showPartialsPayments',
                'Pagamentos Parciais',
                array($this, 'showPartialOrders'),
                $screen,
                'advanced',
            );
        }
    }
    
    public function showPartialOrders($object): void
    {
        $order = is_a($object, 'WP_Post') ? wc_get_order($object->ID) : $object;
        
        if ($order->get_meta('_wc_lkn_is_partial_main_order') == 'yes') {
            wc_get_template(
                '/partialTablesAdmin.php',
                array(
                    'orderStatus' => $order->get_status(),
                    'totalPeding' => number_format(floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0, 2, ',', '.'),
                    'totalConfirmed' => number_format(floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0, 2, ',', '.'),
                    'total' => number_format(floatval($order->get_total()) ?: 0.0, 2, ',', '.'),
                    'partialsOrdersIds' => $order->get_meta('_wc_lkn_partials_id'),
                ),
                'woocommerce/pix/',
                plugin_dir_path( __FILE__ ) . 'templates/'
            );
            
            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-table.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        }
    }
}