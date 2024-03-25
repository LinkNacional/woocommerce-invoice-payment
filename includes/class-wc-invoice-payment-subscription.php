<?php

class Wc_Payment_Invoice_Subscription{

    public function add_checkbox( $products_type ) {
        global $post;
        $subscription_interval = get_post_meta( $post->ID, '_lkn-wcip-subscription-product', true );
        $products_type['subscriptionCheckbox'] = array(
            'id'            => '_lkn-wcip-subscription-product',
			'wrapper_class' => 'show_if_simple',
			'label'         => __( 'Subscription', 'wc-invoice-payment' ),
			'description'   => __( 'This is a subscription product.', 'wc-invoice-payment' ),
			'default'       => $subscription_interval ? 'yes' : 'no',
		);
		return $products_type;
	}

    public function add_tab( $tabs ) {
		$tabs['subscriptionTab'] = array(
			'label'    => __( 'Subscription', 'wc-invoice-payment' ),
			'target'   => 'lkn-wcip-subscription-data',
			'class'    => array(),
			'priority' => 90,
		);		
        return apply_filters( 'testee5', $tabs ); //TODO Remover teste
	}
    
    //TODO Terminar logica de exibição dos campos na criação de fatura com WP Cron
    //TODO Alterar traduções 
    public function add_text_field_to_subscription_tab() {
        global $post;
        $subscription_number = get_post_meta( $post->ID, 'lkn_wcip_subscription_interval_number', true );
        $subscription_interval = get_post_meta( $post->ID, 'lkn_wcip_subscription_interval_type', true );
        echo <<<HTML
            <div id="lkn-wcip-subscription-data" class="panel woocommerce_options_panel">
                <p class="form-field">
                    <label for="lkn_wcip_subscription_interval_number">Subscriptions Per Interval</label>
                    <input type="number" class="short wc_input_number" min="1" name="lkn_wcip_subscription_interval_number" id="lkn_wcip_subscription_interval_number" value="{$subscription_number}">
                    <select id="lkn_wcip_subscription_interval_type" name="lkn_wcip_subscription_interval_type" class="lkn_wcip_subscription_interval_type">
        HTML;
                        echo '<option value="day"';
                        echo ($subscription_interval == 'day') ? ' selected="selected"' : '';
                        echo '>Days</option>';
                        
                        echo '<option value="week"';
                        echo ($subscription_interval == 'week') ? ' selected="selected"' : '';
                        echo '>Weeks</option>';
                        
                        echo '<option value="month"';
                        echo ($subscription_interval == 'month') ? ' selected="selected"' : '';
                        echo '>Months</option>';
                        
                        echo '<option value="year"';
                        echo ($subscription_interval == 'year') ? ' selected="selected"' : '';
                        echo '>Years</option>';
        echo <<<HTML
                    </select>
                </p>
            </div>
            

        HTML;
        wp_enqueue_script( 'custom-admin-js', plugin_dir_url(__FILE__) . '../admin/js/wc-invoice-payment-subscription.js', array( 'jquery' ), '', true );
    }

    public function save_subscription_fields( $post_id ) {
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
    function validate_product( $order_id ) {
        $order = wc_get_order( $order_id );
        $items = $order->get_items();
    
        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            $is_subscription_enabled = get_post_meta( $product_id, '_lkn-wcip-subscription-product', true );
    
            if ( $is_subscription_enabled === 'on' ) {
                $subscription_interval_number = get_post_meta( $product_id, 'lkn_wcip_subscription_interval_number', true );
                $subscription_interval_type = get_post_meta( $product_id, 'lkn_wcip_subscription_interval_type', true );
    
                $next_due_date = $this->calculate_next_due_date( $subscription_interval_number, $subscription_interval_type );
    
                $this->schedule_next_invoice_generation( $order_id, $next_due_date );
            }
        }
    }
    
    function calculate_next_due_date( $interval_number, $interval_type ) {
        $current_time = current_time( 'timestamp' );
    
        switch ( $interval_type ) {
            case 'day':
                $next_due_date = strtotime( "+{$interval_number} day", $current_time );
                // Se a fatura for maior que uma semana, diminua uma semana da data de vencimento
                if ($interval_number > 7) {
                    $next_due_date = strtotime( "-1 week", $next_due_date );
                }
                // Se for uma semana ou menos, diminua alguns dias
                elseif ($interval_number <= 7 && $interval_number > 1) {
                    $next_due_date = strtotime( "-3 days", $next_due_date );
                }
                // Se for apenas um dia, diminua algumas horas
                elseif ($interval_number == 1) {
                    $next_due_date = strtotime( "-6 hours", $next_due_date );
                }
                break;
            case 'week':
                $next_due_date = strtotime( "+{$interval_number} week", $current_time );
                // Se a fatura for maior que uma semana, diminua uma semana da data de vencimento
                if ($interval_number > 1) {
                    $next_due_date = strtotime( "-1 week", $next_due_date );
                }
                // Se for apenas uma semana, diminua alguns dias
                else {
                    $next_due_date = strtotime( "-3 days", $next_due_date );
                }
                break;
            case 'month':
                $next_due_date = strtotime( "+{$interval_number} month", $current_time );
                // Se a fatura for maior que um mês, diminua algumas semanas
                if ($interval_number > 1) {
                    $next_due_date = strtotime( "-2 weeks", $next_due_date );
                }
                // Se for apenas um mês, diminua alguns dias
                else {
                    $next_due_date = strtotime( "-3 days", $next_due_date );
                }
                break;
            case 'year':
                $next_due_date = strtotime( "+{$interval_number} year", $current_time );
                // Se a fatura for maior que um ano, diminua alguns meses
                if ($interval_number > 1) {
                    $next_due_date = strtotime( "-3 months", $next_due_date );
                }
                // Se for apenas um ano, diminua algumas semanas
                else {
                    $next_due_date = strtotime( "-2 weeks", $next_due_date );
                }
                break;
            default:
                $next_due_date = 0;
                break;
        }
    
        return $next_due_date;
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
        $currency = $order->get_currency();
        $exp_date = $order->get_meta('lkn_exp_date');

        $new_order = wc_create_order( array(
            'status' => 'wc-pending',
            'customer_id' => $customer_id,
            'currency' => $currency,
        ) );
        $new_order->set_billing_first_name($billing_first_name);
        $new_order->set_billing_last_name($billing_last_name);
        $new_order->set_billing_email($billing_email);
        $new_order->set_payment_method($payment_method);
        if ( ! $new_order ) {
            return;
        }
        add_option("dataExpirada", json_encode($exp_date));
        // Definir a data de vencimento da nova ordem com base na data de vencimento da ordem original
        $new_order->update_meta_data( 'lkn_exp_date', $exp_date );

        // Calcular e definir o total da nova ordem com base nos itens da ordem original
        $total_amount = 0;
        foreach ( $order->get_items() as $item ) {
            $total_amount += $item->get_total();
            $new_order->add_product( $item->get_product(), $item->get_quantity() );
        }
        $new_order->set_total( $total_amount );

        // Calcular totais e salvar a nova ordem
        $new_order->calculate_totals();
        $new_order->save();

        // Adicionar a nova ordem à lista de faturas
        $invoice_list = get_option( 'lkn_wcip_invoices', array() );
        $invoice_list[] = $new_order->get_id();
        update_option( 'lkn_wcip_invoices', $invoice_list );

        // Agendar evento cron para a nova ordem, se necessário
        if ( ! empty( $exp_date ) ) {
            $today_time = time();
            $exp_date_time = strtotime( $exp_date );
            $next_verification = 0;

            if ( $today_time > $exp_date_time ) {
                $next_verification = $today_time - $exp_date_time;
            } else {
                $next_verification = $exp_date_time - $today_time;
            }

            wp_schedule_event( time() + $next_verification, 'daily', 'lkn_wcip_cron_hook', array( $new_order->get_id() ) );
        }

        // Enviar e-mail de notificação ao cliente, se necessário
        if ( isset( $_POST['lkn_wcip_form_actions'] ) && sanitize_text_field( $_POST['lkn_wcip_form_actions'] ) === 'send_email' ) {
            WC()->mailer()->customer_invoice( $new_order );

            // Adicionar nota de ordem
            $new_order->add_order_note( __( 'Order details manually sent to customer.', 'woocommerce' ), false, true );
        }

        // Exibir mensagem de sucesso
        echo '<div class="lkn_wcip_notice_positive">' . esc_html( __( 'Invoice successfully created', 'wc-invoice-payment' ) ) . '</div>';
    }
    
}