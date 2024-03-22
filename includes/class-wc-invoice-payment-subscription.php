<?php

class Wc_Payment_Invoice_Loader_Subscription{

    public function addCheckbox( $products_type ) {
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

    public function addTab( $tabs ) {
		$tabs['subscriptionTab'] = array(
			'label'    => __( 'Subscription', 'wc-invoice-payment' ),
			'target'   => 'lkn-wcip-subscription-data',
			'class'    => array(),
			'priority' => 90,
		);		
        return apply_filters( 'testee5', $tabs );       
	}
    
    function meu_processo_de_validacao( $order_id ) {
        // Obtenha o pedido
        $order = wc_get_order( $order_id );
        echo "<script>consoe(".json_encode($order).")</script>";
        add_option($order_id."Teste", json_encode($_POST));
        // Iterar sobre os itens do pedido
        
    }
    //TODO Finalizar logica de intervalo de geração de fatura com o WP Crontrol
    //TODO Fazer logica para o campo de intervalo não ser obrigatório quando estiver invisivel
    //TODO Alterar classes e traduções 
    public function addTextFieldToSubscriptionTab() {
        global $post;
        $subscription_number = get_post_meta( $post->ID, 'wps_sfw_subscription_number', true );
        $subscription_interval = get_post_meta( $post->ID, 'wps_sfw_subscription_interval', true );
    
        echo <<<HTML
            <div id="lkn-wcip-subscription-data" class="panel woocommerce_options_panel">
                <p class="form-field wps_sfw_subscription_number_field">
                    <label for="wps_sfw_subscription_number">Subscriptions Per Interval</label>
                    <input type="number" class="short wc_input_number" min="1" required="" name="wps_sfw_subscription_number" id="wps_sfw_subscription_number" value="{$subscription_number}">
                    <select id="wps_sfw_subscription_interval" name="wps_sfw_subscription_interval" class="wps_sfw_subscription_interval">
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
        if ( isset( $_POST['wps_sfw_subscription_number'] ) ) {
            $subscription_number = sanitize_text_field( $_POST['wps_sfw_subscription_number'] );
            update_post_meta( $post_id, 'wps_sfw_subscription_number', $subscription_number );
        }
    
        if ( isset( $_POST['wps_sfw_subscription_interval'] ) ) {
            $subscription_interval = sanitize_text_field( $_POST['wps_sfw_subscription_interval'] );
            update_post_meta( $post_id, 'wps_sfw_subscription_interval', $subscription_interval );
        }

        if ( isset( $_POST['_lkn-wcip-subscription-product'] ) ) {
            $subscription_checkbox = sanitize_text_field( $_POST['_lkn-wcip-subscription-product'] );
            update_post_meta( $post_id, '_lkn-wcip-subscription-product', $subscription_checkbox );
        }else{
            update_post_meta( $post_id, '_lkn-wcip-subscription-product', '' );
        }
    }
    
}