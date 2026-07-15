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
        $order_id = isset($parameters['orderId']) ? intval($parameters['orderId']) : 0;
        $user_id  = isset($parameters['userId']) ? intval($parameters['userId']) : 0;

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
        $order_access_token = isset($_COOKIE['wc_invoice_payment_order_' . $order_id]) ? sanitize_text_field(wp_unslash($_COOKIE['wc_invoice_payment_order_' . $order_id])) : '';
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

    /**
     * Encontra uma partial order pendente já criada pra este pedido/valor.
     */
    private function findPendingPartialOrder($parent_order, $amount) {
        $partials = $parent_order->get_meta('_wc_lkn_partials_id', true);
        error_log(json_encode($partials));
        if (!is_array($partials)) return null;

        foreach ($partials as $pid) {
            $po = wc_get_order((int) $pid);
            if (!$po || $po->get_status() !== 'wc-partial-pend') continue;
            $po_amount = round((float) $po->get_total(), 2);
            error_log(abs($po_amount - $amount));
            if (abs($po_amount - $amount) < 0.02) return $po;
        }
        return null;
    }

    /**
     * Carrega itens do partial order no carrinho e retorna redirect pra checkout.
     */
    private function buildCheckoutRedirect($partial_order, $parent_order_id, $partial_amount) {
        if (!WC()->cart) wc_load_cart();
        WC()->cart->empty_cart();

        foreach ($partial_order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            if ($product_id) {
                WC()->cart->add_to_cart($product_id, $quantity, $variation_id);
            }
        }

        WC()->session->set('lkn_partial_amount', $partial_amount);
        WC()->session->set('lkn_partial_order_id', $partial_order->get_id());
        WC()->session->set('lkn_partial_parent_order_id', $parent_order_id);

        WC()->cart->calculate_totals();

        return new WP_REST_Response([
            'success'       => true,
            'partial_order' => $partial_order->get_id(),
            'payment_url'   => wc_get_checkout_url(),
        ], 200);
    }
    
    public function createPartialPayment($request) {
        $parameters = $request->get_params();
    
        $order_id = isset($parameters['orderId']) ? intval($parameters['orderId']) : 0;
        $user_id  = isset($parameters['userId']) ? intval($parameters['userId']) : 0;
        $partial_amount = round(isset($parameters['partialAmount']) ? floatval($parameters['partialAmount']) : 0.0, 2);

        if (!$order_id || !$partial_amount) {
            return new WP_REST_Response(['error' => 'Parâmetros inválidos.'], 400);
        }
        
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_REST_Response(['error' => 'Pedido não encontrado.'], 404);
        }
        
        $order_total = floatval($order->get_total());
        $total_peding = floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0;
        $total_confirmed = floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0;

        // Se a ordem tem _wc_lkn_original_total (split no checkout), usa ele.
        // Senao, usa o total da ordem (modelo antigo pai-filho).
        $original_total = floatval($order->get_meta('_wc_lkn_original_total'));
        if ($original_total <= 0) {
            $original_total = $order_total;
        }

        // Verifica se o cliente pode gerar mais uma fatura com o valor informado
        $max_allowed = round($original_total - $total_peding - $total_confirmed, 2);

        error_log("[createPartialPayment] order_id=$order_id original_total=$original_total total_peding=$total_peding total_confirmed=$total_confirmed max_allowed=$max_allowed partial_amount=$partial_amount");

        if ($partial_amount > $max_allowed) {
            // Pode já ter partial order criado de tentativa anterior (ex: crash no cart)
            $existing = $this->findPendingPartialOrder($order, $partial_amount);
            error_log(json_encode($existing));
            if ($existing) {
                error_log("[createPartialPayment] reusing existing partial order: {$existing->get_id()}");
                return $this->buildCheckoutRedirect($existing, $order_id, $partial_amount);
            }
            return new WP_REST_Response(['error' => 'Valor solicitado excede o valor disponível para pagamento.'], 400);
        }

        // Garante que cart está carregado ANTES de criar o partial order
        if (!WC()->cart) {
            wc_load_cart();
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

        $partial_order->set_customer_ip_address($order->get_customer_ip_address());
        $partial_order->set_customer_user_agent($order->get_customer_user_agent());
        $partial_order->set_currency($order->get_currency());
        
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

        // Copia os produtos do pedido principal para o filho
        foreach ($order->get_items() as $item) {
            $partial_order->add_item(clone $item);
        }

        // Copia frete
        foreach ($order->get_shipping_methods() as $shipping_item) {
            $partial_order->add_item(clone $shipping_item);
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
        $partial_order->update_meta_data('_wc_lkn_partial_remaining', $discount);
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

        // Recria o carrinho e redireciona pro checkout
        return $this->buildCheckoutRedirect($partial_order, $order_id, $partial_amount);
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
