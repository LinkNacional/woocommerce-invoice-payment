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

        wc_get_template(
            '/dokanSettings.php',
            array(
                '_donation_type' => $_donation_type,
                '_regular_price'      => $_regular_price,
                '_donation_button_values' => $_donation_button_values,
                '_donation_hide_custom_amount' => $_donation_hide_custom_amount,
                '_donation_free_text' => $_donation_free_text,
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
            wp_localize_script('wcInvoicePaymentDonationDokanScript', 'lknWcipDonationVariables', array(
            ));
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
                        if ($donation_type !== 'free' && $donation_type !== 'variable') {
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
                'fixed'    => __('Fixed Amount (Donate a fixed value item)', 'wc-invoice-payment'),
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
            'id'                => '_regular_price',
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
        if (is_product() || is_shop() || is_product_category() || is_woocommerce()) {
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
    }

    /**
     * Valida os dados ao adicionar produto de doação ao carrinho.
     */
    public function validate_donation_add_to_cart($passed, $product_id, $quantity)
    {
        $product = wc_get_product($product_id);
        if ($product && $product->get_type() === 'donation') {
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

}
