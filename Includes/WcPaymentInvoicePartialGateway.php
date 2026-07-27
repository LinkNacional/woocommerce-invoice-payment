<?php

namespace LknWc\WcInvoicePayment\Includes;

use WC_Payment_Gateway;

if (! defined('ABSPATH')) {
    exit;
}

final class WcPaymentInvoicePartialGateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id                 = 'lkn_wcip_partial_gateway';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = __('Partial Payment', 'wc-invoice-payment');
        $this->method_description = __('Internal gateway to initiate partial payment.', 'wc-invoice-payment');
        $this->title              = __('Partial Payment', 'wc-invoice-payment');

        $this->supports = array('products');

        $this->enabled = 'yes';
    }

    public function is_available()
    {
        if (!WC()->session) {
            return false;
        }

        if (WC()->session->get('lkn_partial_mode_active') !== 'yes') {
            return false;
        }

        return parent::is_available();
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return array('result' => 'failure');
        }

        $subtotal = 0;
        foreach ($order->get_items('line_item') as $item) {
            $subtotal += (float) $item->get_subtotal();
        }
        if ($subtotal <= 0) {
            $subtotal = (float) $order->get_subtotal();
        }

        $shipping_total = (float) $order->get_shipping_total();
        $base_total = $subtotal + $shipping_total;

        // Metadados de controle — o pedido pai é um pedido comum, não wc-partial.
        $order->update_meta_data('_wc_lkn_original_total', $base_total);
        $order->update_meta_data('_wc_lkn_is_partial_main_order', 'yes');
        $order->update_meta_data('_wc_lkn_total_peding', $base_total);
        $order->update_meta_data('_wc_lkn_total_confirmed', 0);
        $order->update_meta_data('_wc_lkn_pay_remaining_pending', 'yes');
        $order->update_meta_data('lkn_ini_date', gmdate('Y-m-d'));
        $order->update_meta_data('lkn_exp_date', gmdate('Y-m-d'));

        // Salva frete escolhido
        if (WC()->session) {
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

        // Status processing garante que o analytics registra o pedido (status nativo).
        // O save() persiste metas + status processing juntos.
        $order->set_status('processing');
        $order->add_order_note(__('Partial payment started. Waiting for first payment.', 'wc-invoice-payment'));
        $order->save();

        // Migra pra wc-partial (controle interno). O analytics já registrou o
        // pedido como processing — essa transição não afeta o analytics.
        $order->set_status('wc-partial');
        $order->save();

        // Sessão pro checkout pay_remaining (1º split)
        WC()->session->set('lkn_partial_parent_order_id', $order->get_id());
        WC()->session->set('lkn_partial_base_total', $base_total);
        WC()->session->__unset('lkn_partial_mode_active');

        // Flag: Blocks checkout esvazia carrinho no redirect. restoreCartForPartialInit
        // recarrega os itens do pedido pai antes do checkout renderizar.
        WC()->session->set('lkn_partial_needs_cart_restore', $order->get_id());

        // Redirect direto pro checkout de split (1/2). Thank-you é só após pagar.
        return array(
            'result'   => 'success',
            'redirect' => add_query_arg('pay_remaining', $order->get_id(), wc_get_checkout_url()),
        );
    }
}
