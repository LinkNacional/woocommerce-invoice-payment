<?php
namespace LknWc\WcInvoicePayment\Includes;

use WP_REST_Response;
use WC_Customer;

final class WcPaymentInvoiceEndpoint {
    public function registerEndpoints(): void {
        register_rest_route('invoice_payments', '/create_partial_payment', array(
            'methods' => 'POST',
            'callback' => array($this, 'createPartialPayment'),
            'permission_callback' => array($this, 'checkCreatePartialPaymentPermission'),
        ));
        register_rest_route('invoice_payments', '/cancel_partial_payment', array(
            'methods'  => 'POST',
            'callback' => array($this, 'cancelPartialPayment'),
            'permission_callback' => array($this, 'checkCancelPartialPaymentPermission'),
        ));
    }
    
    /**
     * Verifica permissão para criar pagamento parcial
     */
    public function checkCreatePartialPaymentPermission($request) {
        // Verificar nonce para proteção CSRF
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('invalid_nonce', 'Nonce inválido', array('status' => 403));
        }

        $parameters = $request->get_params();
        $order_id = isset($parameters['orderId']) ? $parameters['orderId'] : 0;
        $user_id = isset($parameters['userId']) ? intval($parameters['userId']) : 0;

        // Permitir criação de novo pedido
        if ($order_id === 'newOrder') {
            return true;
        }

        // Verificar se o pedido existe
        if (!$order_id) {
            return new \WP_Error('invalid_order', 'ID do pedido é obrigatório', array('status' => 400));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new \WP_Error('order_not_found', 'Pedido não encontrado', array('status' => 404));
        }

        $current_user_id = get_current_user_id();
        
        // Se usuário está logado, verificar se é o dono do pedido ou admin
        if ($current_user_id > 0) {
            if (current_user_can('manage_woocommerce') || $current_user_id == $order->get_customer_id()) {
                return true;
            }
            return new \WP_Error('insufficient_permission', 'Você não tem permissão para criar pagamentos parciais para este pedido', array('status' => 403));
        }

        // Para usuários não logados, validações mais flexíveis
        // Verificar se o user_id fornecido corresponde ao dono do pedido
        if ($user_id != $order->get_customer_id()) {
            return new \WP_Error('user_mismatch', 'Usuário não corresponde ao dono do pedido', array('status' => 403));
        }

        // Validação adicional: verificar se é um pedido recente (últimas 24 horas) ou há uma sessão válida
        if (!$this->isRecentOrderOrValidSession($order)) {
            return new \WP_Error('order_access_denied', 'Acesso negado: pedido muito antigo ou sessão inválida', array('status' => 403));
        }

        return true;
    }

    /**
     * Verifica permissão para cancelar pagamento parcial
     */
    public function checkCancelPartialPaymentPermission($request) {
        // Verificar nonce para proteção CSRF
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('invalid_nonce', 'Nonce inválido', array('status' => 403));
        }

        $params = $request->get_params();
        $partial_order_id = isset($params['partialOrderId']) ? intval($params['partialOrderId']) : 0;

        if (!$partial_order_id) {
            return new \WP_Error('invalid_order_id', 'ID da ordem parcial é obrigatório', array('status' => 400));
        }

        $partial_order = wc_get_order($partial_order_id);
        if (!$partial_order || $partial_order->get_meta('_wc_lkn_is_partial_order') !== 'yes') {
            return new \WP_Error('invalid_partial_order', 'Ordem parcial não encontrada ou inválida', array('status' => 404));
        }

        $current_user_id = get_current_user_id();
        
        // Se usuário está logado, verificar se é o dono do pedido ou admin
        if ($current_user_id > 0) {
            if (current_user_can('manage_woocommerce') || $current_user_id == $partial_order->get_customer_id()) {
                return true;
            }
            return new \WP_Error('insufficient_permission', 'Você não tem permissão para cancelar este pagamento parcial', array('status' => 403));
        }

        // Para usuários não logados, validação mais flexível
        // Verificar se é um pedido recente ou há sessão válida
        if (!$this->isRecentOrderOrValidSession($partial_order)) {
            return new \WP_Error('access_denied', 'Acesso negado para cancelar este pagamento', array('status' => 403));
        }

        return true;
    }

    /**
     * Valida se a sessão atual corresponde ao pedido
     */
    private function validateOrderSession($order_id, $user_id) {
        // Para usuários não logados, verificar se existe um cookie/sessão válida
        // que associe o usuário atual com o pedido
        
        // Verificar se existe uma sessão WC válida
        if (!WC()->session) {
            return false;
        }

        // Verificar se o customer_id da sessão corresponde
        $session_customer_id = WC()->session->get_customer_id();
        if ($session_customer_id && $session_customer_id == $user_id) {
            return true;
        }

        // Verificar se existe um cookie de ordem recente
        $recent_orders = WC()->session->get('wc_recent_orders', array());
        if (is_array($recent_orders) && in_array($order_id, $recent_orders)) {
            return true;
        }

        // Para casos onde o usuário acabou de criar o pedido, permitir por um tempo limitado
        // baseado em um cookie específico do plugin
        $order_access_token = isset($_COOKIE['wc_invoice_payment_order_' . $order_id]) ? $_COOKIE['wc_invoice_payment_order_' . $order_id] : '';
        if ($order_access_token) {
            $expected_token = wp_hash($order_id . $user_id . 'wc_invoice_payment');
            return hash_equals($expected_token, $order_access_token);
        }

        return false;
    }

    /**
     * Verifica se é um pedido recente ou há uma sessão válida
     */
    private function isRecentOrderOrValidSession($order) {
        // Verificar se o pedido é recente (últimas 24 horas)
        $order_date = $order->get_date_created();
        if ($order_date) {
            $now = new \DateTime();
            $order_datetime = $order_date;
            $diff = $now->diff($order_datetime);
            
            // Se foi criado nas últimas 24 horas, permitir
            if ($diff->days == 0) {
                return true;
            }
        }

        // Verificar se há uma sessão válida
        return $this->validateOrderSession($order->get_id(), $order->get_customer_id());
    }
    
    public function createPartialPayment($request) {
        $parameters = $request->get_params();
    
        $order_id = isset($parameters['orderId']) ? $parameters['orderId'] : 0;
        $user_id = isset($parameters['userId']) ? intval($parameters['userId']) : 0;
        $partial_amount = isset($parameters['partialAmount']) ? floatval($parameters['partialAmount']) : 0.0;

        if($order_id == 'newOrder'){
            $cart = isset($parameters['cart']) ? $parameters['cart'] : null;

            $order = wc_create_order([
                'status' => 'pending',
                'customer_id' => $user_id
            ]);

            $customer = new WC_Customer($user_id);
            $first_name = $customer->get_billing_first_name() ?: $customer->get_first_name();
            $order->set_billing_first_name($first_name);


            // Adicionar produtos ao pedido
            foreach ($cart['cart_contents'] as $item) {
                if (isset($item['product_id']) && isset($item['quantity'])) {
                    $product_id = $item['product_id'];
                    $quantity = $item['quantity'];
                    $variation_id = isset($item['variation_id']) ? $item['variation_id'] : 0;
                    $variation = isset($item['variation']) && is_array($item['variation']) ? $item['variation'] : [];

                    try {
                        $order->add_product(wc_get_product($variation_id > 0 ? $variation_id : $product_id), $quantity, [
                            'variation' => $variation,
                        ]);
                    } catch (Exception $e) {
                        return new WP_REST_Response(['error' => 'Erro ao adicionar produto: ' . $e->getMessage()], 400);
                    }
                }
            }

            $order->calculate_totals();
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

        // Definir cookie de acesso para usuários não logados
        $this->setOrderAccessCookie($order_id, $user_id);

        
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
    
    /**
     * Define um cookie de acesso para validar sessões de usuários não logados
     */
    private function setOrderAccessCookie($order_id, $user_id) {
        if (get_current_user_id() > 0) {
            return; // Não precisa de cookie para usuários logados
        }
        
        $token = wp_hash($order_id . $user_id . 'wc_invoice_payment');
        $cookie_name = 'wc_invoice_payment_order_' . $order_id;
        
        // Cookie válido por 24 horas
        $expire = time() + (24 * 60 * 60);
        
        setcookie($cookie_name, $token, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }
    
}
