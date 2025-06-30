<?php

namespace LknWc\WcInvoicePayment\Includes;
use WC_Order;

final class WcPaymentInvoicePartial
{
    public function enqueueCheckoutScripts(){
        if ( is_checkout() && WC()->payment_gateways() && ! empty( WC()->payment_gateways()->get_available_payment_gateways() ) && get_option('lkn_wcip_partial_payments_enabled', '') == 'on'){
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
        $order_statuses['wc-partial'] = array(
            'label' => __('Pagamento parcial', 'wc-invoice-payment'),
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
        $order_statuses['wc-partial'] = __('Pagamento parcial', 'wc-invoice-payment');
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
                    'total' => number_format($totalToPay ?: 0.0, 2, ',', '.'),
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
                        $order->update_status('wc-partial-comp');
                        if(($totalConfirmed + $orderTotal) >= $parentOrder->get_total()) {
                            $parentOrder->update_status(get_option('lkn_wcip_partial_complete_status', 'wc-processing'));
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
                'normal',
                'high'
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

    public function hidePartialOrdersRequest ( $queryArgs ) {
        $queryArgs['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => '_wc_lkn_is_partial_order',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_wc_lkn_is_partial_order',
                'value'   => 'yes',
                'compare' => '!=',
            ),
        );
        wp_enqueue_style('teste-admin-style', plugin_dir_url(__FILE__) . 'css/wc-teste21.css', array(), '', 'all');
        
        return $queryArgs;
    }

    public function fixTableCount($count, $statuses) {
        $statuses = array_map('sanitize_text_field', (array) $statuses);

        if (empty($statuses)) {
            return $count;
        }

        // Buscar todas as orders com a meta parcial
        $excluded_orders = wc_get_orders(array(
            'limit'        => -1,
            'status'       => $statuses,
            'return'       => 'ids',
            'meta_query'   => array(
                array(
                    'key'     => '_wc_lkn_is_partial_order',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            ),
        ));

        $excluded_count = count($excluded_orders);
        return max(0, $count - $excluded_count);
    }

    public function deletePartialOrders($orderId) {
        // Verifica se é uma order do WooCommerce
        $order = wc_get_order( $orderId );
        if ( ! $order ) {
            return;
        }

        // Recupera os IDs dos pedidos filhos (parciais)
        $partialsList = $order->get_meta( '_wc_lkn_partials_id', true );
        // Garante que é array
        if ( ! is_array( $partialsList ) ) {
            return;
        }
        
        // Sanitiza os IDs para garantir que são inteiros
        $partialsList = array_map( 'intval', $partialsList );
        
        // Remove duplicados e valores inválidos
        $partialsList = array_filter( array_unique( $partialsList ) );
        
        // Deleta cada pedido filho
        foreach ( $partialsList as $partial_id ) {
            // Confirma se é um pedido válido antes de deletar
            $partial_order = wc_get_order( $partial_id );
            if ( $partial_order && $partial_order->get_type() === 'shop_order' ) {
                
                // Remove do cache (WooCommerce 8+ com COT)
                if ( class_exists( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class ) ) {
                    $container = wc_get_container();
                    $order_data_store = $container->get( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class );
                    if ( method_exists( $order_data_store, 'delete_order_data_from_custom_order_tables' ) ) {
                        $order_data_store->delete_order_data_from_custom_order_tables( $partial_id );
                    }
                }

                // Limpa cache do WordPress
                clean_post_cache($partial_id);

                // Remove do banco de dados permanentemente
                wp_delete_post($partial_id, true); // true = deletar permanentemente (sem ir pra lixeira)

                // Pode forçar mais limpeza se desejar
                global $wpdb;
                $wpdb->delete( $wpdb->prefix . 'postmeta', array( 'post_id' => $partial_id ) );
                $wpdb->delete( $wpdb->prefix . 'woocommerce_order_items', array( 'order_id' => $partial_id ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id NOT IN (SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items)" ) );
            }
        }
    }
}