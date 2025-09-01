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
            'quoteStatus' => $quoteStatus,
            'emailDescription' => __('We will use this email to send information and updates about your quote.', 'wc-invoice-payment'),
            'addressDescription' => __('Enter the address where you want your quote to be delivered.', 'wc-invoice-payment'),
            'reviewText' => __('Under Review', 'wc-invoice-payment'),
            'requestQuoteText' => __('Request Quote', 'wc-invoice-payment'),
            'quoteSummaryText' => __('Quote Summary', 'wc-invoice-payment'),
            'quotesText' => __('Quotes', 'wc-invoice-payment')
        ));
    }

    public function registerQuoteStatus( $order_statuses ) {
        $order_statuses['wc-quote-draft'] = array(
            'label' => __('Quote Draft', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-pending'] = array(
            'label' => __('Quote Pending', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-awaiting'] = array(
            'label' => __('Quote Awaiting Approval', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-approved'] = array(
            'label' => __('Quote Approved', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-cancelled'] = array(
            'label' => __('Quote Cancelled', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-quote-expired'] = array(
            'label' => __('Quote Expired', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        return $order_statuses;
    }

    public function createQuoteStatus($order_statuses){
        $order_statuses['wc-quote-draft'] = __('Quote Draft', 'wc-invoice-payment');
        $order_statuses['wc-quote-pending'] = __('Quote Pending', 'wc-invoice-payment');
        $order_statuses['wc-quote-awaiting'] = __('Quote Awaiting Approval', 'wc-invoice-payment');
        $order_statuses['wc-quote-approved'] = __('Quote Approved', 'wc-invoice-payment');
        $order_statuses['wc-quote-cancelled'] = __('Quote Cancelled', 'wc-invoice-payment');
        $order_statuses['wc-quote-expired'] = __('Quote Expired', 'wc-invoice-payment');
        return $order_statuses;
    }

    public function allowQuoteStatusCancel($statuses) {
        $statuses[] = 'quote-awaiting';
        $statuses[] = 'quote-approved';
        return $statuses;
    }

    /**
     * Intercepta páginas order-pay para exibir interface de aprovação de orçamento
     */
    public function interceptOrderPayPage(): void {
        global $wp;
        
        // Verificar se estamos na página order-pay
        if (!isset($_GET['pay_for_order']) || $_GET['pay_for_order'] !== 'true') {
            return;
        }
        
        // Tentar obter o ID do pedido
        $order_id = null;
        if (isset($_GET['key'])) {
            // Buscar pedido pela order_key
            $orders = wc_get_orders(array(
                'limit' => 1,
                'order_key' => wc_clean($_GET['key'])
            ));
            if (!empty($orders)) {
                $order_id = $orders[0]->get_id();
                $order = $orders[0];
                
                // Verificar se é um orçamento que precisa de aprovação
                if ($order->get_meta('lkn_is_quote') === 'yes' && 
                    in_array($order->get_status(), ['quote-awaiting'])) {
                    
                    error_log("interceptOrderPayPage - Redirecionando para aprovação: Order ID $order_id");
                    
                    // Limpar output buffer e renderizar nossa página
                    if (ob_get_level()) {
                        ob_end_clean();
                    }
                    
                    // Carregar cabeçalho do WordPress
                    get_header();
                    
                    // Renderizar nossa interface
                    $this->renderQuoteApprovalPageFull($order);
                    
                    // Carregar rodapé do WordPress  
                    get_footer();
                    
                    exit;
                }
            }
        }
    }

    public function showQuoteFields($orderId): void {
        $quoteOrder = wc_get_order( $orderId );
        
        // Verificar se é um orçamento
        if($quoteOrder->get_meta('lkn_is_quote') != 'yes'){
            return;
        }
        
        // Verificar se estamos na página order-pay e o orçamento precisa de aprovação
        $is_order_pay_page = isset($_GET['pay_for_order']) && $_GET['pay_for_order'] === 'true';
        $needs_approval = in_array($quoteOrder->get_status(), ['quote-awaiting', 'quote-pending']);
        
        // Debug - adicionar log para verificar condições
        error_log("showQuoteFields Debug - Order ID: $orderId, Status: " . $quoteOrder->get_status() . ", is_order_pay_page: " . ($is_order_pay_page ? 'true' : 'false') . ", needs_approval: " . ($needs_approval ? 'true' : 'false'));
        
        if ($is_order_pay_page && $needs_approval) {
            // Substituir todo o conteúdo da página com a interface de aprovação
            echo '<style>
                .woocommerce-checkout, 
                .woocommerce-order-pay,
                .woocommerce-info,
                .woocommerce-error,
                .woocommerce-message {
                    display: none !important;
                }
            </style>';
            
            error_log("showQuoteFields - Renderizando página de aprovação para ordem $orderId");
            $this->renderQuoteApprovalPage($quoteOrder);
            return;
        }
        
        // Código existente para outras páginas
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
            'confirmApprove' => __('Are you sure you want to approve this quote?', 'wc-invoice-payment'),
            'confirmCancel' => __('Are you sure you want to cancel this quote?', 'wc-invoice-payment'),
            'approveText' => __('Approve', 'wc-invoice-payment'),
            'cancelText' => __('Cancel', 'wc-invoice-payment'),
            'quoteDetailsText' => __('Quote Details', 'wc-invoice-payment'),
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
            wp_die(__('Invalid security action.', 'wc-invoice-payment'));
        }

        // Verificar se o quote_id foi fornecido
        if (!isset($_GET['quote_id']) || empty($_GET['quote_id'])) {
            wp_die(__('Quote ID not provided.', 'wc-invoice-payment'));
        }

        $quote_id = intval($_GET['quote_id']);
        $quote_order = wc_get_order($quote_id);

        // Verificar se o pedido existe
        if (!$quote_order) {
            wp_die(__('Quote not found.', 'wc-invoice-payment'));
        }

        // Verificar se é realmente um orçamento
        if ($quote_order->get_meta('lkn_is_quote') !== 'yes') {
            wp_die(__('This order is not a quote.', 'wc-invoice-payment'));
        }

        // Verificar se o status permite aprovação
        $current_status = $quote_order->get_status();
        if (!in_array($current_status, ['quote-awaiting', 'quote-pending'])) {
            wp_die(__('This quote cannot be approved in its current status.', 'wc-invoice-payment'));
        }

        // Aprovar o orçamento
        $quote_order->update_status('quote-approved', __('Quote approved by customer.', 'wc-invoice-payment'));

        // Obter informações do cliente para a nota
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        
        // Criar nota detalhada com email e IP
        $note = __('Quote approved by customer ', 'wc-invoice-payment') . $user_email;
        if (!empty($user_ip)) {
            $note .= ' (IP: ' . $user_ip . ')';
        }
        $note .= __(' on date: ', 'wc-invoice-payment') . current_time('d/m/Y H:i:s');
        
        // Adicionar nota ao pedido
        $quote_order->add_order_note($note);

        
        //lkn_wcip_create_invoice_automatically
        if(get_option('lkn_wcip_create_invoice_automatically', 'yes') == 'yes') {
            // Criar fatura automaticamente
            $this->create_invoice($quote_order);
        }
        
        // Redirecionar de volta para a página anterior ou página de pedidos como fallback
        $redirect_url = wp_get_referer();
        if (!$redirect_url || strpos($redirect_url, 'lkn_wcip_approve_quote') !== false) {
            $redirect_url = $quote_order->get_checkout_order_received_url();
        }

        // Adicionar parâmetro displayQuoteNotice à URL de redirecionamento
        $redirect_url = add_query_arg('displayQuoteNotice', 'true', $redirect_url);
        
        wp_redirect($redirect_url);
        exit;
    }

    public function create_invoice($quote_order) {
        $quote_expiration_days = get_option('lkn_wcip_quote_expiration', 10);
        $iniDate = new \DateTime();
        $iniDateFormatted = $iniDate->format('Y-m-d');
        $expiration_date = gmdate("Y-m-d", strtotime($iniDateFormatted . ' +' . $quote_expiration_days . ' days'));
        
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
        $invoice->update_meta_data('lkn_ini_date', $iniDateFormatted);

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
        wc_add_notice(__('Quote approved successfully!', 'wc-invoice-payment'), 'success');

        
        $invoiceList = get_option('lkn_wcip_invoices', array());
        
        if ( !in_array( $invoice->get_id(), $invoiceList ) ) {
            $invoiceList[] = $invoice->get_id();
        }
        
        update_option('lkn_wcip_invoices', $invoiceList);
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
                $new_items['quotes'] = __('Quotes', 'wc-invoice-payment');
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
            WC_PAYMENT_INVOICE_ROOT_DIR . 'Includes/templates/'
        );
    }

    /**
     * Change page title for quotes endpoint
     */
    public function changeQuotesPageTitle($title) {
        // Verifica se estamos na página de orçamentos
        if (isset($_GET['quotes']) || strpos($_SERVER['REQUEST_URI'], '/quotes') !== false) {
            return __('Quotes', 'wc-invoice-payment');
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

    /**
     * Carrega script para atualizar título na página de confirmação de orçamento
     */
    public function enqueueQuoteConfirmationScript(): void {
        global $wp;
        
        // Verificar se estamos na página order-received
        $is_order_received = isset($wp->query_vars['order-received']) && !empty($wp->query_vars['order-received']);
        $is_order_received_url = false;
        
        // Verificar URL atual para capturar pages order-received
        if (isset($_SERVER['REQUEST_URI'])) {
            $current_url = $_SERVER['REQUEST_URI'];
            $is_order_received_url = preg_match('/order-received\/\d+/', $current_url);
        }
        
        if ($is_order_received || $is_order_received_url) {
            $orderId = $this->getOrderIdFromContext();
            
            if ($orderId && function_exists('wc_get_order')) {
                $order = \wc_get_order($orderId);
                
                if ($order && is_object($order) && $order->get_meta('lkn_is_quote') === 'yes') {
                    wp_enqueue_script( 'wcInvoiceQuoteConfirmation', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-quote-confirmation.js', array( 'jquery' ), WC_PAYMENT_INVOICE_VERSION, true );
                    wp_localize_script( 'wcInvoiceQuoteConfirmation', 'wcInvoiceQuoteConfirmation', array(
                        'orderId' => $orderId,
                        'quoteStatus' => $order->get_status(),
                        'isQuote' => 'yes',
                        'draftTitle' => __('Quote Draft', 'wc-invoice-payment'),
                        'draftMessage' => __('Your quote is being prepared.', 'wc-invoice-payment'),
                        'pendingTitle' => __('Quote Received', 'wc-invoice-payment'),
                        'pendingMessage' => __('Your quote has been received and is being analyzed.', 'wc-invoice-payment'),
                        'awaitingTitle' => __('Quote Awaiting Approval', 'wc-invoice-payment'),
                        'awaitingMessage' => __('Your quote is ready and awaits your approval.', 'wc-invoice-payment'),
                        'approvedTitle' => __('Quote Approved', 'wc-invoice-payment'),
                        'approvedMessage' => __('Your quote has been successfully approved.', 'wc-invoice-payment'),
                        'cancelledTitle' => __('Quote Cancelled', 'wc-invoice-payment'),
                        'cancelledMessage' => __('Your quote has been cancelled.', 'wc-invoice-payment'),
                        'expiredTitle' => __('Quote Expired', 'wc-invoice-payment'),
                        'expiredMessage' => __('Your quote has expired.', 'wc-invoice-payment')
                    ));
                }
            }
        }
    }

    /**
     * Renderiza a página completa de aprovação do orçamento
     */
    public function renderQuoteApprovalPageFull($order) {
        // Incluir CSS e JS
        wp_enqueue_style('wc-invoice-quote-approval', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-quote-approval.css', array(), WC_PAYMENT_INVOICE_VERSION);
        wp_enqueue_script('wc-invoice-quote-approval', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-quote-approval.js', array('jquery'), WC_PAYMENT_INVOICE_VERSION, true);
        
        // Localizar dados para JavaScript
        wp_localize_script('wc-invoice-quote-approval', 'wcInvoiceQuoteApproval', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lkn_wcip_quote_action'),
            'quoteId' => $order->get_id(),
            'texts' => array(
                'confirmApprove' => __('Tem certeza que deseja aprovar este orçamento?', 'wc-invoice-payment'),
                'confirmCancel' => __('Tem certeza que deseja cancelar este orçamento?', 'wc-invoice-payment'),
                'processing' => __('Processando...', 'wc-invoice-payment')
            )
        ));
        
        // Carregar CSS/JS
        wp_head();
        
        $order_id = $order->get_id();
        ?>
        <div class="woocommerce">
            <div class="wc-invoice-quote-approval-container">
                <h2><?php _e('Detalhes do Orçamento', 'wc-invoice-payment'); ?></h2>
                
                <div class="quote-details-summary">
                    <p><strong><?php _e('Orçamento #', 'wc-invoice-payment'); ?><?php echo $order->get_order_number(); ?></strong></p>
                    <p><?php printf(__('Criado em %s', 'wc-invoice-payment'), $order->get_date_created()->date_i18n(get_option('date_format'))); ?></p>
                    <p><?php printf(__('Status: %s', 'wc-invoice-payment'), '<span class="quote-status">' . wc_get_order_status_name($order->get_status()) . '</span>'); ?></p>
                    <?php 
                    $exp_date = $order->get_meta('lkn_exp_date');
                    if ($exp_date) {
                        $exp_date_formatted = date_i18n(get_option('date_format'), strtotime($exp_date));
                        printf(__('Válido até %s', 'wc-invoice-payment'), $exp_date_formatted);
                    }
                    ?>
                </div>

                <table class="shop_table shop_table_responsive quote-details-table">
                    <thead>
                        <tr>
                            <th class="product-name"><?php _e('Produto', 'wc-invoice-payment'); ?></th>
                            <th class="product-total"><?php _e('Total', 'wc-invoice-payment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($order->get_items() as $item_id => $item) {
                            ?>
                            <tr class="quote-item">
                                <td class="product-name">
                                    <?php echo esc_html($item->get_name()); ?>
                                    <?php if ($item->get_quantity() > 1): ?>
                                        <strong class="product-quantity"> × <?php echo $item->get_quantity(); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td class="product-total">
                                    <?php echo $order->get_formatted_line_subtotal($item); ?>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        
                        <?php if ($order->get_shipping_total() > 0): ?>
                        <tr class="shipping-row">
                            <td class="product-name"><?php _e('Entrega:', 'wc-invoice-payment'); ?></td>
                            <td class="product-total"><?php echo wc_price($order->get_shipping_total()); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($order->get_total_tax() > 0): ?>
                        <tr class="tax-row">
                            <td class="product-name"><?php _e('Impostos:', 'wc-invoice-payment'); ?></td>
                            <td class="product-total"><?php echo wc_price($order->get_total_tax()); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr class="order-total">
                            <td class="product-name"><strong><?php _e('Total:', 'wc-invoice-payment'); ?></strong></td>
                            <td class="product-total"><strong><?php echo $order->get_formatted_order_total(); ?></strong></td>
                        </tr>
                    </tbody>
                </table>

                <div class="quote-actions">
                    <h3><?php _e('Ações:', 'wc-invoice-payment'); ?></h3>
                    <div class="quote-action-buttons">
                        <button type="button" id="approve-quote-btn" class="button button-primary approve-quote" data-quote-id="<?php echo $order_id; ?>">
                            <?php _e('Aprovar', 'wc-invoice-payment'); ?>
                        </button>
                        <button type="button" id="cancel-quote-btn" class="button button-secondary cancel-quote" data-quote-id="<?php echo $order_id; ?>">
                            <?php _e('Cancelar', 'wc-invoice-payment'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        // Carregar CSS/JS no rodapé
        wp_footer();
    }

    /**
     * Renderiza a página de aprovação do orçamento
     */
    public function renderQuoteApprovalPage($order) {
        // Incluir CSS e JS
        wp_enqueue_style('wc-invoice-quote-approval', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-quote-approval.css', array(), WC_PAYMENT_INVOICE_VERSION);
        wp_enqueue_script('wc-invoice-quote-approval', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-quote-approval.js', array('jquery'), WC_PAYMENT_INVOICE_VERSION, true);
        
        // Localizar dados para JavaScript
        wp_localize_script('wc-invoice-quote-approval', 'wcInvoiceQuoteApproval', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lkn_wcip_quote_action'),
            'quoteId' => $order->get_id(),
            'texts' => array(
                'confirmApprove' => __('Tem certeza que deseja aprovar este orçamento?', 'wc-invoice-payment'),
                'confirmCancel' => __('Tem certeza que deseja cancelar este orçamento?', 'wc-invoice-payment'),
                'processing' => __('Processando...', 'wc-invoice-payment')
            )
        ));
        
        $order_id = $order->get_id();
        ?>
        <div class="woocommerce">
            <div class="wc-invoice-quote-approval-container">
                <h2><?php _e('Detalhes do Orçamento', 'wc-invoice-payment'); ?></h2>
                
                <div class="quote-details-summary">
                    <p><strong><?php _e('Orçamento #', 'wc-invoice-payment'); ?><?php echo $order->get_order_number(); ?></strong></p>
                    <p><?php printf(__('Criado em %s', 'wc-invoice-payment'), $order->get_date_created()->date_i18n(get_option('date_format'))); ?></p>
                    <p><?php printf(__('Status: %s', 'wc-invoice-payment'), '<span class="quote-status">' . wc_get_order_status_name($order->get_status()) . '</span>'); ?></p>
                    <?php 
                    $exp_date = $order->get_meta('lkn_exp_date');
                    if ($exp_date) {
                        $exp_date_formatted = date_i18n(get_option('date_format'), strtotime($exp_date));
                        printf(__('Válido até %s', 'wc-invoice-payment'), $exp_date_formatted);
                    }
                    ?>
                </div>

                <table class="shop_table shop_table_responsive quote-details-table">
                    <thead>
                        <tr>
                            <th class="product-name"><?php _e('Produto', 'wc-invoice-payment'); ?></th>
                            <th class="product-total"><?php _e('Total', 'wc-invoice-payment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($order->get_items() as $item_id => $item) {
                            ?>
                            <tr class="quote-item">
                                <td class="product-name">
                                    <?php echo esc_html($item->get_name()); ?>
                                    <?php if ($item->get_quantity() > 1): ?>
                                        <strong class="product-quantity"> × <?php echo $item->get_quantity(); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td class="product-total">
                                    <?php echo $order->get_formatted_line_subtotal($item); ?>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        
                        <?php if ($order->get_shipping_total() > 0): ?>
                        <tr class="shipping-row">
                            <td class="product-name"><?php _e('Entrega:', 'wc-invoice-payment'); ?></td>
                            <td class="product-total"><?php echo wc_price($order->get_shipping_total()); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($order->get_total_tax() > 0): ?>
                        <tr class="tax-row">
                            <td class="product-name"><?php _e('Impostos:', 'wc-invoice-payment'); ?></td>
                            <td class="product-total"><?php echo wc_price($order->get_total_tax()); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr class="order-total">
                            <td class="product-name"><strong><?php _e('Total:', 'wc-invoice-payment'); ?></strong></td>
                            <td class="product-total"><strong><?php echo $order->get_formatted_order_total(); ?></strong></td>
                        </tr>
                    </tbody>
                </table>

                <div class="quote-actions">
                    <h3><?php _e('Ações:', 'wc-invoice-payment'); ?></h3>
                    <div class="quote-action-buttons">
                        <button type="button" id="approve-quote-btn" class="button button-primary approve-quote" data-quote-id="<?php echo $order_id; ?>">
                            <?php _e('Aprovar', 'wc-invoice-payment'); ?>
                        </button>
                        <button type="button" id="cancel-quote-btn" class="button button-secondary cancel-quote" data-quote-id="<?php echo $order_id; ?>">
                            <?php _e('Cancelar', 'wc-invoice-payment'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
