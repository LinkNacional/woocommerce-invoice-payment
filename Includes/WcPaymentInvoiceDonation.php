<?php

namespace LknWc\WcInvoicePayment\Includes;

/**
 * Classe responsável por gerenciar o tipo de produto "Doação".
 *
 * @since 1.0.0
 */
final class WcPaymentInvoiceDonation
{
    public static function loadDokanSettingsTemplate( $post, $post_id ) {
        $_donation_type   = get_post_meta( $post_id, '_donation_type', true );
        $_regular_price    = get_post_meta( $post_id, '_regular_price', true );
        $_donation_button_values = get_post_meta( $post_id, '_donation_button_values', true );
        $_donation_hide_custom_amount = get_post_meta( $post_id, '_donation_hide_custom_amount', true );
        $_donation_free_text = get_post_meta( $post_id, '_donation_free_text', true );
        
        // === DADOS DE META DE DOAÇÃO ===
        $_donation_enable_goal = get_post_meta( $post_id, '_donation_enable_goal', true );
        $_donation_goal_amount = get_post_meta( $post_id, '_donation_goal_amount', true );
        $_donation_show_progress = get_post_meta( $post_id, '_donation_show_progress', true );
        
        // === DADOS DE DATA LIMITE ===
        $_donation_enable_deadline = get_post_meta( $post_id, '_donation_enable_deadline', true );
        $_donation_deadline_date = get_post_meta( $post_id, '_donation_deadline_date', true );
        $_donation_show_countdown = get_post_meta( $post_id, '_donation_show_countdown', true );
        $_donation_deadline_message = get_post_meta( $post_id, '_donation_deadline_message', true );
        // Set default values if never saved before
        if (!metadata_exists('post', $post_id, '_donation_type')) {
            $_donation_type = 'fixed';
        }
        if (!metadata_exists('post', $post_id, '_regular_price')) {
            $_regular_price = 0;
        }
        if (!metadata_exists('post', $post_id, '_donation_button_values')) {
            $_donation_button_values = '10, 20, 25';
        }
        if (!metadata_exists('post', $post_id, '_donation_hide_custom_amount')) {
            $_donation_hide_custom_amount = 'no';
        }
        if (!metadata_exists('post', $post_id, '_donation_free_text')) {
            $_donation_free_text = __('Free', 'wc-invoice-payment');
        }
        if (!metadata_exists('post', $post_id, '_donation_enable_goal')) {
            $_donation_enable_goal = 'no';
        }
        if (!metadata_exists('post', $post_id, '_donation_goal_amount')) {
            $_donation_goal_amount = '';
        }
        if (!metadata_exists('post', $post_id, '_donation_show_progress')) {
            $_donation_show_progress = 'yes';
        }
        
        // === VALORES PADRÃO PARA DATA LIMITE ===
        if (!metadata_exists('post', $post_id, '_donation_enable_deadline')) {
            $_donation_enable_deadline = 'no';
        }
        if (!metadata_exists('post', $post_id, '_donation_deadline_date')) {
            $_donation_deadline_date = '';
        }
        if (!metadata_exists('post', $post_id, '_donation_show_countdown')) {
            $_donation_show_countdown = 'yes';
        }
        if (!metadata_exists('post', $post_id, '_donation_deadline_message')) {
            $_donation_deadline_message = __('The donation period has ended. Thank you for your interest!', 'wc-invoice-payment');
        }

        wc_get_template(
            '/dokanSettings.php',
            array(
                '_donation_type' => $_donation_type,
                '_regular_price'      => $_regular_price,
                '_donation_button_values' => $_donation_button_values,
                '_donation_hide_custom_amount' => $_donation_hide_custom_amount,
                '_donation_free_text' => $_donation_free_text,
                '_donation_enable_goal' => $_donation_enable_goal,
                '_donation_goal_amount' => $_donation_goal_amount,
                '_donation_show_progress' => $_donation_show_progress,
                '_donation_enable_deadline' => $_donation_enable_deadline,
                '_donation_deadline_date' => $_donation_deadline_date,
                '_donation_show_countdown' => $_donation_show_countdown,
                '_donation_deadline_message' => $_donation_deadline_message,
            ),
            'woocommerce/pix/',
            plugin_dir_path( __FILE__ ) . 'templates/'
        );
            
    }

    public function saveDokanSettings( $post_id ) {
        // Salva o tipo de doação
        if (isset($_POST['_donation_type'])) {
            update_post_meta($post_id, '_donation_type', sanitize_text_field($_POST['_donation_type']));
        }
        // Salva os valores dos botões para doação variável
        if (isset($_POST['_donation_button_values'])) {
            update_post_meta($post_id, '_donation_button_values', sanitize_text_field($_POST['_donation_button_values']));
        }
        // Salva o texto para doação grátis
        if (isset($_POST['_donation_free_text'])) {
            update_post_meta($post_id, '_donation_free_text', sanitize_text_field($_POST['_donation_free_text']));
        }
        if(isset($_POST['_regular_donation_price'])){
            $regular_price =  floatval($_POST['_regular_donation_price']);
            update_post_meta( $post_id, '_regular_price', $regular_price );
        }
        // Salva a configuração de ocultar campo personalizado
        if (isset($_POST['_donation_hide_custom_amount'])) {
            update_post_meta($post_id, '_donation_hide_custom_amount', 'yes');
        } else {
            update_post_meta($post_id, '_donation_hide_custom_amount', 'no');
        }
        
        // === SALVA DADOS DE META DE DOAÇÃO PARA DOKAN ===
        if (isset($_POST['_donation_enable_goal'])) {
            update_post_meta($post_id, '_donation_enable_goal', 'yes');
        } else {
            update_post_meta($post_id, '_donation_enable_goal', 'no');
        }
        
        if (isset($_POST['_donation_goal_amount'])) {
            $goal_amount = floatval($_POST['_donation_goal_amount']);
            update_post_meta($post_id, '_donation_goal_amount', $goal_amount);
        }
        
        if (isset($_POST['_donation_show_progress'])) {
            update_post_meta($post_id, '_donation_show_progress', 'yes');
        } else {
            update_post_meta($post_id, '_donation_show_progress', 'no');
        }
        
        // === SALVA DADOS DE DATA LIMITE PARA DOKAN ===
        if (isset($_POST['_donation_enable_deadline'])) {
            update_post_meta($post_id, '_donation_enable_deadline', 'yes');
        } else {
            update_post_meta($post_id, '_donation_enable_deadline', 'no');
        }
        
        if (isset($_POST['_donation_deadline_date'])) {
            $deadline_date = sanitize_text_field($_POST['_donation_deadline_date']);
            
            // Validação: a data deve ser pelo menos 1 minuto no futuro
            if (!empty($deadline_date)) {
                $deadline_timestamp = strtotime($deadline_date);
                $min_timestamp = strtotime('+1 minute');
                
                if ($deadline_timestamp < $min_timestamp) {
                    // Se a data for no passado ou muito próxima, define para 1 minuto no futuro
                    $deadline_date = date('Y-m-d\TH:i', $min_timestamp);
                }
            }
            
            update_post_meta($post_id, '_donation_deadline_date', $deadline_date);
        }
        
        if (isset($_POST['_donation_show_countdown'])) {
            update_post_meta($post_id, '_donation_show_countdown', 'yes');
        } else {
            update_post_meta($post_id, '_donation_show_countdown', 'no');
        }
        
        if (isset($_POST['_donation_deadline_message'])) {
            update_post_meta($post_id, '_donation_deadline_message', sanitize_textarea_field($_POST['_donation_deadline_message']));
        }
        
        // Para doação de valor fixo, o preço é salvo automaticamente pelo WooCommerce
        // Para doação variável e grátis, definimos o preço como 0
        $donation_type = isset($_POST['_donation_type']) ? sanitize_text_field($_POST['_donation_type']) : 'fixed';
        if ($donation_type === 'variable' || $donation_type === 'free') {
            update_post_meta($post_id, '_regular_price', 0);
            update_post_meta($post_id, '_price', 0);
        }
    }

    public function enqueueDokanScripts(){
        if (get_option('lkn_wcip_anonymous_donation_checkout', '') == 'yes' && function_exists('dokan_is_seller_dashboard') && dokan_is_seller_dashboard()){
            wp_enqueue_script( 'wcInvoicePaymentDonationDokanScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-donation-dokan.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
            wp_enqueue_style('wcInvoicePaymentDonationStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-donation-dokan.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        }
    }

    public function addDonationType( $product_types ) {
        $product_types['donation'] = __( 'Donation', 'dokan' );
        return $product_types;
    }

    public function enqueueCheckoutScripts(){
        if (function_exists('WC')) {
            $only_free_or_variable_donations = true;
            if (isset(WC()->cart)) {
                foreach (WC()->cart->get_cart() as $cart_item) {
                    $product = $cart_item['data'];
                    // Verifica se todos os produtos são doações do tipo free ou variable
                    if ($product->get_type() === 'donation') {
                        $donation_type = get_post_meta($product->get_id(), '_donation_type', true);
                        if ($donation_type !== 'variable') {
                            $only_free_or_variable_donations = false;
                        }
                    } else {
                        $only_free_or_variable_donations = false;
                    }
                }
            }
        }
        if ( is_checkout() && 
            WC()->payment_gateways() && 
            ! empty( WC()->payment_gateways()->get_available_payment_gateways() ) && 
            get_option('lkn_wcip_anonymous_donation_checkout', '') == 'yes' && 
            $only_free_or_variable_donations){
            $currency_code =  get_woocommerce_currency();
            $currency_symbol = get_woocommerce_currency_symbol( $currency_code );
            wp_enqueue_script( 'wcInvoicePaymentDonationScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-donation-checkout.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
            wp_enqueue_style('wcInvoicePaymentDonationStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-donation-checkout.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
            wp_localize_script('wcInvoicePaymentDonationScript', 'lknWcipDonationVariables', array(
                'minPartialAmount' => get_option('lkn_wcip_partial_interval_minimum', 0),
                'cart' => WC()->cart,
                'userId' => get_current_user_id(),
                'symbol' => $currency_symbol,
                'partialPaymentTitle' => __('Partial Payment', 'wc-invoice-payment'),
                'partialPaymentDescription' => __('Enter the amount you want to pay now, the rest can be paid later with other payment methods.', 'wc-invoice-payment'),
                'payPartialText' => __('Pay Partial', 'wc-invoice-payment'),
            ));
        }
    }

    /**
     * Adiciona o tipo de produto "Doação" ao WooCommerce.
     *
     * @param array $types Array de tipos de produtos existentes.
     * @return array Array de tipos de produtos com o novo tipo "donation" adicionado.
     */
    public function add_donation_product_type($types)
    {
        if (get_option('lkn_wcip_donation_product_enabled', 'no') === 'yes') {
            $types['donation'] = __('Donation', 'wc-invoice-payment');
        }
        return $types;
    }

    /**
     * Define quais recursos são suportados pelo tipo de produto doação.
     * 
     * @param array $supports Lista de recursos suportados.
     * @param string $product_type Tipo do produto.
     * @return array Lista de recursos suportados.
     */
    public function add_donation_product_supports($supports, $product_type)
    {
        if ($product_type === 'donation') {
            return array_merge($supports, [
                'virtual',
                'downloadable'
            ]);
        }
        return $supports;
    }

    /**
     * Adiciona opções específicas para produtos do tipo doação.
     *
     * @param array $options Array de opções de tipo de produto.
     * @return array Array modificado com opções de doação.
     */
    public function add_donation_type_options($options)
    {
        // Verifica se o tipo de produto de doação está habilitado nas configurações
        if (get_option('lkn_wcip_donation_product_enabled', 'no') === 'yes') {
            $options['virtual']['wrapper_class'] .= ' show_if_donation';
            $options['downloadable']['wrapper_class'] .= ' show_if_donation';
        }
        
        return $options;
    }

    /**
     * Adiciona abas do WooCommerce que devem aparecer para produtos de doação.
     *
     * @param array $tabs Array de abas de dados do produto.
     * @return array Array modificado com as abas visíveis.
     */
    public function add_donation_product_data_tabs($tabs)
    {
        // Garante que as abas essenciais apareçam para produtos de doação
        if (isset($tabs['inventory'])) {
            $tabs['inventory']['class'][] = 'show_if_simple show_if_donation';
        }
        
        if (isset($tabs['linked_product'])) {
            $tabs['linked_product']['class'][] = 'show_if_simple show_if_donation';
        }
        
        if (isset($tabs['attribute'])) {
            $tabs['attribute']['class'][] = 'show_if_simple show_if_donation';
        }
        
        if (isset($tabs['advanced'])) {
            $tabs['advanced']['class'][] = 'show_if_simple show_if_donation';
        }
        
        return $tabs;
    }

    /**
     * Adiciona a aba de configurações de doação no produto.
     *
     * @param array $tabs Array de abas do produto.
     * @return array Array modificado com a aba de doação.
     */
    public function add_donation_product_tab($tabs)
    {
        // Verifica se o tipo de produto de doação está habilitado nas configurações
        if (get_option('lkn_wcip_donation_product_enabled', 'no') === 'yes') {
            $tabs['donation'] = array(
                'label'    => __('Donation', 'wc-invoice-payment'),
                'target'   => 'donation_product_data',
                'class'    => array('show_if_donation'),
                'priority' => 21,
            );
        }
        
        return $tabs;
    }

    /**
     * Adiciona o painel de configurações de doação.
     */
    public function add_donation_product_panel()
    {
        global $woocommerce, $post;
        echo '<div id="donation_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="options_group">';
        woocommerce_wp_select(array(
            'id'          => '_donation_type',
            'label'       => __('Donation type', 'wc-invoice-payment'),
            'options'     => array(
                'fixed'    => __('Fixed Amount (Donate item with fixed value item)', 'wc-invoice-payment'),
                'variable' => __('Variable Amount (Receive monetary donations)', 'wc-invoice-payment'),
                'free'     => __('Free (Donate an item)', 'wc-invoice-payment'),
            ),
            'desc_tip'    => true,
            'description' => __('Select the donation type.', 'wc-invoice-payment'),
        ));
        $regular_price = get_post_meta(get_the_ID(), '_regular_price', true);
        if (!metadata_exists('post', get_the_ID(), '_regular_price')) {
            $regular_price = 0;
        }
        woocommerce_wp_text_input(array(
            'id'                => '_regular_donation_price',
            'label'             => __('Donation amount (' . get_woocommerce_currency_symbol() . ')', 'wc-invoice-payment'),
            'placeholder'       => wc_format_localized_price(0),
            'description'       => __('Set the fixed donation amount.', 'wc-invoice-payment'),
            'type'              => 'text',
            'desc_tip'          => true,
            'data_type'         => 'price',
            'wrapper_class'     => 'show_if_donation_fixed lkn_value_input',
            'value'             => $regular_price,
        ));
        $button_values = get_post_meta(get_the_ID(), '_donation_button_values', true);
        if (!metadata_exists('post', get_the_ID(), '_donation_button_values')) {
            $button_values = '10, 20, 25';
        }
        woocommerce_wp_textarea_input(array(
            'id'          => '_donation_button_values',
            'label'       => __('Preset button values', 'wc-invoice-payment'),
            'description' => __('Enter the preset button values separated by comma. E.g.: 10, 20, 25', 'wc-invoice-payment'),
            'desc_tip'    => true,
            'wrapper_class' => 'show_if_donation_variable',
            'value'         => $button_values,
        ));
        $hide_custom_amount = get_post_meta(get_the_ID(), '_donation_hide_custom_amount', true);
        if (!metadata_exists('post', get_the_ID(), '_donation_hide_custom_amount')) {
            $hide_custom_amount = 'no';
        }
        woocommerce_wp_checkbox(array(
            'id'          => '_donation_hide_custom_amount',
            'label'       => __('Hide custom amount field', 'wc-invoice-payment'),
            'description' => __('Check this option to hide the custom amount field and show only the preset buttons.', 'wc-invoice-payment'),
            'desc_tip'    => true,
            'wrapper_class' => 'show_if_donation_variable',
            'value'         => $hide_custom_amount,
        ));
        $free_text = get_post_meta(get_the_ID(), '_donation_free_text', true);
        if (!metadata_exists('post', get_the_ID(), '_donation_free_text')) {
            $free_text = __('Free', 'wc-invoice-payment');
        }
        woocommerce_wp_text_input(array(
            'id'          => '_donation_free_text',
            'label'       => __('Text', 'wc-invoice-payment'),
            'placeholder' => __('Free', 'wc-invoice-payment'),
            'description' => __('Text to be displayed for free donation.', 'wc-invoice-payment'),
            'desc_tip'    => true,
            'wrapper_class' => 'show_if_donation_free',
            'value'         => $free_text,
        ));
        
        // === CAMPOS DE META DE DOAÇÃO ===
        echo '<div class="donation-goal-section options_group show_if_donation_variable" style="display: none;">';
        echo '<h4>' . esc_html__('Donation Goal Settings', 'wc-invoice-payment') . '</h4>';
        
        // Habilitar meta de doação
        $enable_goal = get_post_meta(get_the_ID(), '_donation_enable_goal', true);
        woocommerce_wp_checkbox(array(
            'id'          => '_donation_enable_goal',
            'label'       => __('Enable donation goal', 'wc-invoice-payment'),
            'description' => __('Enable a donation goal for this product. When reached, donations will no longer be accepted.', 'wc-invoice-payment'),
            'desc_tip'    => true,
            'value'       => $enable_goal,
            'wrapper_class' => 'show_if_donation_variable',
        ));
        
        // Valor da meta
        $goal_amount = get_post_meta(get_the_ID(), '_donation_goal_amount', true);
        woocommerce_wp_text_input(array(
            'id'                => '_donation_goal_amount',
            'label'             => __('Goal amount (' . get_woocommerce_currency_symbol() . ')', 'wc-invoice-payment'),
            'placeholder'       => wc_format_localized_price(0),
            'description'       => __('Set the donation goal amount. When this amount is reached with completed orders, no more donations will be accepted.', 'wc-invoice-payment'),
            'type'              => 'text',
            'desc_tip'          => true,
            'data_type'         => 'price',
            'wrapper_class'     => 'show_if_donation_goal show_if_donation_variable',
            'value'             => $goal_amount,
        ));
        
        // Mostrar progresso na página do produto
        $show_progress = get_post_meta(get_the_ID(), '_donation_show_progress', true);
        if (!metadata_exists('post', get_the_ID(), '_donation_show_progress')) {
            $show_progress = 'yes';
        }
        woocommerce_wp_checkbox(array(
            'id'          => '_donation_show_progress',
            'label'       => __('Show progress bar', 'wc-invoice-payment'),
            'description' => __('Display the donation progress bar on the product page.', 'wc-invoice-payment'),
            'desc_tip'    => true,
            'value'       => $show_progress,
            'wrapper_class' => 'show_if_donation_goal show_if_donation_variable',
        ));
        
        // === CONFIGURAÇÕES DE DATA LIMITE ===
        echo '<h4>' . esc_html__('Donation Deadline Settings', 'wc-invoice-payment') . '</h4>';
        
        // Habilitar data limite
        $enable_deadline = get_post_meta(get_the_ID(), '_donation_enable_deadline', true);
        woocommerce_wp_checkbox(array(
            'id'          => '_donation_enable_deadline',
            'label'       => __('Enable donation deadline', 'wc-invoice-payment'),
            'description' => __('Set a deadline for donations. After this date, no more donations will be accepted.', 'wc-invoice-payment'),
            'desc_tip'    => true,
            'value'       => $enable_deadline,
            'wrapper_class' => 'show_if_donation',
        ));
        
        // Data limite
        $deadline_date = get_post_meta(get_the_ID(), '_donation_deadline_date', true);
        // Define o valor mínimo como 1 minuto no futuro
        $min_datetime = date('Y-m-d\TH:i', strtotime('+1 minute'));
        woocommerce_wp_text_input(array(
            'id'                => '_donation_deadline_date',
            'label'             => __('Deadline date and time', 'wc-invoice-payment'),
            'placeholder'       => $min_datetime,
            'type'              => 'datetime-local',
            'desc_tip'          => true,
            'wrapper_class'     => 'show_if_donation_deadline',
            'value'             => $deadline_date,
            'custom_attributes' => array(
                'min' => $min_datetime
            ),
        ));
        
        // Mostrar contador regressivo
        $show_countdown = get_post_meta(get_the_ID(), '_donation_show_countdown', true);
        if (!metadata_exists('post', get_the_ID(), '_donation_show_countdown')) {
            $show_countdown = 'yes';
        }
        woocommerce_wp_checkbox(array(
            'id'          => '_donation_show_countdown',
            'label'       => __('Show countdown timer', 'wc-invoice-payment'),
            'description' => __('Display a countdown timer on the product page showing time remaining until deadline.', 'wc-invoice-payment'),
            'desc_tip'    => true,
            'value'       => $show_countdown,
            'wrapper_class' => 'show_if_donation_deadline',
        ));
        
        // Mensagem quando prazo expirado
        $deadline_message = get_post_meta(get_the_ID(), '_donation_deadline_message', true);
        if (!metadata_exists('post', get_the_ID(), '_donation_deadline_message')) {
            $deadline_message = __('The donation period has ended. Thank you for your interest!', 'wc-invoice-payment');
        }
        woocommerce_wp_textarea_input(array(
            'id'          => '_donation_deadline_message',
            'label'       => __('Deadline expired message', 'wc-invoice-payment'),
            'description' => __('Message to display when the donation deadline has passed.', 'wc-invoice-payment'),
            'desc_tip'    => true,
            'wrapper_class' => 'show_if_donation_deadline',
            'value'         => $deadline_message,
        ));
        
        // Campo oculto para o valor atual da doação (atualizado automaticamente)
        $current_amount = get_post_meta(get_the_ID(), '_donation_current_amount', true);
        if (!$current_amount) {
            $current_amount = 0;
        }
        echo '<input type="hidden" id="_donation_current_amount" name="_donation_current_amount" value="' . esc_attr($current_amount) . '" />';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Salva os dados de configuração da doação.
     *
     * @param int $post_id ID do produto.
     */
    public function save_donation_product_data($post_id)
    {
        // Verifica se é um produto do tipo doação
        $product_type = isset($_POST['product-type']) ? $_POST['product-type'] : '';
        if ($product_type !== 'donation') {
            return;
        }
        // Salva o tipo de doação
        if (isset($_POST['_donation_type'])) {
            update_post_meta($post_id, '_donation_type', $_POST['_donation_type']);
        }
        // Salva os valores dos botões para doação variável
        if (isset($_POST['_donation_button_values'])) {
            update_post_meta($post_id, '_donation_button_values', $_POST['_donation_button_values']);
        }
        // Salva o texto para doação grátis
        if (isset($_POST['_donation_free_text'])) {
            update_post_meta($post_id, '_donation_free_text', $_POST['_donation_free_text']);
        }
        // Salva a configuração de ocultar campo personalizado
        if (isset($_POST['_donation_hide_custom_amount'])) {
            update_post_meta($post_id, '_donation_hide_custom_amount', 'yes');
        } else {
            update_post_meta($post_id, '_donation_hide_custom_amount', 'no');
        }
        
        // === SALVA DADOS DE META DE DOAÇÃO ===
        // Habilitar meta de doação
        if (isset($_POST['_donation_enable_goal'])) {
            update_post_meta($post_id, '_donation_enable_goal', 'yes');
        } else {
            update_post_meta($post_id, '_donation_enable_goal', 'no');
        }
        
        // Valor da meta
        if (isset($_POST['_donation_goal_amount'])) {
            $goal_amount = wc_format_decimal($_POST['_donation_goal_amount']);
            update_post_meta($post_id, '_donation_goal_amount', $goal_amount);
        }
        
        // Mostrar progresso
        if (isset($_POST['_donation_show_progress'])) {
            update_post_meta($post_id, '_donation_show_progress', 'yes');
        } else {
            update_post_meta($post_id, '_donation_show_progress', 'no');
        }
        
        // === SALVA DADOS DE DATA LIMITE ===
        // Habilitar data limite
        if (isset($_POST['_donation_enable_deadline'])) {
            update_post_meta($post_id, '_donation_enable_deadline', 'yes');
        } else {
            update_post_meta($post_id, '_donation_enable_deadline', 'no');
        }
        
        // Data limite
        if (isset($_POST['_donation_deadline_date'])) {
            $deadline_date = sanitize_text_field($_POST['_donation_deadline_date']);
            
            // Validação: a data deve ser pelo menos 1 minuto no futuro
            if (!empty($deadline_date)) {
                $deadline_timestamp = strtotime($deadline_date);
                $min_timestamp = strtotime('+1 minute');
                
                if ($deadline_timestamp < $min_timestamp) {
                    // Se a data for no passado ou muito próxima, define para 1 minuto no futuro
                    $deadline_date = date('Y-m-d\TH:i', $min_timestamp);
                }
            }
            
            update_post_meta($post_id, '_donation_deadline_date', $deadline_date);
        }
        
        // Mostrar contador regressivo
        if (isset($_POST['_donation_show_countdown'])) {
            update_post_meta($post_id, '_donation_show_countdown', 'yes');
        } else {
            update_post_meta($post_id, '_donation_show_countdown', 'no');
        }
        
        // Mensagem quando prazo expirado
        if (isset($_POST['_donation_deadline_message'])) {
            update_post_meta($post_id, '_donation_deadline_message', sanitize_textarea_field($_POST['_donation_deadline_message']));
        }
        
        // Salva o valor atual da doação (para manter consistência)
        if (isset($_POST['_donation_current_amount'])) {
            $current_amount = wc_format_decimal($_POST['_donation_current_amount']);
            update_post_meta($post_id, '_donation_current_amount', $current_amount);
        }
        
        // Para doação de valor fixo, o preço é salvo automaticamente pelo WooCommerce
        // Para doação variável e grátis, definimos o preço como 0
        $donation_type = isset($_POST['_donation_type']) ? $_POST['_donation_type'] : 'fixed';
        if ($donation_type === 'variable' || $donation_type === 'free') {
            update_post_meta($post_id, '_regular_price', 0);
            update_post_meta($post_id, '_price', 0);
        }
    }

    /**
     * Carrega estilos e scripts para a página de produto no admin.
     */
    public function enqueue_donation_assets()
    {
        global $typenow;
        
        // Carrega apenas na página de produtos no admin
        if ($typenow === 'product') {
            // Carrega o CSS do admin
            wp_enqueue_style(
                'wc-invoice-payment-donation',
                plugin_dir_url(__DIR__) . 'Admin/css/wc-invoice-payment-donation.css',
                array(),
                WC_PAYMENT_INVOICE_VERSION,
                'all'
            );
            
            // Carrega o JavaScript do admin
            wp_enqueue_script(
                'wc-invoice-payment-donation-js',
                plugin_dir_url(__DIR__) . 'Admin/js/wc-invoice-payment-donation.js',
                array('jquery'),
                WC_PAYMENT_INVOICE_VERSION,
                true
            );
        }
    }

    /**
     * Carrega estilos e scripts para o frontend.
     */
    public function enqueue_donation_frontend_assets()
    {
        
    }

    /**
     * Personaliza o texto do botão "adicionar ao carrinho" para produtos de doação.
     */
    public function donation_add_to_cart_text($text, $product)
    {
        if ($product->get_type() === 'donation') {
            $donation_type = $product->get_meta('_donation_type', true);
            if ($donation_type === 'variable') {
                return __('Make a donation', 'wc-invoice-payment');
            }
        }
        return $text;
    }

    /**
     * Personaliza o texto do botão "adicionar ao carrinho" na página individual do produto.
     */
    public function donation_single_add_to_cart_text($text, $product)
    {
        if ($product->get_type() === 'donation') {
            $donation_type = $product->get_meta('_donation_type', true);
            if ($donation_type === 'variable') {
                return __('Make a donation', 'wc-invoice-payment');
            }
        }
        return $text;
    }

    /**
     * Carrega o template personalizado para produtos de doação.
     */
    public function donation_add_to_cart_template()
    {
        wc_get_template('donation.php', array(), '', plugin_dir_path(__DIR__) . 'Public/partials/');
        wp_enqueue_style(
            'wc-invoice-payment-donation-frontend',
            plugin_dir_url(__DIR__) . 'Public/css/wc-invoice-payment-donation-frontend.css',
            array(),
            WC_PAYMENT_INVOICE_VERSION,
            'all'
        );
        wp_enqueue_script(
            'wc-invoice-payment-donation-frontend-js',
            plugin_dir_url(__DIR__) . 'Public/js/wc-invoice-payment-donation-frontend.js',
            array('jquery'),
            WC_PAYMENT_INVOICE_VERSION,
            true
        );
        wp_localize_script(
            'wc-invoice-payment-donation-frontend-js',
            'phpAttributes',
            array(
                'makeDonation' => __('Make a donation', 'wc-invoice-payment'),
            )
        );
    }

    /**
     * Valida os dados ao adicionar produto de doação ao carrinho.
     */
    public function validate_donation_add_to_cart($passed, $product_id, $quantity)
    {
        $product = wc_get_product($product_id);
        if ($product && $product->get_type() === 'donation') {
            // Verifica se a doação está dentro do prazo limite
            if (!$this->is_donation_within_deadline($product_id)) {
                $deadline_message = get_post_meta($product_id, '_donation_deadline_message', true);
                if (!$deadline_message) {
                    $deadline_message = __('The donation period has ended. Thank you for your interest!', 'wc-invoice-payment');
                }
                wc_add_notice($deadline_message, 'error');
                return false;
            }
            
            // Verifica se a meta de doação foi atingida
            $enable_goal = get_post_meta($product_id, '_donation_enable_goal', true);
            if ($enable_goal === 'yes') {
                $progress = $this->get_donation_progress($product_id);
                if ($progress['goal_reached']) {
                    wc_add_notice(__('This donation goal has already been reached. Thank you for your interest!', 'wc-invoice-payment'), 'error');
                    return false;
                }
            }
            
            $donation_type = $product->get_meta('_donation_type', true);
            if ($donation_type === 'variable') {
                if (!isset($_POST['donation_amount']) || empty($_POST['donation_amount'])) {
                    wc_add_notice(__('Please enter a donation amount.', 'wc-invoice-payment'), 'error');
                    return false;
                }
                $amount = floatval($_POST['donation_amount']);
                if ($amount <= 0) {
                    wc_add_notice(__('The donation amount must be greater than zero.', 'wc-invoice-payment'), 'error');
                    return false;
                }
            }
        }
        return $passed;
    }

    /**
     * Adiciona dados customizados do item ao carrinho.
     */
    public function add_donation_cart_item_data($cart_item_data, $product_id, $variation_id)
    {
        $product = wc_get_product($product_id);
        if ($product && $product->get_type() === 'donation') {
            $donation_type = $product->get_meta('_donation_type', true);
            if ($donation_type === 'variable' && isset($_POST['donation_amount'])) {
                $amount = floatval($_POST['donation_amount']);
                $cart_item_data['donation_amount'] = $amount;
                // Adiciona uma chave única para evitar que doações com valores diferentes sejam agrupadas
                $cart_item_data['unique_key'] = md5(microtime() . rand());
            }
        }
        return $cart_item_data;
    }

    /**
     * Personaliza o HTML do preço para produtos de doação grátis.
     * Usa a função wc_price do WooCommerce mas substitui o conteúdo para doações grátis.
     */
    public function customize_donation_price_html($price_html, $product)
    {
        if ($product->get_type() === 'donation') {
            $donation_type = $product->get_meta('_donation_type', true);
            if ($donation_type === 'free') {
                $free_text = $product->get_meta('_donation_free_text', true);
                $display_text = $free_text ?: __('Free', 'wc-invoice-payment');
                return '<span class="woocommerce-Price-amount amount"><bdi>' . esc_html($display_text) . '</bdi></span>';
            }
        }
        return $price_html;
    }


    /**
     * Define o preço do produto de doação no carrinho.
     */
    public function set_donation_cart_item_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['donation_amount'])) {
                $cart_item['data']->set_price($cart_item['donation_amount']);
            }
        }
    }

    /**
     * Calcula o progresso atual da doação baseado em pedidos concluídos.
     * 
     * @param int $product_id ID do produto
     * @return array Array com 'current_amount', 'goal_amount', 'percentage'
     */
    public function get_donation_progress($product_id)
    {
        $goal_amount = get_post_meta($product_id, '_donation_goal_amount', true);
        $current_amount = get_post_meta($product_id, '_donation_current_amount', true);
        
        if (!$current_amount) {
            $current_amount = 0;
        }
        
        if (!$goal_amount || $goal_amount <= 0) {
            return array(
                'current_amount' => 0,
                'goal_amount' => 0,
                'percentage' => 0,
                'goal_reached' => false
            );
        }
        
        $percentage = min(100, ($current_amount / $goal_amount) * 100);
        $goal_reached = $current_amount >= $goal_amount;
        
        return array(
            'current_amount' => $current_amount,
            'goal_amount' => $goal_amount,
            'percentage' => $percentage,
            'goal_reached' => $goal_reached
        );
    }

    /**
     * Renderiza a barra de progresso da doação.
     * 
     * @param int $product_id ID do produto
     * @return string HTML da barra de progresso
     */
    public function render_donation_progress_bar($product_id)
    {
        $enable_goal = get_post_meta($product_id, '_donation_enable_goal', true);
        $show_progress = get_post_meta($product_id, '_donation_show_progress', true);
        
        if ($enable_goal !== 'yes' || $show_progress !== 'yes') {
            return '';
        }
        
        $progress = $this->get_donation_progress($product_id);
        
        if ($progress['goal_amount'] <= 0) {
            return '';
        }
        
        $remaining_amount = max(0, $progress['goal_amount'] - $progress['current_amount']);
        
        // Formatação simples e robusta
        $current_formatted = 'R$ ' . number_format($progress['current_amount'], 2, ',', '.');
        $goal_formatted = 'R$ ' . number_format($progress['goal_amount'], 2, ',', '.');
        $remaining_formatted = 'R$ ' . number_format($remaining_amount, 2, ',', '.');
        
        ob_start();
        ?>
        <div class="donation-progress-container">
            <div class="donation-progress-header">
                <div class="donation-progress-title">
                    <?php esc_html_e('Donation Progress', 'wc-invoice-payment'); ?>
                </div>
                <div class="donation-progress-percentage">
                    <?php printf(esc_html__('%s%% funded', 'wc-invoice-payment'), number_format($progress['percentage'], 1)); ?>
                </div>
            </div>
            
            <div class="donation-progress-bar">
                <div class="donation-progress-fill" style="width: <?php echo esc_attr($progress['percentage']); ?>%"></div>
            </div>
            
            <div class="donation-progress-details">
                <div class="donation-current-info">
                    <span class="donation-label"><?php esc_html_e('Raised:', 'wc-invoice-payment'); ?></span>
                    <span class="donation-amount"><?php echo esc_html($current_formatted); ?></span>
                </div>
                <div class="donation-goal-info">
                    <span class="donation-label"><?php esc_html_e('Goal:', 'wc-invoice-payment'); ?></span>
                    <span class="donation-amount"><?php echo esc_html($goal_formatted); ?></span>
                </div>
                <?php if (!$progress['goal_reached']) : ?>
                <div class="donation-remaining-info">
                    <span class="donation-label"><?php esc_html_e('Remaining:', 'wc-invoice-payment'); ?></span>
                    <span class="donation-amount"><?php echo esc_html($remaining_formatted); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($progress['goal_reached']) : ?>
                <div class="donation-goal-reached">
                    <?php esc_html_e('Goal reached! Thank you for your support.', 'wc-invoice-payment'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Atualiza o progresso da doação quando um pedido é concluído.
     * 
     * @param int $order_id ID do pedido
     */
    public function update_donation_progress_on_order_complete($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if (!$product || $product->get_type() !== 'donation') {
                continue;
            }
            
            $enable_goal = get_post_meta($product_id, '_donation_enable_goal', true);
            if ($enable_goal !== 'yes') {
                continue;
            }
            
            $current_amount = get_post_meta($product_id, '_donation_current_amount', true);
            if (!$current_amount) {
                $current_amount = 0;
            }
            
            $item_total = $item->get_total();
            $new_current_amount = $current_amount + $item_total;
            
            update_post_meta($product_id, '_donation_current_amount', $new_current_amount);
            
            // Verifica se a meta foi atingida
            $goal_amount = get_post_meta($product_id, '_donation_goal_amount', true);
            if ($goal_amount && $new_current_amount >= $goal_amount) {
                // Meta atingida - pode implementar ações específicas aqui se necessário
                do_action('wc_invoice_payment_donation_goal_reached', $product_id, $new_current_amount, $goal_amount);
            }
        }
    }

    /**
     * Verifica se a doação está dentro do prazo limite.
     * 
     * @param int $product_id ID do produto
     * @return bool True se dentro do prazo, false se expirado
     */
    public function is_donation_within_deadline($product_id)
    {
        $enable_deadline = get_post_meta($product_id, '_donation_enable_deadline', true);
        if ($enable_deadline !== 'yes') {
            return true; // Sem limite de prazo
        }
        
        $deadline_date = get_post_meta($product_id, '_donation_deadline_date', true);
        if (!$deadline_date) {
            return true; // Sem data definida
        }
        
        $current_time = current_time('timestamp');
        // Se o formato já inclui hora (formato datetime-local), usa diretamente
        // Senão, adiciona 23:59:59 para compatibilidade com formato antigo
        if (strpos($deadline_date, 'T') !== false) {
            $deadline_timestamp = strtotime($deadline_date);
        } else {
            $deadline_timestamp = strtotime($deadline_date . ' 23:59:59');
        }
        
        return $current_time <= $deadline_timestamp;
    }

    /**
     * Calcula o tempo restante até o prazo limite.
     * 
     * @param int $product_id ID do produto
     * @return array Array com dias, horas, minutos e segundos restantes
     */
    public function get_deadline_time_remaining($product_id)
    {
        $enable_deadline = get_post_meta($product_id, '_donation_enable_deadline', true);
        if ($enable_deadline !== 'yes') {
            return null;
        }
        
        $deadline_date = get_post_meta($product_id, '_donation_deadline_date', true);
        if (!$deadline_date) {
            return null;
        }
        
        $current_time = current_time('timestamp');
        // Se o formato já inclui hora (formato datetime-local), usa diretamente
        // Senão, adiciona 23:59:59 para compatibilidade com formato antigo
        if (strpos($deadline_date, 'T') !== false) {
            $deadline_timestamp = strtotime($deadline_date);
        } else {
            $deadline_timestamp = strtotime($deadline_date . ' 23:59:59');
        }
        $time_diff = $deadline_timestamp - $current_time;
        
        if ($time_diff <= 0) {
            return array(
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0,
                'expired' => true
            );
        }
        
        return array(
            'days' => floor($time_diff / 86400),
            'hours' => floor(($time_diff % 86400) / 3600),
            'minutes' => floor(($time_diff % 3600) / 60),
            'seconds' => $time_diff % 60,
            'expired' => false
        );
    }

    /**
     * Renderiza o contador regressivo da doação.
     * 
     * @param int $product_id ID do produto
     * @return string HTML do contador regressivo
     */
    public function render_donation_countdown($product_id)
    {
        $enable_deadline = get_post_meta($product_id, '_donation_enable_deadline', true);
        $show_countdown = get_post_meta($product_id, '_donation_show_countdown', true);
        
        if ($enable_deadline !== 'yes' || $show_countdown !== 'yes') {
            return '';
        }
        
        $time_remaining = $this->get_deadline_time_remaining($product_id);
        if (!$time_remaining) {
            return '';
        }
        
        if ($time_remaining['expired']) {
            $deadline_message = get_post_meta($product_id, '_donation_deadline_message', true);
            if (!$deadline_message) {
                $deadline_message = __('The donation period has ended. Thank you for your interest!', 'wc-invoice-payment');
            }
            
            ob_start();
            ?>
            <div class="donation-deadline-expired">
                <div class="donation-deadline-message">
                    <?php echo esc_html($deadline_message); ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
        
        ob_start();
        ?>
        <div class="donation-countdown-container">
            <div class="donation-countdown-title">
                <?php esc_html_e('Time remaining:', 'wc-invoice-payment'); ?>
            </div>
            <div class="donation-countdown-timer" data-deadline="<?php echo esc_attr(get_post_meta($product_id, '_donation_deadline_date', true)); ?>">
                <div class="countdown-item">
                    <span class="countdown-number" id="countdown-days"><?php echo esc_html($time_remaining['days']); ?></span>
                    <span class="countdown-label"><?php esc_html_e('Days', 'wc-invoice-payment'); ?></span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="countdown-hours"><?php echo esc_html($time_remaining['hours']); ?></span>
                    <span class="countdown-label"><?php esc_html_e('Hours', 'wc-invoice-payment'); ?></span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="countdown-minutes"><?php echo esc_html($time_remaining['minutes']); ?></span>
                    <span class="countdown-label"><?php esc_html_e('Minutes', 'wc-invoice-payment'); ?></span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="countdown-seconds"><?php echo esc_html($time_remaining['seconds']); ?></span>
                    <span class="countdown-label"><?php esc_html_e('Seconds', 'wc-invoice-payment'); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Função para resetar o progresso da doação (útil para testes).
     * 
     * @param int $product_id ID do produto
     */
    public function reset_donation_progress($product_id)
    {
        update_post_meta($product_id, '_donation_current_amount', 0);
    }

    /**
     * Processa doação anônima - hook woocommerce_checkout_order_processed
     * 
     * @param int $order_id
     * @param array $posted_data
     * @param WC_Order $order
     */
    public function process_anonymous_donation($order_id, $posted_data, $order)
    {
        // Verifica se existe o campo de doação anônima nos dados enviados
        $is_anonymous = false;
        
        if (isset($posted_data['anonymous_donation']) && $posted_data['anonymous_donation'] === '1') {
            $is_anonymous = true;
        }
        
        // Verifica também nos dados $_POST
        if (isset($_POST['anonymous_donation']) && $_POST['anonymous_donation'] === '1') {
            $is_anonymous = true;
        }
        
        if ($is_anonymous) {
            $this->remove_order_address_data($order_id);
        }
    }

    /**
     * Processa doação anônima - hook woocommerce_rest_checkout_process_payment_with_context
     * 
     * @param \PaymentContext $context
     * @param \PaymentResult $result
     */
    public function process_anonymous_donation_rest($context, $result)
    {
        $request = $context->payment_data;
        
        // Verifica se existe o campo de doação anônima nos dados da requisição
        if (isset($request['anonymous_donation']) && $request['anonymous_donation'] === '1') {
            $order = $result->payment_details['order'] ?? null;
            if ($order && method_exists($order, 'get_id')) {
                $this->remove_order_address_data($order->get_id());
            }
        }
    }

    /**
     * Processa doação anônima - hook woocommerce_store_api_checkout_order_processed  
     * 
     * @param WC_Order $order
     */
    public function process_anonymous_donation_blocks($order)
    {
        // Para o checkout em blocos, verificamos se há meta de doação anônima na sessão ou na order
        $is_anonymous = false;
        
        // Verifica se já foi marcado como doação anônima (pode ter sido processado antes)
        $anonymous_meta = $order->get_meta('_anonymous_donation_temp');
        if ($anonymous_meta === 'yes') {
            $is_anonymous = true;
        }
        
        // Verifica na sessão do WooCommerce
        if (function_exists('WC') && \WC()->session) {
            $session_anonymous = \WC()->session->get('anonymous_donation');
            if ($session_anonymous === 'yes') {
                $is_anonymous = true;
                // Remove da sessão após usar
                \WC()->session->__unset('anonymous_donation');
            }
        }
        
        // Verifica se existe no request atual
        $request_data = $_POST;
        if (isset($request_data['anonymous_donation']) && $request_data['anonymous_donation'] === '1') {
            $is_anonymous = true;
        }
        
        // Verifica nos dados de input do request
        $input = file_get_contents('php://input');
        if ($input) {
            $json_data = json_decode($input, true);
            if (isset($json_data['anonymous_donation']) && $json_data['anonymous_donation'] === '1') {
                $is_anonymous = true;
            }
        }
        
        if ($is_anonymous) {
            $this->remove_order_address_data($order->get_id());
        }
    }

    /**
     * Captura dados de doação anônima da Store API
     * 
     * @param WP_Error $errors
     * @param WP_REST_Request $request
     * @return WP_Error
     */
    public function capture_anonymous_donation_data($errors, $request)
    {
        $params = $request->get_params();
        
        // Se é doação anônima, armazena temporariamente na sessão
        if (isset($params['anonymous_donation']) && $params['anonymous_donation'] === '1') {
            // Salva na sessão do WooCommerce
            if (function_exists('WC') && \WC()->session) {
                \WC()->session->set('anonymous_donation', 'yes');
            }
        }
        
        return $errors;
    }

    /**
     * Remove dados de endereço da order para doação anônima
     * 
     * @param int $order_id
     */
    private function remove_order_address_data($order_id)
    {
        $order = \wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        // Verifica se o pedido contém produtos de doação
        $has_donation = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_type() === 'donation') {
                $has_donation = true;
                break;
            }
        }

        // Se não tem produto de doação, não remove os dados
        if (!$has_donation) {
            return;
        }

        // Salva um meta indicando que é doação anônima
        $order->update_meta_data('_anonymous_donation', 'yes');

        // Remove dados pessoais de billing
        $order->set_billing_first_name('');
        $order->set_billing_last_name('');
        $order->set_billing_company('');
        $order->set_billing_address_1('');
        $order->set_billing_address_2('');
        $order->set_billing_city('');
        $order->set_billing_state('');
        $order->set_billing_postcode('');
        $order->set_billing_country('');
        $order->set_billing_phone('');

        // Remove dados pessoais de shipping
        $order->set_shipping_first_name('');
        $order->set_shipping_last_name('');
        $order->set_shipping_company('');
        $order->set_shipping_address_1('');
        $order->set_shipping_address_2('');
        $order->set_shipping_city('');
        $order->set_shipping_state('');
        $order->set_shipping_postcode('');
        $order->set_shipping_country('');
        $order->set_shipping_phone('');

        // Adiciona nota no pedido informando que é doação anônima
        $order->add_order_note('Doação anônima: Dados de endereço do cliente foram removidos para proteger a privacidade do doador.');

        // Salva as alterações
        $order->save();

        // Log para debug (opcional)
        if (function_exists('wc_get_logger')) {
            $logger = \wc_get_logger();
            $logger->info('Dados de endereço removidos para doação anônima', array('source' => 'wc-invoice-payment'));
        }
    }

}
