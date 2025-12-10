<?php

namespace LknWc\WcInvoicePayment\Includes;
use WC_Order;

final class WcPaymentInvoiceFeeOrDiscount
{
    public function caclulateCart($cart) {
        if(isset(WC()->session->chosen_payment_method)){
            $chosenMethod = WC()->session->chosen_payment_method;
            $active = get_option('lkn_wcip_fee_or_discount_method_activated_' . $chosenMethod);
            $type = get_option('lkn_wcip_fee_or_discount_type_' . $chosenMethod);
            $percentOrFixed = get_option('lkn_wcip_fee_or_discount_percent_fixed_' . $chosenMethod);
            $value = get_option('lkn_wcip_fee_or_discount_value_' . $chosenMethod);

            if($active == 'yes'){
                $total = $cart->get_subtotal(); 

                if ($percentOrFixed === 'percent') {
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

                if($percentOrFixed == 'percent'){
                    $cartTotal = (float) WC()->cart->get_subtotal( '' );
                    $value = ($value / 100) * $cartTotal;
                }
    
                if ($active === 'yes') {
                    $data[$gateway_id] = [
                        'type' => $type, // 'fee' ou 'discount'
                        'mode' => $percentOrFixed, // 'percent' ou 'fixed'
                        'value' => $value,
                        'label' => sprintf(
                            __('%s of %s', 'wc-invoice-payment'),
                            $type === 'fee' ? __('Fee', 'wc-invoice-payment') : __('Discount', 'wc-invoice-payment'),
                            wc_price($value)
                        ),
                    ];
                }
            }
    
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
    private function getRedeInstallmentInfo($product_price) {
        // Busca as configurações do rede_credit (pode variar o nome exato)
        $settings = get_option('woocommerce_rede_credit_settings', array());
        
        if (empty($settings)) {
            // Tenta outras variações possíveis do nome das configurações
            $settings = get_option('woocommerce_maxipago_credit_settings', array());
        }
        
        if (empty($settings)) {
            return false;
        }
        
        $maxParcels = isset($settings['max_parcels_number']) ? (int)$settings['max_parcels_number'] : 12;
        $minParcelValue = isset($settings['min_parcels_value']) ? (int)$settings['min_parcels_value'] : 5;
        
        if (!$maxParcels || $product_price < $minParcelValue) {
            return false;
        }
        
        $maxInstallmentWithoutInterest = 1;
        
        // Procura a maior parcela sem juros
        for ($i = 1; $i <= $maxParcels; $i++) {
            $parcelAmount = $product_price / $i;
            
            // Verifica se a parcela atende ao valor mínimo
            if ($parcelAmount < $minParcelValue && $i > 1) {
                break;
            }
            
            // Verifica se existe configuração de juros para esta parcela
            $interestKey = $i . 'x';
            if (isset($settings[$interestKey])) {
                $interest = (float) $settings[$interestKey];
                
                // Se tem juros > 0, para na parcela anterior
                if ($interest > 0) {
                    break;
                }
            }
            
            $maxInstallmentWithoutInterest = $i;
        }
        
        $installmentValue = $product_price / $maxInstallmentWithoutInterest;
        
        return sprintf(
            ' em até %dx de %s sem juros',
            $maxInstallmentWithoutInterest,
            wp_strip_all_tags(wc_price($installmentValue))
        );
    }

    /**
     * Obtém informações de parcelamento para o gateway Cielo Credit
     *
     * @param float $product_price Preço do produto
     * @return string|false String com informação de parcelamento ou false se não houver
     */
    private function getCieloCreditInstallmentInfo($product_price) {
        // Busca as configurações do lkn_cielo_credit
        $settings = get_option('woocommerce_lkn_cielo_credit_settings', array());
        
        if (empty($settings)) {
            return false;
        }
        
        // Verifica se o parcelamento está ativo
        $installmentActive = isset($settings['installment_payment']) ? $settings['installment_payment'] : 'no';
        if ($installmentActive !== 'yes') {
            return false;
        }
        
        $maxParcels = isset($settings['installment_limit']) ? (int)$settings['installment_limit'] : 12;
        $minParcelValue = isset($settings['installment_min']) ? (float)str_replace(',', '.', $settings['installment_min']) : 5.0;
        
        if (!$maxParcels || $product_price < $minParcelValue) {
            return false;
        }
        
        $maxInstallmentWithoutInterest = 1;
        $interestOrDiscount = isset($settings['interest_or_discount']) ? $settings['interest_or_discount'] : 'no_interest';
        
        // Procura a maior parcela sem juros
        for ($i = 1; $i <= $maxParcels; $i++) {
            $parcelAmount = $product_price / $i;
            
            // Verifica se a parcela atende ao valor mínimo
            if ($parcelAmount < $minParcelValue && $i > 1) {
                break;
            }
            
            // Verifica se existe configuração de juros para esta parcela
            if ($interestOrDiscount === 'interest') {
                $interestKey = $i . 'x';
                if (isset($settings[$interestKey])) {
                    $interest = (float) $settings[$interestKey];
                    
                    // Se tem juros > 0, para na parcela anterior
                    if ($interest > 0) {
                        break;
                    }
                }
            }
            
            $maxInstallmentWithoutInterest = $i;
        }
        
        $installmentValue = $product_price / $maxInstallmentWithoutInterest;
        
        return sprintf(
            ' em até %dx de %s sem juros',
            $maxInstallmentWithoutInterest,
            wp_strip_all_tags(wc_price($installmentValue))
        );
    }

    /**
     * Obtém informações de parcelamento para o gateway Cielo Debit (quando tem opção de crédito)
     *
     * @param float $product_price Preço do produto
     * @return string|false String com informação de parcelamento ou false se não houver
     */
    private function getCieloDebitInstallmentInfo($product_price) {
        // Busca as configurações do lkn_cielo_debit
        $settings = get_option('woocommerce_lkn_cielo_debit_settings', array());

        if (empty($settings)) {
            return false;
        }
        
        // Verifica se o parcelamento está ativo (Cielo Debit pode ter opção de crédito)
        $installmentActive = isset($settings['installment_payment']) ? $settings['installment_payment'] : 'no';
        if ($installmentActive !== 'yes') {
            return false;
        }
        
        $maxParcels = isset($settings['installment_limit']) ? (int)$settings['installment_limit'] : 12;
        $minParcelValue = isset($settings['installment_min']) ? (float)str_replace(',', '.', $settings['installment_min']) : 5.0;
        
        if (!$maxParcels || $product_price < $minParcelValue) {
            return false;
        }
        
        $maxInstallmentWithoutInterest = 1;
        $interestOrDiscount = isset($settings['interest_or_discount']) ? $settings['interest_or_discount'] : 'no_interest';
        
        // Procura a maior parcela sem juros
        for ($i = 1; $i <= $maxParcels; $i++) {
            $parcelAmount = $product_price / $i;
            
            // Verifica se a parcela atende ao valor mínimo
            if ($parcelAmount < $minParcelValue && $i > 1) {
                break;
            }
            
            // Se não há configuração de juros/desconto, todas as parcelas são sem juros
            if ($interestOrDiscount === 'no_interest') {
                $maxInstallmentWithoutInterest = $i;
                continue;
            }
            
            // Verifica se existe configuração de juros para esta parcela
            if ($interestOrDiscount === 'interest') {
                $interestKey = $i . 'x';
                if (isset($settings[$interestKey])) {
                    $interest = (float) $settings[$interestKey];
                     
                    // Se tem juros > 0, para na parcela anterior
                    if ($interest > 0) {
                        break;
                    }
                }
            }
            
            $maxInstallmentWithoutInterest = $i;
        }
        
        $installmentValue = $product_price / $maxInstallmentWithoutInterest;
        
        return sprintf(
            ' em até %dx de %s sem juros',
            $maxInstallmentWithoutInterest,
            wp_strip_all_tags(wc_price($installmentValue))
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
        wp_enqueue_style(
            'wc-invoice-payment-method-prices',
            WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-method-prices.css',
            array(),
            WC_PAYMENT_INVOICE_VERSION,
            'all'
        );
        foreach ($gateways as $gateway_id => $gateway) {
            // Verifica se deve mostrar o preço para este método
            $show_price = get_option('lkn_wcip_fee_or_discount_show_price_' . $gateway_id);
            $method_active = get_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id);
            
            if ($show_price === 'yes' && $method_active === 'yes') {
                $final_price = $this->calculateProductPriceWithFeeOrDiscount($product_price, $gateway_id);
                $type = get_option('lkn_wcip_fee_or_discount_type_' . $gateway_id);

                // Só adiciona se o preço for diferente do original
                $gateway_title = isset($gateway->settings->title) ? $gateway->settings->title : $gateway->title;
                
                // Adiciona classe CSS baseada no tipo (fee ou discount)
                $css_class = $type === 'fee' ? 'fee-type' : 'discount-type';
                
                // Verifica se é método PIX para adicionar ícone
                $pix_icon = '';
                $is_pix_method = stripos($gateway_id, 'pix') !== false;
                
                if ($is_pix_method) {
                    $pix_icon_url = WC_PAYMENT_INVOICE_ROOT_URL . 'Public/images/pix.svg';
                    $pix_icon = sprintf(
                        '<img id="lknWcInvoicePixIcon" src="%s" alt="PIX" style="width: 20px; height: 20px; vertical-align: middle; display: inline-block;">',
                        esc_url($pix_icon_url)
                    );
                }
                
                
                if ($gateway_id === 'rede_credit') {
                    $installment_info = $this->getRedeInstallmentInfo($final_price);
                    
                    if ($installment_info) {
                        $credit_icon_url = WC_PAYMENT_INVOICE_ROOT_URL . 'Public/images/creditCard.svg';
                        $credit_icon = sprintf(
                            '<img id="lknWcInvoiceCreditIcon" src="%s" alt="Cartão" style="width: 20px; height: 20px; vertical-align: middle; display: inline-block;">',
                            esc_url($credit_icon_url)
                        );
                        
                        $price_info = sprintf(
                            '%s%s',
                            $credit_icon,
                            $installment_info
                        );
                    } else {
                        $price_info = sprintf(
                            '%s%s no %s',
                            $pix_icon,
                            wc_price($final_price),
                            esc_html($gateway_title)
                        );
                    }
                } elseif ($gateway_id === 'lkn_cielo_credit') {
                    // Gateway Cielo Credit (wc_cielo_payment_gateway plugin)
                    $installment_info = $this->getCieloCreditInstallmentInfo($final_price);
                    
                    if ($installment_info) {
                        $credit_icon_url = WC_PAYMENT_INVOICE_ROOT_URL . 'Public/images/creditCard.svg';
                        $credit_icon = sprintf(
                            '<img id="lknWcInvoiceCreditIcon" src="%s" alt="Cartão" style="width: 20px; height: 20px; vertical-align: middle; display: inline-block;">',
                            esc_url($credit_icon_url)
                        );
                        
                        $price_info = sprintf(
                            '%s%s',
                            $credit_icon,
                            $installment_info
                        );
                    } else {
                        $price_info = sprintf(
                            '%s%s no %s',
                            $pix_icon,
                            wc_price($final_price),
                            esc_html($gateway_title)
                        );
                    }
                } elseif ($gateway_id === 'lkn_cielo_debit') {
                    // Gateway Cielo Debit (wc_cielo_payment_gateway plugin) - pode ter opção de crédito
                    $installment_info = $this->getCieloDebitInstallmentInfo($final_price);
                    
                    if ($installment_info) {
                        $credit_icon_url = WC_PAYMENT_INVOICE_ROOT_URL . 'Public/images/creditCard.svg';
                        $credit_icon = sprintf(
                            '<img id="lknWcInvoiceCreditIcon" src="%s" alt="Cartão" style="width: 20px; height: 20px; vertical-align: middle; display: inline-block;">',
                            esc_url($credit_icon_url)
                        );
                        
                        $price_info = sprintf(
                            '%s%s',
                            $credit_icon,
                            $installment_info
                        );
                    } else {
                        $price_info = sprintf(
                            '%s%s no %s',
                            $pix_icon,
                            wc_price($final_price),
                            esc_html($gateway_title)
                        );
                    }
                } else {
                    $price_info = sprintf(
                        '%s%s no %s',
                        $pix_icon,
                        wc_price($final_price),
                        esc_html($gateway_title)
                    );
                }
                
                
                $additional_prices[] = sprintf(
                    '<span id="lknWcInvoicePriceMethodsSpan"
                    class="wc-invoice-payment-method-price %s"
                    style="
                        display: flex;
                        align-items: center;
                        gap: 5px;"
                    >%s</span>',
                    esc_attr($css_class),
                    $price_info
                );
            }
        }

        if (!empty($additional_prices)) {
            $price_html .= '<br><div id="lknWcInvoicePriceMethodsDiv" class="wc-invoice-payment-method-prices" style="margin-top: 5px;">';
            $price_html .= implode('', $additional_prices);
            $price_html .= '</div>';
        }

        return $price_html;
    }
    
}