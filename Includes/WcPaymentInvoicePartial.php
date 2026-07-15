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

            // Detecta se é Checkout Blocks ou clássico
            $checkout_page_id = wc_get_page_id('checkout');
            $is_blocks = $checkout_page_id && has_block('woocommerce/checkout', $checkout_page_id);

            if (! $is_blocks) {
                // Checkout clássico: script antigo (cria ordem parcial + redirect)
                wp_enqueue_script( 'wcInvoicePaymentPartialScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
                wp_localize_script('wcInvoicePaymentPartialScript', 'lknWcipPartialVariables', array(
                    'minPartialAmount' => get_option('lkn_wcip_partial_interval_minimum', 0),
                    'cartTotal' => WC()->cart->total,
                    'cartTotalAjaxUrl' => admin_url('admin-ajax.php'),
                    'cartTotalNonce' => wp_create_nonce('lkn_wcip_cart_total'),
                    'userId' => get_current_user_id(),
                    'symbol' => $currency_symbol,
                    'partialPaymentTitle' => __('Partial Payment', 'wc-invoice-payment'),
                    'partialPaymentDescription' => __('Enter the amount you want to pay now, the rest can be paid later with other payment methods.', 'wc-invoice-payment'),
                    'payPartialText' => __('Pay Partial', 'wc-invoice-payment'),
                    'nonce' => wp_create_nonce('wp_rest'),
                ));
            } else {
                // Checkout Blocks: enfileira script que popula o step injetado via render_block
                $pay_remaining = isset($_GET['pay_remaining']) ? intval($_GET['pay_remaining']) : 0;

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
                    'paidNowLabel'        => __('Voce pagara agora:', 'wc-invoice-payment'),
                    'paidLaterLabel'      => __('Restante para depois:', 'wc-invoice-payment'),
                    'gatewayLockedText'   => __('Indisponivel para pagamento parcial', 'wc-invoice-payment'),
                    'maxValueLabel'       => __('Valor maximo:', 'wc-invoice-payment'),
                    'feesAddedLabel'      => __('Taxas/juros adicionais:', 'wc-invoice-payment'),
                    'initialBaseMax'      => (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_shipping_total() - (float) WC()->cart->get_discount_total(),
                    'currencyCode'        => get_woocommerce_currency(),
                    'priceFormat'         => array(
                        'decimal_sep'   => wc_get_price_decimal_separator(),
                        'thousand_sep'  => wc_get_price_thousand_separator(),
                        'decimals'      => wc_get_price_decimals(),
                        'currency_pos'  => get_option('woocommerce_currency_pos', 'left'),
                    ),
                    'isPayRemaining'      => $pay_remaining > 0,
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
        $inv[] = $partial_order_id;
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

        $order->save();
        $partial_order->save();

        wp_send_json_success(array(
            'payment_url'   => $partial_order->get_checkout_payment_url(),
            'order_id'      => $order_id,
            'partial_order' => $partial_order_id,
        ));
    }

    /**
     * AJAX: retorna o HTML da tabela do pedido parcial.
     */
    public function ajaxRefreshOrderReview() {
        $orderId = isset($_POST['orderId']) ? intval(wp_unslash($_POST['orderId'])) : 0;
        if (!$orderId) wp_send_json_error();

        $order = wc_get_order($orderId);
        if (!$order || $order->get_meta('_wc_lkn_is_partial_order') !== 'yes') wp_send_json_error();

        $remaining = (float) $order->get_meta('_wc_lkn_partial_remaining');
        $label     = __('Remaining balance (pay later)', 'wc-invoice-payment');

        // Recalcula carrinho
        WC()->session->set('lkn_wcip_skip_fee', true);
        $this->removeFeeFromCart($label);
        WC()->cart->calculate_totals();
        WC()->session->set('lkn_wcip_skip_fee', false);

        // Coleta fees do carrinho
        $cartFull = (float) WC()->cart->get_cart_contents_total()
                  + (float) WC()->cart->get_shipping_total()
                  + (float) WC()->cart->get_taxes_total();
        $otherFees = array();
        foreach (WC()->cart->get_fees() as $fee) {
            if ($fee->name === $label) continue;
            $cartFull   += (float) $fee->amount;
            $otherFees[] = array('name' => $fee->name, 'amount' => (float) $fee->amount);
        }

        // Sincroniza fees do carrinho → pedido
        foreach ($order->get_fees() as $itemId => $feeItem) { $order->remove_item($itemId); }
        foreach ($otherFees as $f) {
            if (abs($f['amount']) < 0.001) continue;
            $fi = new \WC_Order_Item_Fee();
            $fi->set_name($f['name']);
            $fi->set_amount($f['amount']);
            $fi->set_total($f['amount']);
            $order->add_item($fi);
        }
        if ($remaining > 0.001) {
            $fi = new \WC_Order_Item_Fee();
            $fi->set_name($label);
            $fi->set_amount(-$remaining);
            $fi->set_total(-$remaining);
            $order->add_item($fi);
        }
        $order->calculate_totals();
        $order->save();

        // Recoloca fee no carrinho
        WC()->cart->add_fee($label, -$remaining, false);
        WC()->cart->calculate_totals();

        // Renderiza APENAS a tabela (não o form inteiro)
        ob_start();
        ?>
        <table class="shop_table">
            <thead><tr>
                <th class="product-name">Produto</th>
                <th class="product-quantity">Qtd</th>
                <th class="product-total">Total</th>
            </tr></thead>
            <tbody>
                <?php foreach ($order->get_items() as $item): ?>
                <tr class="order_item">
                    <td class="product-name"><?php echo esc_html($item->get_name()); ?></td>
                    <td class="product-quantity"><strong>&times;&nbsp;<?php echo esc_html($item->get_quantity()); ?></strong></td>
                    <td class="product-subtotal"><?php echo wp_kses_post(wc_price($item->get_total(), array('currency' => $order->get_currency()))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" colspan="2">Subtotal:</th>
                    <td class="product-total"><?php echo wp_kses_post(wc_price(WC()->cart->get_cart_contents_total(), array('currency' => $order->get_currency()))); ?></td>
                </tr>
                <?php $shippingTotal = (float) WC()->cart->get_shipping_total(); if ($shippingTotal > 0): ?>
                <tr>
                    <th scope="row" colspan="2">Entrega:</th>
                    <td class="product-total"><?php echo wp_kses_post(wc_price($shippingTotal, array('currency' => $order->get_currency()))); ?></td>
                </tr>
                <?php endif; ?>
                <?php foreach ($otherFees as $fee): ?>
                <tr>
                    <th scope="row" colspan="2"><?php echo esc_html($fee['name']); ?>:</th>
                    <td class="product-total"><?php echo wp_kses_post(wc_price($fee['amount'], array('currency' => $order->get_currency()))); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (abs($remaining) > 0.001): ?>
                <tr>
                    <th scope="row" colspan="2"><?php echo esc_html($label); ?>:</th>
                    <td class="product-total"><?php echo wp_kses_post(wc_price(-$remaining, array('currency' => $order->get_currency()))); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row" colspan="2">Total:</th>
                    <td class="product-total"><?php echo wp_kses_post(wc_price($order->get_total(), array('currency' => $order->get_currency()))); ?></td>
                </tr>
            </tfoot>
        </table>
        <?php
        wp_send_json_success(array('html' => ob_get_clean()));
    }

    /**
     * Remove um fee específico do carrinho pelo nome.
     */
    private function removeFeeFromCart($name) {
        if (!WC()->cart->fees_api()) return;

        $prop = new \ReflectionProperty('WC_Cart_Fees', 'fees');
        $prop->setAccessible(true);
        $fees = $prop->getValue(WC()->cart->fees_api());

        foreach ($fees as $key => $fee) {
            if ($fee->name === $name) {
                unset($fees[$key]);
            }
        }

        $prop->setValue(WC()->cart->fees_api(), $fees);
    }

    /**
     * Na página order-pay de um pedido filho parcial.
     */
    public function markPartialOrderSession() {
        $orderId = absint(get_query_var('order-pay'));
        if (!$orderId) return;

        $order = wc_get_order($orderId);
        if (!$order || $order->get_meta('_wc_lkn_is_partial_order') !== 'yes') {
            return;
        }

        WC()->session->set('lkn_wcip_partial_order_paying', $orderId);

        // Registra hooks de fee
        add_action('woocommerce_cart_calculate_fees', array($this, 'clearCartFeesAndApply'), 1);
        add_action('woocommerce_cart_calculate_fees', array($this, 'applyPartialOrderFee'), 9999);

        // Garante que chosen_payment_method está setado para o Rede PRO
        // adicionar o fee "Juros" corretamente.
        $paymentMethod = $order->get_payment_method();
        if (!$paymentMethod || $paymentMethod === 'multiplePayment') {
            // Pega o primeiro gateway habilitado para pagamento parcial
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
            $first = array_key_first($gateways);
            $paymentMethod = $first ?: 'rede_credit';
        }
        WC()->session->set('chosen_payment_method', $paymentMethod);

        // Força o cálculo dos totais do carrinho AGORA.
        WC()->cart->calculate_totals();

        // Copia fees do carrinho pro pedido
        foreach ($order->get_items('fee') as $itemId => $item) {
            $order->remove_item($itemId);
        }
        foreach (WC()->cart->get_fees() as $fee) {
            $item = new \WC_Order_Item_Fee();
            $item->set_name($fee->name);
            $item->set_amount((float) $fee->amount);
            $item->set_total((float) $fee->total);
            $order->add_item($item);
        }
        $order->calculate_totals(true);
        $order->save();

        wp_enqueue_script(
            'wc-invoice-payment-partial-order-refresh',
            WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial-order-refresh.js',
            array('jquery'),
            WC_PAYMENT_INVOICE_VERSION,
            true
        );

        wp_localize_script('wc-invoice-payment-partial-order-refresh', 'lknWcipPartialOrderRefresh', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'partialOrderId' => $orderId,
        ));
    }

    /**
     * Limpa todos os fees do carrinho (exceto "Remaining") para recálculo limpo.
     */
    public function clearCartFeesAndApply() {
        $label = __('Remaining balance (pay later)', 'wc-invoice-payment');
        $this->removeFeeFromCart($label);
    }

    /**
     * Remove todos os fees do pedido exceto "Remaining balance (pay later)".
     */
    private function resetOrderFees($order) {
        $label = __('Remaining balance (pay later)', 'wc-invoice-payment');
        $remainingAmount = (float) $order->get_meta('_wc_lkn_partial_remaining');

        foreach ($order->get_fees() as $itemId => $feeItem) {
            $order->remove_item($itemId);
        }

        if ($remainingAmount > 0.001) {
            $item = new \WC_Order_Item_Fee();
            $item->set_name($label);
            $item->set_amount(-$remainingAmount);
            $item->set_total(-$remainingAmount);
            $order->add_item($item);
        }
    }

    /**
     * Aplica um fee negativo no carrinho igual à diferença entre
     * o total original e o valor do pedido filho parcial.
     * O Rede inclui este fee no cálculo de parcelas (não é "Interest" nem "Discount").
     */
    public function applyPartialOrderFee() {
        // Pula durante o ajaxRefreshOrderReview (controle manual)
        if (WC()->session && WC()->session->get('lkn_wcip_skip_fee')) return;

        $orderId = WC()->session ? WC()->session->get('lkn_wcip_partial_order_paying') : null;
        if (!$orderId) return;

        $order = wc_get_order($orderId);
        if (!$order || $order->get_meta('_wc_lkn_is_partial_order') !== 'yes') return;

        $partial_total   = (float) $order->get_total();
        $label           = __('Remaining balance (pay later)', 'wc-invoice-payment');

        $cart_full_total = (float) WC()->cart->get_cart_contents_total()
                         + (float) WC()->cart->get_shipping_total()
                         + (float) WC()->cart->get_taxes_total();

        foreach (WC()->cart->get_fees() as $fee) {
            if ($fee->name === $label) continue; // não conta este fee
            $cart_full_total += (float) $fee->amount;
        }

        $remaining = $cart_full_total - $partial_total;

        if ($remaining > 0.001) {
            WC()->cart->add_fee($label, -$remaining, false);
        }
    }

    /**
     * Filtro para plugins de gateway (Rede, etc).
     * Intercepta o total usado para cálculo de parcelas e devolve o valor do pedido filho.
     *
     * Para funcionar, o plugin de gateway precisa aplicar este filtro no cálculo de parcelas:
     *   $cart_total = apply_filters('lkn_wcip_partial_cart_total', $cart_total);
     */
    public function overridePartialTotalForGateways($cart_total) {
        if (!WC()->session) return $cart_total;

        $orderId = WC()->session->get('lkn_wcip_partial_order_paying');
        if (!$orderId) return $cart_total;

        $order = wc_get_order($orderId);
        if (!$order || $order->get_meta('_wc_lkn_is_partial_order') !== 'yes') return $cart_total;

        return (float) $order->get_total();
    }

    /**
     * Filtra os gateways disponíveis na página de pagamento de um pedido filho parcial,
     * mostrando apenas os gateways habilitados na configuração de pagamento parcial.
     *
     * Também filtra no checkout normal quando o split de pagamento está ativo
     * (session lkn_partial_amount).
     */
    public function filterGatewaysForPartialOrder($gateways) {
        // Cenário 1: order-pay de pedido filho parcial (modelo antigo)
        $orderId = absint(get_query_var('order-pay'));
        if ($orderId) {
            $order = wc_get_order($orderId);
            if ($order && $order->get_meta('_wc_lkn_is_partial_order') === 'yes') {
                return $this->filterGatewaysByPartialConfig($gateways);
            }
        }

        // Cenário 2: split ativo no checkout
        if (WC()->session && (float) WC()->session->get('lkn_partial_amount', 0) > 0) {
            return $this->filterGatewaysByPartialConfig($gateways);
        }

        return $gateways;
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
            wp_localize_script('wcInvoicePaymentPartialScript', 'lknWcipPartialTableVariables', array(
                'orderId' => $order->get_id(),
                'totalToPay' => $totalToPay,
                /* translators: %s: order total price (replaced in JavaScript) */
                'confirmPayment' => __('Are you sure you want to pay %s?', 'wc-invoice-payment'),
                'confirmCancel' => __('Are you sure you want to cancel this partial payment?', 'wc-invoice-payment'),
                'nonce' => wp_create_nonce('wp_rest'),
                'symbol' => get_woocommerce_currency_symbol( $order->get_currency() ),
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
        wp_enqueue_style('teste-admin-style', plugin_dir_url(__FILE__) . 'css/wc-teste21.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        
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
                                                <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \esc_url($orderInvoice->get_checkout_payment_url()); ?>" target="_blank" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Payment Link', 'wc-invoice-payment'); ?>">
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
        
        $payment_url = $order->get_checkout_payment_url();
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
        if (get_option('lkn_wcip_partial_payments_enabled', '') !== 'yes') return $content;
        if (!is_checkout()) return $content;
        if (strpos($content, 'lkn-wcip-partial-split-step') !== false) return $content;

        // So processa o bloco principal do checkout
        $blockName = isset($block['blockName']) ? $block['blockName'] : '';
        if ($blockName !== 'woocommerce/checkout') return $content;

        $pay_remaining = isset($_GET['pay_remaining']) ? intval($_GET['pay_remaining']) : 0;
        $title        = esc_html__('Pagamento Parcial', 'wc-invoice-payment');
        $symbol       = get_woocommerce_currency_symbol(get_woocommerce_currency());
        $base_max     = WC()->cart ? (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_shipping_total() - (float) WC()->cart->get_discount_total() : 0;
        $base_max_f   = number_format($base_max, 2, ',', '.');

        $step  = '<div class="wc-block-components-checkout-step lkn-wcip-partial-split-step">';
        $step .= '<div class="wc-block-components-checkout-step__heading" style="margin-bottom:16px">';
        $step .= '<h2 class="wc-block-components-title wc-block-components-checkout-step__title">' . $title . '</h2>';
        $step .= '</div>';
        $step .= '<div class="wc-block-components-checkout-step__content">';
        $step .= '<div class="lkn-wcip-partial-split-step-content">';

        // Container (mesmo estilo do original)
        $step .= '<div class="lkn-wcip-split-blocks-container" style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:6px;padding:16px">';

        // Checkbox
        if ($pay_remaining > 0) {
            $step .= '<label style="display:flex;align-items:flex-start;gap:8px;cursor:default;margin-bottom:0;font-size:14px">';
            $step .= '<input id="lkn-wcip-split-checkbox" type="checkbox" checked disabled style="width:18px;height:18px;margin-top:1px;flex-shrink:0">';
            $step .= '<span>Marque para dividir o pagamento.</span>';
            $step .= '</label>';
        } else {
            $step .= '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;margin-bottom:0;font-size:14px">';
            $step .= '<input id="lkn-wcip-split-checkbox" type="checkbox" style="width:18px;height:18px;margin-top:1px;flex-shrink:0">';
            $step .= '<span>Marque para dividir o pagamento.</span>';
            $step .= '</label>';
            $step .= '<p class="lkn-wcip-base-max-msg" style="font-size:13px;color:#666;margin:8px 0 0;display:none">Valor maximo: <strong class="lkn-wcip-base-max-val">' . $symbol . '&nbsp;' . $base_max_f . '</strong> <span style="font-size:12px;color:#999">(sem juros/taxas do gateway)</span></p>';
        }

        // Campos (input + botão — escondidos inicialmente em ambos modos)
        $step .= '<div class="lkn-wcip-split-fields" style="margin-top:8px;display:none">';
        $step .= '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
        $step .= '<input id="lkn-wcip-split-amount" type="text" placeholder="' . $symbol . ' 0,00" style="flex:1 1 140px;padding:10px 12px;font-size:16px;border:1px solid #ccc;border-radius:4px;min-width:120px">';
        $step .= '<button id="lkn-wcip-split-btn" type="button" style="padding:10px 20px;font-size:14px;font-weight:600;background:#007cba;color:#fff;border:none;border-radius:4px;cursor:pointer">Split pagamento</button>';
        $step .= '</div></div>';

        // Resultado (visível imediatamente no pay_remaining)
        $step .= '<div id="lkn-wcip-split-result" style="' . ($pay_remaining > 0 ? '' : 'display:none') . '"></div>';

        $step .= '</div>'; // .lkn-wcip-split-blocks-container
        $step .= '</div>'; // .lkn-wcip-partial-split-step-content
        $step .= '</div></div>';

        // Injeta ANTES do bloco de pagamento
        $pattern = '/(<div[^>]*data-block-name="woocommerce\/checkout-payment-block"[^>]*><\/div>)/';
        return preg_replace($pattern, $step . '$1', $content, 1);
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
        $session_order_id = WC()->session->get('lkn_partial_order_id');

        if ($pay_remaining > 0 && $session_order_id && $pay_remaining == $session_order_id) {
            error_log("[LockShipping:clearOnLoad] PRESERVED session — pay_remaining=$pay_remaining matches lkn_partial_order_id=$session_order_id");
            return;
        }

        if ($session_order_id) {
            error_log("[LockShipping:clearOnLoad] CLEANING zombie session — lkn_partial_order_id=$session_order_id but pay_remaining=" . ($pay_remaining ?: 'none'));
        }

        // Qualquer outro caso: limpa tudo (checkout normal ou sessão velha/zumbi)
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

        $remaining = $cart_total - $partial_amount;

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

        // Recalcula o remaining: total original - o que sera pago agora
        $remaining = $base_total + $gateway_fees - $final_total;
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

            // cart_total REAL (get_total inclui TODOS os fees, inclusive o nosso)
            $cart_total = (float) $cart->get_total();

            $gateway_fees = 0.0;
            foreach ($cart->get_fees() as $fee) {
                if ($fee->name !== __('Pagamento Parcial (saldo restante)', 'wc-invoice-payment')) {
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
     */
    public function savePartialRemainingOnOrderBlocks($order) {
        if (!$order instanceof \WC_Order) return;
        $this->maybeSaveSplitDataToOrder($order);
    }

    /**
     * Se havia split ativo, salva os dados na ordem e limpa a sessão.
     */
    private function maybeSaveSplitDataToOrder($order) {
        if (!WC()->session) {
            return;
        }

        $partial_amount = (float) WC()->session->get('lkn_partial_amount', 0);
        if ($partial_amount <= 0) {
            return;
        }

        // Fluxo "pagar restante" da thank-you page
        $partial_order_id = (int) WC()->session->get('lkn_partial_order_id');
        if ($partial_order_id > 0) {
            $this->handlePayRemainingOrder($order, $partial_order_id, $partial_amount);
            return;
        }

        // Fluxo original: split no checkout
        // O "original total" é só produto + frete (base_total), sem juros do gateway.
        // O cliente paga o partial_amount e os juros são custo do gateway, não da loja.
        // remaining = base_total - partial_amount (o que falta pra loja receber)
        $base_total = (float) WC()->session->get('lkn_partial_base_total', 0);
        $remaining = $base_total - $partial_amount;
        if ($remaining < 0) $remaining = 0;

        $order->update_meta_data('_wc_lkn_is_partial_main_order', 'yes');
        $order->update_meta_data('_wc_lkn_partial_amount_paid', $partial_amount);
        $order->update_meta_data('_wc_lkn_partial_remaining', $remaining);
        $order->update_meta_data('_wc_lkn_original_total', $base_total);
        $order->update_meta_data('_wc_lkn_total_peding', 0);
        $order->update_meta_data('_wc_lkn_total_confirmed', $partial_amount);
        $order->update_meta_data('lkn_ini_date', gmdate('Y-m-d'));

        // Salva o frete escolhido para o fluxo "pagar restante"
        $this->saveChosenShippingToOrder($order);

        $order->save();
        $order->update_status('wc-partial');

        $this->cleanSplitSession();
    }

    /**
     * Fluxo "pagar restante": atualiza o partial order e o parent order.
     */
    private function handlePayRemainingOrder($order, $partial_order_id, $partial_amount) {
        $partial_order = wc_get_order($partial_order_id);
        if (!$partial_order) {
            return;
        }

        $parent_id = WC()->session->get('lkn_partial_parent_order_id');
        $parent_order = $parent_id ? wc_get_order((int) $parent_id) : null;

        // Marca o pedido parcial como pago (order_total = valor real cobrado incluindo juros)
        $order_total = (float) $order->get_total();
        $partial_order->update_status('wc-partial');
        $partial_order->add_order_note(sprintf(
            'Pagamento do restante realizado via pedido #%s — valor base: %s, valor cobrado: %s',
            $order->get_id(),
            wc_price($partial_amount),
            wc_price($order_total)
        ));
        $partial_order->save();

        if ($parent_order) {
            // confirmed usa partial_amount (sem juros), não order_total
            $confirmed = (float) $parent_order->get_meta('_wc_lkn_total_confirmed') ?: 0;
            $confirmed += $partial_amount;
            $parent_order->update_meta_data('_wc_lkn_total_confirmed', $confirmed);
            $parent_order->update_meta_data('_wc_lkn_total_peding', 0);

            $original_total = (float) $parent_order->get_meta('_wc_lkn_original_total');
            if ($original_total > 0 && round($confirmed, 2) >= round($original_total, 2)) {
                $parent_order->update_status('completed');
                $parent_order->add_order_note('Todos os pagamentos parciais foram concluídos.');
            }

            $parent_order->save();
        }

        $order->update_meta_data('_wc_lkn_is_partial_pay_remaining', 'yes');
        $order->update_meta_data('_wc_lkn_parent_order_id', $parent_id);
        $order->update_meta_data('_wc_lkn_partial_order_id', $partial_order_id);
        $order->save();

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
     * Salva os dados do frete escolhido como meta no pedido,
     * para que o fluxo "pagar restante" possa travar o frete.
     *
     * Salva duas coisas:
     * 1. _wc_lkn_chosen_shipping_rates: os rate_ids exatos (ex: "melhor_envio:5")
     * 2. _wc_lkn_chosen_shipping: dados legíveis (method_title, total)
     */
    private function saveChosenShippingToOrder($order) {
        $tag = '[LockShipping:save]';
        if (!WC()->session) {
            error_log("$tag ABORT: no session");
            return;
        }

        // Rate IDs exatos do WooCommerce (ex: "flat_rate:1", "local_pickup", "melhor_envio:5")
        $chosen_rates = WC()->session->get('chosen_shipping_methods');
        if (!empty($chosen_rates) && is_array($chosen_rates)) {
            error_log("$tag chosen_rates=" . wp_json_encode($chosen_rates));
            $order->update_meta_data('_wc_lkn_chosen_shipping_rates', wp_json_encode($chosen_rates));
        } else {
            error_log("$tag no chosen_rates in session");
        }

        // Dados legíveis (título, total) para exibir aviso no checkout
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
            error_log("$tag shipping_data=" . wp_json_encode($shipping_data));
            $order->update_meta_data('_wc_lkn_chosen_shipping', wp_json_encode($shipping_data));
        } else {
            error_log("$tag no shipping items in order (retirada/local pickup?)");
        }
    }

    /**
     * Filtra os métodos de entrega no checkout quando está no fluxo
     * "pagar restante", exibindo apenas o frete escolhido no primeiro pagamento.
     *
     * Hook: woocommerce_package_rates (priority 9999)
     */
    public function filterShippingForPartialRemaining($rates) {
        $tag = '[LockShipping:filter]';
        if (!WC()->session) {
            error_log("$tag ABORT: no session");
            return $rates;
        }

        $partial_order_id = WC()->session->get('lkn_partial_order_id');
        if (!$partial_order_id) return $rates;

        // Dump de todos os rates disponíveis
        $all_ids = array_keys($rates);
        error_log("$tag partial_order_id=$partial_order_id all_rates=[" . implode(', ', $all_ids) . "]");

        // Usa os rate_ids exatos restaurados na sessão (vindos do meta _wc_lkn_chosen_shipping_rates)
        $allowed_ids = WC()->session->get('lkn_partial_shipping_rate_ids');
        if (empty($allowed_ids) || !is_array($allowed_ids)) {
            error_log("$tag ABORT: lkn_partial_shipping_rate_ids is empty or not array");
            return $rates;
        }

        // Normaliza pra string — chosen_shipping_methods salva como string,
        // mas PHP pode converter chaves numéricas do array $rates pra int.
        $allowed_ids = array_map('strval', $allowed_ids);
        error_log("$tag allowed_ids=[" . implode(', ', $allowed_ids) . "]");

        $filtered = array();
        foreach ($rates as $rate_id => $rate) {
            if (in_array((string) $rate_id, $allowed_ids, true)) {
                $filtered[$rate_id] = $rate;
            }
        }

        // Se filtrou tudo e ficou vazio, mantém todos (segurança)
        if (empty($filtered) && !empty($rates)) {
            error_log("$tag WARNING: filtered all — falling back to all rates");
            return $rates;
        }

        $kept_ids = array_keys($filtered);
        error_log("$tag RESULT kept=[" . implode(', ', $kept_ids) . "]");
        return $filtered;
    }

    /**
     * Exibe um aviso no checkout informando que o frete está travado
     * (fluxo "pagar restante").
     *
     * Hook: woocommerce_before_checkout_form
     */
    public function addLockedShippingNotice($checkout = null) {
        $tag = '[LockShipping:notice]';
        if (!WC()->session) {
            error_log("$tag ABORT: no session");
            return;
        }

        $partial_order_id = WC()->session->get('lkn_partial_order_id');
        if (!$partial_order_id) return;

        $saved_shipping = WC()->session->get('lkn_partial_shipping_methods');
        if (empty($saved_shipping) || !is_array($saved_shipping)) {
            error_log("$tag partial_order_id=$partial_order_id but no lkn_partial_shipping_methods");
            return;
        }

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
                error_log("$tag showing notice: $title — $total");
                wc_add_notice($msg, 'notice');
            }
        }
    }

    /**
     * Exibe na thank-you page um card informando o saldo restante
     * e botão para pagar o restante.
     */
    public function displayPartialRemainingOnThankyou($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Fluxo "pagar restante": verifica se o parent foi totalmente quitado
        if ($order->get_meta('_wc_lkn_is_partial_pay_remaining') === 'yes') {
            $parent_id = (int) $order->get_meta('_wc_lkn_parent_order_id');
            $parent_order = $parent_id ? wc_get_order($parent_id) : null;
            if ($parent_order) {
                $original_total = (float) $parent_order->get_meta('_wc_lkn_original_total');
                $confirmed = (float) $parent_order->get_meta('_wc_lkn_total_confirmed');
                $remaining = round($original_total - $confirmed, 2);

                if ($remaining <= 0) {
                    // Totalmente pago — exibe card verde de conclusão com detalhes das parcelas
                    $first_base   = (float) $parent_order->get_meta('_wc_lkn_partial_amount_paid');
                    $first_total  = (float) $parent_order->get_total();
                    $second_base  = round($confirmed - $first_base, 2);
                    $second_total = (float) $order->get_total();
                    $total_pago   = $first_total + $second_total;
                    ?>
                    <div class="lkn-wcip-partial-thankyou-card" style="
                        background: #f0f7f0;
                        border: 2px solid #008a20;
                        border-radius: 8px;
                        padding: 24px;
                        margin: 24px 0;
                        text-align: center;
                    ">
                        <h3 style="margin: 0 0 12px; color: #008a20;">
                            <?php esc_html_e('Pagamento Totalmente Concluído', 'wc-invoice-payment'); ?>
                        </h3>
                        <div style="text-align: left; max-width: 340px; margin: 0 auto 20px; font-size: 14px; line-height: 1.8; color: #555;">
                            <div style="display: flex; justify-content: space-between; padding: 2px 0;">
                                <span><?php esc_html_e('Subtotal + Frete:', 'wc-invoice-payment'); ?></span>
                                <strong><?php echo wc_price($original_total, array('currency' => $order->get_currency())); ?></strong>
                            </div>
                            <hr style="border: none; border-top: 1px dashed #ccc; margin: 4px 0;">
                            <div style="display: flex; justify-content: space-between; padding: 2px 0;">
                                <span><?php esc_html_e('1ª parcela:', 'wc-invoice-payment'); ?></span>
                                <span><strong><?php echo wc_price($first_base, array('currency' => $order->get_currency())); ?></strong> <?php esc_html_e('(base)', 'wc-invoice-payment'); ?></span>
                            </div>
                            <?php if (round($first_total - $first_base, 2) > 0.01): ?>
                            <div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 12px; color: #007cba;">
                                <span><?php esc_html_e('  + taxas/juros:', 'wc-invoice-payment'); ?></span>
                                <span><?php echo wc_price(round($first_total - $first_base, 2), array('currency' => $order->get_currency())); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 13px; font-weight: 600;">
                                <span><?php esc_html_e('  Total cobrado:', 'wc-invoice-payment'); ?></span>
                                <span><?php echo wc_price($first_total, array('currency' => $order->get_currency())); ?></span>
                            </div>
                            <?php endif; ?>
                            <hr style="border: none; border-top: 1px dashed #ccc; margin: 4px 0;">
                            <div style="display: flex; justify-content: space-between; padding: 2px 0;">
                                <span><?php esc_html_e('2ª parcela:', 'wc-invoice-payment'); ?></span>
                                <span><strong><?php echo wc_price($second_base, array('currency' => $order->get_currency())); ?></strong> <?php esc_html_e('(base)', 'wc-invoice-payment'); ?></span>
                            </div>
                            <?php if (round($second_total - $second_base, 2) > 0.01): ?>
                            <div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 12px; color: #007cba;">
                                <span><?php esc_html_e('  + taxas/juros:', 'wc-invoice-payment'); ?></span>
                                <span><?php echo wc_price(round($second_total - $second_base, 2), array('currency' => $order->get_currency())); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 13px; font-weight: 600;">
                                <span><?php esc_html_e('  Total cobrado:', 'wc-invoice-payment'); ?></span>
                                <span><?php echo wc_price($second_total, array('currency' => $order->get_currency())); ?></span>
                            </div>
                            <?php endif; ?>
                            <hr style="border: none; border-top: 2px solid #008a20; margin: 6px 0;">
                            <div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 15px; font-weight: 600; color: #008a20;">
                                <span><?php esc_html_e('Total pago:', 'wc-invoice-payment'); ?></span>
                                <span><?php echo wc_price($total_pago, array('currency' => $order->get_currency())); ?></span>
                            </div>
                        </div>
                        <p style="font-size: 13px; color: #008a20; margin: 0;">
                            <?php esc_html_e('Todos os pagamentos parciais foram concluídos com sucesso.', 'wc-invoice-payment'); ?>
                        </p>
                    </div>
                    <?php
                }
            }
            return;
        }

        // Calcula remaining: original_total (só produto+frete, sem juros) - confirmed (o que usuário escolheu pagar)
        $original_total = (float) $order->get_meta('_wc_lkn_original_total');
        $confirmed = (float) $order->get_meta('_wc_lkn_total_confirmed');
        $remaining = round($original_total - $confirmed, 2);

        if ($remaining <= 0) return;

        // Base: o que usuário escolheu pagar (sem juros). Confirmed: o que foi confirmado no parent.
        $partial_amount = (float) $order->get_meta('_wc_lkn_partial_amount_paid');
        // Total real cobrado (base + juros do gateway)
        $paid = (float) $order->get_total();
        // Juros/taxas: diferença entre o cobrado e o valor base
        $fees = round($paid - $partial_amount, 2);

        // URL para pagar o restante (usa o endpoint REST existente)
        $pay_rest_url = rest_url('invoice_payments/create_partial_payment');
        $nonce = wp_create_nonce('wp_rest');
        $symbol = get_woocommerce_currency_symbol($order->get_currency());

        ?>
        <div class="lkn-wcip-partial-thankyou-card" style="
            background: #f8f9fa;
            border: 2px solid #007cba;
            border-radius: 8px;
            padding: 24px;
            margin: 24px 0;
            text-align: center;
        ">
            <h3 style="margin: 0 0 12px; color: #007cba;">
                <?php esc_html_e('Pagamento Parcial Realizado', 'wc-invoice-payment'); ?>
            </h3>

            <div style="text-align: left; max-width: 340px; margin: 0 auto 20px; font-size: 14px; line-height: 1.8; color: #555;">
                <div style="display: flex; justify-content: space-between; padding: 2px 0;">
                    <span><?php esc_html_e('Valor pago (base):', 'wc-invoice-payment'); ?></span>
                    <strong><?php echo wc_price($partial_amount, array('currency' => $order->get_currency())); ?></strong>
                </div>
                <?php if ($fees > 0.01): ?>
                <div style="display: flex; justify-content: space-between; padding: 2px 0; color: #007cba;">
                    <span><?php esc_html_e('Taxas/juros do gateway:', 'wc-invoice-payment'); ?></span>
                    <strong><?php echo wc_price($fees, array('currency' => $order->get_currency())); ?></strong>
                </div>
                <hr style="border: none; border-top: 1px dashed #ccc; margin: 4px 0;">
                <div style="display: flex; justify-content: space-between; padding: 2px 0; font-weight: 600; color: #333;">
                    <span><?php esc_html_e('Total cobrado:', 'wc-invoice-payment'); ?></span>
                    <span><?php echo wc_price($paid, array('currency' => $order->get_currency())); ?></span>
                </div>
                <?php endif; ?>
                <hr style="border: none; border-top: 1px dashed #ccc; margin: 4px 0;">
                <div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 15px; font-weight: 600; color: #d63638;">
                    <span><?php esc_html_e('Saldo restante:', 'wc-invoice-payment'); ?></span>
                    <span><?php echo wc_price($remaining, array('currency' => $order->get_currency())); ?></span>
                </div>
            </div>
            <button id="lknWcipPayRemainingBtn" class="button alt" style="
                background: #007cba;
                color: #fff;
                padding: 12px 24px;
                font-size: 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            " data-order-id="<?php echo esc_attr($order_id); ?>"
               data-amount="<?php echo esc_attr($remaining); ?>"
               data-nonce="<?php echo esc_attr($nonce); ?>"
               data-rest-url="<?php echo esc_url($pay_rest_url); ?>">
                <?php esc_html_e('Pagar Restante', 'wc-invoice-payment'); ?>
            </button>
            <p style="font-size: 13px; color: #666; margin: 12px 0 0;">
                <?php esc_html_e('Você pode pagar o restante agora ou depois, usando outro método de pagamento.', 'wc-invoice-payment'); ?>
            </p>
        </div>

        <script type="text/javascript">
        (function($) {
            $('#lknWcipPayRemainingBtn').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Processando...', 'wc-invoice-payment')); ?>');

                $.ajax({
                    url: btn.data('rest-url'),
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': btn.data('nonce') },
                    data: JSON.stringify({
                        orderId: parseInt(btn.data('order-id')),
                        partialAmount: parseFloat(btn.data('amount')),
                        userId: <?php echo intval(get_current_user_id()); ?>
                    }),
                    success: function(res) {
                        if (res && res.payment_url) {
                            window.location.href = res.payment_url;
                        } else if (res && res.success) {
                            window.location.reload();
                        }
                    },
                    error: function(xhr) {
                        var msg = '<?php echo esc_js(__('Erro ao processar. Tente novamente.', 'wc-invoice-payment')); ?>';
                        try {
                            var err = JSON.parse(xhr.responseText);
                            if (err && err.error) msg = err.error;
                        } catch(e) {}
                        alert(msg);
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Pagar Restante', 'wc-invoice-payment')); ?>');
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}