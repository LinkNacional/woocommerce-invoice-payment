<?php

namespace LknWc\WcInvoicePayment\Includes;

final class WcPaymentInvoiceWhatsAppButton
{
    public $loader;

    public function __construct($loader)
    {
        $this->loader = $loader;
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Adiciona o botão WhatsApp nas páginas de produtos
        $this->loader->add_action('woocommerce_after_add_to_cart_button', $this, 'add_whatsapp_buy_button');
        
        // Carrega CSS e JS para o botão WhatsApp
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_whatsapp_assets');
    }

    /**
     * Adiciona o botão "Comprar pelo WhatsApp" após o botão "Adicionar ao carrinho"
     */
    public function add_whatsapp_buy_button()
    {
        // Verifica se a funcionalidade está habilitada
        $whatsapp_enabled = get_option('lkn_wcip_whatsapp_buy_button_enabled', 'no');
        if ($whatsapp_enabled !== 'yes') {
            return;
        }

        global $product;
        
        // Verifica se o produto existe
        if (!$product) {
            return;
        }

        // Obtém informações do produto
        $product_name = $product->get_name();
        $product_price = wc_price($product->get_price());
        $product_url = get_permalink($product->get_id());
        
        // Remove tags HTML do preço para a mensagem
        $clean_price = wp_strip_all_tags($product_price);
        
        // Obtém o template da mensagem das configurações
        $message_template = get_option('lkn_wcip_whatsapp_message_text', __('Olá! Tenho interesse no produto: %productName% - Preço: %price%. Link: %productLink%', 'wc-invoice-payment'));
        
        // Monta a mensagem substituindo os parâmetros
        $message = str_replace(
            array('%productName%', '%price%', '%productLink%'),
            array($product_name, $clean_price, $product_url),
            $message_template
        );
        
        // Codifica a mensagem para URL
        $encoded_message = urlencode($message);
        
        // Obtém o número de telefone da configuração
        $phone_number = get_option('lkn_wcip_whatsapp_phone_number', '');
        
        // URL do WhatsApp - com ou sem número específico
        if (!empty($phone_number)) {
            $whatsapp_url = "https://wa.me/" . $phone_number . "?text=" . $encoded_message;
        } else {
            $whatsapp_url = "https://wa.me/?text=" . $encoded_message;
        }
        
        ?>
        <div class="lkn-whatsapp-buy-wrapper">
            <a href="<?php echo esc_url($whatsapp_url); ?>" 
               target="_blank" 
               rel="noopener noreferrer"
               class="lkn-whatsapp-buy-button">
                <svg class="lkn-whatsapp-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                </svg>
                <?php 
                    $button_text = get_option('lkn_wcip_whatsapp_button_text', __('Comprar pelo WhatsApp', 'wc-invoice-payment'));
                    echo esc_html($button_text); 
                ?>
            </a>
        </div>
        <?php
    }

    /**
     * Carrega os assets (CSS e JS) para o botão WhatsApp
     */
    public function enqueue_whatsapp_assets()
    {
        // Verifica se a funcionalidade está habilitada
        $whatsapp_enabled = get_option('lkn_wcip_whatsapp_buy_button_enabled', 'no');
        if ($whatsapp_enabled !== 'yes') {
            return;
        }

        // Só carrega nas páginas de produtos
        if (!is_product()) {
            return;
        }

        // Carrega o CSS do botão WhatsApp
        wp_enqueue_style(
            'lkn-whatsapp-button',
            WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-whatsapp-button.css',
            array(),
            WC_PAYMENT_INVOICE_VERSION
        );
    }
}