<?php

namespace LknWc\WcInvoicePayment\Includes;
use WC_Shortcode_My_Account;
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
        
        // Tenta capturar o order_id de diferentes formas
        $orderId = $this->getOrderIdFromContext();
        $quoteStatus = null;
        
        if($orderId && function_exists('wc_get_order')){
            $quoteOrder = \wc_get_order( $orderId );
            if($quoteOrder && is_object($quoteOrder)){
                //status do orçamento
                $quoteStatus = $quoteOrder->get_status();
            }
        }
        //if(!wcInvoiceHidePrice.quoteStatus || wcInvoiceHidePrice.quoteStatus == 'quoteStatus'){
        if($showPrice == 'no' && ($quoteStatus == null || $quoteStatus == 'quote-pending')){
            wp_enqueue_style('wcInvoiceHidePrice', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-hide-price.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        }
        wp_enqueue_script( 'wcInvoiceHidePrice', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-quote.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
        wp_localize_script( 'wcInvoiceHidePrice', 'wcInvoiceHidePrice', array(
            'quoteMode' => $quoteMode,
            'showPrice' => $showPrice,
            'showCupon' => get_option( 'lkn_wcip_display_coupon', 'no' ),
            'cart' => WC()->cart,
            'wc' => WC(),
            'userId' => get_current_user_id(),
            'orderId' => $orderId,
            'quoteStatus' => $quoteStatus
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

    public function allowQuoteStatusCancel($statuses) {
        $statuses[] = 'quote-awaiting';
        $statuses[] = 'quote-approved';
        return $statuses;
    }

    public function showQuoteFields($orderId): void {
        $quoteOrder = wc_get_order( $orderId );
        if($quoteOrder->get_meta('lkn_is_quote') == 'yes'){
            $invoiceOrder = wc_get_order( $quoteOrder->get_meta('_wc_lkn_invoice_id') );
            
            $wcInvoicePaymentQuoteTableVariables = array(
                'quoteOrderId' => $quoteOrder->get_id(),
                'quoteStatus' => $quoteOrder->get_status(),
                'approvalQuoteUrl' => wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'lkn_wcip_approve_quote',
                            'quote_id' => $quoteOrder->get_id(),
                        ),
                        wc_get_account_endpoint_url( 'orders' )
                    ),
                    'lkn_wcip_approve_quote'
                ),
                'cancelUrl' => $quoteOrder->get_cancel_order_url(wc_get_page_permalink('myaccount')),
            );

            if($invoiceOrder){
                $wcInvoicePaymentQuoteTableVariables['paymentPaymentUrl'] = $invoiceOrder->get_checkout_payment_url();
                $wcInvoicePaymentQuoteTableVariables['invoiceOrder'] = $invoiceOrder;
            }
            //noticia para o cliente que foi aprovado o orçamento
            wp_enqueue_script( 'wcInvoicePaymentQuoteScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-quote-table.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
            wp_enqueue_style('wcInvoicePaymentQuoteStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-quote-table.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
            wp_localize_script('wcInvoicePaymentQuoteScript', 'wcInvoicePaymentQuoteTableVariables', $wcInvoicePaymentQuoteTableVariables);
        }
    }

    /**
     * Handle quote approval action
     */
    public function handleQuoteApproval(): void {
        // Verificar se é a ação correta
        if (!isset($_GET['action']) || $_GET['action'] !== 'lkn_wcip_approve_quote') {
            return;
        }

        // Verificar nonce de segurança
        if (!wp_verify_nonce($_GET['_wpnonce'], 'lkn_wcip_approve_quote')) {
            wp_die(__('Ação de segurança inválida.', 'wc-invoice-payment'));
        }

        // Verificar se o quote_id foi fornecido
        if (!isset($_GET['quote_id']) || empty($_GET['quote_id'])) {
            wp_die(__('ID do orçamento não fornecido.', 'wc-invoice-payment'));
        }

        $quote_id = intval($_GET['quote_id']);
        $quote_order = wc_get_order($quote_id);

        // Verificar se o pedido existe
        if (!$quote_order) {
            wp_die(__('Orçamento não encontrado.', 'wc-invoice-payment'));
        }

        // Verificar se é realmente um orçamento
        if ($quote_order->get_meta('lkn_is_quote') !== 'yes') {
            wp_die(__('Este pedido não é um orçamento.', 'wc-invoice-payment'));
        }

        // Verificar se o status permite aprovação
        $current_status = $quote_order->get_status();
        if (!in_array($current_status, ['quote-awaiting', 'quote-pending'])) {
            wp_die(__('Este orçamento não pode ser aprovado no status atual.', 'wc-invoice-payment'));
        }

        // Configurar data de vencimento do orçamento
        $quote_expiration_days = get_option('lkn_wcip_quote_expiration', 10);
        $iniDate = new \DateTime();
        $iniDateFormatted = $iniDate->format('Y-m-d');
        $expiration_date = gmdate("Y-m-d", strtotime($iniDateFormatted . ' +' . $quote_expiration_days . ' days'));
        
        // Adicionar meta data de data de vencimento ao orçamento
        $quote_order->add_meta_data('lkn_exp_date', $expiration_date);

        // Aprovar o orçamento
        $quote_order->update_status('quote-approved', __('Orçamento aprovado pelo cliente.', 'wc-invoice-payment'));

        // Obter informações do cliente para a nota
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        
        // Criar nota detalhada com email e IP
        $note = __('Orçamento aprovado pelo cliente ', 'wc-invoice-payment') . $user_email;
        if (!empty($user_ip)) {
            $note .= ' (IP: ' . $user_ip . ')';
        }
        $note .= __(' na data: ', 'wc-invoice-payment') . current_time('d/m/Y H:i:s');
        
        // Adicionar nota ao pedido
        $quote_order->add_order_note($note);

        // Criar ordem clonada do orçamento
        $invoice = wc_create_order();
        
        // Copiar dados básicos do orçamento para a ordem clonada
        $invoice->set_customer_id($quote_order->get_customer_id());
        
        // Copiar endereço de cobrança
        $invoice->set_billing_first_name($quote_order->get_billing_first_name());
        $invoice->set_billing_last_name($quote_order->get_billing_last_name());
        $invoice->set_billing_company($quote_order->get_billing_company());
        $invoice->set_billing_address_1($quote_order->get_billing_address_1());
        $invoice->set_billing_address_2($quote_order->get_billing_address_2());
        $invoice->set_billing_city($quote_order->get_billing_city());
        $invoice->set_billing_state($quote_order->get_billing_state());
        $invoice->set_billing_postcode($quote_order->get_billing_postcode());
        $invoice->set_billing_country($quote_order->get_billing_country());
        $invoice->set_billing_email($quote_order->get_billing_email());
        $invoice->set_billing_phone($quote_order->get_billing_phone());
        
        // Copiar endereço de entrega
        $invoice->set_shipping_first_name($quote_order->get_shipping_first_name());
        $invoice->set_shipping_last_name($quote_order->get_shipping_last_name());
        $invoice->set_shipping_company($quote_order->get_shipping_company());
        $invoice->set_shipping_address_1($quote_order->get_shipping_address_1());
        $invoice->set_shipping_address_2($quote_order->get_shipping_address_2());
        $invoice->set_shipping_city($quote_order->get_shipping_city());
        $invoice->set_shipping_state($quote_order->get_shipping_state());
        $invoice->set_shipping_postcode($quote_order->get_shipping_postcode());
        $invoice->set_shipping_country($quote_order->get_shipping_country());
        
        $invoice->set_currency($quote_order->get_currency());
        
        // Copiar itens do orçamento para a ordem clonada
        foreach ($quote_order->get_items() as $item) {
            $invoice->add_product(
                wc_get_product($item->get_product_id()),
                $item->get_quantity(),
                array(
                    'variation' => $item->get_variation_id() ? wc_get_product($item->get_variation_id()) : null,
                    'totals' => array(
                        'subtotal' => $item->get_subtotal(),
                        'total' => $item->get_total()
                    )
                )
            );
        }
        
        // Copiar métodos de entrega e seus custos
        foreach ($quote_order->get_items('shipping') as $shipping_item) {
            $invoice->add_item($shipping_item);
        }
        
        // Copiar taxas e descontos
        foreach ($quote_order->get_fees() as $fee) {
            $invoice->add_fee($fee);
        }
        
        // Copiar impostos
        foreach ($quote_order->get_items('tax') as $tax_item) {
            $invoice->add_item($tax_item);
        }
        
        // Copiar cupons
        foreach ($quote_order->get_coupon_codes() as $coupon_code) {
            $invoice->apply_coupon($coupon_code);
        }
        
        // Adicionar meta data de data de vencimento à ordem clonada
        $invoice->add_meta_data('lkn_exp_date', $expiration_date);
        $invoice->add_meta_data('lkn_quote_id', $quote_order->get_id());

        // Calcular totais da ordem clonada
        $invoice->calculate_totals();
        
        // Salvar a ordem clonada
        $invoice->save();
        
        // Adicionar referência da ordem clonada ao orçamento
        $quote_order->add_meta_data('_wc_lkn_invoice_id', $invoice->get_id());

        // Salvar as alterações do orçamento
        $quote_order->save();

        // Adicionar mensagem de sucesso
        wc_add_notice(__('Orçamento aprovado com sucesso!', 'wc-invoice-payment'), 'success');

        // Redirecionar de volta para a página anterior ou página de pedidos como fallback
        $redirect_url = wp_get_referer();
        if (!$redirect_url || strpos($redirect_url, 'lkn_wcip_approve_quote') !== false) {
            $redirect_url = wc_get_account_endpoint_url('orders');
        }

        $invoiceList = get_option('lkn_wcip_invoices', array());
        
        if ( !in_array( $invoice->get_id(), $invoiceList ) ) {
            $invoiceList[] = $invoice->get_id();
        }
        
        update_option('lkn_wcip_invoices', $invoiceList);
        
        // Adicionar parâmetro displayQuoteNotice à URL de redirecionamento
        $redirect_url = add_query_arg('displayQuoteNotice', 'true', $redirect_url);
        
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Exclude quotes from WooCommerce My Orders query
     * 
     * @param array $args Query arguments
     * @return array Modified query arguments
     */
    public function excludeQuotesFromOrdersQuery($args) {
        // Adiciona meta_query para excluir pedidos que são orçamentos
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = array();
        }
        
        $args['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => 'lkn_is_quote',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key'     => 'lkn_is_quote',
                'value'   => 'yes',
                'compare' => '!='
            )
        );
        
        return $args;
    }

    /**
     * Add quotes endpoint to WooCommerce
     */
    public function addQuotesEndpoint() {
        add_rewrite_endpoint('quotes', EP_ROOT | EP_PAGES);
    }

    /**
     * Force flush rewrite rules (call once to register endpoint)
     */
    public function forceFlushRewriteRules() {
        add_rewrite_endpoint('quotes', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }

    /**
     * Add quotes query var
     */
    public function addQuotesQueryVars($vars) {
        $vars[] = 'quotes';
        return $vars;
    }

    /**
     * Add quotes menu item to My Account
     */
    public function addQuotesMenuItem($items) {
        // Adiciona o item "Orçamentos" após "Pedidos"
        $new_items = array();
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'orders') {
                $new_items['quotes'] = __('Orçamentos', 'wc-invoice-payment');
            }
        }
        return $new_items;
    }

    /**
     * Show quotes endpoint content
     */
    public function showQuotesEndpointContent() {
        $current_page = empty(get_query_var('quotes')) ? 1 : absint(get_query_var('quotes'));

        // Query para buscar apenas orçamentos do cliente
        $customer_quotes = wc_get_orders(array(
            'customer' => get_current_user_id(),
            'page'     => $current_page,
            'paginate' => true,
            'meta_key' => 'lkn_is_quote',
            'meta_value' => 'yes',
            'meta_compare' => '='
        ));

        wc_get_template(
            'myaccount/quotes.php',
            array(
                'current_page'    => absint($current_page),
                'customer_quotes' => $customer_quotes,
                'has_quotes'      => 0 < $customer_quotes->total,
            ),
            '',
            WC_PAYMENT_INVOICE_ROOT_DIR . 'templates/'
        );
    }

    /**
     * Change page title for quotes endpoint
     */
    public function changeQuotesPageTitle($title) {
        // Verifica se estamos na página de orçamentos
        if (isset($_GET['quotes']) || strpos($_SERVER['REQUEST_URI'], '/quotes') !== false) {
            return 'Orçamentos';
        }
        
        return $title;
    }

    /**
     * Tenta capturar o order_id de diferentes contextos
     * @return int|null
     */
    private function getOrderIdFromContext() {
        global $wp;
        
        // Caso 1: order_id via GET parameter
        if (isset($_GET['order_id'])) {
            return intval($_GET['order_id']);
        }
        
        // Caso 2: Verifica query vars do WordPress
        if (isset($wp->query_vars['order-received']) && !empty($wp->query_vars['order-received'])) {
            return intval($wp->query_vars['order-received']);
        }
        
        if (isset($wp->query_vars['view-order']) && !empty($wp->query_vars['view-order'])) {
            return intval($wp->query_vars['view-order']);
        }
        
        // Caso 3: Verifica URL atual diretamente para capturar IDs
        $current_url = $_SERVER['REQUEST_URI'];
        
        // Para URLs do tipo /finalizar-compra/order-received/284/
        if (preg_match('/order-received\/(\d+)/', $current_url, $matches)) {
            return intval($matches[1]);
        }
        
        // Para URLs do tipo /minha-conta/view-order/284/
        if (preg_match('/view-order\/(\d+)/', $current_url, $matches)) {
            return intval($matches[1]);
        }
        
        // Caso 4: Verifica se há um order ID nos parâmetros da URL
        if (preg_match('/[\/=](\d+)[\/\?\#]?/', $current_url, $matches)) {
            // Valida se é um ID válido verificando se é um pedido existente
            $potential_order_id = intval($matches[1]);
            if ($potential_order_id > 0 && function_exists('wc_get_order')) {
                $order = \wc_get_order($potential_order_id);
                if ($order && is_object($order)) {
                    return $potential_order_id;
                }
            }
        }
        
        return null;
    }
}
