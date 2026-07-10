<?php

namespace LknWc\WcInvoicePayment\Includes;
use WC_Order;

final class WcPaymentInvoiceFeeOrDiscount
{
    private $rendered_products = [];

    public function __construct() {
        add_action('wp_ajax_lkn_wcip_set_default_gateway', array($this, 'ajaxSetDefaultGateway'));
        add_action('wp_ajax_nopriv_lkn_wcip_set_default_gateway', array($this, 'ajaxSetDefaultGateway'));
    }

    public function ajaxSetDefaultGateway() {
        if (!isset($_POST['gateway'])) {
            wp_send_json_error();
        }
        $gateway = sanitize_text_field(wp_unslash($_POST['gateway']));
        WC()->session->set('chosen_payment_method', $gateway);
        wp_send_json_success();
    }

    public function caclulateCart($cart) {
        if(isset(WC()->session->chosen_payment_method)){
            $chosenMethod = WC()->session->chosen_payment_method;
            $active = get_option('lkn_wcip_fee_or_discount_method_activated_' . $chosenMethod);
            $type = get_option('lkn_wcip_fee_or_discount_type_' . $chosenMethod);
            $percentOrFixed = get_option('lkn_wcip_fee_or_discount_percent_fixed_' . $chosenMethod);
            $value = get_option('lkn_wcip_fee_or_discount_value_' . $chosenMethod);

            if($active == 'yes'){
                $total = $cart->get_subtotal(); 

                if ($percentOrFixed === 'percent' || $percentOrFixed === 'percentage') {
                    $amount = ($total * $value) / 100;
                } else {
                    $amount = $value;
                }

                if ($type === 'fee') {
                    $cart->add_fee(__('Fee', 'wc-invoice-payment'), $amount, true);
                } elseif ($type === 'discount') {
                    $cart->add_fee(__('Discount', 'wc-invoice-payment'), -$amount, true);
                }
            }
        }
    }
    
    public function loadScripts(){
        if (is_checkout() && WC()->payment_gateways() && ! empty(WC()->payment_gateways()->get_available_payment_gateways())) {
    
            // Obtem todos os métodos disponíveis no checkout
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
            $data = [];
    
            foreach ($gateways as $gateway_id => $gateway) {
                $active = get_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id);
                $type = get_option('lkn_wcip_fee_or_discount_type_' . $gateway_id); // 'fee' ou 'discount'
                $percentOrFixed = get_option('lkn_wcip_fee_or_discount_percent_fixed_' . $gateway_id); // 'percent' ou 'fixed'
                $value = (float) get_option('lkn_wcip_fee_or_discount_value_' . $gateway_id);

                if($percentOrFixed == 'percent' || $percentOrFixed == 'percentage'){
                    $cartTotal = (float) WC()->cart->get_subtotal( '' );
                    $value = ($value / 100) * $cartTotal;
                }
    
                if ($active === 'yes') {
                    $gateway_icon_name = get_option('lkn_wcip_fee_or_discount_icon_' . $gateway_id, 'wallet');
                    $gateway_icon_url = WC_PAYMENT_INVOICE_ROOT_URL . 'Public/images/' . $gateway_icon_name . '.svg';

                    $data[$gateway_id] = [
                        'type' => $type,
                        'mode' => $percentOrFixed,
                        'value' => $value,
                        'icon' => $gateway_icon_url,
                        'label' => sprintf(
                            '<span class="wc-invoice-checkout-badge %s">%s %s <img src="%s" class="wc-invoice-checkout-badge-icon" alt=""></span>',
                            $type === 'fee' ? 'checkout-fee' : 'checkout-discount',
                            $type === 'fee' ? __('Fee', 'wc-invoice-payment') : __('Discount', 'wc-invoice-payment'),
                            wp_strip_all_tags(wc_price($value)),
                            esc_url($gateway_icon_url)
                        ),
                    ];
                }
            }
    
            wp_enqueue_style(
                'wc-invoice-payment-method-prices',
                WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-method-prices.css',
                array(),
                WC_PAYMENT_INVOICE_VERSION,
                'all'
            );
            wp_enqueue_script(
                'wcInvoicePaymentFeeOrDiscountScript',
                WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-fee-or-discount.js',
                ['jquery', 'wp-api'],
                WC_PAYMENT_INVOICE_VERSION,
                false
            );
    
            wp_localize_script('wcInvoicePaymentFeeOrDiscountScript', 'wcInvoicePaymentFeeOrDiscountVariables', [
                'methods' => $data,
                'translations' => [
                    'fee' => __('Fee', 'wc-invoice-payment'),
                    'discount' => __('Discount', 'wc-invoice-payment'),
                ],
                'showFeeOption' => get_option('lkn_wcip_show_fee_activated'),
                'showDiscountOption' => get_option('lkn_wcip_show_discount_activated'),
            ]);
        }
    }

    /**
     * Calcula o preço de um produto com fee/discount aplicado para um método de pagamento
     *
     * @param float $product_price Preço base do produto
     * @param string $gateway_id ID do método de pagamento
     * @return float Preço com fee/discount aplicado
     */
    public function calculateProductPriceWithFeeOrDiscount($product_price, $gateway_id) {
        $active = get_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id);
        $type = get_option('lkn_wcip_fee_or_discount_type_' . $gateway_id);
        $percentOrFixed = get_option('lkn_wcip_fee_or_discount_percent_fixed_' . $gateway_id);
        $value = (float) get_option('lkn_wcip_fee_or_discount_value_' . $gateway_id);

        if ($active !== 'yes' || !$value) {
            return $product_price;
        }

        if ($percentOrFixed === 'percent' || $percentOrFixed === 'percentage') {
            $amount = ($product_price * $value) / 100;
        } else {
            $amount = $value;
        }

        if ($type === 'fee') {
            return $product_price + $amount;
        } elseif ($type === 'discount') {
            return max(0, $product_price - $amount); // Evita preços negativos
        }

        return $product_price;
    }

    /**
     * Obtém informações de parcelamento para o gateway Rede Credit
     *
     * @param float $product_price Preço do produto
     * @return string|false String com informação de parcelamento ou false se não houver
     */
    private function getRedeInstallmentInfo($product_price, $product = null) {
        $settings = get_option('woocommerce_rede_credit_settings', array());

        if (empty($settings)) {
            $settings = get_option('woocommerce_maxipago_credit_settings', array());
        }

        if (empty($settings)) {
            return false;
        }

        $globalMaxParcels = isset($settings['max_parcels_number']) ? (int) $settings['max_parcels_number'] : 12;
        $minParcelValue   = isset($settings['min_parcels_value']) ? (int) $settings['min_parcels_value'] : 5;

        // Limite por produto tem prioridade
        $maxParcels = $globalMaxParcels;
        if ($product) {
            $productLimit = get_post_meta($product->get_id(), 'lknRedeProdutctInterest', true);
            if ($productLimit !== 'default' && $productLimit !== '' && $productLimit !== false && $productLimit !== null) {
                $productLimit = (int) $productLimit;
                if ($productLimit === 0) {
                    return false; // Produto sem parcelamento
                }
                $maxParcels = $productLimit;
            }
        }

        if ( ! $maxParcels || $product_price < $minParcelValue) {
            return false;
        }

        // Respeita valor mínimo da parcela
        $effectiveMax = min($maxParcels, max(1, (int) floor($product_price / $minParcelValue)));

        return sprintf(
            'em até %dx de %s',
            $effectiveMax,
            wc_price($product_price / $effectiveMax)
        );
    }

    /**
     * Obtém informações de parcelamento para o gateway Cielo Credit
     *
     * @param float $product_price Preço do produto
     * @return string|false String com informação de parcelamento ou false se não houver
     */
    private function getCieloCreditInstallmentInfo($product_price, $product = null) {
        $settings = get_option('woocommerce_lkn_cielo_credit_settings', array());

        if (empty($settings)) {
            return false;
        }

        return $this->getCieloInstallmentInfoCommon($product_price, $settings, $product);
    }

    private function getCieloDebitInstallmentInfo($product_price, $product = null) {
        $settings = get_option('woocommerce_lkn_cielo_debit_settings', array());

        if (empty($settings)) {
            return false;
        }

        return $this->getCieloInstallmentInfoCommon($product_price, $settings, $product);
    }

    /**
     * Lógica comum de parcelamento para os gateways Cielo (Credit e Debit).
     */
    private function getCieloInstallmentInfoCommon($product_price, $settings, $product = null) {
        $installmentActive = isset($settings['installment_payment']) ? $settings['installment_payment'] : 'no';
        if ($installmentActive !== 'yes') {
            return false;
        }

        $globalMaxParcels = isset($settings['installment_limit']) ? (int) $settings['installment_limit'] : 12;
        $minParcelValue   = isset($settings['installment_min']) ? (float) str_replace(',', '.', $settings['installment_min']) : 5.0;

        // Limite por produto tem prioridade
        $maxParcels = $globalMaxParcels;
        if ($product) {
            $productLimit = get_post_meta($product->get_id(), 'lknCieloApiProProdutctInterest', true);
            if ($productLimit !== 'default' && $productLimit !== '' && $productLimit !== false && $productLimit !== null) {
                $productLimit = (int) $productLimit;
                if ($productLimit === 0) {
                    return false; // Produto sem parcelamento
                }
                $maxParcels = $productLimit;
            }
        }

        if ( ! $maxParcels || $product_price < $minParcelValue) {
            return false;
        }

        // Respeita valor mínimo da parcela
        $effectiveMax = min($maxParcels, max(1, (int) floor($product_price / $minParcelValue)));

        return sprintf(
            'em até %dx de %s',
            $effectiveMax,
            wc_price($product_price / $effectiveMax)
        );
    }

    /**
     * Adiciona preços com fee/discount após o preço principal do produto
     *
     * @param string $price_html HTML do preço atual
     * @param WC_Product $product Produto do WooCommerce
     * @return string HTML do preço modificado
     */
    public function addPaymentMethodPrices($price_html, $product) {
        // Não exibe na área administrativa (wp-admin)
        if (is_admin()) {
            return $price_html;
        }
        
        $pid = $product->get_id();
        $type = $product->get_type();
        $isVar = $product->is_type('variable') ? 'VARIABLE' : ($product->is_type('variation') ? 'VARIATION' : 'SIMPLE');
        
        // Evita duplicação: cada produto só é processado uma vez por requisição
        if (isset($this->rendered_products[$pid])) {
            return $price_html;
        }
        
        // Só exibe em páginas de produto individual ou loops de produto
        if (!is_product() && !wc_get_loop_prop('is_shortcode') && !is_shop() && !is_product_category()) {
            return $price_html;
        }
        
        // Verifica se há gateways ativos
        if (!WC()->payment_gateways()) {
            return $price_html;
        }

        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        if (empty($gateways)) {
            return $price_html;
        }

        $product_price = (float) $product->get_price();
        if (!$product_price) {
            return $price_html;
        }
        
        if(stripos($price_html, 'range:') !== false){
            return $price_html;
        }
        
        $additional_prices = [];
        $gateway_final_prices = []; // guarda o preço calculado de cada gateway
        wp_enqueue_style(
            'wc-invoice-payment-method-prices',
            WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-method-prices.css',
            array(),
            WC_PAYMENT_INVOICE_VERSION,
            'all'
        );
        wp_enqueue_script(
            'wc-invoice-method-prices',
            WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-method-prices.js',
            array(),
            WC_PAYMENT_INVOICE_VERSION,
            true
        );
        wp_localize_script('wc-invoice-method-prices', 'wcInvoiceMethodPrices', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'confirmText' => __('Change default payment method to', 'wc-invoice-payment'),
        ));
        foreach ($gateways as $gateway_id => $gateway) {
            // Verifica se deve mostrar o preço para este método
            $show_price = get_option('lkn_wcip_fee_or_discount_show_price_' . $gateway_id);
            
            if ($show_price === 'yes') {
                $final_price = $this->calculateProductPriceWithFeeOrDiscount($product_price, $gateway_id);
                $gateway_final_prices[$gateway_id] = $final_price;
                $type = get_option('lkn_wcip_fee_or_discount_type_' . $gateway_id);

                // Só adiciona se o preço for diferente do original
                $gateway_title = isset($gateway->settings->title) ? $gateway->settings->title : $gateway->title;
                
                // Adiciona classe CSS baseada no tipo (fee ou discount)
                $css_class = $type === 'fee' ? 'fee-type' : 'discount-type';
                
                // Obtém o ícone configurado para este gateway de pagamento
                $gateway_icon_name = get_option('lkn_wcip_fee_or_discount_icon_' . $gateway_id, 'wallet');
                $gateway_icon_url = WC_PAYMENT_INVOICE_ROOT_URL . 'Public/images/' . $gateway_icon_name . '.svg';
                $gateway_icon = sprintf(
                    '<img class="lknWcInvoiceGatewayIcon" src="%s" alt="%s">',
                    esc_url($gateway_icon_url),
                    esc_attr($gateway_icon_name)
                );
                
                // Verifica se há informações de parcelamento para gateways específicos
                $installment_info = '';
                if ($gateway_id === 'rede_credit') {
                    $installment_info = $this->getRedeInstallmentInfo($final_price, $product);
                } elseif ($gateway_id === 'lkn_cielo_credit') {
                    $installment_info = $this->getCieloCreditInstallmentInfo($final_price, $product);
                } elseif ($gateway_id === 'lkn_cielo_debit') {
                    $installment_info = $this->getCieloDebitInstallmentInfo($final_price, $product);
                }
                
                // Label de taxa/desconto do invoice plugin (texto puro, sem tags extras)
                $fee_label = '';
                if ($final_price != $product_price) {
                    $fee_type   = get_option('lkn_wcip_fee_or_discount_type_' . $gateway_id);
                    $fee_value  = (float) get_option('lkn_wcip_fee_or_discount_value_' . $gateway_id);
                    $fee_mode   = get_option('lkn_wcip_fee_or_discount_percent_fixed_' . $gateway_id);
                    $isPercent  = ($fee_mode === 'percent' || $fee_mode === 'percentage');
                    
                    if ($fee_value > 0) {
                        $amountStr = $isPercent
                            ? $fee_value . '%'
                            : wp_strip_all_tags(wc_price($fee_value));
                        
                        if ($fee_type === 'fee') {
                            $label = sprintf(
                                /* translators: %s: fee amount (e.g. "R$ 1,00" or "5%") */
                                __('Fee: %s', 'wc-invoice-payment'),
                                $amountStr
                            );
                        } else {
                            $label = sprintf(
                                /* translators: %s: discount amount */
                                __('Discount: %s', 'wc-invoice-payment'),
                                $amountStr
                            );
                        }
                        $fee_label = ' <span class="wc-invoice-fee-label">(' . $label . ')</span>';
                    }
                }
                
                // Monta a informação do preço com o ícone configurado
                if (!empty($installment_info)) {
                    $price_info = sprintf(
                        '%s %s%s no %s',
                        $gateway_icon,
                        $installment_info,
                        $fee_label,
                        esc_html($gateway_title)
                    );
                } else {
                    $price_info = sprintf(
                        '%s a partir de %s%s no %s',
                        $gateway_icon,
                        wc_price($final_price),
                        $fee_label,
                        esc_html($gateway_title)
                    );
                }
                
                
                $additional_prices[$gateway_id] = sprintf(
                    '<span class="wc-invoice-payment-method-price %s" data-gateway="%s" data-gateway-title="%s">%s</span>',
                    esc_attr($css_class),
                    esc_attr($gateway_id),
                    esc_attr($gateway_title),
                    $price_info
                );
            }
        }

        if (!empty($additional_prices)) {
            // Gateway default (session ou primeiro da lista) vai primeiro e com destaque
            $default_gateway = null;
            if (WC()->session && WC()->session->get('chosen_payment_method')) {
                $default_gateway = WC()->session->get('chosen_payment_method');
            }
            if (!$default_gateway || !isset($additional_prices[$default_gateway])) {
                $default_gateway = array_key_first($additional_prices);
            }

            // Preço do gateway default para exibir como "novo preço" com corte no original
            $default_final = isset($gateway_final_prices[$default_gateway])
                ? $gateway_final_prices[$default_gateway]
                : $product_price;
            $any_price_changed = (count(array_unique(array_merge(
                array($product_price),
                array_values($gateway_final_prices)
            ))) > 1);

            // Aplica corte (riscado) + novo preço colorido no valor principal
            if ($any_price_changed && $isVar === 'VARIABLE') {
                $min_price   = (float) $product->get_variation_price('min');
                $max_price   = (float) $product->get_variation_price('max');
                $default_min = $this->calculateProductPriceWithFeeOrDiscount($min_price, $default_gateway);
                $default_max = $this->calculateProductPriceWithFeeOrDiscount($max_price, $default_gateway);
                $is_price_up = ($default_min > $min_price || $default_max > $max_price);

                $price_html = '<span class="wc-invoice-original-price">' . $price_html . '</span> ';
                $price_html .= '<span class="wc-invoice-new-price' . ($is_price_up ? ' price-up' : '') . '">'
                    . wc_price($default_min) . ' – ' . wc_price($default_max)
                    . '</span>';
            } elseif ($any_price_changed && $default_final != $product_price) {
                $is_price_up = ($default_final > $product_price);
                $price_html = '<span class="wc-invoice-original-price">' . $price_html . '</span> ';
                $price_html .= '<span class="wc-invoice-new-price' . ($is_price_up ? ' price-up' : '') . '">'
                    . wc_price($default_final) . '</span>';
            }

            // Variações: só aplica corte de preço, sem bloco de cartões
            if ($isVar === 'VARIATION') {
                $this->rendered_products[$pid] = true;
                return $price_html;
            }

            // Mantém a ordem original, só marca o default com .is-default
            $ordered = array();
            foreach ($additional_prices as $gw_id => $html) {
                if ($gw_id === $default_gateway) {
                    $ordered[] = str_replace(
                        'wc-invoice-payment-method-price ',
                        'wc-invoice-payment-method-price is-default ',
                        $html
                    );
                } else {
                    $ordered[] = $html;
                }
            }

            $price_html .= '<div class="wc-invoice-payment-method-prices">';
            $price_html .= implode('', $ordered);
            $price_html .= '</div>';
        }

        $this->rendered_products[$pid] = true;
        return $price_html;
    }
    
}