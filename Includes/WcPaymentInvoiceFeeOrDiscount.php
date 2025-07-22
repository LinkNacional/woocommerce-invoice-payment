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

            if($active == 'on'){
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
                    $cartTotal = (float) WC()->cart->get_total( '' );
                    $value = ($value / 100) * $cartTotal;
                }
    
                if ($active === 'on') {
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
    
            wp_enqueue_style(
                'wcInvoicePaymentFeeOrDiscountStyle',
                WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-fee-or-discount.css',
                [],
                WC_PAYMENT_INVOICE_VERSION,
                'all'
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
    
}