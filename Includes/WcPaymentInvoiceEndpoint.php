<?php
namespace LknWc\WcInvoicePayment\Includes;

use WP_REST_Response;
use WC_Customer;

final class WcPaymentInvoiceEndpoint {
    public function registerEndpoints(): void {
        register_rest_route('invoice_payments', '/create_partial_payment', array(
            'methods' => 'POST',
            'callback' => array($this, 'createPartialPayment'),
        ));
        register_rest_route('invoice_payments', '/cancel_partial_payment', array(
            'methods'  => 'POST',
            'callback' => array($this, 'cancelPartialPayment'),
        ));
    }
    
    public function createPartialPayment($request) {
        $parameters = $request->get_params();
    
        $order_id = isset($parameters['orderId']) ? $parameters['orderId'] : 0;
        $user_id = isset($parameters['userId']) ? intval($parameters['userId']) : 0;
        $partial_amount = isset($parameters['partialAmount']) ? floatval($parameters['partialAmount']) : 0.0;

        if($order_id == 'newOrder'){
            $cart = isset($parameters['cart']) ? $parameters['cart'] : null;

            $total = 0;
            if (isset($cart['cart_contents']) && is_array($cart['cart_contents'])) {
                foreach ($cart['cart_contents'] as $item) {
                    if (isset($item['line_total'])) {
                        $total += floatval($item['line_total']);
                    }
                }
            }

            $order = wc_create_order( array(
                'status' => 'pending',
                'customer_id' => $user_id
            ));

            $customer = new WC_Customer($user_id);
            $first_name = $customer->get_billing_first_name() ?: $customer->get_first_name();
            $order->set_billing_first_name($first_name);
            $order->set_total($total);
            $order->save();

            $order_id = $order->get_id();
        }
        
        if (!$order_id || !$partial_amount) {
            return new WP_REST_Response(['error' => 'Parâmetros inválidos.'], 400);
        }
        
        $order = wc_get_order($order_id);
        
        $order_total = floatval($order->get_total());
        $total_peding = floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0;
        $total_confirmed = floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0;

        // Verifica se o cliente pode gerar mais uma fatura com o valor informado
        $max_allowed = $order_total - $total_peding - $total_confirmed;

        if ($partial_amount > $max_allowed) {
            return new WP_REST_Response(['error' => 'Valor solicitado excede o valor disponível para pagamento.'], 404);
        }

        if (!$order) {
            return new WP_REST_Response(['error' => 'Pedido não encontrado.'], 404);
        }
    
        // Criar nova ordem parcial
        $partial_order = wc_create_order([
            'customer_id' => $order->get_customer_id(),
        ]);
        
        // Copia os endereços de cobrança e envio
        $partial_order->set_address($order->get_address('billing'), 'billing');
        $partial_order->set_address($order->get_address('shipping'), 'shipping');
        
        // Copia o e-mail do cliente, caso necessário
        $partial_order->set_billing_email($order->get_billing_email());
        
        // Copia outros metadados úteis, se necessário
        $meta_to_copy = [
            '_customer_ip_address',
            '_customer_user_agent',
            '_order_currency',
        ];
        foreach ($meta_to_copy as $meta_key) {
            $meta_value = $order->get_meta($meta_key);
            if ($meta_value) {
                $partial_order->update_meta_data($meta_key, $meta_value);
            }
        }
        
        $partial_order->update_meta_data('_wc_lkn_is_partial_order', 'yes');
        $order->update_meta_data('_wc_lkn_is_partial_main_order', 'yes');
        $partial_order_id = $partial_order->get_id();
        $partial_order->set_payment_method('multiplePayment');

        $order_link = admin_url("admin.php?page=edit-invoice&invoice={$order_id}");
        $partial_order->add_order_note("Pedido parcial criado a partir do pedido <a href=\"{$order_link}\">#{$order_id}</a>", false);
        
        $order_link = admin_url("admin.php?page=edit-invoice&invoice={$partial_order_id}");
        $order->add_order_note("Pedido parcial criado <a href=\"{$order_link}\">#{$partial_order_id}</a>", false);

        
        $invoiceList = get_option('lkn_wcip_invoices', array());
        
        if ( !in_array( $order_id, $invoiceList ) ) {
            $invoiceList[] = $order_id;
        }
        $invoiceList[] =  $partial_order_id;
        
        update_option('lkn_wcip_invoices', $invoiceList);
    
        // Adiciona item fictício (ajuste isso conforme necessário)
        $partial_order->add_product(wc_get_product(0), 1, [
            'subtotal' => $partial_amount,
            'total'    => $partial_amount,
            'name'     => 'Pagamento parcial',
        ]);

        $partial_order->calculate_totals();
        $order->update_meta_data('lkn_ini_date', gmdate('Y-m-d', time()));
        $partial_order->update_meta_data('lkn_ini_date', gmdate('Y-m-d', time()));

        $partial_order->update_status('wc-partial-pend');
        $order->update_status('wc-partial');

        $partialsList = $order->get_meta('_wc_lkn_partials_id', true);
        // Garante que é array
        if (!is_array($partialsList)) {
            $partialsList = [];
        }

        // Remove qualquer item que não seja ID (int)
        $partialsList = array_map('intval', $partialsList);

        // Adiciona o novo ID, se ainda não estiver na lista
        $partial_id = (int) $partial_order->get_id();
        if (!in_array($partial_id, $partialsList, true)) {
            $partialsList[] = $partial_id;
        }

        // Atualiza apenas com IDs
        $order->update_meta_data('_wc_lkn_partials_id', $partialsList);

        
        $total_peding += $partial_amount;
        
        $order->update_meta_data('_wc_lkn_total_peding', $total_peding);
        $partial_order->update_meta_data('_wc_lkn_parent_id', $order_id);

        $order->save();
        $partial_order->save();

        
        return new WP_REST_Response([
            'success'       => true,
            'partial_order' => $partial_order->get_id(),
            'payment_url'   => $partial_order->get_checkout_payment_url(),
        ], 200);
    }

    public function cancelPartialPayment($request) {
        $params = $request->get_params();
        $partial_order_id = isset($params['partialOrderId']) ? intval($params['partialOrderId']) : 0;
    
        if (!$partial_order_id) {
            return new WP_REST_Response(['error' => 'ID da ordem parcial é obrigatório.'], 400);
        }
    
        $partial_order = wc_get_order($partial_order_id);
    
        if (!$partial_order || $partial_order->get_meta('_wc_lkn_is_partial_order') !== 'yes') {
            return new WP_REST_Response(['error' => 'Ordem parcial não encontrada ou inválida.'], 404);
        }
    
        // Cancela o pedido parcial
        $partial_order->update_status('cancelled');
    
        // Obtém o pedido pai
        $parent_id = intval($partial_order->get_meta('_wc_lkn_parent_id'));
        if (!$parent_id) {
            return new WP_REST_Response(['error' => 'Pedido pai não encontrado.'], 404);
        }
    
        $parent_order = wc_get_order($parent_id);
        if (!$parent_order) {
            return new WP_REST_Response(['error' => 'Pedido pai inválido.'], 404);
        }
    
        // Subtrai o valor parcial do total pendente
        $partial_total = floatval($partial_order->get_total());
        $total_pending = floatval($parent_order->get_meta('_wc_lkn_total_peding')) ?: 0.0;
    
        $new_pending_total = max(0, $total_pending - $partial_total);
        $parent_order->update_meta_data('_wc_lkn_total_peding', $new_pending_total);
    
        // Remove o ID da ordem parcial da lista de parciais
        $partialsList = $parent_order->get_meta('_wc_lkn_partials_id', true);
        if (!is_array($partialsList)) {
            $partialsList = [];
        }
    
        $partialsList = array_map('intval', $partialsList);
        $partialsList = array_filter($partialsList, function ($id) use ($partial_order_id) {
            return $id !== $partial_order_id;
        });
    
        $parent_order->update_meta_data('_wc_lkn_partials_id', $partialsList);
    
        $parent_order->save();
        $partial_order->save();
    
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Pagamento parcial cancelado com sucesso.',
            'new_pending_total' => $new_pending_total,
        ], 200);
    }
    
}
