<?php

namespace LknWc\WcInvoicePayment\Includes;
use WC_Order;
use Exception;

final class WcPaymentInvoicePartial
{
    public function enqueueCheckoutScripts(){
        if ( is_checkout() && WC()->payment_gateways() && ! empty( WC()->payment_gateways()->get_available_payment_gateways() ) && get_option('lkn_wcip_partial_payments_enabled', '') == 'yes'){
            $currency_code =  get_woocommerce_currency();
            $currency_symbol = get_woocommerce_currency_symbol( $currency_code );

            // Detecta se é Checkout Blocks ou clássico — usa a página atual, não a configurada
            $current_page_id = is_checkout() ? get_queried_object_id() : 0;
            $is_blocks = $current_page_id && has_block('woocommerce/checkout', $current_page_id);

            if (! $is_blocks) {
                // Checkout clássico: mesmo script de split do Blocks, adaptado.
                wp_enqueue_script(
                    'wcInvoicePaymentPartialSplitClassic',
                    WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial-split-classic.js',
                    array('jquery'),
                    WC_PAYMENT_INVOICE_VERSION,
                    true
                );

                $pay_remaining = isset($_GET['pay_remaining']) ? intval($_GET['pay_remaining']) : 0;
                // Fallback via sessão para WC Blocks (AJAX pode não enviar GET params)
                if ($pay_remaining <= 0 && WC()->session) {
                    $sp = (int) WC()->session->get('lkn_partial_parent_order_id');
                    $sa = (float) WC()->session->get('lkn_partial_amount');
                    if ($sp > 0 && $sa > 0) $pay_remaining = $sp;
                }
                $split_config = array(
                    'ajaxUrl'             => admin_url('admin-ajax.php'),
                    'nonce'               => wp_create_nonce('lkn_wcip_partial_split'),
                    'minPartialAmount'    => get_option('lkn_wcip_partial_interval_minimum', 0),
                    'symbol'              => $currency_symbol,
                    'isPayRemaining'      => $pay_remaining > 0,
                    'parentConfirmed'     => ($pay_remaining > 0) ? (float) (wc_get_order($pay_remaining) ? wc_get_order($pay_remaining)->get_meta('_wc_lkn_total_confirmed') : 0) : 0,
                    'userId'              => get_current_user_id(),
                    'restUrl'             => rest_url('invoice_payments/create_partial_payment'),
                    'restNonce'           => wp_create_nonce('wp_rest'),
                    'isClassic'           => true,
                );
                wp_localize_script('wcInvoicePaymentPartialSplitClassic', 'lknWcipSplitBlocksConfig', $split_config);
            } else {
                // Checkout Blocks: enfileira script que popula o step injetado via render_block
                $pay_remaining = isset($_GET['pay_remaining']) ? intval($_GET['pay_remaining']) : 0;
                // Fallback via sessão para WC Blocks (AJAX pode não enviar GET params)
                if ($pay_remaining <= 0 && WC()->session) {
                    $sp = (int) WC()->session->get('lkn_partial_parent_order_id');
                    $sa = (float) WC()->session->get('lkn_partial_amount');
                    if ($sp > 0 && $sa > 0) $pay_remaining = $sp;
                }

                wp_enqueue_script(
                    'wcInvoicePaymentPartialSplitBlocks',
                    WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial-split-blocks.js',
                    array('jquery', 'wp-api'),
                    WC_PAYMENT_INVOICE_VERSION,
                    true
                );

                $split_config = array(
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
                    'initialBaseMax'      => (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_shipping_total() - (float) WC()->cart->get_discount_total(),
                    'currencyCode'        => get_woocommerce_currency(),
                    'priceFormat'         => array(
                        'decimal_sep'   => wc_get_price_decimal_separator(),
                        'thousand_sep'  => wc_get_price_thousand_separator(),
                        'decimals'      => wc_get_price_decimals(),
                        'currency_pos'  => get_option('woocommerce_currency_pos', 'left'),
                    ),
                    'isPayRemaining'      => $pay_remaining > 0,
                    'parentConfirmed'     => ($pay_remaining > 0) ? (float) (wc_get_order($pay_remaining) ? wc_get_order($pay_remaining)->get_meta('_wc_lkn_total_confirmed') : 0) : 0,
                    'userId'              => get_current_user_id(),
                    'restUrl'             => rest_url('invoice_payments/create_partial_payment'),
                    'restNonce'           => wp_create_nonce('wp_rest'),
                );

                wp_localize_script('wcInvoicePaymentPartialSplitBlocks', 'lknWcipSplitBlocksConfig', $split_config);
            }

            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
            wp_enqueue_style(
                'wcInvoicePaymentPartialSplitBlocksStyle',
                WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-split.css',
                array(),
                WC_PAYMENT_INVOICE_VERSION,
                'all'
            );
        }
    }

    /**
     * AJAX: retorna o total atual do carrinho (respeita frete, cupom, taxas).
     */
    public function ajaxGetCartTotal() {
        check_ajax_referer('lkn_wcip_cart_total', 'nonce');

        if (!WC()->cart) {
            wp_send_json_error(array('message' => 'Cart not available'));
        }

        wp_send_json_success(array('total' => (float) WC()->cart->total));
    }

    /**
     * AJAX: cria o pedido principal (com frete/taxas/cupom) + pedido filho parcial.
     * Substitui o fluxo newOrder do REST pois o AJAX tem o carrinho completo.
     */
    public function ajaxCreatePartialPayment() {
        check_ajax_referer('lkn_wcip_cart_total', 'nonce');

        $user_id = get_current_user_id();
        $partial_amount = isset($_POST['partialAmount']) ? floatval(wp_unslash($_POST['partialAmount'])) : 0.0;

        if (!$partial_amount) {
            wp_send_json_error(array('message' => 'Valor inválido.'));
        }

        if (!WC()->cart || WC()->cart->is_empty()) {
            wp_send_json_error(array('message' => 'Carrinho vazio.'));
        }

        // Valida contra o total real do carrinho
        $cart_total = (float) WC()->cart->total;
        if ($partial_amount >= $cart_total) {
            wp_send_json_error(array('message' => 'Valor não pode ser maior ou igual ao total.'));
        }

        // Cria o pedido principal a partir do carrinho
        WC()->cart->calculate_totals();

        $order = wc_create_order(array(
            'status'      => 'pending',
            'customer_id' => $user_id,
        ));

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product   = $cart_item['data'];
            $quantity  = $cart_item['quantity'];
            $args      = array('variation' => $cart_item['variation'] ?? array());
            $order->add_product($product, $quantity, $args);
        }

        // Copia endereço
        if ($user_id > 0) {
            $customer = new \WC_Customer($user_id);
            $order->set_billing_first_name($customer->get_billing_first_name());
            $order->set_billing_last_name($customer->get_billing_last_name());
            $order->set_billing_email($customer->get_billing_email());
            $order->set_billing_phone($customer->get_billing_phone());
            $order->set_address($customer->get_billing(), 'billing');
            $order->set_address($customer->get_shipping(), 'shipping');
        }

        // Cupons
        foreach (WC()->cart->get_applied_coupons() as $code) {
            $order->apply_coupon($code);
        }

        // Fees
        foreach (WC()->cart->get_fees() as $fee) {
            $item = new \WC_Order_Item_Fee();
            $item->set_name($fee->name);
            $item->set_amount($fee->amount);
            $item->set_total($fee->total);
            $item->set_tax_status($fee->taxable ? 'taxable' : 'none');
            $item->set_tax_class($fee->tax_class);
            $order->add_item($item);
        }

        // Shipping
        $packages = WC()->cart->get_shipping_packages();
        $chosen   = WC()->session->get('chosen_shipping_methods');
        if (!empty($chosen) && !empty($packages)) {
            foreach ($packages as $pkg_key => $pkg) {
                if (!isset($chosen[$pkg_key])) continue;
                $rates_key = 'shipping_for_package_' . $pkg_key;
                $all_rates = WC()->session->get($rates_key, array());
                $rates = isset($all_rates['rates']) ? $all_rates['rates'] : array();
                if (isset($rates[$chosen[$pkg_key]])) {
                    $rate = $rates[$chosen[$pkg_key]];
                    $shipping_item = new \WC_Order_Item_Shipping();
                    $shipping_item->set_method_title($rate->label);
                    $shipping_item->set_method_id($rate->id);
                    $shipping_item->set_total($rate->cost);
                    if (!empty($rate->taxes)) {
                        $shipping_item->set_taxes(array('total' => $rate->taxes));
                    }
                    $order->add_item($shipping_item);
                }
            }
        }

        $order->calculate_totals();
        $order->save();

        $order_id = $order->get_id();

        // ======= Daqui pra baixo: igual ao createPartialPayment do REST =======

        $order_total = (float) $order->get_total();
        $total_peding = (float) ($order->get_meta('_wc_lkn_total_peding') ?: 0);
        $total_confirmed = (float) ($order->get_meta('_wc_lkn_total_confirmed') ?: 0);

        if ($partial_amount > ($order_total - $total_peding - $total_confirmed)) {
            wp_send_json_error(array('message' => 'Valor excede o disponível.'));
        }

        $partial_order = wc_create_order(array('customer_id' => $order->get_customer_id()));
        $partial_order->set_address($order->get_address('billing'), 'billing');
        $partial_order->set_address($order->get_address('shipping'), 'shipping');
        $partial_order->set_billing_email($order->get_billing_email());

        $partial_order->set_customer_ip_address($order->get_customer_ip_address());
        $partial_order->set_customer_user_agent($order->get_customer_user_agent());
        $partial_order->set_currency($order->get_currency());

        $partial_order->update_meta_data('_wc_lkn_is_partial_order', 'yes');
        $order->update_meta_data('_wc_lkn_is_partial_main_order', 'yes');
        $partial_order->set_payment_method('multiplePayment');

        $order_link = admin_url("admin.php?page=edit-invoice&invoice={$order_id}");
        $partial_order->add_order_note("Pedido parcial criado a partir do pedido <a href=\"{$order_link}\">#{$order_id}</a>", false);
        $partial_order_id = $partial_order->get_id();
        $order_link2 = admin_url("admin.php?page=edit-invoice&invoice={$partial_order_id}");
        $order->add_order_note("Pedido parcial criado <a href=\"{$order_link2}\">#{$partial_order_id}</a>", false);

        $inv = get_option('lkn_wcip_invoices', array());
        if (!in_array($order_id, $inv)) $inv[] = $order_id;
        if (!in_array($partial_order_id, $inv)) $inv[] = $partial_order_id;
        update_option('lkn_wcip_invoices', $inv);

        // Copia os produtos do pedido principal para o filho
        foreach ($order->get_items() as $item) {
            $partial_order->add_item(clone $item);
        }

        // Copia frete
        foreach ($order->get_shipping_methods() as $shipping_item) {
            $partial_order->add_item(clone $shipping_item);
        }

        // Copia taxas
        foreach ($order->get_fees() as $fee_item) {
            $partial_order->add_item(clone $fee_item);
        }

        $partial_order->calculate_totals();
        $child_full_total = (float) $partial_order->get_total();

        // Aplica desconto para reduzir ao valor parcial
        $discount = $child_full_total - $partial_amount;
        if ($discount > 0.001) {
            $discount_fee = new \WC_Order_Item_Fee();
            $discount_fee->set_name(__('Remaining balance (pay later)', 'wc-invoice-payment'));
            $discount_fee->set_amount(-$discount);
            $discount_fee->set_total(-$discount);
            $partial_order->add_item($discount_fee);
        }

        $partial_order->calculate_totals();

        // Salva o remaining como meta fixa do pedido filho.
        // Isso é usado depois na página order-pay pra recalcular o total
        // quando os juros/parcelas mudam, mantendo o remaining constante.
        $partial_order->update_meta_data('_wc_lkn_partial_remaining', $discount);

        $order->update_meta_data('lkn_ini_date', gmdate('Y-m-d'));
        $partial_order->update_meta_data('lkn_ini_date', gmdate('Y-m-d'));

        $partial_order->update_status('wc-partial-pend');
        $order->update_status('wc-partial');

        $partialsList = $order->get_meta('_wc_lkn_partials_id') ?: array();
        if (!is_array($partialsList)) $partialsList = array();
        if (!in_array($partial_order_id, $partialsList)) $partialsList[] = $partial_order_id;
        $order->update_meta_data('_wc_lkn_partials_id', $partialsList);

        $order->update_meta_data('_wc_lkn_total_peding', $total_peding + $partial_amount);
        $partial_order->update_meta_data('_wc_lkn_parent_id', $order_id);

        // Garante que _wc_lkn_original_total esteja definido no pai
        // (necessário para compatibilidade com fluxos que dependem dessa meta)
        if (!$order->get_meta('_wc_lkn_original_total')) {
            $order->update_meta_data('_wc_lkn_original_total', $order_total);
        }

        $order->save();
        $partial_order->save();

        wp_send_json_success(array(
            'payment_url'   => self::partialCheckoutUrl($partial_order->get_id()),
            'order_id'      => $order_id,
            'partial_order' => $partial_order_id,
        ));
    }

    /**
    /**
    /**
     * Filtra os gateways disponíveis na página de pagamento de um pedido filho parcial,
     * mostrando apenas os gateways habilitados na configuração de pagamento parcial.
     *
     * Também filtra no checkout normal quando o split de pagamento está ativo
     * (session lkn_partial_amount).
     */
    public function filterGatewaysForPartialOrder($gateways) {
        // Cenário 1: order-pay de pedido filho parcial
        $orderId = absint(get_query_var('order-pay'));
        if ($orderId) {
            $order = wc_get_order($orderId);
            if ($order && $order->get_meta('_wc_lkn_is_partial_order') === 'yes') {
                return $this->filterGatewaysByPartialConfig($gateways);
            }
        }

        // Cenário 2: checkout pay_remaining (session lkn_partial_parent_order_id > 0)
        if (WC()->session && (int) WC()->session->get('lkn_partial_parent_order_id') > 0) {
            return $this->filterGatewaysByPartialConfig($gateways);
        }

        // Cenário 3: split ativo no checkout (sessão lkn_partial_amount > 0)
        if (WC()->session && (float) WC()->session->get('lkn_partial_amount', 0) > 0) {
            return $this->filterGatewaysByPartialConfig($gateways);
        }

        // Cenário 4: checkbox de parcial marcado no checkout normal (modo parcial ativo)
        if (WC()->session && WC()->session->get('lkn_partial_mode_active') === 'yes') {
            $partial_gateway_id = 'lkn_wcip_partial_gateway';
            if (isset($gateways[$partial_gateway_id])) {
                return array($partial_gateway_id => $gateways[$partial_gateway_id]);
            }
            return array();
        }

        return $gateways;
    }

    /**
     * Gera a URL de order-pay para um pedido parcial.
     */
    public static function partialCheckoutUrl($partial_order_id) {
        $order = wc_get_order($partial_order_id);
        if (!$order) return '';
        $parent_id = (int) $order->get_meta('_wc_lkn_parent_id');
        if ($parent_id) {
            $parent = wc_get_order($parent_id);
            if ($parent && $parent->get_meta('_wc_lkn_pay_remaining_pending') !== 'yes') {
                return $order->get_checkout_order_received_url();
            }
            return rest_url('invoice_payments/resume_partial/' . $parent_id);
        }
        return $order->get_checkout_payment_url();
    }

    /**
     * No order-pay de pedido parcial: aplica o fee "Remaining balance" no carrinho.
     */
    public function markPartialOrderSession() {
        $orderId = absint(get_query_var('order-pay'));
        if (!$orderId) return;

        $order = wc_get_order($orderId);
        if (!$order || $order->get_meta('_wc_lkn_is_partial_order') !== 'yes') return;

        $remaining = (float) $order->get_meta('_wc_lkn_partial_remaining');
        if ($remaining <= 0.001) return;

        add_action('woocommerce_cart_calculate_fees', function ($cart) use ($remaining) {
            $cart->add_fee(
                __('Remaining balance (pay later)', 'wc-invoice-payment'),
                -$remaining,
                false
            );
        }, 9999);

        // Trava frete no order-pay
        $parent_id = $order->get_meta('_wc_lkn_parent_id');
        if ($parent_id) {
            $parent_order = wc_get_order($parent_id);
            if ($parent_order) {
                $rates_json = $parent_order->get_meta('_wc_lkn_chosen_shipping_rates');
                if ($rates_json) {
                    $rate_ids = json_decode($rates_json, true);
                    if (is_array($rate_ids) && !empty($rate_ids)) {
                        WC()->session->set('lkn_partial_shipping_rate_ids', $rate_ids);
                    }
                }
            }
        }
    }

    /**
     * Filtra gateways mantendo apenas os habilitados na config de pagamento parcial.
     */
    private function filterGatewaysByPartialConfig($gateways) {
        $filtered = array();
        foreach ($gateways as $gateway_id => $gateway) {
            $enabled = get_option('lkn_wcip_partial_payments_method_' . $gateway_id, 'no');
            if ($enabled === 'yes') {
                $filtered[$gateway_id] = $gateway;
            }
        }
        return $filtered;
    }

    public function outputStatusIndicatorCss() {
        ?>
        <style>
            .woocommerce-order-status__indicator.is-partial-comp { background: #4ab866; border-color: #93d5a4; }
            .woocommerce-order-status__indicator.is-partial { background: #f0a020; border-color: #f5c87a; }
            .woocommerce-order-status__indicator.is-partial-pend { background: #8c8c8c; border-color: #b8b8b8; }
            .woocommerce-order-status__indicator.is-partial-cancelled { background: #e04545; border-color: #f09999; }
        </style>
        <?php
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
        $order_statuses['wc-partial-cancelled'] = array(
            'label' => __('Pagamento parcial cancelado', 'wc-invoice-payment'),
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
        $order_statuses['wc-partial-cancelled'] = __('Pagamento parcial cancelado', 'wc-invoice-payment');
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

    public function enqueuePartialTableAssets() {
        if (is_admin()) return;
        wp_enqueue_style('wcInvoicePaymentPartialTableStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-table.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
    }

    public function showPartialFields($orderId): void {
        $currentOrder = wc_get_order( $orderId );
        if (!$currentOrder) return;

        $parent_id = $currentOrder->get_meta('_wc_lkn_parent_id');
        $is_child  = (bool) $parent_id;
        $is_parent = !$is_child && $currentOrder->get_meta('_wc_lkn_original_total');

        if (!$is_parent && !$is_child) return;

        // Referência: sempre o pai
        $parentOrder = $is_child ? wc_get_order($parent_id) : $currentOrder;
        if (!$parentOrder) return;

        $originalTotal = floatval($parentOrder->get_meta('_wc_lkn_original_total')) ?: floatval($parentOrder->get_total());
        $confirmed     = $this->recalcConfirmedFromChildren($parentOrder);
        $peding        = $this->recalcPedingFromChildren($parentOrder);
        $restante      = max(0, $originalTotal - $confirmed - $peding);

        // Dados do pedido atual (varia se for pai ou filho)
        if ($is_parent) {
            $myPaid = floatval($parentOrder->get_meta('_wc_lkn_partial_amount_paid')) ?: 0.0;
            $myTotal = floatval($parentOrder->get_total());
            $myStatus = $parentOrder->get_status();
        } else {
            $myPaid = floatval($currentOrder->get_total());
            $myTotal = $myPaid;
            $myStatus = $currentOrder->get_status();
        }

        // Monta lista completa de relacionados (pai primeiro, filhos por ID)
        $allRelated = array();
        $allRelated[] = $parentOrder;
        // Coleta todos os filhos, incluindo o atual, ordena por ID
        $allChildren = array();
        if ($currentOrder->get_id() != $parentOrder->get_id()) {
            $allChildren[] = $currentOrder;
        }
        $childrenIds = (array) $parentOrder->get_meta('_wc_lkn_partials_id');
        foreach ($childrenIds as $cid) {
            $child = wc_get_order((int) $cid);
            if ($child && $child->get_id() != $currentOrder->get_id() && $child->get_status() !== 'trash') {
                $allChildren[] = $child;
            }
        }
        usort($allChildren, function($a, $b) { return $a->get_id() - $b->get_id(); });
        $allRelated = array_merge($allRelated, $allChildren);

        // Valor da 1ª parcela (para exibição no filho)
        if ($is_parent) {
            $firstPaid = number_format($myPaid, 2, ',', '.');
        } else {
            $firstPaid = number_format($confirmed, 2, ',', '.');
        }

        // Children details for consolidated view (sorted by id)
        $childrenDetails = array();
        $totalWithFees = 0.0;
        foreach ($allRelated as $relOrder) {
            if ($relOrder->get_meta('_wc_lkn_parent_id')) { // is child
                $cb = (float) $relOrder->get_meta('_wc_lkn_partial_amount_paid');
                $ct = (float) $relOrder->get_total();
                if ($cb <= 0) $cb = $ct;
                $totalWithFees += $ct;
                $childrenDetails[] = array(
                    'base'   => number_format($cb, 2, ',', '.'),
                    'total'  => number_format($ct, 2, ',', '.'),
                    'fees'   => number_format(round($ct - $cb, 2), 2, ',', '.'),
                );
            }
        }

        wc_get_template(
            '/partialTablesClient.php',
            array(
                'donationId'  => $currentOrder->get_id(),
                'orderStatus' => $myStatus,
                'myPaid'       => number_format($myPaid, 2, ',', '.'),
                'firstPaid'    => $firstPaid,
                'totalPeding'  => number_format($peding, 2, ',', '.'),
                'totalConfirmed' => number_format($confirmed, 2, ',', '.'),
                'restante'     => number_format($restante, 2, ',', '.'),
                'isParent'     => $is_parent,
                'allRelated'   => $allRelated,
                'symbol'       => get_woocommerce_currency_symbol( $currentOrder->get_currency() ),
                'childrenDetails' => $childrenDetails,
                'totalWithFees'   => number_format($totalWithFees, 2, ',', '.'),
                'originalTotal'   => number_format($originalTotal, 2, ',', '.'),
            ),
            'woocommerce/pix/',
            plugin_dir_path( __FILE__ ) . 'templates/'
        );
        
        wp_enqueue_script( 'wcInvoicePaymentPartialScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial-table.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
        wp_localize_script('wcInvoicePaymentPartialScript', 'lknWcipPartialTableVariables', array(
            'orderId' => $parentOrder->get_id(),
            'totalToPay' => $restante,
            'confirmPayment' => __('Are you sure you want to pay %s?', 'wc-invoice-payment'),
            'confirmCancel' => __('Are you sure you want to cancel this partial payment?', 'wc-invoice-payment'),
            'nonce' => wp_create_nonce('wp_rest'),
            'symbol' => get_woocommerce_currency_symbol( $currentOrder->get_currency() ),
        ));
    }

    public function statusChanged($orderId, $oldStatus, $newStatus, $order) {
        $order = wc_get_order( $orderId );

        // Se maybeSaveSplitDataToOrder já processou o confirmed/peding
        if ($order->get_meta('_wc_lkn_split_handled') === 'yes') {
            if ($order->get_meta('_wc_lkn_parent_id')) {
                $parent_order = wc_get_order($order->get_meta('_wc_lkn_parent_id'));
                if ($parent_order && $parent_order->get_meta('_wc_lkn_pay_remaining_pending') === 'yes') {
                    // Só completa se confirmed >= original_total E todos filhos pagaram
                    $original_total = (float) $parent_order->get_meta('_wc_lkn_original_total');
                    $confirmed = (float) $parent_order->get_meta('_wc_lkn_total_confirmed');
                    if ($original_total > 0 && round($confirmed, 2) >= round($original_total, 2)
                        && $this->allChildrenPaid($parent_order)) {
                        $complete_status = get_option('lkn_wcip_partial_complete_status', 'wc-partial-comp');
                        if (empty($complete_status)) $complete_status = 'wc-partial-comp';
                        $parent_order->delete_meta_data('_wc_lkn_pay_remaining_pending');
                        $parent_order->delete_meta_data('_wc_lkn_pay_remaining_pending');
                        $parent_order->set_status($complete_status);
                        $parent_order->add_order_note('Todos os pagamentos parciais foram concluídos.');
                        $parent_order->save();

                        // Sync Analytics
                        global $wpdb;
                        $wpdb->update("{$wpdb->prefix}wc_order_stats", array('status' => $complete_status), array('order_id' => $parent_order->get_id()));

                    }
                }
            }
            return;
        }

        // Se é filho do novo fluxo de split e NÃO tem split_handled,
        // significa que o gateway (Rede) disparou update_status ANTES de
        // maybeSaveSplitDataToOrder rodar. Não mexe em confirmed/peding —
        // maybeSaveSplitDataToOrder vai recalcular tudo corretamente.
        if ($order->get_meta('_wc_lkn_parent_id') && $order->get_meta('_wc_lkn_is_partial_order') === 'yes') {
            return;
        }

        // Fluxo legado (sem _wc_lkn_is_partial_order explícito)
        if($order->get_meta('_wc_lkn_parent_id')){
            $parentOrder = wc_get_order( $order->get_meta('_wc_lkn_parent_id') );
            
            if($parentOrder){
                $paymentMethod = $order->get_payment_method();
                
                // Status de sucesso: config nova por gateway > config antiga > fallback
                $per_gateway_status = get_option('lkn_wcip_partial_complete_status_' . $paymentMethod, null);
                if ($per_gateway_status !== null && $per_gateway_status !== false) {
                    $successStatuses = $per_gateway_status;
                } else {
                    $savedStatus = get_option('lkn_wcip_partial_payment_methods_statuses', array());
                    $successStatuses = $savedStatus[$paymentMethod] ?? 'wc-completed';
                }
                
                $totalPending = floatval($parentOrder->get_meta('_wc_lkn_total_peding')) ?: 0.0;
                $totalConfirmed = floatval($parentOrder->get_meta('_wc_lkn_total_confirmed')) ?: 0.0;
                $orderTotal = floatval($order->get_total()) ?: 0.0;
                $newStatus = 'wc-' . $newStatus;
        
                switch ($newStatus) {
                    case 'wc-cancelled':
                        $parentOrder->update_meta_data('_wc_lkn_total_peding', $totalPending - $orderTotal);
                        $scheduled_events = _get_cron_array();
                        // verifica todos os eventos agendados
                        foreach ($scheduled_events as $timestamp => $cron_events) {
                            foreach ($cron_events as $hook => $events) {
                                foreach ($events as $event) {
                                    // Verifique se o evento está associado ao seu gancho (hook)
                                    if ('generate_invoice_event' === $hook) {
                                        // Verifique se os argumentos do evento contêm o ID da ordem que você deseja remover
                                        $event_args = $event['args'];
                                        if (is_array($event_args) && in_array($orderId, $event_args)) {
                                            // Remova o evento do WP Cron
                                            wp_unschedule_event($timestamp, $hook, $event_args);
                                        }
                                    }
                                    if ('lkn_wcip_cron_hook' === $hook) {
                                        // Verifique se os argumentos do evento contêm o ID da ordem que você deseja remover
                                        $event_args = $event['args'];
                                        if (is_array($event_args) && in_array($orderId, $event_args)) {
                                            // Remova o evento do WP Cron
                                            wp_unschedule_event($timestamp, $hook, $event_args);
                                            
                                        }
                                    }
                                }
                            }
                        }
                        break;
                    case $successStatuses:
                        $parentOrder->update_meta_data("_wc_lkn_total_peding", $totalPending - $orderTotal);
                        $parentOrder->update_meta_data("_wc_lkn_total_confirmed", $totalConfirmed + $orderTotal);
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

    /**
     * Injeta botão "Copiar link de pagamento" na metabox "Ações do Pedido".
     * 
     * Funciona para pedidos pai e filho do sistema de pagamento parcial.
     * O botão fica desabilitado quando o pagamento já foi concluído
     * (status do pedido bate com o status configurado como "completo").
     */
    public function injectPaymentLinkButton(): void {
        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
        $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
        
        
        if (!$current_screen || $current_screen->id !== $screen) {
            return;
        }

        $order_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Detecta se é pai ou filho
        $is_parent = (
            $order->get_meta('_wc_lkn_is_partial_main_order') === 'yes'
            || $order->get_meta('_wc_lkn_original_total')
        ) && !$order->get_meta('_wc_lkn_parent_id');
        // Filho: tem _wc_lkn_is_partial_order OU tem _wc_lkn_parent_id (fallback para fluxos que não setaram a flag)
        $is_child = (
            $order->get_meta('_wc_lkn_is_partial_order') === 'yes'
            || $order->get_meta('_wc_lkn_parent_id')
        ) && $order->get_meta('_wc_lkn_parent_id');

        if (!$is_parent && !$is_child) {
            return;
        }

        $current_status = $order->get_status();
        $is_complete = false;
        $payment_url = '';

        if ($is_child) {
            // Filho: link do order-pay
            $payment_url = self::partialCheckoutUrl($order_id);

            // Status configurado como "completo" para o gateway deste pedido
            $payment_method = $order->get_payment_method();

            // Prioridade: nova config por gateway > config antiga > fallback 'wc-completed'
            $per_gateway_status = get_option('lkn_wcip_partial_complete_status_' . $payment_method, null);
            if ($per_gateway_status !== null && $per_gateway_status !== false) {
                $saved_status = $per_gateway_status;
            } else {
                $old_statuses = get_option('lkn_wcip_partial_payment_methods_statuses', array());
                $saved_status = $old_statuses[$payment_method] ?? 'wc-completed';
            }

            // Remove prefixo 'wc-' para comparação
            $saved_status_clean = str_replace('wc-', '', $saved_status);

            // Status do filho é considerado completo se bater com o configurado
            // ou se já estiver como partial-comp
            if ($current_status === $saved_status_clean || $current_status === 'partial-comp') {
                $is_complete = true;
            }
        } else {
            // Pai: link de pagamento via REST
            $payment_url = rest_url('invoice_payments/resume_partial/' . $order_id);

            // Status global configurado como "completo"
            $global_complete_status = get_option('lkn_wcip_partial_complete_status', 'wc-processing');
            $global_complete_clean = str_replace('wc-', '', $global_complete_status);

            // Pai está completo se status bater com o global OU se não houver pendências
            $total_pending = floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0;
            if ($current_status === $global_complete_clean || $total_pending <= 0.0) {
                $is_complete = true;
            }
        }

        $disabled_attr = $is_complete ? 'disabled' : '';
        $disabled_class = $is_complete ? 'disabled' : '';
        $tooltip = $is_complete
            ? esc_attr__('Pagamento já concluído.', 'wc-invoice-payment')
            : esc_attr__('Copiar link de pagamento para enviar ao cliente.', 'wc-invoice-payment');
        $label = $is_complete
            ? esc_html__('Pagamento concluído', 'wc-invoice-payment')
            : esc_html__('Copiar link de pagamento', 'wc-invoice-payment');

        ?>
        <script type="text/javascript">
        (function($) {
            function lknWcipInjectCopyButton() {
                var $actionsBox = $('#woocommerce-order-actions');
                if (!$actionsBox.length) return;
                var $saveBtn = $actionsBox.find('button.save_order');
                if (!$saveBtn.length) return;

                // Evita duplicata
                if ($actionsBox.find('#lknWcipCopyPaymentLink').length) return;

                var buttonHtml = '<button type="button" id="lknWcipCopyPaymentLink" class="button <?php echo esc_js($disabled_class); ?>" <?php echo esc_js($disabled_attr); ?> style="margin-left:6px;" title="<?php echo esc_js($tooltip); ?>"><?php echo esc_js($label); ?></button>';
                $saveBtn.after(buttonHtml);

                <?php if (!$is_complete): ?>
                $('#lknWcipCopyPaymentLink').on('click', function() {
                    var url = <?php echo wp_json_encode($payment_url); ?>;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function() {
                            var $btn = $('#lknWcipCopyPaymentLink');
                            var originalText = $btn.text();
                            $btn.text('<?php echo esc_js(__('Link copiado!', 'wc-invoice-payment')); ?>').addClass('button-primary');
                            setTimeout(function() {
                                $btn.text(originalText).removeClass('button-primary');
                            }, 2000);
                        }).catch(function() {
                            lknWcipFallbackCopy(url);
                        });
                    } else {
                        lknWcipFallbackCopy(url);
                    }
                });

                function lknWcipFallbackCopy(text) {
                    var $temp = $('<textarea>');
                    $('body').append($temp);
                    $temp.val(text).select();
                    try {
                        document.execCommand('copy');
                        var $btn = $('#lknWcipCopyPaymentLink');
                        var originalText = $btn.text();
                        $btn.text('<?php echo esc_js(__('Link copiado!', 'wc-invoice-payment')); ?>').addClass('button-primary');
                        setTimeout(function() {
                            $btn.text(originalText).removeClass('button-primary');
                        }, 2000);
                    } catch (e) {
                        alert('<?php echo esc_js(__('Falha ao copiar. Link:', 'wc-invoice-payment')); ?> ' + text);
                    }
                    $temp.remove();
                }
                <?php endif; ?>
            }

            // Tenta injetar imediatamente; se falhar (DOM não pronto), tenta de novo
            if (document.readyState === 'loading') {
                $(document).ready(lknWcipInjectCopyButton);
            } else {
                lknWcipInjectCopyButton();
            }
        })(jQuery);
        </script>
        <?php
    }

    public function showPartialsPayments($order){
        $orderId = get_the_id();
        $order = wc_get_order( $orderId );
        
        // Detecta se é pai (tem _wc_lkn_is_partial_main_order ou _wc_lkn_original_total, e NÃO tem _wc_lkn_parent_id)
        $is_parent = $order && (
            $order->get_meta('_wc_lkn_is_partial_main_order') == 'yes'
            || $order->get_meta('_wc_lkn_original_total')
        ) && !$order->get_meta('_wc_lkn_parent_id');
        
        // Detecta se é filho: _wc_lkn_is_partial_order OU tem _wc_lkn_parent_id (fallback)
        $is_child = $order && (
            $order->get_meta('_wc_lkn_is_partial_order') == 'yes'
            || $order->get_meta('_wc_lkn_parent_id')
        ) && $order->get_meta('_wc_lkn_parent_id');
        
        if($is_parent || $is_child){
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
        
        // Detecta se é pai ou filho
        $is_parent = (
            $order->get_meta('_wc_lkn_is_partial_main_order') == 'yes'
            || $order->get_meta('_wc_lkn_original_total')
        ) && !$order->get_meta('_wc_lkn_parent_id');
        // Filho: _wc_lkn_is_partial_order OU tem _wc_lkn_parent_id (fallback)
        $is_child = (
            $order->get_meta('_wc_lkn_is_partial_order') == 'yes'
            || $order->get_meta('_wc_lkn_parent_id')
        ) && $order->get_meta('_wc_lkn_parent_id');
        
        if ($is_parent || $is_child) {
            $parentId = null;
            
            if ($is_child) {
                // Filho: busca os dados do pai para exibir a tabela de irmãos
                $parentId = $order->get_meta('_wc_lkn_parent_id');
                $parent_order = wc_get_order($parentId);
                if ($parent_order) {
                    $orderStatus = $parent_order->get_status();
                    $totalPeding = number_format(floatval($parent_order->get_meta('_wc_lkn_total_peding')) ?: 0.0, 2, ',', '.');
                    $totalConfirmed = number_format(floatval($parent_order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0, 2, ',', '.');
                    $total = number_format(floatval($parent_order->get_total()) ?: 0.0, 2, ',', '.');
                    $partialsOrdersIds = (array) $parent_order->get_meta('_wc_lkn_partials_id');
                } else {
                    // Fallback: pai não encontrado, mostra só o próprio filho
                    $orderStatus = $order->get_status();
                    $totalPeding = '0,00';
                    $totalConfirmed = number_format(floatval($order->get_total()) ?: 0.0, 2, ',', '.');
                    $total = number_format(floatval($order->get_total()) ?: 0.0, 2, ',', '.');
                    $partialsOrdersIds = array($order->get_id());
                }
            } else {
                // Pai: comportamento original
                $orderStatus = $order->get_status();
                $totalPeding = number_format(floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0, 2, ',', '.');
                $totalConfirmed = number_format(floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0, 2, ',', '.');
                $total = number_format(floatval($order->get_total()) ?: 0.0, 2, ',', '.');
                $partialsOrdersIds = (array) $order->get_meta('_wc_lkn_partials_id');
            }
            
            wc_get_template(
                '/partialTablesAdmin.php',
                array(
                    'orderStatus' => $order->get_status(),
                    'totalPeding' => $totalPeding,
                    'totalConfirmed' => $totalConfirmed,
                    'total' => $total,
                    'partialsOrdersIds' => $partialsOrdersIds,
                    'parentId' => $parentId,
                ),
                'woocommerce/pix/',
                plugin_dir_path( __FILE__ ) . 'templates/'
            );
            
        wp_enqueue_style('wcInvoicePaymentPartialTableStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-table.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        }
    }

    public function hidePartialOrdersRequest ( $queryArgs ) {
        // Oculta pedidos filhos da tabela principal do WooCommerce.
        // Um pedido é considerado filho se tem _wc_lkn_is_partial_order = 'yes'
        // OU se tem _wc_lkn_parent_id (fallback para fluxos antigos).
        $queryArgs['meta_query'][] = array(
            'relation' => 'AND',
            array(
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
            ),
            array(
                'key'     => '_wc_lkn_parent_id',
                'compare' => 'NOT EXISTS',
            ),
        );
        
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
                $wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id NOT IN (SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items)" );
            }
        }
    }

    /**
     * Adiciona páginas de faturas no menu do dashboard do Dokan
     *
     * @param array $urls Array de URLs do menu
     * @return array Array modificado com as novas páginas
     */
    public function addDokanInvoicesPage($urls) {
        $urls['faturas'] = array(
            'title' => __('Faturas', 'wc-invoice-payment'),
            'icon'  => '<i class="fas fa-file-invoice"></i>',
            'url'   => dokan_get_navigation_url('faturas'),
            'pos'   => 51
        );
        
        return $urls;
    }    /**
     * Carrega o template das páginas de faturas no dashboard do Dokan
     *
     * @return void
     */
    public function loadDokanInvoicesTemplate() {
        global $wp;

        // Verificar permissões
        if (!function_exists('dokan_is_user_seller') || !dokan_is_user_seller(get_current_user_id())) {
            if (function_exists('dokan_get_template_part')) {
                dokan_get_template_part('global/no-permission');
            } else {
                echo '<div class="dokan-alert dokan-alert-danger">' . esc_html__('Você não tem permissão para acessar esta página.', 'wc-invoice-payment') . '</div>';
            }
            return;
        }

        // Verifica se estamos na página de listagem de faturas
        if (isset($wp->query_vars['faturas']) || (isset($wp->query_vars['custom']) && $wp->query_vars['custom'] === 'faturas')) {
            $this->renderDokanInvoicesPage();
        }
        
        // Verifica se estamos na página de criação de nova fatura
        if (isset($wp->query_vars['nova-fatura']) || (isset($wp->query_vars['custom']) && $wp->query_vars['custom'] === 'nova-fatura')) {
            $this->renderDokanNewInvoicePage();
        }
    }

    /**
     * Renderiza a página de faturas do dashboard do Dokan
     *
     * @return void
     */
    private function renderDokanInvoicesPage() {
        // Carrega CSS específico para a página de faturas
        wp_enqueue_style('wcInvoicePaymentDokanInvoicesStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-dokan-invoices.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        
        // Carrega JavaScript específico para a página de faturas
        wp_enqueue_script('wcInvoicePaymentDokanInvoicesScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-dokan-invoices.js', array('jquery'), WC_PAYMENT_INVOICE_VERSION, true);
        
        // Localizar variáveis para o JavaScript
        wp_localize_script('wcInvoicePaymentDokanInvoicesScript', 'lknWcipDokanVars', array(
            'nonce' => wp_create_nonce('wp_rest'),
            'downloading' => __('Baixando...', 'wc-invoice-payment'),
            'invoice' => __('Fatura', 'wc-invoice-payment'),
            'pdfError' => __('Erro ao gerar PDF da fatura', 'wc-invoice-payment'),
            'itemName' => __('Nome', 'wc-invoice-payment'),
            'itemAmount' => __('Valor', 'wc-invoice-payment')
        ));
        
        // Obter faturas do vendedor atual
        $current_user_id = \get_current_user_id();
        $invoices = $this->getVendorInvoices($current_user_id);
        
        
        ?>
        <div class="dokan-dashboard-wrap">
            <?php
            /**
             * dokan_dashboard_content_before hook
             *
             * @hooked get_dashboard_side_navigation
             *
             * @since 2.4
             */
            \do_action('dokan_dashboard_content_before');
            ?>

            <div class="dokan-dashboard-content dokan-orders-content">
                <?php
                /**
                 * dokan_orders_content_inside_before hook
                 *
                 * @since 1.0.0
                 */
                \do_action('dokan_orders_content_inside_before');
                ?>

                <article class="dokan-orders-area">
                    <?php
                    /**
                     * dokan_orders_content_area_header hook
                     *
                     * @since 1.0.0
                     */
                    \do_action('dokan_orders_content_area_header');
                    ?>

                    <form action="" method="POST" class="dokan-right">
                        <div class="dokan-form-group">
                            <a href="<?php echo \esc_url(\dokan_get_navigation_url('nova-fatura')); ?>" class="dokan-btn dokan-btn-sm dokan-btn-theme">
                                <i class="fas fa-plus"></i> <?php \esc_html_e('Nova Fatura', 'wc-invoice-payment'); ?>
                            </a>
                        </div>
                    </form>

                    <form id="invoice-filter" method="POST" class="dokan-form-inline">
                        <div class="dokan-form-group">
                            <label for="bulk-invoice-action-selector" class="screen-reader-text"><?php \esc_html_e('Select bulk action', 'wc-invoice-payment'); ?></label>

                            <select name="status" id="bulk-invoice-action-selector" class="dokan-form-control chosen">
                                <option class="bulk-invoice-status" value="-1"><?php \esc_html_e('Bulk Actions', 'wc-invoice-payment'); ?></option>
                                <option class="bulk-invoice-status" value="wc-on-hold"><?php \esc_html_e('Change status to on-hold', 'wc-invoice-payment'); ?></option>
                                <option class="bulk-invoice-status" value="wc-processing"><?php \esc_html_e('Change status to processing', 'wc-invoice-payment'); ?></option>
                                <option class="bulk-invoice-status" value="wc-completed"><?php \esc_html_e('Change status to completed', 'wc-invoice-payment'); ?></option>
                            </select>
                        </div>

                        <div class="dokan-form-group">
                            <?php \wp_nonce_field('dokan_invoice_bulk_action', 'dokan_invoice_bulk_nonce'); ?>
                            <input type="submit" name="bulk_invoice_status_change" id="bulk-invoice-action" class="dokan-btn dokan-btn-theme" value="<?php \esc_attr_e('Apply', 'wc-invoice-payment'); ?>">
                        </div>

                        <table class="dokan-table dokan-table-striped">
                            <thead>
                                <tr>
                                    <th id="cb" class="manage-column column-cb check-column">
                                        <label for="cb-select-all"></label>
                                        <input id="cb-select-all" class="dokan-checkbox" type="checkbox">
                                    </th>
                                    <th><?php \esc_html_e('Invoice', 'wc-invoice-payment'); ?></th>
                                    <th><?php \esc_html_e('Invoice Total', 'wc-invoice-payment'); ?></th>
                                    <th><?php \esc_html_e('Status', 'wc-invoice-payment'); ?></th>
                                    <th><?php \esc_html_e('Customer', 'wc-invoice-payment'); ?></th>
                                    <th><?php \esc_html_e('Date', 'wc-invoice-payment'); ?></th>
                                    <th><?php \esc_html_e('Due Date', 'wc-invoice-payment'); ?></th>
                                    <th width="17%"><?php \esc_html_e('Action', 'wc-invoice-payment'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($invoices)) : ?>
                                    <?php foreach ($invoices as $invoice) : 
                                        $order_id = $invoice['id'];
                                        $orderInvoice = wc_get_order($order_id);

                                        $nonce = wp_create_nonce( 'dokan_view_order' );
                                        $view_url = add_query_arg(
                                            [
                                                'order_id' => $order_id,
                                                '_wpnonce' => $nonce,
                                            ],
                                            dokan_get_navigation_url( 'orders' )
                                        );
                                        ?>
                                        <tr>
                                            <th class="dokan-order-select check-column">
                                                <label for="cb-select-<?php echo \esc_attr($invoice['id']); ?>"></label>
                                                <input class="cb-select-items dokan-checkbox" type="checkbox" name="bulk_invoices[]" value="<?php echo \esc_attr($invoice['id']); ?>">
                                            </th>
                                            <td class="dokan-order-id column-primary" data-title="<?php \esc_attr_e('Invoice', 'wc-invoice-payment'); ?>">
                                                <a href="<?php echo \esc_url($view_url); ?>">
                                                    <?php /* translators: %s: invoice number */ ?>
                                                    <strong><?php printf(esc_html__('Invoice %s', 'wc-invoice-payment'), esc_html($invoice['number'])); ?></strong>
                                                </a>
                                                <button type="button" class="toggle-row"></button>
                                            </td>
                                            <td class="dokan-order-total" data-title="<?php \esc_attr_e('Invoice Total', 'wc-invoice-payment'); ?>">
                                                <?php echo \wp_kses_post($invoice['total_formatted']); ?>
                                            </td>
                                            <td class="dokan-order-status" data-title="<?php \esc_attr_e('Status', 'wc-invoice-payment'); ?>">
                                                <?php echo wp_kses_post($this->getStatusLabel($invoice['status'])); ?>
                                            </td>
                                            <td class="dokan-order-customer" data-title="<?php \esc_attr_e('Customer', 'wc-invoice-payment'); ?>">
                                                <?php echo \esc_html($invoice['customer_name'] ?: __('Guest', 'wc-invoice-payment')); ?>
                                            </td>
                                            <td class="dokan-order-date" data-title="<?php \esc_attr_e('Date', 'wc-invoice-payment'); ?>">
                                                <abbr title="<?php echo \esc_attr($invoice['date_created']); ?>">
                                                    <?php echo \esc_html($invoice['date_created']); ?>
                                                </abbr>
                                            </td>
                                            <td class="dokan-order-date" data-title="<?php \esc_attr_e('Due Date', 'wc-invoice-payment'); ?>">
                                                <?php echo \esc_html($invoice['date_due']); ?>
                                            </td>
                                            <td class="dokan-order-action" width="17%" data-title="<?php \esc_attr_e('Action', 'wc-invoice-payment'); ?>">
                                                <?php if ($invoice['status'] === 'on-hold' || $invoice['status'] === 'pending' ) : ?>
                                                    <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \esc_url(\wp_nonce_url(\admin_url('admin-ajax.php?action=dokan-mark-order-processing&order_id=' . $invoice['id']), 'dokan-mark-order-processing')); ?>" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Mark Processing', 'wc-invoice-payment'); ?>">
                                                        <i class="far fa-clock">&nbsp;</i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($invoice['status'] === 'on-hold' || $invoice['status'] === 'pending' || $invoice['status'] === 'processing') : ?>
                                                    <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \esc_url(\wp_nonce_url(\admin_url('admin-ajax.php?action=dokan-mark-order-complete&order_id=' . $invoice['id']), 'dokan-mark-order-complete')); ?>" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Mark Complete', 'wc-invoice-payment'); ?>">
                                                        <i class="fas fa-check">&nbsp;</i>
                                                    </a>
                                                <?php endif; ?>
                                                <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \esc_url($view_url); ?>" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('View', 'wc-invoice-payment'); ?>">
                                                    <i class="far fa-eye">&nbsp;</i>
                                                </a>
                                                <button class="dokan-btn dokan-btn-default dokan-btn-sm tips lkn_wcip_generate_pdf_btn" data-invoice-id="<?php echo \esc_attr($invoice['id']); ?>" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Download Invoice', 'wc-invoice-payment'); ?>" type="button">
                                                    <i class="fas fa-download">&nbsp;</i>
                                                </button>
                                                <!-- Link de pagamento da fatura -->
                                                <?php $dokanUrl = ($orderInvoice->get_meta('_wc_lkn_is_partial_order') === 'yes') ? \LknWc\WcInvoicePayment\Includes\WcPaymentInvoicePartial::partialCheckoutUrl($orderInvoice->get_id()) : $orderInvoice->get_checkout_payment_url(); ?>
                                                <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \esc_url($dokanUrl); ?>" target="_blank" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Payment Link', 'wc-invoice-payment'); ?>">
                                                    <i class="fas fa-credit-card"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="dokan-message">
                                                <?php \esc_html_e('No invoices found.', 'wc-invoice-payment'); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
                </article>

                <?php
                /**
                 * dokan_orders_content_inside_after hook
                 *
                 * @since 1.0.0
                 */
                \do_action('dokan_orders_content_inside_after');
                ?>
            </div>

            <?php
            /**
             * dokan_dashboard_content_after hook
             *
             * @since 2.4
             */
            \do_action('dokan_dashboard_content_after');
            ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Funcionalidade de select all (igual ao Dokan)
            $('#cb-select-all').on('change', function() {
                $('.cb-select-items').prop('checked', this.checked);
            });
            
            // Atualizar select all quando checkboxes individuais mudam
            $('.cb-select-items').on('change', function() {
                var total = $('.cb-select-items').length;
                var checked = $('.cb-select-items:checked').length;
                $('#cb-select-all').prop('indeterminate', checked > 0 && checked < total);
                $('#cb-select-all').prop('checked', checked === total);
            });
        });
        </script>
        <?php
    }

    /**
     * Renderiza a página de criação de nova fatura do dashboard do Dokan
     *
     * @return void
     */
    private function renderDokanNewInvoicePage() {
        // Carrega CSS específico para a página de faturas
        wp_enqueue_style('wcInvoicePaymentDokanInvoicesStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-dokan-invoices.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        
        // Processar formulário se foi enviado
        if (isset($_POST['dokan_create_invoice_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dokan_create_invoice_nonce'])), 'dokan_create_invoice_action')) {
            $this->processNewInvoiceForm();
        }
        
        ?>
        <div class="dokan-dashboard-wrap">
            <?php
            /**
             * dokan_dashboard_content_before hook
             *
             * @hooked get_dashboard_side_navigation
             *
             * @since 2.4
             */
            do_action('dokan_dashboard_content_before');
            ?>

            <div class="dokan-dashboard-content dokan-new-invoice-content">
                <?php
                /**
                 * dokan_new_invoice_content_inside_before hook
                 *
                 * @since 1.0.0
                 */
                do_action('dokan_new_invoice_content_inside_before');
                ?>

                <article class="dokan-new-invoice-area">
                    <?php
                    /**
                     * dokan_new_invoice_content_area_header hook
                     *
                     * @since 1.0.0
                     */
                    do_action('dokan_new_invoice_content_area_header');
                    ?>

                    <div class="dokan-new-invoice-dashboard">
                        <div class="dokan-new-invoice-header">
                            <h1 class="entry-title"><?php esc_html_e('Nova Fatura', 'wc-invoice-payment'); ?></h1>
                        </div>

                        <form method="post" class="wcip-form-wrap dokan-invoice-form">
                            <?php wp_nonce_field('dokan_create_invoice_action', 'dokan_create_invoice_nonce'); ?>
                            
                            <div class="wcip-invoice-data">
                                <div id="wcPaymentInvoiceTitles">
                                    <h3 class="title"><?php esc_html_e('Detalhes da fatura', 'wc-invoice-payment'); ?></h3>
                                    <h3 class="title"><?php esc_html_e('Dados do Pagador', 'wc-invoice-payment'); ?></h3>
                                </div>
                                <div class="invoice-row-wrap">
                                    <div class="invoice-column-wrap">
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_payment_status_input"><?php esc_html_e('Status', 'wc-invoice-payment'); ?></label>
                                            <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" class="regular-text">
                                                <option value="wc-pending"><?php esc_html_e('Pending payment', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-processing"><?php esc_html_e('Processing', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-on-hold"><?php esc_html_e('On hold', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-completed"><?php esc_html_e('Completed', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-cancelled"><?php esc_html_e('Cancelled', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-refunded"><?php esc_html_e('Refunded', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-failed"><?php esc_html_e('Failed', 'wc-invoice-payment'); ?></option>
                                            </select>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_select_invoice_template"><?php esc_html_e('Template do PDF da fatura', 'wc-invoice-payment'); ?></label>
                                            <select name="lkn_wcip_select_invoice_template" id="lkn_wcip_select_invoice_template" class="regular-text" required>
                                                <option value="global"><?php esc_html_e('Template padrão', 'wc-invoice-payment'); ?></option>
                                                <?php
                                                // Buscar templates disponíveis
                                                $templates_dir = WC_PAYMENT_INVOICE_ROOT_DIR . 'Includes/templates/';
                                                if (is_dir($templates_dir)) {
                                                    $templates = array_diff(scandir($templates_dir), array('.', '..'));
                                                    foreach ($templates as $template) {
                                                        if (is_dir($templates_dir . $template) && $template !== 'myaccount') {
                                                            $preview_url = WC_PAYMENT_INVOICE_ROOT_URL . 'Includes/templates/' . $template . '/preview.webp';
                                                            echo '<option data-preview-url="' . esc_url($preview_url) . '" value="' . esc_attr($template) . '">' . esc_html(ucfirst($template)) . '</option>';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_select_invoice_language"><?php esc_html_e('Idioma do PDF da fatura', 'wc-invoice-payment'); ?></label>
                                            <select name="lkn_wcip_select_invoice_language" id="lkn_wcip_select_invoice_language" class="regular-text" required>
                                                <?php
                                                $languages = get_available_languages();
                                                $current_locale = get_locale();
                                                $locale_names = array(
                                                    'pt_BR' => 'Portuguese (Brazil)',
                                                    'en_US' => 'English (United States)'
                                                );
                                                
                                                // Adiciona idioma atual
                                                $current_name = isset($locale_names[$current_locale]) ? $locale_names[$current_locale] : $current_locale;
                                                echo '<option value="' . esc_attr($current_locale) . '" selected>' . esc_html($current_name) . '</option>';
                                                
                                                // Adiciona outros idiomas disponíveis
                                                foreach ($languages as $language) {
                                                    if ($language !== $current_locale) {
                                                        $name = isset($locale_names[$language]) ? $locale_names[$language] : $language;
                                                        echo '<option value="' . esc_attr($language) . '">' . esc_html($name) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_extra_data"><?php esc_html_e('Dados extra', 'wc-invoice-payment'); ?></label>
                                            <textarea name="lkn_wcip_extra_data" id="lkn_wcip_extra_data" class="regular-text"></textarea>
                                        </div>    
                                    </div>
                                    <div class="invoice-column-wrap">
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_name_input"><?php esc_html_e('Nome', 'wc-invoice-payment'); ?></label>
                                            <input name="lkn_wcip_name" type="text" id="lkn_wcip_name_input" class="regular-text" required>
                                        </div>
                                        <div class="input-row-wrap" id="lknWcipEmailInput">
                                            <label for="lkn_wcip_email_input"><?php esc_html_e('E-mail', 'wc-invoice-payment'); ?></label>
                                            <input name="lkn_wcip_email" type="email" id="lkn_wcip_email_input" class="regular-text" required>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_country_input"><?php esc_html_e('País', 'wc-invoice-payment'); ?></label>
                                            <select name="lkn_wcip_country" id="lkn_wcip_country_input" class="regular-text">
                                                <?php
                                                if (function_exists('WC')) {
                                                    $countries = WC()->countries->get_countries();
                                                    $base_country = WC()->countries->get_base_country();
                                                    foreach ($countries as $code => $name) {
                                                        $selected = ($code === $base_country) ? 'selected' : '';
                                                        echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_html($name) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wcip-invoice-data wcip-postbox">
                                <span class="text-bold"><?php esc_html_e('Ações de fatura', 'wc-invoice-payment'); ?></span>
                                <hr>
                                <div class="wcip-row">
                                    <div class="input-row-wrap">
                                        <select name="lkn_wcip_form_actions">
                                            <option value="no_action" selected><?php esc_html_e('Selecione uma ação...', 'wc-invoice-payment'); ?></option>
                                            <option value="send_email"><?php esc_html_e('Enviar fatura para o cliente', 'wc-invoice-payment'); ?></option>
                                        </select>
                                    </div>
                                    <div class="input-row-wrap">
                                        <label for="lkn_wcip_exp_date_input"><?php esc_html_e('Data de vencimento', 'wc-invoice-payment'); ?></label>
                                        <input id="lkn_wcip_exp_date_input" type="date" name="lkn_wcip_exp_date" min="<?php echo esc_attr(gmdate('Y-m-d')); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wcip-invoice-data">
                                <h3 class="title"><?php esc_html_e('Preço', 'wc-invoice-payment'); ?></h3>
                                <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                                    <div class="price-row-wrap price-row-0">
                                        <div class="input-row-wrap">
                                            <label><?php esc_html_e('Nome', 'wc-invoice-payment'); ?></label>
                                            <input name="lkn_wcip_name_invoice_0" type="text" id="lkn_wcip_name_invoice_0" class="regular-text" required>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label><?php esc_html_e('Valor', 'wc-invoice-payment'); ?></label>
                                            <input name="lkn_wcip_amount_invoice_0" type="tel" id="lkn_wcip_amount_invoice_0" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');" required>
                                        </div>
                                        <div class="input-row-wrap">
                                            <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(0)">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="invoice-row-wrap">
                                    <button type="button" class="btn btn-add-line" onclick="lkn_wcip_add_amount_row()"><?php esc_html_e('Adicionar linha', 'wc-invoice-payment'); ?></button>
                                </div>
                            </div>
                            
                            <div class="wcip-invoice-data">
                                <h3 class="title"><?php esc_html_e('Notas do rodapé', 'wc-invoice-payment'); ?></h3>
                                <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                                    <div class="input-row-wrap">
                                        <label><?php esc_html_e('Detalhes em HTML', 'wc-invoice-payment'); ?></label>
                                        <textarea name="lkn-wc-invoice-payment-footer-notes" id="lkn-wc-invoice-payment-footer-notes" class="regular-text"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wcip-invoice-actions">
                                <button type="submit" class="dokan-btn dokan-btn-primary"><?php esc_html_e('Criar Fatura', 'wc-invoice-payment'); ?></button>
                                <a href="<?php echo \esc_url(dokan_get_navigation_url('faturas')); ?>" class="dokan-btn dokan-btn-default"><?php esc_html_e('Cancelar', 'wc-invoice-payment'); ?></a>
                            </div>
                        </form>
                    </div>
                </article>

                <?php
                /**
                 * dokan_new_invoice_content_inside_after hook
                 *
                 * @since 1.0.0
                 */
                do_action('dokan_new_invoice_content_inside_after');
                ?>
            </div>

            <?php
            /**
             * dokan_dashboard_content_after hook
             *
             * @since 2.4
             */
            do_action('dokan_dashboard_content_after');
            ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Funções para adicionar/remover linhas de preço
            window.lkn_wcip_row_counter = 0;
            
            window.lkn_wcip_add_amount_row = function() {
                lkn_wcip_row_counter++;
                var row = '<div class="price-row-wrap price-row-' + lkn_wcip_row_counter + '">' +
                    '<div class="input-row-wrap">' +
                        '<label><?php esc_html_e("Nome", "wc-invoice-payment"); ?></label>' +
                        '<input name="lkn_wcip_name_invoice_' + lkn_wcip_row_counter + '" type="text" class="regular-text" required>' +
                    '</div>' +
                    '<div class="input-row-wrap">' +
                        '<label><?php esc_html_e("Valor", "wc-invoice-payment"); ?></label>' +
                        '<input name="lkn_wcip_amount_invoice_' + lkn_wcip_row_counter + '" type="tel" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, \'\').replace(/(\..*?)\..*/g, \'$1\');" required>' +
                    '</div>' +
                    '<div class="input-row-wrap">' +
                        '<button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(' + lkn_wcip_row_counter + ')">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                    '</div>' +
                '</div>';
                $('#wcip-invoice-price-row').append(row);
            };
            
            window.lkn_wcip_remove_amount_row = function(row_id) {
                $('.price-row-' + row_id).remove();
            };
        });
        </script>
        <?php
    }

    /**
     * Processa o formulário de criação de nova fatura
     *
     * @return void
     */
    private function processNewInvoiceForm() {
        $current_user_id = get_current_user_id();
        
        try {
            // Validar campos obrigatórios
            $required_fields = array(
                'lkn_wcip_name' => __('Nome', 'wc-invoice-payment'),
                'lkn_wcip_email' => __('E-mail', 'wc-invoice-payment'),
                'lkn_wcip_exp_date' => __('Data de vencimento', 'wc-invoice-payment'),
                'lkn_wcip_name_invoice_0' => __('Nome do item', 'wc-invoice-payment'),
                'lkn_wcip_amount_invoice_0' => __('Valor do item', 'wc-invoice-payment')
            );
            
            foreach ($required_fields as $field => $label) {
                if (empty($_POST[$field])) {
                    /* translators: %s: field label */
                    throw new Exception(sprintf(__('Campo obrigatório: %s', 'wc-invoice-payment'), $label));
                }
            }
            
            // Coletar dados do formulário
            $invoice_data = array(
                'payment_status' => isset($_POST['lkn_wcip_payment_status']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_payment_status'])) : '',
                'payment_method' => 'multiplePayment', // Valor padrão fixo
                'currency' => \get_woocommerce_currency(), // Moeda padrão do WooCommerce
                'template' => isset($_POST['lkn_wcip_select_invoice_template']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_select_invoice_template'])) : '',
                'language' => isset($_POST['lkn_wcip_select_invoice_language']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_select_invoice_language'])) : '',
                'customer_name' => isset($_POST['lkn_wcip_name']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_name'])) : '',
                'customer_email' => isset($_POST['lkn_wcip_email']) ? sanitize_email(wp_unslash($_POST['lkn_wcip_email'])) : '',
                'country' => isset($_POST['lkn_wcip_country']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_country'])) : '',
                'extra_data' => isset($_POST['lkn_wcip_extra_data']) ? sanitize_textarea_field(wp_unslash($_POST['lkn_wcip_extra_data'])) : '',
                'form_action' => isset($_POST['lkn_wcip_form_actions']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_form_actions'])) : '',
                'due_date' => isset($_POST['lkn_wcip_exp_date']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_exp_date'])) : '',
                'footer_notes' => isset($_POST['lkn-wc-invoice-payment-footer-notes']) ? wp_kses_post(wp_unslash($_POST['lkn-wc-invoice-payment-footer-notes'])) : ''
            );
            
            // Coletar itens da fatura
            $invoice_items = array();
            $counter = 0;
            while (isset($_POST['lkn_wcip_name_invoice_' . $counter])) {
                if (!empty($_POST['lkn_wcip_name_invoice_' . $counter]) && !empty($_POST['lkn_wcip_amount_invoice_' . $counter])) {
                    $invoice_items[] = array(
                        'name' => sanitize_text_field(wp_unslash($_POST['lkn_wcip_name_invoice_' . $counter])),
                        'amount' => floatval(str_replace(',', '.', str_replace('.', '', sanitize_text_field(wp_unslash($_POST['lkn_wcip_amount_invoice_' . $counter])))))
                    );
                }
                $counter++;
            }
            
            if (empty($invoice_items)) {
                throw new Exception(__('Pelo menos um item deve ser adicionado à fatura', 'wc-invoice-payment'));
            }
            
            // Criar a fatura
            $order_id = $this->createInvoiceOrder($invoice_data, $invoice_items, $current_user_id);
            
            if ($order_id) {
                // Redirecionar para página de faturas com mensagem de sucesso
                $redirect_url = add_query_arg(array(
                    'message' => 'invoice_created',
                    'order_id' => $order_id
                ), dokan_get_navigation_url('faturas'));
                
                wp_safe_redirect($redirect_url);
                exit;
            } else {
                throw new Exception(__('Erro ao criar a fatura', 'wc-invoice-payment'));
            }
            
        } catch (Exception $e) {
            // Exibir mensagem de erro
            echo '<div class="dokan-alert dokan-alert-danger"><p>' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    /**
     * Cria uma ordem/fatura
     *
     * @param array $invoice_data Dados da fatura
     * @param array $invoice_items Itens da fatura
     * @param int $vendor_id ID do vendedor
     * @return int|false ID da ordem criada ou false em caso de erro
     */
    private function createInvoiceOrder($invoice_data, $invoice_items, $vendor_id) {
        try {
            // Criar ordem
            $order = \wc_create_order(array(
                'status' => \str_replace('wc-', '', $invoice_data['payment_status']),
                'customer_id' => 0 // Visitante por padrão
            ));
            
            if (!$order) {
                return false;
            }
            
            // Definir autor da ordem como o vendedor
            \wp_update_post(array(
                'ID' => $order->get_id(),
                'post_author' => $vendor_id
            ));
            
            // Adicionar dados de endereçamento
            $order->set_billing_first_name($invoice_data['customer_name']);
            $order->set_billing_email($invoice_data['customer_email']);
            $order->set_billing_country($invoice_data['country']);
            
            // Adicionar itens à ordem como produtos/line items (igual ao administrador)
            $total = 0;
            foreach ($invoice_items as $item) {
                // Criar item de linha do pedido
                $order_item = new \WC_Order_Item_Product();
                $order_item->set_name($item['name']);
                $order_item->set_quantity(1);
                $order_item->set_subtotal($item['amount']);
                $order_item->set_total($item['amount']);
                
                // Adicionar meta dados para identificar como item de fatura
                $order_item->add_meta_data('_lkn_wcip_is_invoice_item', 'yes');
                $order_item->add_meta_data('_lkn_wcip_invoice_item_name', $item['name']);
                $order_item->add_meta_data('_lkn_wcip_invoice_item_amount', $item['amount']);
                
                // Adicionar o item à ordem
                $order->add_item($order_item);
                $total += $item['amount'];
            }
            
            // Definir moeda
            $order->set_currency($invoice_data['currency']);
            
            // Salvar meta dados da fatura
            $order->update_meta_data('_lkn_wcip_invoice_data', $invoice_data);
            $order->update_meta_data('lkn_exp_date', $invoice_data['due_date']);
            $order->update_meta_data('_lkn_wcip_is_invoice', 'yes');
            $order->update_meta_data('_lkn_wcip_is_dokan_invoice', 'yes');
            $order->update_meta_data('_lkn_wcip_vendor_id', $vendor_id);
            $order->update_meta_data('_dokan_vendor_id', $vendor_id);
            $order->save();

            // Adicionar nota indicando que foi criada pelo vendedor (apenas para administradores)
            $vendor_info = \get_userdata($vendor_id);
            $vendor_name = $vendor_info ? $vendor_info->display_name : __('Vendedor', 'wc-invoice-payment');
            $edit_link = \admin_url('admin.php?page=edit-invoice&invoice=' . $order->get_id());
            $note = \sprintf(
                /* translators: %1$s: edit invoice URL, %2$s: vendor name */
                __('Esta <a href="%1$s" target="_blank">fatura</a> foi criada pelo vendedor: %2$s', 'wc-invoice-payment'),
                $edit_link,
                $vendor_name
            );
            $order->add_order_note($note, false, false); // false = apenas para administradores

            // Definir autor do pedido
            wp_update_post([
                'ID'          => $order->get_id(),
                'post_author' => $vendor_id,
            ]);

            // Criar entrada do Dokan
            if ( function_exists( 'dokan_sync_insert_order' ) ) {
                dokan_sync_insert_order( $order->get_id() );
            }

            // Corrigir seller_id na tabela dokan_orders
            global $wpdb;
            $wpdb->update(
                "{$wpdb->prefix}dokan_orders",
                [ 'seller_id' => (int) $vendor_id ],
                [ 'order_id'  => (int) $order->get_id() ],
                [ '%d' ],
                [ '%d' ]
            );
            
            
            
            if (!empty($invoice_data['footer_notes'])) {
                $order->update_meta_data('_lkn_wcip_footer_notes', $invoice_data['footer_notes']);
            }
            
            if (!empty($invoice_data['extra_data'])) {
                $order->update_meta_data('_lkn_wcip_extra_data', $invoice_data['extra_data']);
            }
            
            // Recalcular totais
            $order->calculate_totals();
            $order->save();
            
            // Enviar e-mail se solicitado
            if ($invoice_data['form_action'] === 'send_email') {
                // Enviar e-mail da fatura
                if (\function_exists('WC')) {
                    \WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger($order->get_id());
                }
            }
            
            return $order->get_id();
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obter faturas do vendedor atual
     *
     * @param int $vendor_id ID do vendedor
     * @return array Array de faturas
     */
    private function getVendorInvoices($vendor_id) {
        // Buscar apenas faturas do Dokan (não pagamentos parciais)
        $args = array(
            'limit'        => -1,
            'status'       => array('completed', 'processing', 'pending', 'on-hold', 'cancelled', 'refunded', 'failed'),
            'meta_query'   => array(
                'relation' => 'AND',
                array(
                    'key'     => '_lkn_wcip_is_dokan_invoice',
                    'value'   => 'yes',
                    'compare' => '='
                ),
                array(
                    'key'     => '_dokan_vendor_id',
                    'value'   => $vendor_id,
                    'compare' => '='
                )
            )
        );
        
        $orders = \wc_get_orders($args);
        $invoices = array();
        
        foreach ($orders as $order) {
            if (!$order || !is_a($order, 'WC_Order')) {
                continue;
            }
            
            // Verificar se é realmente uma fatura do vendedor
            if (\function_exists('dokan_get_seller_id_by_order') && \dokan_get_seller_id_by_order($order->get_id()) != $vendor_id) {
                continue;
            }
            
            $invoice_data = array(
                'id'               => $order->get_id(),
                'number'           => $order->get_order_number(),
                'customer_name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'status'           => $order->get_status(),
                'status_label'     => \wc_get_order_status_name($order->get_status()),
                'total_formatted'  => $order->get_formatted_order_total(),
                'type'             => $this->getInvoiceType($order),
                'date_created'     => $order->get_date_created()->date_i18n('d/m/Y H:i'),
                'date_due'         => $this->getInvoiceDueDate($order),
                'edit_url'         => $order->get_edit_order_url(),
            );
            
            $invoices[] = $invoice_data;
        }
        
        // Ordenar por data de criação (mais recente primeiro)
        usort($invoices, function($a, $b) {
            return strcmp($b['date_created'], $a['date_created']);
        });
        
        return $invoices;
    }

    /**
     * Obter tipo da fatura
     *
     * @param WC_Order $order
     * @return string
     */
    private function getInvoiceType($order) {
        if (\get_post_meta($order->get_id(), '_lkn_wcip_is_partial_payment', true) === 'yes') {
            return \__('Pagamento Parcial', 'wc-invoice-payment');
        }
        
        if (\get_post_meta($order->get_id(), '_lkn_wcip_invoice_data', true)) {
            return \__('Fatura', 'wc-invoice-payment');
        }
        
        return \__('Pedido', 'wc-invoice-payment');
    }

    /**
     * Obter data de vencimento da fatura
     *
     * @param WC_Order $order
     * @return string
     */
    private function getInvoiceDueDate($order) {
        $due_date = \get_post_meta($order->get_id(), 'lkn_exp_date', true);
        
        if ($due_date) {
            $date = new \DateTime($due_date);
            return $date->format('d/m/Y');
        }
        
        // Se não tem data de vencimento específica, usar data de criação + 30 dias como padrão
        $created_date = $order->get_date_created();
        if ($created_date) {
            $due_date = clone $created_date;
            $due_date->modify('+30 days');
            return $due_date->date_i18n('d/m/Y');
        }
        
        return '-';
    }

    /**
     * Obter contagem de faturas por status
     *
     * @param int $vendor_id ID do vendedor
     * @return array Array com contagem por status
     */
    private function getInvoiceStatusCounts($vendor_id) {
        // Buscar apenas faturas do Dokan (não pagamentos parciais)
        $args = array(
            'limit'        => -1,
            'status'       => 'any',
            'meta_query'   => array(
                'relation' => 'AND',
                array(
                    'key'     => '_lkn_wcip_is_dokan_invoice',
                    'value'   => 'yes',
                    'compare' => '='
                ),
                array(
                    'key'     => '_dokan_vendor_id',
                    'value'   => $vendor_id,
                    'compare' => '='
                )
            )
        );
        
        $orders = \wc_get_orders($args);
        $counts = array(
            'all' => 0,
            'by_status' => array()
        );
        
        foreach ($orders as $order) {
            if (!$order || !is_a($order, 'WC_Order')) {
                continue;
            }
            
            $status = $order->get_status();
            $counts['all']++;
            
            if (!isset($counts['by_status'][$status])) {
                $counts['by_status'][$status] = 0;
            }
            $counts['by_status'][$status]++;
        }
        
        return $counts;
    }

    /**
     * Obter label do status formatado (similar ao Dokan)
     *
     * @param string $status Status da ordem
     * @return string HTML do label do status
     */
    private function getStatusLabel($status) {
        $status_classes = array(
            'pending'    => 'dokan-label dokan-label-warning',
            'on-hold'    => 'dokan-label dokan-label-warning',
            'processing' => 'dokan-label dokan-label-info',
            'completed'  => 'dokan-label dokan-label-success',
            'cancelled'  => 'dokan-label dokan-label-danger',
            'refunded'   => 'dokan-label dokan-label-danger',
            'failed'     => 'dokan-label dokan-label-danger',
            'partial-pend' => 'dokan-label dokan-label-warning',
            'partial-comp' => 'dokan-label dokan-label-success',
            'partial'    => 'dokan-label dokan-label-info',
        );
        
        $class = isset($status_classes[$status]) ? $status_classes[$status] : 'dokan-label dokan-label-default';
        $label = \wc_get_order_status_name($status);
        
        return sprintf('<span class="%s">%s</span>', $class, $label);
    }

    /**
     * Adiciona query variables para as páginas de faturas do Dokan
     *
     * @param array $query_vars Array de query variables
     * @return array Array modificado com as novas query variables
     */
    public function addDokanInvoicesQueryVar($query_vars) {
        $query_vars['faturas'] = 'faturas';
        $query_vars['nova-fatura'] = 'nova-fatura';
        return $query_vars;
    }

    /**
     * Gera URL segura para download de fatura
     *
     * @param int $invoice_id ID da fatura
     * @return string URL de download com nonce
     */
    private function getInvoiceDownloadUrl($invoice_id) {
        return add_query_arg(array(
            'action' => 'lkn_wcip_download_invoice',
            'invoice_id' => $invoice_id,
            'nonce' => wp_create_nonce('lkn_wcip_download_invoice_' . $invoice_id)
        ), admin_url('admin-ajax.php'));
    }

    /**
     * Registra handlers AJAX para download de faturas
     */
    private function registerDownloadInvoiceAjax() {
        add_action('wp_ajax_lkn_wcip_download_invoice', array($this, 'handleInvoiceDownload'));
        add_action('wp_ajax_nopriv_lkn_wcip_download_invoice', array($this, 'handleInvoiceDownload'));
    }

    /**
     * Handler AJAX para processar download de faturas
     */
    public function handleInvoiceDownload() {
        // Verificar nonce
        $invoice_id = intval($_GET['invoice_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_GET['nonce'] ?? ''));
        
        if (!wp_verify_nonce($nonce, 'lkn_wcip_download_invoice_' . $invoice_id)) {
            wp_die(esc_html__('Security check failed', 'wc-invoice-payment'));
        }

        // Verificar se usuário pode baixar esta fatura
        if (!$this->canUserDownloadInvoice($invoice_id)) {
            wp_die(esc_html__('You do not have permission to download this invoice', 'wc-invoice-payment'));
        }

        // Gerar e servir o PDF
        $this->generateAndServeInvoicePdf($invoice_id);
    }

    /**
     * Verifica se o usuário atual pode baixar a fatura
     *
     * @param int $invoice_id ID da fatura
     * @return bool True se pode baixar, false caso contrário
     */
    private function canUserDownloadInvoice($invoice_id) {
        global $wpdb;
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return false;
        }

        // Verificar se a fatura pertence ao vendedor atual
        $vendor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT seller_id FROM {$wpdb->prefix}dokan_orders WHERE order_id = %d",
            $invoice_id
        ));

        return ($vendor_id && intval($vendor_id) === $current_user_id);
    }

    /**
     * Gera e serve o PDF da fatura
     *
     * @param int $invoice_id ID da fatura
     */
    private function generateAndServeInvoicePdf($invoice_id) {
        // Usar a mesma API REST que já existe para gerar PDFs
        $rest_request = new \WP_REST_Request('GET', '/wc-invoice-payment/v1/generate-pdf');
        $rest_request->set_param('invoice_id', $invoice_id);
        
        // Processar a requisição
        $rest_server = rest_get_server();
        $response = $rest_server->dispatch($rest_request);
        
        if (is_wp_error($response)) {
            wp_die(esc_html__('Error generating PDF', 'wc-invoice-payment'));
        }

        // Se chegou até aqui, o PDF foi gerado e servido pela API REST
        exit;
    }

    /**
     * Adiciona botões de fatura na página de detalhes do pedido do Dokan
     *
     * @param WC_Order $order
     * @return void
     */
    public function addInvoiceButtonsToOrderDetails($order) {
        // Verificar se é uma fatura (pedido criado pelo plugin)
        $is_invoice = $order->get_meta('_lkn_wcip_invoice_data');
        
        if (!$is_invoice) {
            return;
        }
        
        // Carregar CSS específico para os botões
        wp_enqueue_style('wcInvoicePaymentDokanInvoicesStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-dokan-invoices.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        
        // Verificar se o usuário tem permissão (é o vendedor da fatura)
        $current_user_id = get_current_user_id();
        if (!function_exists('dokan_is_user_seller') || !dokan_is_user_seller($current_user_id)) {
            return;
        }
        
        // Verificar se a fatura pertence ao vendedor
        $vendor_id = $order->get_meta('_dokan_vendor_id');
        if (!$vendor_id || (int) $vendor_id !== $current_user_id) {
            // Verificar na tabela wp_dokan_orders se existe relação
            global $wpdb;
            $dokan_order = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}dokan_orders WHERE order_id = %d AND seller_id = %d",
                    $order->get_id(),
                    $current_user_id
                )
            );
            
            if (empty($dokan_order)) {
                return;
            }
        }
        
        $payment_url = ($order->get_meta('_wc_lkn_is_partial_order') === 'yes') ? self::partialCheckoutUrl($order->get_id()) : $order->get_checkout_payment_url();
        $order_id = $order->get_id();
        
        ?>
        <div class="" style="width:100%; margin-top: 20px;">
            <div class="dokan-panel dokan-panel-default lkn-wcip-invoice-actions">
                <div class="dokan-panel-heading">
                    <strong><?php esc_html_e('Ações da Fatura', 'wc-invoice-payment'); ?></strong>
                </div>
                <div class="dokan-panel-body">
                    <div class="lkn-wcip-invoice-buttons" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <button class="dokan-btn dokan-btn-success dokan-btn-sm lkn_wcip_generate_pdf_btn" 
                                data-invoice-id="<?php echo esc_attr($order_id); ?>" 
                                type="button">
                            <i class="fas fa-download"></i> <?php esc_html_e('Baixar Fatura', 'wc-invoice-payment'); ?>
                        </button>
                        
                        <a class="dokan-btn dokan-btn-info dokan-btn-sm" 
                           href="<?php echo esc_url($payment_url); ?>" 
                           target="_blank">
                            <i class="fas fa-credit-card"></i> <?php esc_html_e('Link de Pagamento da Fatura', 'wc-invoice-payment'); ?>
                        </a>
                        
                        <button class="dokan-btn dokan-btn-default dokan-btn-sm" 
                                onclick="lkn_wcip_display_dokan_modal()" 
                                type="button">
                            <i class="fas fa-share-alt"></i> <?php esc_html_e('Compartilhar Link', 'wc-invoice-payment'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de compartilhamento -->
        <div id="lkn-wcip-dokan-share-modal" style="display: none;">
            <div id="lkn-wcip-dokan-share-modal-content">
                    <?php esc_html_e('Compartilhe com', 'wc-invoice-payment'); ?>
                </h3>
                <div id="lkn-wcip-share-buttons" style="display: flex; gap: 15px; justify-content: center; margin: 20px 0;">
                    <a href="#" class="lkn-wcip-share-icon dashicons dashicons-whatsapp" 
                       onclick="lkn_wcip_open_dokan_popup('whatsapp', '<?php echo esc_js($payment_url); ?>')" 
                       style="font-size: 32px; color: #25D366; text-decoration: none;">
                    </a>
                    <a href="#" class="lkn-wcip-share-icon dashicons dashicons-twitter" 
                       onclick="lkn_wcip_open_dokan_popup('twitter', '<?php echo esc_js($payment_url); ?>')" 
                       style="font-size: 32px; color: #1DA1F2; text-decoration: none;">
                    </a>
                    <a href="mailto:?subject=<?php echo esc_attr(__('Link de fatura', 'wc-invoice-payment')); ?>&body=<?php echo esc_attr($payment_url); ?>" 
                       class="lkn-wcip-share-icon dashicons dashicons-email-alt" 
                       target="_blank"
                       style="font-size: 32px; color: #34495e; text-decoration: none;">
                    </a>
                </div>
                <h3 id="lkn-wcip-share-title" style="color: #333;">
                    <?php esc_html_e('Ou copie o link', 'wc-invoice-payment'); ?>
                </h3>
                <div id="lkn-wcip-copy-link-div" style="display: flex; gap: 5px; align-items: center;">
                    <input id="lkn-wcip-dokan-copy-input" type="text" value="<?php echo esc_attr($payment_url); ?>" readonly 
                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                    <span onclick="lkn_wcip_copy_dokan_link()" class="lkn-wcip-copy-button">
                        <span class="dashicons dashicons-clipboard" style="color: white;"></span>
                    </span>
                </div>
                <a href="#" onclick="lkn_wcip_display_dokan_modal()" 
                   style="position: absolute; top: 10px; right: 15px; font-size: 24px; font-weight: bold; color: #aaa; text-decoration: none;">×</a>
            </div>
        </div>

        <script type="text/javascript">
        function lkn_wcip_display_dokan_modal() {
            var modal = document.getElementById('lkn-wcip-dokan-share-modal');
            if (modal.style.display === 'none' || modal.style.display === '') {
                modal.style.display = 'flex';
            } else {
                modal.style.display = 'none';
            }
        }

        function lkn_wcip_open_dokan_popup(type, url) {
            var share_url = '';
            var message = '<?php echo esc_js(__("Confira este link de pagamento: ", "wc-invoice-payment")); ?>';
            
            switch(type) {
                case 'whatsapp':
                    share_url = 'https://wa.me/?text=' + encodeURIComponent(message + url);
                    break;
                case 'twitter':
                    share_url = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(message + url);
                    break;
            }
            
            if (share_url) {
                window.open(share_url, '_blank', 'width=600,height=400');
            }
        }

        function lkn_wcip_copy_dokan_link() {
            var copyInput = document.getElementById('lkn-wcip-dokan-copy-input');
            copyInput.select();
            copyInput.setSelectionRange(0, 99999); // Para dispositivos móveis
            
            try {
                document.execCommand('copy');
                
                var copyButton = event.target.closest('.lkn-wcip-copy-button');
                var originalHTML = copyButton.innerHTML;
                copyButton.innerHTML = '<span class="dashicons dashicons-yes" style="color: white;"></span>';
                
                setTimeout(function() {
                    copyButton.innerHTML = originalHTML;
                }, 2000);
                
            } catch (err) {
                console.error('Erro ao copiar: ', err);
            }
        }

        // Funcionalidade do botão de download PDF
        jQuery(document).ready(function($) {
            $('.lkn_wcip_generate_pdf_btn').on('click', function(e) {
                e.preventDefault();
                
                var invoiceId = $(this).data('invoice-id');
                if (!invoiceId) {
                    return;
                }
                
                var downloadUrl = '<?php echo esc_url(rest_url('wc-invoice-payment/v1/generate-pdf')); ?>?invoice_id=' + invoiceId;
                
                // Criar link temporário para download
                var link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'invoice-' + invoiceId + '.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
        </script>
        <?php
    }

    /**
     * Inicializa sistema de faturas do Dokan
     */
    public function initDokanInvoicesSystem() {
        $this->registerDownloadInvoiceAjax();
    }

    /**
     * Injeta o step de Pagamento Parcial no Checkout Blocks via render_block.
     * Padrao identico ao woo-better-shipping-calculator (entrega agendada).
     */
    public function injectPartialSplitStepIntoCheckout($content, $block) {
        $blockName = isset($block['blockName']) ? $block['blockName'] : '';
        if ($blockName !== 'woocommerce/checkout') return $content;
        if (strpos($content, 'lkn-wcip-partial-split-step') !== false) return $content;

        $step_html = $this->buildPartialSplitStepHtml();
        if (empty($step_html)) return $content;

        $pattern = '/(<div[^>]*data-block-name="woocommerce\/checkout-payment-block"[^>]*><\/div>)/';
        return preg_replace($pattern, $step_html . '$1', $content, 1);
    }

    /**
     * Injeta step de Pagamento Parcial no checkout clássico (shortcode).
     */
    public function injectPartialSplitStepClassic() {
        if (get_option('lkn_wcip_partial_payments_enabled', '') !== 'yes') return;

        $step_html = $this->buildPartialSplitStepHtml();
        if (empty($step_html)) return;

        echo $step_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built with esc_* functions
    }

    /**
     * Constrói o HTML do step de Pagamento Parcial (compartilhado Blocks + clássico).
     * Retorna HTML ou string vazia se não aplicável.
     */
    private function buildPartialSplitStepHtml() {
        if (get_option('lkn_wcip_partial_payments_enabled', '') !== 'yes') return '';
        if (!is_checkout()) return '';

        $pay_remaining = isset($_GET['pay_remaining']) ? intval($_GET['pay_remaining']) : 0;
        // Fallback via sessão para WC Blocks (AJAX pode não enviar GET params)
        if ($pay_remaining <= 0 && WC()->session) {
            $session_parent_id = (int) WC()->session->get('lkn_partial_parent_order_id');
            $session_amount = (float) WC()->session->get('lkn_partial_amount');
            if ($session_parent_id > 0 && $session_amount > 0) {
                $pay_remaining = $session_parent_id;
            }
        }
        $title        = esc_html__('Pagamento Parcial', 'wc-invoice-payment');
        $symbol       = get_woocommerce_currency_symbol(get_woocommerce_currency());
        $base_max     = WC()->cart ? (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_shipping_total() - (float) WC()->cart->get_discount_total() : 0;
        $base_max_f   = number_format($base_max, 2, ',', '.');

        // Detecta se o usuário tem pagamentos parciais pendentes de retomada
        $pending_orders = array();
        if ($pay_remaining <= 0 && get_current_user_id() > 0) {
            $pending_orders = wc_get_orders(array(
                'limit'        => 10,
                'customer_id'  => get_current_user_id(),
                'meta_query'   => array(
                    array('key' => '_wc_lkn_pay_remaining_pending', 'value' => 'yes'),
                ),
            ));
            if (empty($pending_orders)) {
                $pending_orders = wc_get_orders(array(
                    'limit'       => 20,
                    'meta_query'  => array(
                        array('key' => '_wc_lkn_pay_remaining_pending', 'value' => 'yes'),
                    ),
                ));
                if (!empty($pending_orders)) {
                    $user = get_userdata(get_current_user_id());
                    if ($user && $user->user_email) {
                        $email = $user->user_email;
                        $pending_orders = array_values(array_filter($pending_orders, function ($o) use ($email) {
                            return $o->get_billing_email() === $email;
                        }));
                    }
                }
            }
        }

        // Deduplica: resolve filhos ao pai, pula pais já concluídos/cancelados
        if (!empty($pending_orders)) {
            $seen_parents = array();
            $deduped = array();
            $done_statuses = array('wc-partial-comp', 'wc-partial-cancelled', 'wc-completed', 'wc-cancelled');
            foreach ($pending_orders as $po) {
                $parent_id = (int) $po->get_meta('_wc_lkn_parent_id');
                $resolved_id = $parent_id > 0 ? $parent_id : $po->get_id();
                if (in_array($resolved_id, $seen_parents, true)) continue;
                $parent = $parent_id > 0 ? wc_get_order($parent_id) : $po;
                if (!$parent) continue;
                // Pula pais que já estão em status final
                if (in_array('wc-' . $parent->get_status(), $done_statuses, true)) continue;
                $seen_parents[] = $resolved_id;
                $deduped[] = $parent;
            }
            $pending_orders = array_filter($deduped);
        }

        $step  = '<div class="wc-block-components-checkout-step lkn-wcip-partial-split-step'
            . ($pay_remaining > 0 ? ' lkn-wcip-pay-remaining' : '')
            . '">';
        $step .= '<div class="wc-block-components-checkout-step__heading" style="margin-bottom:16px">';
        $step .= '<h2 class="wc-block-components-title wc-block-components-checkout-step__title">' . $title . '</h2>';
        $step .= '</div>';
        $step .= '<div class="wc-block-components-checkout-step__content">';
        $step .= '<div class="lkn-wcip-partial-split-step-content">';

        // Container (mesmo estilo do original)
        $step .= '<div class="lkn-wcip-split-blocks-container" style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:6px;padding:16px">';

        $is_first_payment = false; // definido no elseif ($pay_remaining > 0)

        if (!empty($pending_orders)) {
            // Modo "retomar": lista de pedidos com pagamento pendente
            $pay_rest_url = esc_url(rest_url('invoice_payments/create_partial_payment'));
            $nonce = esc_attr(wp_create_nonce('wp_rest'));
            $rest_url = esc_url(rest_url('invoice_payments/cancel_partial_payment'));

            $plural = count($pending_orders) > 1;
            $step .= '<label style="font-size:14px;margin-bottom:8px;display:block">';
            $step .= '<span style="font-size:20px">⚠️</span> ';
            if ($plural) {
                $step .= esc_html__('Você tem pagamentos parciais pendentes:', 'wc-invoice-payment');
            } else {
                $step .= esc_html__('Você iniciou um pagamento parcial e não concluiu:', 'wc-invoice-payment');
            }
            $step .= '</label>';

            foreach ($pending_orders as $po) {
                $pid             = $po->get_id();
                $original_total  = (float) $po->get_meta('_wc_lkn_original_total');
                $confirmed       = $this->recalcConfirmedFromChildren($po);
                $remaining       = round($original_total - $confirmed, 2);

                // Encontra filhos com status pendente (não concluído conforme conf do gateway)
                $cancel_id = $pid;
                $pending_child_id = null;
                $partials_ids = $po->get_meta('_wc_lkn_partials_id', true);
                $complete_statuses = $this->getPartialCompleteStatuses();
                if (is_array($partials_ids) && !empty($partials_ids)) {
                    foreach ($partials_ids as $cid) {
                        $child = wc_get_order((int) $cid);
                        if (!$child) continue;
                        $child_status = $child->get_status();
                        $child_pending = !in_array($child_status, $complete_statuses, true);
                        if ($child_pending) {
                            $cancel_id = (int) $cid;
                            $pending_child_id = (int) $cid;
                            break;
                        }
                    }
                }

                // Nome do primeiro produto
                $items = $po->get_items();
                $first_item = !empty($items) ? reset($items) : null;
                $product_name = $first_item ? $first_item->get_name() : __('Pedido', 'wc-invoice-payment');

                // Se remaining=0 mas existe filho pendente, redireciona direto pro pagamento
                $resume_target_id = $pid;
                $resume_amount = $remaining;
                $resume_url = $pay_rest_url;
                $is_order_pay = false;
                if ($pending_child_id) {
                    $pending_child = wc_get_order($pending_child_id);
                    if ($pending_child) {
                        // Se o filho foi cancelado (ex: timeout PIX), recalcula confirmed e manda pro pay_remaining
                        if ($pending_child->get_status() === 'cancelled') {
                            $child_base = (float) $pending_child->get_meta('_wc_lkn_partial_amount_paid');
                            if ($child_base <= 0) $child_base = (float) $pending_child->get_total();
                            $new_confirmed = max(0, $confirmed - $child_base);
                            $po->update_meta_data('_wc_lkn_total_confirmed', $new_confirmed);
                            $po->update_meta_data('_wc_lkn_pay_remaining_pending', 'yes');
                            // Remove filho cancelado da lista de parciais
                            $partials_ids = array_diff($partials_ids, array($pending_child_id));
                            $po->update_meta_data('_wc_lkn_partials_id', array_values($partials_ids));
                            $po->save();
                            $resume_target_id = $pid;
                            $resume_amount = round($original_total - $new_confirmed, 2);
                            $resume_url = $pay_rest_url;
                            $is_order_pay = false;
                        } else {
                            // Filho pendente (ex: PIX não pago): botão "Substituir"
                            $is_order_pay = false;
                            $resume_target_id = $pid;
                            $resume_amount = (float) $pending_child->get_total();
                            $resume_url = esc_url(rest_url('invoice_payments/replace_pending_partial'));
                        }
                    }
                }

                $step .= '<div style="background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:10px;margin-bottom:8px">';
                $step .= '<div style="font-size:13px;color:#333;margin-bottom:4px"><strong>#' . esc_html($po->get_order_number()) . '</strong> — ' . esc_html($product_name) . '</div>';
                $step .= '<div style="font-size:12px;color:#666;margin-bottom:8px">';
                $step .= esc_html__('Pago:', 'wc-invoice-payment') . ' ' . $symbol . '&nbsp;' . number_format($confirmed, 2, ',', '.');
                $step .= ' &nbsp;|&nbsp; ';
                $step .= esc_html__('Restante:', 'wc-invoice-payment') . ' <strong>' . $symbol . '&nbsp;' . number_format($remaining, 2, ',', '.') . '</strong>';
                $step .= '</div>';
                $step .= '<div style="display:flex;gap:6px">';
                // Botão: replace_pending_partial (filho pendente) ou create_partial_payment (demais casos)
                if ($pending_child_id && $resume_url !== $pay_rest_url) {
                    $step .= '<button class="lkn-wcip-replace-pending-btn" type="button" style="padding:6px 12px;font-size:12px;font-weight:600;background:#007cba;color:#fff;border:none;border-radius:3px;cursor:pointer" data-order-id="' . $resume_target_id . '" data-pending-child-id="' . $pending_child_id . '" data-nonce="' . $nonce . '" data-rest-url="' . $resume_url . '">' . esc_html__('Continuar', 'wc-invoice-payment') . '</button>';
                } elseif (!$is_order_pay) {
                    $step .= '<button class="lkn-wcip-resume-btn" type="button" style="padding:6px 12px;font-size:12px;font-weight:600;background:#007cba;color:#fff;border:none;border-radius:3px;cursor:pointer" data-order-id="' . $resume_target_id . '" data-amount="' . $resume_amount . '" data-nonce="' . $nonce . '" data-rest-url="' . $resume_url . '">' . esc_html__('Continuar', 'wc-invoice-payment') . '</button>';
                }
                $step .= '<button class="lkn-wcip-cancel-pending-btn" type="button" style="padding:6px 12px;font-size:12px;background:#fff;color:#d63638;border:1px solid #d63638;border-radius:3px;cursor:pointer" data-order-id="' . $pid . '" data-rest-url="' . $rest_url . '" data-nonce="' . $nonce . '">' . esc_html__('Cancelar', 'wc-invoice-payment') . '</button>';
                $step .= '</div></div>';
            }
        } elseif ($pay_remaining > 0) {
            // Determina se é 1/2 (primeiro pagamento) ou 2/2 (segundo)
            $parent_order = wc_get_order($pay_remaining);
            $confirmed = $parent_order ? $this->recalcConfirmedFromChildren($parent_order) : 0;
            $is_first_payment = ($confirmed <= 0);
            
            $step .= '<label style="display:flex;align-items:flex-start;gap:8px;cursor:default;margin-bottom:0;font-size:14px;pointer-events:none">';
            $step .= '<input id="lkn-wcip-split-checkbox" type="checkbox" checked class="lkn-wcip-checkbox-locked" style="width:18px;height:18px;margin-top:1px;flex-shrink:0">';
            $step .= '<span>Marque para dividir o pagamento.</span>';
            $step .= '</label>';
            
            if ($is_first_payment) {
                // 1/2: mostra valor máximo e input de valor
                $original_total = ($parent_order && (float) $parent_order->get_meta('_wc_lkn_original_total') > 0)
                    ? (float) $parent_order->get_meta('_wc_lkn_original_total')
                    : $base_max;
                $min_amount = (float) get_option('lkn_wcip_partial_interval_minimum', 0);
                if ($min_amount > 0) {
                    $step .= '<p class="lkn-wcip-base-min-msg" style="font-size:12px;color:#999;margin:8px 0 0">Valor mínimo por parcela: <strong>' . $symbol . '&nbsp;' . number_format($min_amount, 2, ',', '.') . '</strong></p>';
                }
                $step .= '<p class="lkn-wcip-base-max-msg" style="font-size:13px;color:#666;margin:4px 0 0">Valor máximo permitido: <strong class="lkn-wcip-base-max-val">' . $symbol . '&nbsp;' . number_format($original_total, 2, ',', '.') . '</strong> <span style="font-size:12px;color:#999">(sem taxas ou descontos)</span></p>';
            }
        } else {
            // Checkout normal (1ª vez): checkbox desmarcado, apenas ele visível
            $step .= '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;margin-bottom:0;font-size:14px">';
            $step .= '<input id="lkn-wcip-split-checkbox" type="checkbox" style="width:18px;height:18px;margin-top:1px;flex-shrink:0">';
            $step .= '<span>Marque para dividir o pagamento.</span>';
            $step .= '</label>';
        }

        // Campos (input + botão)
        // 1/2 (primeiro pagamento via pay_remaining): campos visíveis
        // 2/2 (segundo pagamento): campos ocultos
        // Checkout normal: ocultos até marcar checkbox
        $fields_visible = ($pay_remaining > 0 && $is_first_payment);
        $step .= '<div class="lkn-wcip-split-fields" style="margin-top:8px;' . ($fields_visible ? '' : 'display:none') . '">';
        // Explicação "Como funciona?" (apenas checkout normal)
        if ($pay_remaining <= 0) {
        $step .= '<div style="padding:12px;background:#f0f6fc;border-left:3px solid #007cba;border-radius:4px;font-size:13px;color:#444;line-height:1.6;margin-bottom:10px">';
        $step .= '<strong>' . esc_html__('Como funciona?', 'wc-invoice-payment') . '</strong> ' . esc_html__('Você paga uma parte agora e o restante depois. O valor informado será cobrado neste momento, e o saldo restante ficará disponível para pagamento futuro.', 'wc-invoice-payment');
        $step .= '</div>';
        }
        $step .= '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
        $step .= '<input id="lkn-wcip-split-amount" type="text" placeholder="' . $symbol . ' 0,00" style="flex:1 1 140px;padding:10px 12px;font-size:16px;border:1px solid #ccc;border-radius:4px;min-width:120px">';
        
        // Texto do botão: "Iniciar pagamento parcial" no checkout normal, "Split pagamento" no pay_remaining
        $btn_text = ($pay_remaining > 0) ? 'Split pagamento' : 'Iniciar pagamento parcial';
        $step .= '<button id="lkn-wcip-split-btn" type="button" style="padding:10px 20px;font-size:14px;font-weight:600;background:#007cba;color:#fff;border:none;border-radius:4px;cursor:pointer">' . esc_html($btn_text) . '</button>';
        $step .= '</div>';
        // Mensagem de erro (#1/2)
        $step .= '<div class="lkn-wcip-split-error wc-block-components-validation-error" style="display:none;margin-top:8px;color:#cc1818;font-size:14px" role="alert"><p style="margin:0">' . esc_html__('Digite o valor do pagamento parcial e clique em "Split pagamento" antes de finalizar.', 'wc-invoice-payment') . '</p></div>';
        // Texto de espera (apenas checkout normal)
        if ($pay_remaining <= 0) {
        $step .= '<p style="margin:8px 0 0;font-size:12px;color:#999;text-align:center">' . esc_html__('Após marcar, aguarde o gateway "Pagamento Parcial" aparecer antes de prosseguir.', 'wc-invoice-payment') . '</p>';
        }
        $step .= '</div>'; // .lkn-wcip-split-fields

        // Resultado (visível imediatamente no pay_remaining)
        $step .= '<div id="lkn-wcip-split-result" style="' . ($pay_remaining > 0 ? '' : 'display:none') . '"></div>';

        // Botão "Continuar pagamento" — scroll até opções de pagamento (pay_remaining apenas)
        if ($pay_remaining > 0) {
            $step .= '<button type="button" class="lkn-wcip-scroll-to-payment" style="display:none;width:100%;margin-top:10px;padding:10px 20px;font-size:14px;font-weight:600;background:#007cba;color:#fff;border:none;border-radius:4px;cursor:pointer">'
                . esc_html__('Continuar pagamento', 'wc-invoice-payment')
                . '</button>';
        }

        $step .= '</div>'; // .lkn-wcip-split-blocks-container
        $step .= '</div>'; // .lkn-wcip-partial-split-step-content
        $step .= '</div></div>';

        return $step;
    }

    /**
     * Limpa sessao de split ao carregar a pagina de checkout (F5).
     * Roda em enqueue_block_assets com priority 1, antes de enfileirar scripts.
     */
    public function clearPartialSplitOnPageLoad() {
        if (!is_checkout()) return;
        if (!WC()->session) return;

        // Só preserva se for fluxo "pagar restante" com o param pay_remaining na URL
        $pay_remaining = isset($_GET['pay_remaining']) ? intval($_GET['pay_remaining']) : 0;
        $session_parent_id = WC()->session->get('lkn_partial_parent_order_id');

        if ($pay_remaining > 0 && $session_parent_id && $pay_remaining == $session_parent_id) {
            $parent = wc_get_order($pay_remaining);
            if ($parent) {
                $confirmed = (float) $parent->get_meta('_wc_lkn_total_confirmed');
                if ($confirmed > 0) {
                    // 2/2: calcula automaticamente o restante
                    $original_total = (float) $parent->get_meta('_wc_lkn_original_total');
                    $remaining = round($original_total - $confirmed, 2);
                    if ($remaining > 0) {
                        WC()->session->set('lkn_partial_amount', $remaining);
                    }
                } else {
                    // 1° parcial: limpa valor residual da sessão anterior
                    WC()->session->__unset('lkn_partial_amount');
                    WC()->session->__unset('lkn_partial_remaining');
                    WC()->session->__unset('lkn_partial_disabled_gateways');
                    WC()->session->__unset('lkn_partial_gateway_fees');
                }

                // 1/2 e 2/2: restaura o frete do pai na sessão
                $rates_json = $parent->get_meta('_wc_lkn_chosen_shipping_rates');
                if ($rates_json) {
                    $rate_ids = json_decode($rates_json, true);
                    if (is_array($rate_ids) && !empty($rate_ids)) {
                        WC()->session->set('lkn_partial_shipping_rate_ids', $rate_ids);
                        WC()->session->set('chosen_shipping_methods', $rate_ids);
                    }
                }

                // Limpa gateway escolhido — o cliente vai escolher um novo neste checkout.
                // Evita que fees do gateway anterior (ex: juros de cartão) vazem pro split.
                WC()->session->__unset('chosen_payment_method');
            }
            return;
        }
        WC()->session->__unset('lkn_partial_amount');
        WC()->session->__unset('lkn_partial_remaining');
        WC()->session->__unset('lkn_partial_disabled_gateways');
        WC()->session->__unset('lkn_partial_base_total');
        WC()->session->__unset('lkn_partial_gateway_fees');
        WC()->session->__unset('lkn_partial_order_id');
        WC()->session->__unset('lkn_partial_parent_order_id');
        WC()->session->__unset('lkn_partial_shipping_methods');
        WC()->session->__unset('lkn_partial_shipping_rate_ids');
        WC()->session->__unset('lkn_partial_mode_active');
    }

    // ================================================================
    // SPLIT DE PAGAMENTO NO CHECKOUT (ordem unica, sem redirect)
    // ================================================================

    /**
     * Registra o namespace 'woo_invoice_payment' para extensionCartUpdate.
     * O JS chama extensionCartUpdate({namespace:'woo_invoice_payment', ...})
     * que gera POST /batch real — Rede/Cielo interceptam e refazem parcelas.
     */
    public function registerPartialSplitExtensionCallback() {
        if (!function_exists('woocommerce_store_api_register_update_callback')) return;

        woocommerce_store_api_register_update_callback([
            'namespace' => 'woo_invoice_payment',
            'callback'  => function () {},
        ]);
    }

    /**
     * Hook woocommerce_cart_calculate_fees — priority 9999.
     * Aplica um fee negativo (desconto) igual à diferença entre o total
     * já calculado e o valor parcial informado pelo usuário.
     *
     * Como roda em prioridade máxima, todos os outros fees (juros de gateway,
     * taxas percentuais, etc.) já foram computados — ninguém tem nosso fee
     * como base de cálculo.
     */
    public function applyPartialSplitFee($cart) {
        if (!WC()->session) {
            return;
        }

        $partial_amount = (float) WC()->session->get('lkn_partial_amount', 0);

        if ($partial_amount <= 0) {
            return;
        }

        // Calcula o total MANUALMENTE (mesmo padrão do Rede).
        // get_total('edit') retorna ZERO dentro do calculate_fees porque
        // o WooCommerce só soma os fees ao total DEPOIS de todos os hooks.
        $cart_total = (float) $cart->get_subtotal()
                    + (float) $cart->get_shipping_total()
                    + (float) $cart->get_taxes_total()
                    - (float) $cart->get_discount_total();

        // Base (sem taxas/fees de gateway): valor máximo que o cliente pode pagar
        $base_total = (float) $cart->get_subtotal()
                    + (float) $cart->get_shipping_total()
                    - (float) $cart->get_discount_total();
        WC()->session->set('lkn_partial_base_total', $base_total);

        // Soma dos fees do gateway (juros, taxas, etc.)
        $gateway_fees = 0.0;
        $fee_details = array();
        $current_fees = array();
        foreach ($cart->get_fees() as $fee) {
            $fee_name = $fee->name;
            $fee_amount = (float) $fee->amount;
            $current_fees[] = $fee_name . '=' . $fee_amount;
            // Ignora nosso PRÓPRIO fee
            if ($fee_name !== __('Pagamento Parcial (saldo restante)', 'wc-invoice-payment')) {
                $cart_total += $fee_amount;
                $gateway_fees += $fee_amount;
                $fee_details[] = array('name' => $fee_name, 'amount' => $fee_amount);
            }
        }
        WC()->session->set('lkn_partial_gateway_fees', $gateway_fees);

        $remaining = $base_total - $partial_amount;

        if ($remaining <= 0.01) {
            WC()->session->__unset('lkn_partial_amount');
            WC()->session->__unset('lkn_partial_remaining');
            WC()->session->__unset('lkn_partial_disabled_gateways');
            WC()->session->__unset('lkn_partial_base_total');
            WC()->session->__unset('lkn_partial_gateway_fees');
            return;
        }

        $fee_label = __('Pagamento Parcial (saldo restante)', 'wc-invoice-payment');

        // Fee NEGATIVO fixo (não percentual) → desconto
        $cart->add_fee($fee_label, -$remaining, false);

        // Atualiza o remaining na sessão pra usar na thank-you page
        WC()->session->set('lkn_partial_remaining', $remaining);
    }

    /**
     * AJAX: salva o valor parcial na sessão e retorna o novo estado.
     */
    public function ajaxSetPartialSplit() {
        check_ajax_referer('lkn_wcip_partial_split', 'nonce');

        $partial_amount = isset($_POST['partialAmount'])
            ? floatval(wp_unslash($_POST['partialAmount']))
            : 0.0;

        if ($partial_amount <= 0) {
            wp_send_json_error(array('message' => __('Digite um valor válido.', 'wc-invoice-payment')));
        }

        if (!WC()->cart || WC()->cart->is_empty()) {
            wp_send_json_error(array('message' => __('Carrinho vazio.', 'wc-invoice-payment')));
        }

        $cart_total = (float) WC()->cart->get_total('edit');

        if ($partial_amount >= $cart_total) {
            wp_send_json_error(array(
                'message' => __('O valor parcial deve ser menor que o total do carrinho.', 'wc-invoice-payment'),
            ));
        }

        $min_amount = (float) get_option('lkn_wcip_partial_interval_minimum', 0);
        if ($min_amount > 0 && $partial_amount < $min_amount) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s: minimum partial amount */
                    __('O valor mínimo para pagamento parcial é %s.', 'wc-invoice-payment'),
                    wc_price($min_amount)
                ),
            ));
        }

        // Salva na sessão
        WC()->session->set('lkn_partial_amount', $partial_amount);

        // Calcula o remaining (pré-fee, pra referência do JS)
        $remaining = $cart_total - $partial_amount;
        if ($remaining < 0) $remaining = 0;

        // Valida que o restante também não fique abaixo do mínimo
        if ($min_amount > 0 && $remaining > 0 && $remaining < $min_amount) {
            WC()->session->__unset('lkn_partial_amount');
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %1$s: minimum amount, %2$s: remaining amount */
                    __('O valor restante (R$ %2$s) não pode ser menor que o mínimo para pagamento parcial (R$ %1$s). Ajuste o valor informado.', 'wc-invoice-payment'),
                    number_format($min_amount, 2, ',', '.'),
                    number_format($remaining, 2, ',', '.')
                ),
            ));
        }
        WC()->session->set('lkn_partial_remaining', $remaining);

        // Determina gateways desabilitados (pula se for fluxo "pagar restante")
        if (!WC()->session->get('lkn_partial_order_id')) {
            $disabled = $this->getDisabledGatewaysForPartialSplit();
            WC()->session->set('lkn_partial_disabled_gateways', $disabled);
        }

        // Força recálculo do carrinho (nosso fee priority 1 aplica, depois gateway fees)
        WC()->cart->calculate_totals();

        // Computa final_total MANUALMENTE — get_total('edit') pode retornar 0 dentro
        // do calculate_fees, e get_total() no contexto AJAX também pode vir zerado.
        // Mesmo padrão: subtotal + shipping + taxes + fees - discounts.
        $fee_total = 0.0;
        $gateway_fees = 0.0;
        foreach (WC()->cart->get_fees() as $fee) {
            $fee_amount = (float) $fee->amount;
            $fee_total += $fee_amount;
            if ($fee->name !== __('Pagamento Parcial (saldo restante)', 'wc-invoice-payment')) {
                $gateway_fees += $fee_amount;
            }
        }

        $base_total = (float) WC()->session->get('lkn_partial_base_total', $cart_total);
        $final_total = $base_total + $fee_total;
        WC()->session->set('lkn_partial_gateway_fees', $gateway_fees);

        // Recalcula o remaining: base_total - o que sera pago agora (sem juros)
        $remaining = $base_total - $partial_amount;
        if ($remaining < 0) $remaining = 0;
        WC()->session->set('lkn_partial_remaining', $remaining);

        // Verifica se o gateway selecionado atual é válido
        $chosen = (string) WC()->session->get('chosen_payment_method', '');
        $enabled_ids = $this->getEnabledGatewayIdsForPartialSplit();
        if ($chosen && $enabled_ids && !in_array($chosen, $enabled_ids, true)) {
            // Auto-seleciona o primeiro gateway habilitado
            $first = reset($enabled_ids);
            WC()->session->set('chosen_payment_method', $first);
        }

        wp_send_json_success(array(
            'partial_amount'    => $partial_amount,
            'cart_total'        => $final_total,
            'base_max'          => $base_total,
            'gateway_fees'      => $gateway_fees,
            'remaining'         => $remaining,
            'new_total'         => $final_total,
            'disabled_gateways' => $disabled,
            'active_gateway'    => (string) WC()->session->get('chosen_payment_method'),
        ));
    }

    /**
     * AJAX: remove o split parcial da sessão e restaura o carrinho.
     */
    public function ajaxClearPartialSplit() {
        check_ajax_referer('lkn_wcip_partial_split', 'nonce');

        WC()->session->__unset('lkn_partial_amount');
        WC()->session->__unset('lkn_partial_remaining');
        WC()->session->__unset('lkn_partial_disabled_gateways');
        WC()->session->__unset('lkn_partial_base_total');
        WC()->session->__unset('lkn_partial_gateway_fees');

        // Força recálculo sem o fee do split
        WC()->cart->calculate_totals();

        wp_send_json_success(array(
            'cart_total' => (float) WC()->cart->get_total(),
            'message'    => __('Pagamento parcial cancelado.', 'wc-invoice-payment'),
        ));
    }

    /**
     * AJAX: retorna o estado atual do split.
     */
    public function ajaxGetPartialSplitState() {
        check_ajax_referer('lkn_wcip_partial_split', 'nonce');

        if (!WC()->session) {
            wp_send_json_success(array(
                'active'         => false,
                'partial_amount' => 0,
                'cart_total'     => (float) (WC()->cart ? WC()->cart->get_total('edit') : 0),
                'remaining'      => 0,
                'base_max'       => 0,
                'gateway_fees'   => 0,
                'disabled_gateways' => array(),
                'active_gateway' => '',
            ));
        }

        $partial_amount = (float) WC()->session->get('lkn_partial_amount', 0);
        $active = $partial_amount > 0;

        $cart = WC()->cart;
        if ($cart) {
            $base_total = (float) $cart->get_subtotal()
                        + (float) $cart->get_shipping_total()
                        - (float) $cart->get_discount_total();

            $cart->calculate_totals();

            $gateway_fees = 0.0;
            $cart_total   = (float) $cart->get_total();
            foreach ($cart->get_fees() as $fee) {
                $fee_name = $fee->name;
                if ($fee_name !== __('Pagamento Parcial (saldo restante)', 'wc-invoice-payment')) {
                    $gateway_fees += (float) $fee->amount;
                }
            }
        } else {
            $cart_total   = 0;
            $base_total   = 0;
            $gateway_fees = 0;
        }

        $remaining = (float) WC()->session->get('lkn_partial_remaining', 0);

        wp_send_json_success(array(
            'active'            => $active,
            'partial_amount'    => $partial_amount,
            'cart_total'        => $cart_total,
            'remaining'         => $remaining,
            'base_max'          => $base_total,
            'gateway_fees'      => $gateway_fees,
            'disabled_gateways' => WC()->session->get('lkn_partial_disabled_gateways', array()),
            'active_gateway'    => (string) WC()->session->get('chosen_payment_method', ''),
        ));
    }

    /**
     * AJAX: inicia pagamento parcial no checkout (1ª vez).
     * Cria o pedido pai sem processar pagamento e retorna a URL
     * de redirecionamento (?pay_remaining=ID_DO_PAI).
     */
    public function ajaxInitiatePartialPayment() {
        check_ajax_referer('lkn_wcip_partial_split', 'nonce');

        if (!WC()->cart || WC()->cart->is_empty()) {
            wp_send_json_error(array('message' => __('Carrinho vazio.', 'wc-invoice-payment')));
        }

        // Cria o pedido pai a partir do carrinho (sem checkout — sem pagamento)
        $order = wc_create_order(array(
            'customer_id' => get_current_user_id(),
            'created_via' => 'checkout',
        ));

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $item = new \WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity($cart_item['quantity']);
            $item->set_subtotal((float) $cart_item['line_subtotal']);
            $item->set_total((float) $cart_item['line_total']);
            $order->add_item($item);
        }

        foreach (WC()->cart->get_fees() as $fee) {
            if ($fee->name !== __('Pagamento Parcial (saldo restante)', 'wc-invoice-payment')) {
                $fee_item = new \WC_Order_Item_Fee();
                $fee_item->set_name($fee->name);
                $fee_item->set_amount($fee->amount);
                $fee_item->set_total($fee->total);
                $order->add_item($fee_item);
            }
        }

        $customer = WC()->customer;
        $order->set_address($customer->get_billing(), 'billing');
        $order->set_address($customer->get_shipping(), 'shipping');
        $order->set_billing_email($customer->get_billing_email());

        $shipping_total = (float) WC()->cart->get_shipping_total();
        if ($shipping_total > 0) {
            $shipping_item = new \WC_Order_Item_Shipping();
            $shipping_item->set_method_title(__('Frete', 'wc-invoice-payment'));
            $shipping_item->set_total($shipping_total);
            $order->add_item($shipping_item);
        }

        $this->saveChosenShippingToOrder($order);

        $subtotal = 0;
        foreach ($order->get_items('line_item') as $item) {
            $subtotal += (float) $item->get_subtotal();
        }
        if ($subtotal <= 0) $subtotal = (float) WC()->cart->get_subtotal();
        $base_total = $subtotal + $shipping_total;

        $order->set_total($base_total);
        $order->save();

        $order->update_meta_data('_wc_lkn_original_total', $base_total);
        $order->update_meta_data('_wc_lkn_is_partial_main_order', 'yes');
        $order->update_meta_data('_wc_lkn_total_peding', $base_total);
        $order->update_meta_data('_wc_lkn_total_confirmed', 0);
        $order->update_meta_data('_wc_lkn_pay_remaining_pending', 'yes');
        $order->update_meta_data('lkn_ini_date', gmdate('Y-m-d'));
        $order->update_meta_data('lkn_exp_date', gmdate('Y-m-d'));

        // Status processing garante que o analytics registra o pedido (status nativo).
        $order->set_status('processing');
        $order->add_order_note('Pagamento parcial iniciado. Aguardando primeiro pagamento.');
        $order->save();

        // Migra pra wc-partial (controle interno). O analytics já registrou o
        // pedido como processing — essa transição não afeta o analytics.
        $order->set_status('wc-partial');
        $order->add_order_note('Status do pedido alterado para Pagamento parcial.');
        $order->save();

        WC()->session->set('lkn_partial_amount', 0);
        WC()->session->set('lkn_partial_parent_order_id', $order->get_id());
        WC()->session->set('lkn_partial_base_total', $base_total);
        WC()->session->__unset('lkn_partial_mode_active');

        WC()->cart->empty_cart();
        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            $vid = $item->get_variation_id();
            if ($pid) WC()->cart->add_to_cart($pid, $item->get_quantity(), $vid);
        }

        wp_send_json_success(array(
            'redirect_url' => add_query_arg('pay_remaining', $order->get_id(), wc_get_checkout_url()),
            'order_id'     => $order->get_id(),
        ));
    }

    /**
     * AJAX: ativa/desativa o modo parcial no checkout (esconde/mostra gateways).
     * Chamado quando o usuário marca/desmarca o checkbox de split.
     */
    public function ajaxTogglePartialMode() {
        check_ajax_referer('lkn_wcip_partial_split', 'nonce');

        $active = isset($_POST['active']) && $_POST['active'] === '1';

        if ($active) {
            WC()->session->set('lkn_partial_mode_active', 'yes');
        } else {
            WC()->session->__unset('lkn_partial_mode_active');
            WC()->session->__unset('chosen_payment_method');
        }

        wp_send_json_success(array('active' => $active));
    }

    /**
     * Retorna array de IDs de gateways habilitados para pagamento parcial.
     * Usa a configuração já existente: lkn_wcip_partial_payments_method_{id}
     */
    private function getEnabledGatewayIdsForPartialSplit() {
        if (!WC()->payment_gateways()) return array();

        $enabled = array();
        foreach (WC()->payment_gateways()->get_available_payment_gateways() as $gw_id => $gw) {
            $opt = get_option('lkn_wcip_partial_payments_method_' . $gw_id, 'no');
            if ($opt === 'yes') {
                $enabled[] = $gw_id;
            }
        }
        return $enabled;
    }

    /**
     * Retorna array de IDs de gateways disponíveis mas NÃO habilitados
     * para pagamento parcial — o JS usa pra desabilitar visualmente.
     */
    private function getDisabledGatewaysForPartialSplit() {
        if (!WC()->payment_gateways()) return array();

        $available = array_keys(WC()->payment_gateways()->get_available_payment_gateways());
        $enabled   = $this->getEnabledGatewayIdsForPartialSplit();

        return array_values(array_diff($available, $enabled));
    }

    /**
     * Retorna array de status de WC que são considerados "concluído" para
     * cada gateway habilitado. Usado pra detectar filho pendente.
     */
    private function getPartialCompleteStatuses() {
        $statuses = array('completed', 'processing');
        // Status configurado globalmente
        $complete_status = get_option('lkn_wcip_partial_complete_status', '');
        if ($complete_status && strpos($complete_status, 'wc-') === 0) {
            $statuses[] = substr($complete_status, 3);
        }
        // Status por gateway
        $gateways = WC()->payment_gateways()->payment_gateways();
        foreach ($gateways as $gw_id => $gw) {
            $opt = get_option('lkn_wcip_partial_payments_method_' . $gw_id, 'no');
            if ($opt !== 'yes') continue;
            $gw_status = get_option('lkn_wcip_partial_complete_status_' . $gw_id, '');
            if ($gw_status && strpos($gw_status, 'wc-') === 0) {
                $s = substr($gw_status, 3);
                if (!in_array($s, $statuses, true)) {
                    $statuses[] = $s;
                }
            }
        }
        return array_unique($statuses);
    }

    /**
     * Recalcula o total confirmado somando apenas filhos com status completo.
     */
    private function recalcConfirmedFromChildren($parent_order) {
        $complete_statuses = $this->getPartialCompleteStatuses();
        $confirmed = 0.0;
        $partials_ids = (array) $parent_order->get_meta('_wc_lkn_partials_id');
        foreach ($partials_ids as $pid) {
            $child = wc_get_order((int) $pid);
            if (!$child || $child->get_status() === 'trash') continue;
            if (in_array($child->get_status(), $complete_statuses, true)) {
                $paid = (float) $child->get_meta('_wc_lkn_partial_amount_paid');
                if ($paid <= 0) $paid = (float) $child->get_total();
                $confirmed += $paid;
            }
        }
        return $confirmed;
    }

    /**
     * Recalcula o total pendente somando filhos que não estão completos nem cancelados.
     */
    private function recalcPedingFromChildren($parent_order) {
        $complete_statuses = $this->getPartialCompleteStatuses();
        $peding = 0.0;
        $partials_ids = (array) $parent_order->get_meta('_wc_lkn_partials_id');
        foreach ($partials_ids as $pid) {
            $child = wc_get_order((int) $pid);
            if (!$child || $child->get_status() === 'trash') continue;
            if (!in_array($child->get_status(), $complete_statuses, true)
                && $child->get_status() !== 'cancelled') {
                $amount = (float) $child->get_meta('_wc_lkn_partial_amount_paid');
                if ($amount <= 0) $amount = (float) $child->get_total();
                $peding += $amount;
            }
        }
        return $peding;
    }

    /**
     * Chamado no checkout clássico: salva o remaining como meta da ordem
     * e limpa a sessão do split.
     */
    public function savePartialRemainingOnOrder($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;


        $this->maybeSaveSplitDataToOrder($order);
    }

    /**
     * Chamado no checkout Blocks (Store API): salva o remaining como meta.
     * Recarrega o pedido do banco para garantir que metadados do gateway estejam presentes.
     */
    public function savePartialRemainingOnOrderBlocks($order) {
        if (!$order instanceof \WC_Order) return;
        // Recarrega do banco para garantir que temos os metadados do gateway
        // (o objeto passado pelo Store API pode ser de antes do gateway salvar)
        $fresh_order = wc_get_order($order->get_id());
        if ($fresh_order) {
            $this->maybeSaveSplitDataToOrder($fresh_order);
        }
    }

    /**
     * Se havia split ativo, salva os dados na ordem e limpa a sessão.
     */
    private function maybeSaveSplitDataToOrder($order) {
        // Guard: prevent double-processing (save imediato pra evitar race condition)
        if ($order->get_meta('_wc_lkn_maybe_save_processed') === 'yes') {
            return;
        }
        $order->update_meta_data('_wc_lkn_maybe_save_processed', 'yes');
        $order->save();

        if (!WC()->session) {
            return;
        }

        $partial_amount = (float) WC()->session->get('lkn_partial_amount', 0);
        if ($partial_amount <= 0) {
            return;
        }

        // Fluxo "pagar restante" da thank-you page
        $parent_order_id = (int) WC()->session->get('lkn_partial_parent_order_id');
        if ($parent_order_id > 0) {
            $parent_order = wc_get_order($parent_order_id);
            if ($parent_order) {
                // Recalula confirmed do ZERO somando _wc_lkn_partial_amount_paid de todos os filhos.
                // Evita que statusChanged (do gateway Rede) tenha inflado o valor antes.
                $confirmed = 0.0;
                $existing_partials = (array) $parent_order->get_meta('_wc_lkn_partials_id');
                foreach ($existing_partials as $existing_pid) {
                    $existing = wc_get_order((int) $existing_pid);
                    if ($existing && $existing->get_status() !== 'trash') {
                        $existing_paid = (float) $existing->get_meta('_wc_lkn_partial_amount_paid');
                        if ($existing_paid <= 0) {
                            // Fallback: usa total do pedido
                            $existing_paid = (float) $existing->get_total();
                        }
                        $confirmed += $existing_paid;
                    }
                }
                // Adiciona o valor sendo pago agora
                $confirmed += $partial_amount;

                $parent_order->update_meta_data('_wc_lkn_total_confirmed', $confirmed);
                $parent_order->update_meta_data('_wc_lkn_total_peding', 0);

                // Garante que o pai tenha a flag de main order
                if ($parent_order->get_meta('_wc_lkn_is_partial_main_order') !== 'yes') {
                    $parent_order->update_meta_data('_wc_lkn_is_partial_main_order', 'yes');
                }

                // Adiciona este pedido à lista de parciais do pai
                $partialsList = $parent_order->get_meta('_wc_lkn_partials_id', true);
                if (!is_array($partialsList)) $partialsList = array();
                $partialsList = array_map('intval', $partialsList);
                if (!in_array($order->get_id(), $partialsList, true)) {
                    $partialsList[] = $order->get_id();
                }
                $parent_order->update_meta_data('_wc_lkn_partials_id', $partialsList);

                $original_total = (float) $parent_order->get_meta('_wc_lkn_original_total');

                // Recalcula $original_total se estiver zerado (HPOS ou race condition)
                if ($original_total <= 0) {
                    // Estratégia 0: leitura direta do DB (HPOS meta table)
                    global $wpdb;
                    $raw_meta = $wpdb->get_var($wpdb->prepare(
                        "SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s",
                        $parent_order_id,
                        '_wc_lkn_original_total'
                    ));
                    if ($raw_meta !== null && (float) $raw_meta > 0) {
                        $original_total = (float) $raw_meta;
                    } else {
                        // Legacy: postmeta
                        $raw_meta = get_post_meta($parent_order_id, '_wc_lkn_original_total', true);
                        if ($raw_meta && (float) $raw_meta > 0) {
                            $original_total = (float) $raw_meta;
                        }
                    }

                    // Estratégia 1: recalcula dos itens de linha
                    if ($original_total <= 0) {
                        $recalc = 0;
                        foreach ($parent_order->get_items('line_item') as $item) {
                            $recalc += (float) $item->get_subtotal();
                        }
                        $recalc += (float) $parent_order->get_shipping_total();

                        // Estratégia 2: usa get_total() do pedido (subtotal+frete sem gateway fees)
                        if ($recalc <= 0) {
                            $recalc = (float) $parent_order->get_total();
                        }

                        if ($recalc > 0) {
                            $original_total = $recalc;
                        }
                    }

                    // Estratégia 3: valor da sessão (setado no momento da criação do pai)
                    if ($original_total <= 0) {
                        $session_base = (float) WC()->session->get('lkn_partial_base_total', 0);
                        if ($session_base > 0) {
                            $original_total = $session_base;
                        }
                    }

                    if ($original_total > 0) {
                        $parent_order->update_meta_data('_wc_lkn_original_total', $original_total);
                    } else {
                        $item_count = count($parent_order->get_items('line_item'));
                    }
                }

                // Salva $original_total no filho pra thank-you page ter acesso direto
                $order->update_meta_data('_wc_lkn_original_total', $original_total);

                $all_paid = ($original_total > 0 && round($confirmed, 2) >= round($original_total, 2));

                // Só considera "all_paid" quando TODOS os filhos atingiram o status configurado como sucesso.
                // Pagamentos assíncronos (PIX, boleto) podem ter confirmed >= original_total
                // mas ainda estarem com status "pending" — não devem disparar conclusão.
                if ($all_paid) {
                    $all_paid = $this->allChildrenPaid($parent_order);
                }
                
                if ($all_paid) {
                    $complete_status = get_option('lkn_wcip_partial_complete_status', 'wc-partial-comp');
                    if (empty($complete_status)) $complete_status = 'wc-partial-comp';

                    if (!in_array($parent_order->get_status(), array(substr($complete_status, 3), 'completed'))) {
                        $parent_order->set_status($complete_status);
                        $parent_order->add_order_note('Todos os pagamentos parciais foram concluídos.');
                    }

                    // Sync Analytics: set_status não dispara observer do analytics
                    global $wpdb;
                    $wpdb->update("{$wpdb->prefix}wc_order_stats", array('status' => $complete_status), array('order_id' => $parent_order_id));
                }

                // Vincula este pedido ao pai
                $order->update_meta_data('_wc_lkn_parent_id', $parent_order_id);
                $order->update_meta_data('_wc_lkn_is_partial_order', 'yes');
                $order->update_meta_data('_wc_lkn_partial_amount_paid', $partial_amount);
                $order->update_meta_data('lkn_ini_date', gmdate('Y-m-d'));
                $order->update_meta_data('lkn_exp_date', gmdate('Y-m-d'));

                // Nota no pai
                $child_fees = round($order->get_total() - $partial_amount, 2);
                $note_parts = array(sprintf('base: %s', wc_price($partial_amount)));
                if (abs($child_fees) > 0.01) {
                    $sign = $child_fees > 0 ? '+' : '';
                    $note_parts[] = sprintf('%s %s', $sign, wc_price($child_fees));
                }
                $note_parts[] = sprintf('total cobrado: %s', wc_price($order->get_total()));
                $parent_order->add_order_note(sprintf(
                    'Pagamento parcial #%s processado — %s',
                    $order->get_id(),
                    implode(', ', $note_parts)
                ));

                // Só remove a flag de pendência quando TUDO estiver pago
                if ($all_paid) {
                    $parent_order->delete_meta_data('_wc_lkn_pay_remaining_pending');
                    // Limpa flag dos filhos também
                    foreach ($partialsList as $pid) {
                        $child = wc_get_order((int) $pid);
                        if ($child) {
                            $child->delete_meta_data('_wc_lkn_pay_remaining_pending');
                            $child->save();
                        }
                    }
                } else {
                    // Garante que a flag continue ativa para o próximo pagamento
                    $parent_order->update_meta_data('_wc_lkn_pay_remaining_pending', 'yes');
                    // Também marca o filho para que a query de pending orders o encontre
                    $order->update_meta_data('_wc_lkn_pay_remaining_pending', 'yes');
                    $parent_order->add_order_note(sprintf(
                        'Pagamento parcial concluído. Restante a pagar: %s',
                        wc_price(round($original_total - $confirmed, 2))
                    ));
                }

                // Marca que este split JÁ foi processado para evitar double-counting
                // no hook statusChanged (que adicionaria o total do filho novamente).
                $order->update_meta_data('_wc_lkn_split_handled', 'yes');

                // Normaliza tax data nos fee items (evita warning WC core: Undefined array key 0)
                foreach ($order->get_items('fee') as $fee_item) {
                    $taxes = $fee_item->get_taxes();
                    if (empty($taxes['total'])) {
                        $fee_item->set_taxes(array('total' => array()));
                    }
                    $fee_item->save();
                }

                // ⚠️ Salva o filho PRIMEIRO (com todos os metadados do gateway intactos)
                // Depois salva o pai. Isso garante que metadados de terceiros (ex: Rede)
                // não sejam sobrescritos por múltiplos saves em cascata.
                $order->save();
                $parent_order->save();

                // Adiciona à lista de invoices (com dedup extra)
                $invoiceList = get_option('lkn_wcip_invoices', array());
                if (!in_array($order->get_id(), $invoiceList, true)) {
                    $invoiceList[] = $order->get_id();
                }
                // Remove duplicatas que possam já existir
                $invoiceList = array_values(array_unique($invoiceList, SORT_NUMERIC));
                update_option('lkn_wcip_invoices', $invoiceList);

            }
            // Limpa o carrinho pra não sobrar produto pro próximo pedido
            if (WC()->cart) WC()->cart->empty_cart();
            $this->cleanSplitSession();
            return;
        }

        // Fluxo original: split no checkout
        // O "original total" é só produto + frete (base_total), sem juros do gateway.
        // O cliente paga o partial_amount e os juros são custo do gateway, não da loja.
        // remaining = base_total - partial_amount (o que falta pra loja receber)
        $base_total = (float) WC()->session->get('lkn_partial_base_total', 0);
        $remaining = $base_total - $partial_amount;
        if ($remaining < 0) $remaining = 0;

        $order->update_meta_data('_wc_lkn_partial_amount_paid', $partial_amount);
        $order->update_meta_data('_wc_lkn_partial_remaining', $remaining);
        $order->update_meta_data('_wc_lkn_original_total', $base_total);
        $order->update_meta_data('_wc_lkn_total_peding', 0);
        $order->update_meta_data('_wc_lkn_total_confirmed', $partial_amount);
        $order->update_meta_data('lkn_ini_date', gmdate('Y-m-d'));
        $order->update_meta_data('lkn_exp_date', gmdate('Y-m-d'));

        // Salva o frete escolhido para o fluxo "pagar restante"
        $this->saveChosenShippingToOrder($order);

        $order->save();
        // Status mantido como definido pelo gateway — não força 'wc-partial'

        // Flag para retomada: se o usuário fechar a tela, ao voltar verá o step
        if ($remaining > 0.001) {
            $order->update_meta_data('_wc_lkn_pay_remaining_pending', 'yes');
            $order->save();
        }

        $this->cleanSplitSession();
    }

    private function cleanSplitSession() {
        WC()->session->__unset('lkn_partial_amount');
        WC()->session->__unset('lkn_partial_remaining');
        WC()->session->__unset('lkn_partial_disabled_gateways');
        WC()->session->__unset('lkn_partial_base_total');
        WC()->session->__unset('lkn_partial_gateway_fees');
        WC()->session->__unset('lkn_partial_order_id');
        WC()->session->__unset('lkn_partial_parent_order_id');
        WC()->session->__unset('lkn_partial_shipping_methods');
        WC()->session->__unset('lkn_partial_shipping_rate_ids');
    }

    /**
     * Adiciona botões "Continuar" e "Cancelar" no My Account e thank-you page.
     * Resolve filhos ao pai. Status do pai: partial / partial-pend.
     * "Continuar" só com remaining > 0. "Cancelar" só no My Account.
     */
    public function addPartialActions($actions, $order) {
        // Resolve ao pai se for filho
        $parent_id = (int) $order->get_meta('_wc_lkn_parent_id');
        $is_child  = $parent_id > 0;
        $target = $is_child ? wc_get_order($parent_id) : $order;
        if (!$target) return $actions;

        if ($target->get_meta('_wc_lkn_is_partial_main_order') !== 'yes') return $actions;

        $allowed_statuses = array('wc-partial', 'wc-partial-pend');
        if (!in_array('wc-' . $target->get_status(), $allowed_statuses, true)) return $actions;

        // Se o pedido atual é filho e JÁ foi pago (status de sucesso do gateway), esconde ações.
        // Ex: filho 2 pago → seu próprio pagamento já foi concluído, nada a continuar.
        $complete_statuses = $this->getPartialCompleteStatuses();
        if ($is_child && in_array('wc-' . $order->get_status(), $complete_statuses, true)) {
            return $actions;
        }

        // Na thank-you page (não My Account), só mostra "Continuar" no primeiro filho.
        // Filhos posteriores não devem ter ação de continuar na página de agradecimento.
        $is_my_account = is_wc_endpoint_url('orders');
        if (!$is_my_account && $is_child) {
            $partials_ids = $target->get_meta('_wc_lkn_partials_id', true);
            if (is_array($partials_ids) && !empty($partials_ids)) {
                $first_child_id = (int) reset($partials_ids);
                if ($order->get_id() !== $first_child_id) return $actions;
            }
        }

        $order_id = $target->get_id();
        $original_total = (float) $target->get_meta('_wc_lkn_original_total');
        $confirmed = (float) $target->get_meta('_wc_lkn_total_confirmed');
        $remaining = round($original_total - $confirmed, 2);

        // Botão "Continuar pagamento" — só se ainda tem saldo OU tem filho pendente
        $should_show_continue = $remaining > 0;
        $pending_child_id = null;
        if (!$should_show_continue) {
            $partials_ids = $target->get_meta('_wc_lkn_partials_id', true);
            if (is_array($partials_ids)) {
                foreach ($partials_ids as $cid) {
                    $child = wc_get_order((int) $cid);
                    if (!$child || $child->get_status() === 'trash') continue;
                    if (!in_array('wc-' . $child->get_status(), $complete_statuses, true)) {
                        $should_show_continue = true;
                        $pending_child_id = (int) $cid;
                        break;
                    }
                }
            }
        }

        if ($should_show_continue) {
            $nonce = wp_create_nonce('lkn_resume_partial_' . $order_id);
            if ($pending_child_id) {
                $pending_child = wc_get_order($pending_child_id);
                $url = add_query_arg(array(
                    'pay_for_order' => 'true',
                    'key' => $pending_child->get_order_key(),
                ), wc_get_checkout_url() . 'order-pay/' . $pending_child_id . '/');
            } else {
                $url = add_query_arg(array(
                    'lkn_resume_partial' => $order_id,
                    'lkn_amount'         => $remaining,
                    '_wpnonce'           => $nonce,
                ), wc_get_page_permalink('checkout'));
            }
            $actions['lkn_resume_partial'] = array(
                'url'  => $url,
                'name' => __('Continuar pagamento parcial', 'wc-invoice-payment'),
            );
        }

        // "Cancelar" só no My Account, não na thank-you
        if (is_wc_endpoint_url('orders')) {
            $actions['lkn_cancel_partial'] = array(
                'url'  => add_query_arg(array(
                    'lkn_cancel_pending' => $order_id,
                    '_wpnonce'           => wp_create_nonce('lkn_cancel_pending_' . $order_id),
                ), wc_get_account_endpoint_url('orders')),
                'name' => __('Cancelar pagamento parcial', 'wc-invoice-payment'),
            );
        }

        unset($actions['pay']);
        return $actions;
    }

    /**
     * Restaura carrinho após redirect do gateway mockado (checkout → pay_remaining).
     * O Blocks checkout esvazia o carrinho no redirect; recarrega itens + frete
     * do pedido pai antes do checkout do pay_remaining renderizar.
     *
     * Hook: template_redirect (priority 1)
     */
    public function restoreCartForPartialInit() {
        if (!WC()->session) return;

        $parent_id = (int) WC()->session->get('lkn_partial_needs_cart_restore');
        if ($parent_id <= 0) return;

        WC()->session->__unset('lkn_partial_needs_cart_restore');

        $parent_order = wc_get_order($parent_id);
        if (!$parent_order) return;

        if (!WC()->cart) wc_load_cart();
        WC()->cart->empty_cart();

        foreach ($parent_order->get_items('line_item') as $item) {
            $pid = $item->get_product_id();
            $vid = $item->get_variation_id();
            if ($pid) WC()->cart->add_to_cart($pid, $item->get_quantity(), $vid);
        }

        // Restaura frete
        $rates_json = $parent_order->get_meta('_wc_lkn_chosen_shipping_rates');
        if ($rates_json) {
            $rate_ids = json_decode($rates_json, true);
            if (is_array($rate_ids) && !empty($rate_ids)) {
                WC()->session->set('lkn_partial_shipping_rate_ids', $rate_ids);
                WC()->session->set('chosen_shipping_methods', $rate_ids);
            }
        }
        $shipping_json = $parent_order->get_meta('_wc_lkn_chosen_shipping');
        if ($shipping_json) {
            WC()->session->set('lkn_partial_shipping_methods', json_decode($shipping_json, true));
        }

        WC()->session->__unset('chosen_payment_method');
        WC()->session->__unset('lkn_partial_amount');
        WC()->session->__unset('lkn_partial_remaining');
        WC()->session->__unset('lkn_partial_gateway_fees');

        WC()->cart->calculate_totals();
    }

    /**
     * Intercepta lkn_resume_partial na URL e monta carrinho + sessão
     * redirecionando pro checkout com ?pay_remaining=X.
     */
    public function handleResumePartialFromMyAccount() {
        // Cancelamento
        $cancel_id = isset($_GET['lkn_cancel_pending']) ? intval($_GET['lkn_cancel_pending']) : 0;
        if ($cancel_id) {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (wp_verify_nonce($nonce, 'lkn_cancel_pending_' . $cancel_id)) {
                $order = wc_get_order($cancel_id);
                if ($order && $order->get_meta('_wc_lkn_pay_remaining_pending') === 'yes') {
                    $order->delete_meta_data('_wc_lkn_pay_remaining_pending');
                    $order->set_status('wc-partial-cancelled');
                    $order->add_order_note('Pagamento parcial cancelado.');
                    $order->save();

                    global $wpdb;
                    $wpdb->update("{$wpdb->prefix}wc_order_stats", array('status' => 'wc-cancelled'), array('order_id' => $cancel_id));
                }
            }
            wp_safe_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        // Continuar
        $order_id = isset($_GET['lkn_resume_partial']) ? intval($_GET['lkn_resume_partial']) : 0;
        if (!$order_id) return;

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lkn_resume_partial_' . $order_id)) return;

        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_wc_lkn_pay_remaining_pending') !== 'yes') return;

        $partial_amount = isset($_GET['lkn_amount']) ? floatval($_GET['lkn_amount']) : 0;
        if ($partial_amount <= 0) return;

        if (!WC()->cart) wc_load_cart();
        WC()->cart->empty_cart();

        foreach ($order->get_items() as $item) {
            $product_id   = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity     = $item->get_quantity();
            if ($product_id) {
                WC()->cart->add_to_cart($product_id, $quantity, $variation_id);
            }
        }

        WC()->session->set('lkn_partial_amount', $partial_amount);
        WC()->session->set('lkn_partial_parent_order_id', $order_id);

        // Restaura frete
        $rates_json = $order->get_meta('_wc_lkn_chosen_shipping_rates');
        if ($rates_json) {
            $rate_ids = json_decode($rates_json, true);
            if (is_array($rate_ids) && !empty($rate_ids)) {
                WC()->session->set('lkn_partial_shipping_rate_ids', $rate_ids);
                WC()->session->set('chosen_shipping_methods', $rate_ids);
            }
        }
        $shipping_json = $order->get_meta('_wc_lkn_chosen_shipping');
        if ($shipping_json) {
            WC()->session->set('lkn_partial_shipping_methods', json_decode($shipping_json, true));
        }

        WC()->cart->calculate_totals();

        wp_safe_redirect(add_query_arg('pay_remaining', $order_id, wc_get_checkout_url()));
        exit;
    }

    /**
     * Adiciona coluna "P. parcial" na listagem de pedidos (HPOS).
     */
    public function addPartialColumn($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'order_status') {
                $new['lkn_wcip_partial'] = __('P. parcial', 'wc-invoice-payment');
            }
        }
        return $new;
    }

    /**
     * Renderiza a coluna "P. parcial" com vínculo pai/filho.
     */
    public function renderPartialColumn($order) {
        if (!$order instanceof \WC_Order) return;

        // É pai? (tem _wc_lkn_original_total e NÃO tem _wc_lkn_parent_id)
        $original_total = (float) $order->get_meta('_wc_lkn_original_total');
        $is_parent = !$order->get_meta('_wc_lkn_parent_id');

        if ($original_total > 0 && $is_parent) {
            $partials_ids = $order->get_meta('_wc_lkn_partials_id', true);
            if (is_array($partials_ids) && !empty($partials_ids)) {
                $partials_ids = array_map('intval', $partials_ids);
                $total = count($partials_ids);
                foreach ($partials_ids as $idx => $pid) {
                    $child = wc_get_order($pid);
                    if ($child && $child->get_status() !== 'trash') {
                        $link = admin_url("admin.php?page=edit-invoice&invoice={$child->get_id()}");
                        echo '<span style="font-size:11px">'
                            . esc_html(($idx+1) . '° Parcial') . ' — <a href="' . esc_url($link) . '" title="' . esc_attr(wc_get_order_status_name($child->get_status())) . '">#' . esc_html($child->get_order_number()) . '</a>'
                            . '</span><br>';
                    }
                }
            } else {
                // Ainda sem filho — só mostra que é o primeiro
                echo '<span style="font-size:11px">1° Parcial</span>';
            }
            return;
        }

        // É filho?
        $parent_id = (int) $order->get_meta('_wc_lkn_parent_id');
        if ($parent_id) {
            $parent = wc_get_order($parent_id);
            if ($parent) {
                $partials = $parent->get_meta('_wc_lkn_partials_id', true);
                $total = is_array($partials) ? count($partials) : 2;
                $my_pos = is_array($partials) ? array_search($order->get_id(), array_map('intval', $partials)) : false;
                $pos = ($my_pos !== false) ? (int) $my_pos + 1 : $total;
                $link = admin_url("admin.php?page=edit-invoice&invoice={$parent_id}");
                echo '<span style="font-size:11px">'
                    . esc_html("{$pos}° Parcial") . ' — <a href="' . esc_url($link) . '" title="' . esc_attr(wc_get_order_status_name($parent->get_status())) . '">#' . esc_html($parent->get_order_number()) . '</a>'
                    . '</span>';
            }
            return;
        }

        echo '<span style="color:#999">—</span>';
    }

    /**
     * Renderiza coluna "P. parcial" no modo clássico (CPT).
     */
    public function renderPartialColumnClassic($column, $order_id) {
        if ($column !== 'lkn_wcip_partial') return;
        $order = wc_get_order($order_id);
        $this->renderPartialColumn($order);
    }

    /**
     * Salva os dados do frete escolhido como meta no pedido,
     * para que o fluxo "pagar restante" possa travar o frete.
     *
     * Salva duas coisas:
     * 1. _wc_lkn_chosen_shipping_rates: os rate_ids exatos (ex: "melhor_envio:5")
     * 2. _wc_lkn_chosen_shipping: dados legíveis (method_title, total)
     */
    private function saveChosenShippingToOrder($order) {
        if (!WC()->session) return;

        $chosen_rates = WC()->session->get('chosen_shipping_methods');
        if (!empty($chosen_rates) && is_array($chosen_rates)) {
            $order->update_meta_data('_wc_lkn_chosen_shipping_rates', wp_json_encode($chosen_rates));
        }

        $shipping_methods = $order->get_shipping_methods();
        if (!empty($shipping_methods)) {
            $shipping_data = array();
            foreach ($shipping_methods as $item) {
                $shipping_data[] = array(
                    'method_id'    => $item->get_method_id(),
                    'instance_id'  => $item->get_instance_id(),
                    'method_title' => $item->get_method_title(),
                    'total'        => $item->get_total(),
                );
            }
            $order->update_meta_data('_wc_lkn_chosen_shipping', wp_json_encode($shipping_data));
        }
    }

    /**
     * Filtra os métodos de entrega no checkout quando está no fluxo
     * "pagar restante", exibindo apenas o frete escolhido no primeiro pagamento.
     *
     * Hook: woocommerce_package_rates (priority 9999)
     */
    public function filterShippingForPartialRemaining($rates) {
        if (!WC()->session) return $rates;

        $partial_order_id = WC()->session->get('lkn_partial_order_id');
        $partial_parent_id = WC()->session->get('lkn_partial_parent_order_id');
        $allowed_ids = WC()->session->get('lkn_partial_shipping_rate_ids');

        // Fallback: se não tem sessão mas tem ?pay_remaining na URL, carrega do pedido pai
        if ((empty($allowed_ids) || !is_array($allowed_ids)) && !$partial_parent_id) {
            $pay_remaining = isset($_GET['pay_remaining']) ? intval($_GET['pay_remaining']) : 0;
            if ($pay_remaining > 0) {
                $parent = wc_get_order($pay_remaining);
                if ($parent) {
                    $rates_json = $parent->get_meta('_wc_lkn_chosen_shipping_rates');
                    if ($rates_json) {
                        $rate_ids = json_decode($rates_json, true);
                        if (is_array($rate_ids) && !empty($rate_ids)) {
                            $allowed_ids = $rate_ids;
                            $partial_parent_id = $pay_remaining;
                            WC()->session->set('lkn_partial_shipping_rate_ids', $rate_ids);
                            WC()->session->set('lkn_partial_parent_order_id', $pay_remaining);
                            WC()->session->set('chosen_shipping_methods', $rate_ids);
                        }
                    }
                }
            }
        }

        if (empty($allowed_ids) || !is_array($allowed_ids)) return $rates;

        if (!$partial_order_id && !$partial_parent_id) {
            $order_pay_id = absint(get_query_var('order-pay'));
            if (!$order_pay_id) return $rates;
            $order = wc_get_order($order_pay_id);
            if (!$order || $order->get_meta('_wc_lkn_is_partial_order') !== 'yes') return $rates;
        }

        $allowed_ids = array_map('strval', $allowed_ids);

        $filtered = array();
        foreach ($rates as $rate_id => $rate) {
            if (in_array((string) $rate_id, $allowed_ids, true)) {
                $filtered[$rate_id] = $rate;
            }
        }

        if (empty($filtered) && !empty($rates)) return $rates;
        return $filtered;
    }

    /**
     * Exibe um aviso no checkout informando que o frete está travado
     * (fluxo "pagar restante").
     *
     * Hook: woocommerce_before_checkout_form
     */
    public function addLockedShippingNotice($checkout = null) {
        if (!WC()->session) return;

        $partial_order_id = WC()->session->get('lkn_partial_order_id') ?: WC()->session->get('lkn_partial_parent_order_id');

        // Fallback: carrega do ?pay_remaining na URL se sessão não tem
        if (!$partial_order_id) {
            $pay_remaining = isset($_GET['pay_remaining']) ? intval($_GET['pay_remaining']) : 0;
            if ($pay_remaining > 0) {
                $partial_order_id = $pay_remaining;
            }
        }
        if (!$partial_order_id) return;

        $saved_shipping = WC()->session->get('lkn_partial_shipping_methods');

        // Fallback: carrega shipping methods do pedido pai se sessão não tem
        if (empty($saved_shipping) || !is_array($saved_shipping)) {
            $parent = wc_get_order($partial_order_id);
            if ($parent) {
                $shipping_json = $parent->get_meta('_wc_lkn_chosen_shipping');
                if ($shipping_json) {
                    $saved_shipping = json_decode($shipping_json, true);
                    if (is_array($saved_shipping)) {
                        WC()->session->set('lkn_partial_shipping_methods', $saved_shipping);
                    }
                }
            }
        }
        if (empty($saved_shipping) || !is_array($saved_shipping)) return;

        foreach ($saved_shipping as $shipping) {
            $title = isset($shipping['method_title']) ? $shipping['method_title'] : '';
            if ($title) {
                $total = isset($shipping['total']) ? (float) $shipping['total'] : 0;
                /* translators: 1: shipping method name, 2: shipping cost */
                $msg = sprintf(
                    __('Frete escolhido no pagamento anterior: %1$s (%2$s)', 'wc-invoice-payment'),
                    $title,
                    wc_price($total)
                );
                wc_add_notice($msg, 'notice');
            }
        }
    }

    /**
     * Wrapper: woocommerce_order_details_before_order_table recebe WC_Order.
     * Roda com priority 999 pra ficar após templates de PIX/outros gateways.
     */
    public function displayPartialRemainingBeforeOrderTable($order) {
        if ($order instanceof \WC_Order) {
            $this->displayPartialRemainingOnThankyou($order->get_id());
        }
    }

    public function displayPartialRemainingOnThankyou($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Apenas para pedidos pai ou filho com pagamento parcial pendente
        $parent_id = (int) $order->get_meta('_wc_lkn_parent_id');
        if ($parent_id) {
            // É filho: mostrar card com JS polling que espera os dados do pai atualizarem
            ?>
            <div id="lkn-wcip-thankyou-card-placeholder" style="text-align:center;padding:24px;margin:24px 0">
                <p style="color:#666"><?php esc_html_e('Carregando informações do pagamento...', 'wc-invoice-payment'); ?></p>
            </div>
            <script type="text/javascript">
            (function() {
                var parentId = <?php echo $parent_id; ?>;
                var childId = <?php echo $order_id; ?>;
                var attempts = 0;
                var maxAttempts = 15;

                function checkParentData() {
                    attempts++;
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=lkn_wcip_get_parent_partial_state&parent_id=' + parentId + '&child_id=' + childId
                    }).then(function(r) { return r.json(); }).then(function(res) {
                        if (res && res.success && res.data) {
                            renderCard(res.data);
                        } else if (attempts < maxAttempts) {
                            setTimeout(checkParentData, 1000);
                        }
                    }).catch(function() {
                        if (attempts < maxAttempts) setTimeout(checkParentData, 1000);
                    });
                }

                function renderCard(d) {
                    var original = parseFloat(d.original_total) || 0;
                    var confirmed = parseFloat(d.confirmed) || 0;
                    var stillRemaining = parseFloat(d.still_remaining) || 0;
                    var childTotal = parseFloat(d.child_total) || 0;
                    var childAmount = parseFloat(d.child_amount) || 0;
                    var fees = childTotal - childAmount;

                    var html = '';

                    if (stillRemaining <= 0) {
                        // Tudo pago — card verde com breakdown
                        html = '<div style="background:#f0f7f0;border:2px solid #008a20;border-radius:8px;padding:24px;text-align:center">';
                        html += '<h3 style="margin:0 0 12px;color:#008a20"><?php echo esc_js(__('Pagamento Processado', 'wc-invoice-payment')); ?></h3>';
                        html += '<div style="text-align:left;max-width:340px;margin:0 auto 20px;font-size:14px;line-height:1.8;color:#555">';
                        html += '<div style="display:flex;justify-content:space-between;padding:2px 0"><span><?php echo esc_js(__('Subtotal + Frete:', 'wc-invoice-payment')); ?></span><strong>' + formatBrl(original) + '</strong></div>';
                        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:4px 0">';
                        if (d.children && d.children.length) {
                            for (var ci = 0; ci < d.children.length; ci++) {
                                var c = d.children[ci];
                                html += '<div style="display:flex;justify-content:space-between;padding:2px 0"><span>' + (ci+1) + 'ª parcela (base):</span><strong>' + formatBrl(c.base_amount) + '</strong></div>';
                                if (Math.abs(c.fees) > 0.01) {
                                    var label = '+ Taxas/Descontos:';
                                    html += '<div style="display:flex;justify-content:space-between;padding:2px 0;font-size:12px;color:#007cba"><span>' + label + '</span><span>' + (c.fees > 0 ? '+' : '') + formatBrl(c.fees) + '</span></div>';
                                    html += '<div style="display:flex;justify-content:space-between;padding:2px 0;font-size:13px;font-weight:500"><span>Total cobrado:</span><span>' + formatBrl(c.total) + '</span></div>';
                                }
                            }
                        }
                        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:4px 0">';
                        var totalWithFees = 0;
                        if (d.children && d.children.length) {
                            for (var ci2 = 0; ci2 < d.children.length; ci2++) {
                                totalWithFees += parseFloat(d.children[ci2].total) || 0;
                            }
                        }
                        if (totalWithFees <= 0) totalWithFees = confirmed;
                        html += '<div style="display:flex;justify-content:space-between;padding:2px 0"><span><?php echo esc_js(__('Total pago:', 'wc-invoice-payment')); ?></span><strong style="color:#008a20">' + formatBrl(totalWithFees) + '</strong></div>';
                        html += '</div>';
                        html += '<p style="font-size:13px;color:#008a20;margin:0"><?php echo esc_js(__('Resumo dos valores pagos nas parcelas do pagamento parcial.', 'wc-invoice-payment')); ?></p>';
                        html += '</div>';
                    } else {
                        // Ainda falta — card azul com botão
                        html = '<div style="background:#f8f9fa;border:2px solid #007cba;border-radius:8px;padding:24px;text-align:center">';
                        html += '<h3 style="margin:0 0 12px;color:#007cba"><?php echo esc_js(__('Pagamento Parcial Realizado', 'wc-invoice-payment')); ?></h3>';
                        html += '<div style="text-align:left;max-width:340px;margin:0 auto 20px;font-size:14px;line-height:1.8;color:#555">';
                        html += '<div style="display:flex;justify-content:space-between;padding:2px 0"><span><?php echo esc_js(__('Subtotal + Frete:', 'wc-invoice-payment')); ?></span><strong>' + formatBrl(original) + '</strong></div>';
                        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:4px 0">';
                        html += '<div style="display:flex;justify-content:space-between;padding:2px 0"><span><?php echo esc_js(__('Pago agora:', 'wc-invoice-payment')); ?></span><strong>' + formatBrl(childAmount) + '</strong></div>';
                        if (Math.abs(fees) > 0.01) {
                            var feeLabel = '+ Taxas/Descontos:';
                            html += '<div style="display:flex;justify-content:space-between;padding:2px 0;font-size:12px;color:#007cba"><span>' + feeLabel + '</span><span>' + (fees > 0 ? '+' : '') + formatBrl(fees) + '</span></div>';
                            html += '<div style="display:flex;justify-content:space-between;padding:2px 0;font-size:13px;font-weight:600"><span>Total cobrado:</span><span>' + formatBrl(childTotal) + '</span></div>';
                        }
                        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:4px 0">';
                        html += '<div style="display:flex;justify-content:space-between;padding:2px 0"><span><?php echo esc_js(__('Restante a pagar:', 'wc-invoice-payment')); ?></span><strong style="color:#d63638">' + formatBrl(stillRemaining) + '</strong></div>';
                        html += '</div>';
                        html += '<button id="lknWcipPayRemainingBtn" type="button" style="padding:12px 28px;font-size:15px;font-weight:600;background:#007cba;color:#fff;border:none;border-radius:4px;cursor:pointer" data-order-id="' + parentId + '" data-amount="' + stillRemaining + '">' + '<?php echo esc_js(__('Pagar restante', 'wc-invoice-payment')); ?>' + '</button>';
                        html += '</div>';
                    }

                    document.getElementById('lkn-wcip-thankyou-card-placeholder').innerHTML = html;

                    // Bind do botão "Pagar restante"
                    var btn = document.getElementById('lknWcipPayRemainingBtn');
                    if (btn) {
                        btn.addEventListener('click', function() {
                            this.disabled = true;
                            this.textContent = '<?php echo esc_js(__('Processando...', 'wc-invoice-payment')); ?>';
                            fetch('<?php echo esc_url(rest_url('invoice_payments/create_partial_payment')); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                                },
                                body: JSON.stringify({
                                    orderId: parseInt(this.dataset.orderId),
                                    partialAmount: parseFloat(this.dataset.amount),
                                    userId: <?php echo intval(get_current_user_id()); ?>
                                })
                            }).then(function(r) { return r.json(); }).then(function(res) {
                                if (res && res.payment_url) window.location.href = res.payment_url;
                            }).catch(function() {
                                alert('<?php echo esc_js(__('Erro ao processar. Tente novamente.', 'wc-invoice-payment')); ?>');
                                btn.disabled = false;
                                btn.textContent = '<?php echo esc_js(__('Pagar restante', 'wc-invoice-payment')); ?>';
                            });
                        });
                    }
                }

                function formatBrl(val) {
                    return 'R$ ' + Number(val).toFixed(2).replace('.', ',');
                }

                // Começa o polling após 500ms
                setTimeout(checkParentData, 500);
            })();
            </script>
            <?php
            return;
        }

        // É pai OU filho sem parent_id (edge case): mostra card se ainda falta pagar
        $original_total = (float) $order->get_meta('_wc_lkn_original_total');
        $confirmed = (float) $order->get_meta('_wc_lkn_total_confirmed');

        // Fallback: se é um filho (_wc_lkn_is_partial_order) mas perdeu o parent_id,
        // tenta localizar o pai pelos metadados _wc_lkn_partials_id
        if ($original_total <= 0 && $order->get_meta('_wc_lkn_is_partial_order') === 'yes') {
            $partial_amount_paid = (float) $order->get_meta('_wc_lkn_partial_amount_paid');
            $child_total = (float) $order->get_total();
            $fees = round($child_total - $partial_amount_paid, 2);

            // Busca o pai via query reversa nos partials
            $parent_orders = wc_get_orders(array(
                'limit' => 1,
                'meta_query' => array(
                    array(
                        'key'     => '_wc_lkn_partials_id',
                        'value'   => sprintf('i:%d;', $order_id),
                        'compare' => 'LIKE',
                    ),
                ),
                'type' => 'shop_order',
            ));

            if (!empty($parent_orders)) {
                $parent_order = $parent_orders[0];
                $parent_id = $parent_order->get_id();
                $original_total = (float) $parent_order->get_meta('_wc_lkn_original_total');
                $confirmed = (float) $parent_order->get_meta('_wc_lkn_total_confirmed');
                $remaining = round($original_total - $confirmed, 2);

                if ($original_total <= 0) {
                    // Recalcula do pai
                    $subtotal = 0;
                    foreach ($parent_order->get_items('line_item') as $item) {
                        $subtotal += (float) $item->get_subtotal();
                    }
                    $original_total = $subtotal + (float) $parent_order->get_shipping_total();
                    $remaining = round($original_total - $confirmed, 2);
                }

                // Vincula de volta o parent_id que estava faltando (auto-repair)
                $order->update_meta_data('_wc_lkn_parent_id', $parent_id);
                $order->save();
            } else {
                // Não achou pai — usa os dados disponíveis no filho
                $remaining = round($partial_amount_paid > 0 ? $partial_amount_paid : 0, 2);
                $original_total = $partial_amount_paid;
                $confirmed = $partial_amount_paid;
            }
        } else {
            $remaining = round($original_total - $confirmed, 2);
        }

        if ($remaining <= 0) return;

        $partial_amount = (float) $order->get_meta('_wc_lkn_partial_amount_paid');
        $paid = (float) $order->get_total();
        $fees = round($paid - $partial_amount, 2);

        // Botão aponta pro pai quando filho (auto-repair), senão pro próprio pedido
        $pay_target_id = ($parent_id > 0) ? $parent_id : $order_id;

        $pay_rest_url = rest_url('invoice_payments/create_partial_payment');
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <div class="lkn-wcip-partial-thankyou-card" style="background:#f8f9fa;border:2px solid #007cba;border-radius:8px;padding:24px;margin:24px 0;text-align:center">
            <h3 style="margin:0 0 12px;color:#007cba"><?php esc_html_e('Pagamento Parcial Realizado', 'wc-invoice-payment'); ?></h3>
            <div style="text-align:left;max-width:340px;margin:0 auto 20px;font-size:14px;line-height:1.8;color:#555">
                <div style="display:flex;justify-content:space-between;padding:2px 0"><span><?php esc_html_e('Subtotal + Frete:', 'wc-invoice-payment'); ?></span><strong><?php echo wc_price($original_total); ?></strong></div>
                <?php if ($partial_amount > 0): ?>
                <hr style="border:none;border-top:1px dashed #ccc;margin:4px 0">
                <div style="display:flex;justify-content:space-between;padding:2px 0"><span><?php esc_html_e('Pago agora:', 'wc-invoice-payment'); ?></span><strong><?php echo wc_price($partial_amount); ?></strong></div>
                <?php if (abs($fees) > 0.01): ?>
                <div style="display:flex;justify-content:space-between;padding:2px 0;color:#007cba"><span><?php esc_html_e('+ Taxas/Descontos:', 'wc-invoice-payment'); ?></span><strong><?php echo wc_price($fees); ?></strong></div>
                <hr style="border:none;border-top:1px dashed #ccc;margin:4px 0">
                <div style="display:flex;justify-content:space-between;padding:2px 0;font-weight:600;color:#333"><span><?php esc_html_e('Total cobrado:', 'wc-invoice-payment'); ?></span><span><?php echo wc_price($paid); ?></span></div>
                <?php endif; ?>
                <?php endif; ?>
                <hr style="border:none;border-top:1px dashed #ccc;margin:4px 0">
                <div style="display:flex;justify-content:space-between;padding:2px 0;font-size:15px;font-weight:600;color:#d63638"><span><?php esc_html_e('Saldo restante:', 'wc-invoice-payment'); ?></span><span><?php echo wc_price($remaining); ?></span></div>
            </div>
            <button id="lknWcipPayRemainingBtn" type="button" style="padding:12px 28px;font-size:15px;font-weight:600;background:#007cba;color:#fff;border:none;border-radius:4px;cursor:pointer" data-order-id="<?php echo esc_attr($pay_target_id); ?>" data-amount="<?php echo esc_attr($remaining); ?>"><?php esc_html_e('Pagar restante', 'wc-invoice-payment'); ?></button>
        </div>
        <?php
    }

    /**
     * AJAX: retorna estado parcial do pai para polling na thank-you page.
     */
    public function ajaxGetParentPartialState() {
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $child_id  = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;

        if (!$parent_id) {
            // Tenta deduzir o parent_id do child
            if ($child_id) {
                $child = wc_get_order($child_id);
                if ($child) {
                    $parent_id = (int) $child->get_meta('_wc_lkn_parent_id');
                }
            }
            if (!$parent_id) {
                wp_send_json_error(array('message' => 'Parent ID required'));
            }
        }

        $parent_order = wc_get_order($parent_id);
        if (!$parent_order) {
            wp_send_json_error(array('message' => 'Parent order not found'));
        }

        $original_total = (float) $parent_order->get_meta('_wc_lkn_original_total');
        $confirmed = (float) $parent_order->get_meta('_wc_lkn_total_confirmed');

        // Safety net: recalcula se original_total ainda é 0
        if ($original_total <= 0) {
            // Estratégia 1: partial_amount_paid + partial_remaining (fluxo split)
            $partial_paid = (float) $parent_order->get_meta('_wc_lkn_partial_amount_paid');
            $partial_rem  = (float) $parent_order->get_meta('_wc_lkn_partial_remaining');
            if ($partial_paid > 0 || $partial_rem > 0) {
                $original_total = max($partial_paid, 0) + max($partial_rem, 0);
            }

            // Estratégia 2: recalcula a partir dos itens de linha
            if ($original_total <= 0) {
                $subtotal = 0;
                foreach ($parent_order->get_items('line_item') as $item) {
                    $subtotal += (float) $item->get_subtotal();
                }
                $shipping = (float) $parent_order->get_shipping_total();
                $original_total = $subtotal + $shipping;
            }

            // Estratégia 3: último recurso — total do pedido pai
            if ($original_total <= 0) {
                $original_total = (float) $parent_order->get_total();
            }
        }

        $still_remaining = round($original_total - $confirmed, 2);

        $child_order = $child_id ? wc_get_order($child_id) : null;
        $child_total = $child_order ? (float) $child_order->get_total() : 0;
        $child_amount = $child_order ? (float) $child_order->get_meta('_wc_lkn_partial_amount_paid') : $confirmed;
        if ($child_amount <= 0) {
            $child_amount = $confirmed;
        }

        // Build children breakdown for the "all paid" card (sorted by id)
        $children = array();
        $partials_ids = (array) $parent_order->get_meta('_wc_lkn_partials_id');
        sort($partials_ids, SORT_NUMERIC);
        foreach ($partials_ids as $pid) {
            $ch = wc_get_order((int) $pid);
            if (!$ch || $ch->get_status() === 'trash') continue;
            $ch_base = (float) $ch->get_meta('_wc_lkn_partial_amount_paid');
            $ch_total = (float) $ch->get_total();
            if ($ch_base <= 0) $ch_base = $ch_total;
            $children[] = array(
                'id'          => $ch->get_id(),
                'base_amount' => $ch_base,
                'total'       => $ch_total,
                'fees'        => round($ch_total - $ch_base, 2),
            );
        }

        wp_send_json_success(array(
            'original_total'  => $original_total,
            'confirmed'       => $confirmed,
            'still_remaining' => $still_remaining,
            'child_total'     => $child_total,
            'child_amount'    => $child_amount > 0 ? $child_amount : $confirmed,
            'children'        => $children,
        ));
    }

    /**
     * Exclui pedidos filhos parciais da listagem de pedidos do My Account.
     */
    public function excludePartialChildrenFromMyAccount($args) {
        if (!isset($args['meta_query'])) $args['meta_query'] = array();
        $args['meta_query'][] = array(
            'key'     => '_wc_lkn_parent_id',
            'compare' => 'NOT EXISTS',
        );
        return $args;
    }

    /**
     * Exclui pedidos filhos parciais da listagem de pedidos do WooCommerce (HPOS).
     */
    public function excludePartialChildrenFromOrders($args) {
        if (!is_admin()) return $args;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'woocommerce_page_wc-orders') return $args;

        if (!isset($args['meta_query'])) $args['meta_query'] = array();
        $args['meta_query'][] = array(
            'key'     => '_wc_lkn_parent_id',
            'compare' => 'NOT EXISTS',
        );
        return $args;
    }

    /**
     * Exclui pedidos filhos parciais do Analytics (filtro de resultados).
     */
    public function excludePartialChildrenFromAnalytics($data, $query) {
        if (is_object($data) && isset($data->data) && is_array($data->data)) {
            global $wpdb;
            $child_ids = $wpdb->get_col(
                "SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_wc_lkn_parent_id' AND meta_value REGEXP '^[0-9]+$'"
            );
            if (!empty($child_ids)) {
                $child_ids = array_map('intval', $child_ids);
                $data->data = array_values(array_filter($data->data, function ($item) use ($child_ids) {
                    $oid = is_array($item) ? (int) ($item['order_id'] ?? 0) : (int) ($item->order_id ?? 0);
                    return $oid <= 0 || !in_array($oid, $child_ids, true);
                }));
            }
        }
        return $data;
    }

    /**
     * Exclui pedidos filhos parciais da REST API de pedidos.
     */
    public function excludePartialChildrenFromRestQuery($args, $request) {
        if (!isset($args['meta_query'])) $args['meta_query'] = array();
        $args['meta_query'][] = array(
            'key'     => '_wc_lkn_parent_id',
            'compare' => 'NOT EXISTS',
        );
        return $args;
    }

    /**
     * Insere/atualiza manualmente a tabela wc_order_stats pro pedido pai.
     * wc_create_order manual não dispara os hooks de analytics.
     */
    /**
     * Verifica se TODOS os filhos do pedido pai atingiram o status de sucesso
     * configurado (lkn_wcip_partial_complete_status_{gateway}) ou status final (completed/partial-comp).
     */
    private function allChildrenPaid($parent_order) {
        $partials_ids = (array) $parent_order->get_meta('_wc_lkn_partials_id');
        if (empty($partials_ids)) return false;

        foreach ($partials_ids as $pid) {
            $child = wc_get_order((int) $pid);
            if (!$child || $child->get_status() === 'trash') continue;

            $child_status = $child->get_status();
            $gateway = $child->get_payment_method();

            // Status finais são sempre considerados pagos
            if (in_array($child_status, array('completed', 'partial-comp', 'partial-pend'), true)) {
                continue;
            }

            // Status pendentes: verifica se o gateway já confirmou
            $per_gateway = get_option('lkn_wcip_partial_complete_status_' . $gateway, null);
            if ($per_gateway !== null) {
                $expected = str_replace('wc-', '', $per_gateway);
                if ($child_status === $expected) continue; // bateu com o configurado
            }

            // Se não bateu com nenhuma condição, não está pago
            return false;
        }
        return true;
    }
}