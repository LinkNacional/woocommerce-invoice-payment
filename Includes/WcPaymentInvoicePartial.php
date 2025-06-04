<?php

namespace LknWc\WcInvoicePayment\Includes;

final class WcPaymentInvoicePartial
{
    public function enqueueCheckoutScripts(){
        if (is_checkout()) {
            wp_enqueue_script( 'wcInvoicePaymentPartialScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial.js', array( 'jquery' ), WC_PAYMENT_INVOICE_VERSION, false );
    
            wp_localize_script('wcInvoicePaymentPartialScript', 'wcInvoicePaymentPartialVariables', array(
                
            ));
        }
    }
}