<?php

namespace LknWc\WcInvoicePayment\Includes;

/**
 * Classe responsável por gerenciar o tipo de produto "Doação".
 *
 * @since 1.0.0
 */
final class WcPaymentInvoiceDonation
{
    /**
     * Adiciona o tipo de produto "Doação" ao WooCommerce.
     *
     * @param array $types Array de tipos de produtos existentes.
     * @return array Array de tipos de produtos com o novo tipo "donation" adicionado.
     */
    public function add_donation_product_type($types)
    {
        $types['donation'] = __('Doação', 'wc-invoice-payment');
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
        $options['virtual']['wrapper_class'] .= ' show_if_donation';
        $options['downloadable']['wrapper_class'] .= ' show_if_donation';
        
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
        $tabs['donation'] = array(
            'label'    => __('Doação', 'wc-invoice-payment'),
            'target'   => 'donation_product_data',
            'class'    => array('show_if_donation'),
            'priority' => 21,
        );
        
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
        
        // Tipo de doação
        woocommerce_wp_select(array(
            'id'          => '_donation_type',
            'label'       => __('Tipo de doação', 'wc-invoice-payment'),
            'options'     => array(
                'fixed'    => __('Valor Fixo', 'wc-invoice-payment'),
                'variable' => __('Valor Variável (Doe o Quanto Quiser)', 'wc-invoice-payment'),
            ),
            'desc_tip'    => true,
            'description' => __('Selecione o tipo de doação.', 'wc-invoice-payment'),
        ));
        
        // Campo de preço para valor fixo (reutiliza o campo padrão do WooCommerce)
        woocommerce_wp_text_input(array(
            'id'                => '_regular_price',
            'label'             => __('Valor da doação (' . get_woocommerce_currency_symbol() . ')', 'wc-invoice-payment'),
            'placeholder'       => wc_format_localized_price(0),
            'description'       => __('Defina o valor fixo da doação.', 'wc-invoice-payment'),
            'type'              => 'text',
            'desc_tip'          => true,
            'data_type'         => 'price',
            'wrapper_class'     => 'show_if_donation_fixed',
        ));
        
        // Valores em botões para valor variável
        woocommerce_wp_textarea_input(array(
            'id'            => '_donation_button_values',
            'label'         => __('Valores em botões', 'wc-invoice-payment'),
            'description'   => __('Digite o valor dos botões de valores definidos separado por vírgula. Ex: 10, 20, 25', 'wc-invoice-payment'),
            'desc_tip'      => true,
            'wrapper_class' => 'show_if_donation_variable',
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
        $product_type = isset($_POST['product-type']) ? wc_clean($_POST['product-type']) : '';
        
        if ($product_type !== 'donation') {
            return;
        }
        
        // Salva o tipo de doação
        if (isset($_POST['_donation_type'])) {
            update_post_meta($post_id, '_donation_type', wc_clean($_POST['_donation_type']));
        }
        
        // Salva os valores dos botões para doação variável
        if (isset($_POST['_donation_button_values'])) {
            update_post_meta($post_id, '_donation_button_values', wc_clean($_POST['_donation_button_values']));
        }
        
        // Para doação de valor fixo, o preço é salvo automaticamente pelo WooCommerce
        // Para doação variável, definimos o preço como 0
        $donation_type = isset($_POST['_donation_type']) ? wc_clean($_POST['_donation_type']) : 'fixed';
        
        if ($donation_type === 'variable') {
            update_post_meta($post_id, '_regular_price', 0);
            update_post_meta($post_id, '_price', 0);
        }
    }

    /**
     * Carrega estilos e scripts para a página de produto.
     */
    public function enqueue_donation_assets()
    {
        global $typenow;
        
        // Carrega apenas na página de produtos
        if ($typenow === 'product') {
            // Carrega o CSS
            wp_enqueue_style(
                'wc-invoice-payment-donation',
                plugin_dir_url(__DIR__) . 'Admin/css/wc-invoice-payment-donation.css',
                array(),
                WC_PAYMENT_INVOICE_VERSION,
                'all'
            );
            
            // Carrega o JavaScript
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
     * Personaliza o texto do botão "adicionar ao carrinho" para produtos de doação.
     */
    public function donation_add_to_cart_text($text, $product)
    {
        if ($product->get_type() === 'donation') {
            $donation_type = $product->get_meta('_donation_type', true);
            if ($donation_type === 'variable') {
                return __('Fazer uma doação', 'wc-invoice-payment');
            }
        }
        return $text;
    }

    /**
     * Personaliza o texto do botão "adicionar ao carrinho" na página individual do produto.
     */
    public function donation_single_add_to_cart_text($text, $product)
    {
        //Se a configuração _donation_type for variable
        if ($product->get_type() === 'donation') {
            $donation_type = $product->get_meta('_donation_type', true);
            if ($donation_type === 'variable') {
                return __('Fazer uma doação', 'wc-invoice-payment');
            }
        }
        return $text;
    }

    /**
     * Carrega o template personalizado para produtos de doação.
     */
    public function donation_add_to_cart_template()
    {
        wc_get_template('single-product/add-to-cart/donation.php', array(), '', plugin_dir_path(__DIR__) . 'Public/partials/');
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
                    wc_add_notice(__('Por favor, digite um valor para a doação.', 'wc-invoice-payment'), 'error');
                    return false;
                }
                
                $amount = floatval($_POST['donation_amount']);
                if ($amount <= 0) {
                    wc_add_notice(__('O valor da doação deve ser maior que zero.', 'wc-invoice-payment'), 'error');
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
     * Exibe os dados customizados no carrinho.
     */
    public function display_donation_cart_item_data($item_data, $cart_item)
    {
        if (isset($cart_item['donation_amount'])) {
            $item_data[] = array(
                'key'   => __('Valor da doação', 'wc-invoice-payment'),
                'value' => wc_price($cart_item['donation_amount']),
            );
        }

        return $item_data;
    }
}
