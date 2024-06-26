<?php

class Wc_Payment_Invoice_Subscription{

    function cancel_subscription_callback() {

        if(wp_verify_nonce( $_POST['wcip_rest_nonce'], 'wp_rest' )){

            // Obter o ID da fatura do pedido
            $invoice_id = sanitize_text_field($_POST['invoice_id']);
            $scheduled_events = _get_cron_array();
            // verifica todos os eventos agendados
            foreach ($scheduled_events as $timestamp => $cron_events) {
                foreach ($cron_events as $hook => $events) {
                    foreach ($events as $event) {
                        // Verifique se o evento está associado ao seu gancho (hook)
                        if ($hook === 'generate_invoice_event') {
                            // Verifique se os argumentos do evento contêm o ID da ordem que você deseja remover
                            $event_args = $event['args'];
                            if (is_array($event_args) && in_array($invoice_id, $event_args)) {
                                // Remova o evento do WP Cron
                                wp_unschedule_event($timestamp, $hook, $event_args);
                            }
                        }
                    }
                }
            }            
            wp_die();
        }        
    }


    // Adiciona o campo checkbox nas configurações gerais do WooCommerce
    function custom_wc_general_settings_checkbox($settings) {
        // Encontra a posição do botão de envio (submit)
        $submit_position = count($settings) - 1;
    
        // Adiciona o novo campo antes do botão de envio
        array_splice($settings, $submit_position, 0, array(
            array(
                'title'    => __('Activate invoices', 'woocommerce'),
                'type'     => 'checkbox',
                'id'       => 'active_product_invoices',
                'desc_tip' => __('Create an invoice whenever a product is purchased.', 'woocommerce'),
                'default'  => 'no',
                'desc'     => __('Create invoices for products', 'woocommerce'),
            )
        ));
    
        return $settings;
    }
    
    // Salva o valor do campo checkbox
    function save_custom_wc_general_settings_checkbox() {
        if(wp_verify_nonce($_POST['_wpnonce'])){            
            woocommerce_update_options(
                array(
                    'active_product_invoices' => isset($_POST['active_product_invoices']) ? 'yes' : 'no', 
                )
            );
        }
    }

    public function add_checkbox( $products_type ) {
        global $post;
        //Criando uma nova checkbox no formulário de criação de produtos
        $subscription_product = get_post_meta( $post->ID, '_lkn-wcip-subscription-product', true );
        $products_type['subscriptionCheckbox'] = array(
            'id'            => '_lkn-wcip-subscription-product',
			'wrapper_class' => 'show_if_simple',
			'label'         => __( 'Subscription', 'wc-invoice-payment' ),
			'description'   => __( 'This is a subscription product.', 'wc-invoice-payment' ),
			'default'       => $subscription_product ? 'yes' : 'no',
		);
		return $products_type;
	}

    public function add_tab( $tabs ) {
        //Criando uma nova guia no formulário de criação de produtos
		$tabs['subscriptionTab'] = array(
			'label'    => __( 'Subscription', 'wc-invoice-payment' ),
			'target'   => 'lkn-wcip-subscription-data',
			'class'    => array(),
			'priority' => 90,
		);		
        return apply_filters( 'subscriptionTab', $tabs ); 
	}
    
    public function add_text_field_to_subscription_tab() {
        global $post;
        $subscription_number = get_post_meta( $post->ID, 'lkn_wcip_subscription_interval_number', true );
        $subscription_interval = get_post_meta( $post->ID, 'lkn_wcip_subscription_interval_type', true );
        //Caso nenhum exista o padrão será 1 mês
        if(!$subscription_number && !$subscription_interval){
            $subscription_number = 1;
            $subscription_interval = 'month';
        }
        ?>
        <div id="lkn-wcip-subscription-data" class="panel woocommerce_options_panel">
            <p class="form-field">
                <label for="lkn_wcip_subscription_interval_number"><?php esc_attr_e('Subscription Interval', 'wc-invoice-payment'); ?></label>
                <input type="number" class="short wc_input_number" min="1" name="lkn_wcip_subscription_interval_number" id="lkn_wcip_subscription_interval_number" value="<?php echo esc_attr($subscription_number); ?>">
                <input type="hidden" name="lkn_wcip_subscription_nonce" id="lkn_wcip_subscription_nonce" value="<?php echo esc_attr(wp_create_nonce('subscription_nonce')); ?>">
                <select id="lkn_wcip_subscription_interval_type" name="lkn_wcip_subscription_interval_type" class="lkn_wcip_subscription_interval_type">
                    <?php
                    $options = array(
                        'day' => __('Days', 'wc-invoice-payment'),
                        'week' => __('Weeks', 'wc-invoice-payment'),
                        'month' => __('Months', 'wc-invoice-payment'),
                        'year' => __('Years', 'wc-invoice-payment')
                    );
    
                    foreach ($options as $key => $label) {
                        echo '<option value="' . esc_attr($key) . '"';
                        echo ($subscription_interval == $key) ? ' selected="selected"' : '';
                        echo '>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>
            </p>
        </div>
        <?php
        wp_enqueue_script('custom-admin-js', plugin_dir_url(__FILE__) . '../admin/js/wc-invoice-payment-subscription.js', array('jquery'), '1.4.0', true);
    }
    

    public function save_subscription_fields( $post_id ) {
        //Salva todos os campos criados na meta do post
    
        if(wp_verify_nonce($_POST['lkn_wcip_subscription_nonce'], 'subscription_nonce')){
            if ( isset( $_POST['lkn_wcip_subscription_interval_number'] ) ) {
                $subscription_number = sanitize_text_field( $_POST['lkn_wcip_subscription_interval_number'] );
                update_post_meta( $post_id, 'lkn_wcip_subscription_interval_number', $subscription_number );
            }
        
            if ( isset( $_POST['lkn_wcip_subscription_interval_type'] ) ) {
                $subscription_interval = sanitize_text_field( $_POST['lkn_wcip_subscription_interval_type'] );
                update_post_meta( $post_id, 'lkn_wcip_subscription_interval_type', $subscription_interval );
            }
    
            if ( isset( $_POST['_lkn-wcip-subscription-product'] ) ) {
                $subscription_checkbox = sanitize_text_field( $_POST['_lkn-wcip-subscription-product'] );
                update_post_meta( $post_id, '_lkn-wcip-subscription-product', $subscription_checkbox );
            }else{
                update_post_meta( $post_id, '_lkn-wcip-subscription-product', '' );
            }
        }
    }

    function validate_product( $order_id ) {
        if(get_option("active_product_invoices") == "yes"){

            $order = wc_get_order( $order_id );
            $items = $order->get_items();

            foreach ( $items as $item ) {
                $product_id = $item->get_product_id();
                $is_subscription_enabled = get_post_meta( $product_id, '_lkn-wcip-subscription-product', true );
                $is_subscription_manual = $order->get_meta('lkn_wcip_subscription_is_manual');
                $iniDate = new DateTime();
                $iniDateFormatted = $iniDate->format('Y-m-d');;
                $subscription_interval_number = get_post_meta( $product_id, 'lkn_wcip_subscription_interval_number', true );
                $subscription_interval_type = get_post_meta( $product_id, 'lkn_wcip_subscription_interval_type', true );
                
                //Se for uma assinatura adicionada manualmente será preciso pegar os valores de outra forma
                if($is_subscription_manual){
                    $is_subscription_enabled = $order->get_meta('lkn_is_subscription');
                    $subscription_interval_number = $order->get_meta('lkn_wcip_subscription_interval_number');
                    $subscription_interval_type = $order->get_meta('lkn_wcip_subscription_interval_type');
                };

                $result = $this->calculate_next_due_date( $subscription_interval_number, $subscription_interval_type );
                $next_due_date = $result['next_due_date'];

                //seta data para ver quanto tempo foi removido para ser adicionado depois            
                $order->add_meta_data('lkn_time_removed', $result['time_removed']);            
                $order->add_meta_data('lkn_ini_date', gmdate("Y-m-d", strtotime($iniDateFormatted)));
                if(!$order->get_meta('lkn_exp_date')){
                    $order->add_meta_data('lkn_exp_date', gmdate("Y-m-d", strtotime($iniDateFormatted))); 
                }

                
                //Caso seja assinatura gera evento do WP cron
                if ( $is_subscription_enabled == 'on') {
                    $order->add_meta_data('lkn_is_subscription', true);
                    $this->schedule_next_invoice_generation( $order_id, $next_due_date );
                }
                $order->save();

                //Caso não seja uma assinatura manual é preciso atualizar a lista
                if(!$is_subscription_manual){
                    // Adicionar a nova ordem à lista de faturas
                    $invoice_list = get_option( 'lkn_wcip_invoices', array() );
                    $invoice_list[] = $order->get_id();
                    update_option( 'lkn_wcip_invoices', $invoice_list );
                }
            }
        }
    }
    
    function calculate_next_due_date( $interval_number, $interval_type ) {
        $current_time = current_time( 'timestamp' );
        //Pega a quantidade de tempo de intervalo da cobrança e diminui horas, dias, semanas e meses de acordo com o que foi escolhido        
        switch ( $interval_type ) {
            case 'day':
                $next_due_date = strtotime( "+{$interval_number} day", $current_time );
                if ($interval_number > 7) {
                    $next_due_date = strtotime( "-1 week", $next_due_date );
                    //A variavel $time_removed salva o valor reduzido para ser somado mais tarde e gerar a fatura
                    $time_removed = '1 week';
                } elseif ($interval_number <= 7 && $interval_number > 1) {
                    $next_due_date = strtotime( "-3 days", $next_due_date );
                    $time_removed = '3 days';
                } elseif ($interval_number == 1) {
                    $next_due_date = strtotime( "-6 hours", $next_due_date );
                    $time_removed = '6 hours';
                }
                break;
            case 'week':
                $next_due_date = strtotime( "+{$interval_number} week", $current_time );
                if ($interval_number > 1) {
                    $next_due_date = strtotime( "-1 week", $next_due_date );
                    $time_removed = '1 week';
                } else {
                    $next_due_date = strtotime( "-3 days", $next_due_date );
                    $time_removed = '3 days';
                }
                break;
            case 'month':
                $next_due_date = strtotime( "+{$interval_number} month", $current_time );
                if ($interval_number > 1) {
                    $next_due_date = strtotime( "-2 weeks", $next_due_date );
                    $time_removed = '2 weeks';
                } else {
                    $next_due_date = strtotime( "-3 days", $next_due_date );
                    $time_removed = '3 days';
                }
                break;
            case 'year':
                $next_due_date = strtotime( "+{$interval_number} year", $current_time );
                if ($interval_number > 1) {
                    $next_due_date = strtotime( "-3 months", $next_due_date );
                    $time_removed = '3 months';
                } else {
                    $next_due_date = strtotime( "-2 weeks", $next_due_date );
                    $time_removed = '2 weeks';
                }
                break;
            default:
                $next_due_date = 0;
                $next_due_date = '';
                break;
        }
    
        return array(
            'next_due_date' => $next_due_date,
            'time_removed'  => $time_removed
        );
    }
    
    
    function schedule_next_invoice_generation( $order_id, $due_date ) {
        wp_schedule_single_event( $due_date, 'generate_invoice_event', array( $order_id ) );
    }
    
    function create_next_invoice( $order_id ) {
        $order = wc_get_order( $order_id );
        
        $customer_id = $order->get_customer_id();
        $billing_email = $order->get_billing_email();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $payment_method = $order->get_payment_method();
        $time_removed = $order->get_meta('lkn_time_removed');
        $iniDate = new DateTime();
        $iniDateFormatted = $iniDate->format('Y-m-d');
        //Soma o tempo removido anteriormente para colocar na data de vencimento
        $iniDate->modify("+" . $time_removed);
        $expDateFormatted = gmdate('Y-m-d', $iniDate->getTimestamp());

        $new_order = wc_create_order( array(
            'status' => 'wc-pending',
            'customer_id' => $customer_id,
        ) );
        $new_order->set_billing_first_name($billing_first_name);
        $new_order->set_billing_last_name($billing_last_name);
        $new_order->set_billing_email($billing_email);
        $new_order->set_payment_method($payment_method);
        $new_order->add_meta_data('lkn_ini_date', $iniDateFormatted);
        $new_order->add_meta_data('lkn_exp_date', $expDateFormatted);
        $new_order->add_meta_data('lkn_is_subscription', false);
        $new_order_id = $new_order->get_id();

        if ( ! $new_order ) {
            return;
        }

        // Calcular e definir o total da nova ordem com base nos itens da ordem original
        $total_amount = 0;
        foreach ( $order->get_items() as $item ) {
            $total_amount += $item->get_total();

            $item_data = array(
                'name'          => $item->get_name(),
                'quantity'      => 1,
                'total'    => $item->get_total(), 
            );
            $new_order->add_product( wc_get_product( 'custom' ), 1, $item_data );        
        }
        

        $new_order->set_total( $total_amount );

        //Atualiza data de expiração e id da assinatura para nova data de expiração da fatura gerada 
        $order->update_meta_data('lkn_exp_date', $expDateFormatted);
        $order->update_meta_data('lkn_invoice_id', $new_order_id);
        $order->save();

        $new_order->save();

        // Adicionar a nova ordem à lista de faturas
        $invoice_list = get_option( 'lkn_wcip_invoices', array() );
        $invoice_list[] = $new_order->get_id();
        update_option( 'lkn_wcip_invoices', $invoice_list );

    }
    
}