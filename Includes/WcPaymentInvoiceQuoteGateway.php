<?php

namespace LknWc\WcInvoicePayment\Includes;

use DateTime;
use Exception;
use WC_Logger;
use WC_Subscriptions_Order;
use WC_Payment_Gateway;
use WC_Subscription;

/**
 * WcPaymentInvoiceQuoteGateway class.
 *
 * @author   Link Nacional
 *
 * @since    1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Invoice Quote Payment Gateway.
 *
 * @class    WcPaymentInvoiceQuoteGateway
 *
 * @version  1.0.0
 */
final class WcPaymentInvoiceQuoteGateway extends WC_Payment_Gateway
{
    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                 = 'lkn_invoice_quote_gateway';
        $this->icon               = '';
        $this->has_fields         = false;

        // Define supported features for WooCommerce Blocks
        $this->supports = array(
            'products'
        );

        // Force enable/disable based on quote mode setting
        $this->force_enable_disable_based_on_quote_mode();

    }

    /**
     * Force enable/disable gateway based on quote mode setting
     */
    private function force_enable_disable_based_on_quote_mode()
    {
        $quote_mode = get_option('lkn_wcip_quote_mode', 'no');
        
        if ($quote_mode === 'yes') {
            // Force enable the gateway
            $this->enabled = 'yes';
            update_option('woocommerce_' . $this->id . '_settings', array_merge($this->settings, array('enabled' => 'yes')));
        } else {
            // Force disable the gateway  
            $this->enabled = 'no';
            update_option('woocommerce_' . $this->id . '_settings', array_merge($this->settings, array('enabled' => 'no')));
        }
    }

    /**
     */
    public function is_available()
    {
        $quote_mode = get_option('lkn_wcip_quote_mode', 'no');
        
        // Gateway is only available when quote mode is enabled
        if ($quote_mode !== 'yes') {
            return false;
        }
        
        return parent::is_available();
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $quoteList = get_option('lkn_wcip_quotes', array());

        if (false !== $quoteList) {
            $quoteList[] = $order_id;
            update_option('lkn_wcip_quotes', $quoteList);
        } else {
            update_option('lkn_wcip_quotes', array($order_id));
        }

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('wc-quote-pending', __('Awaiting quote approval', 'wc-invoice-payment'));

        // Add order note
        $order->add_order_note(__('Quote request received. Awaiting approval...', 'wc-invoice-payment'));
        $order->update_meta_data('lkn_is_quote', 'yes');

        
        $iniDate = new \DateTime();
        $iniDateFormatted = $iniDate->format('Y-m-d');
        $quote_expiration_days = get_option('lkn_wcip_quote_expiration', 10);
        $expiration_date = gmdate("Y-m-d", strtotime($iniDateFormatted . ' +' . $quote_expiration_days . ' days'));
        $order->update_meta_data('lkn_ini_date', $iniDateFormatted);
        $order->update_meta_data('lkn_exp_date', $expiration_date);
        $order->save();

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
}
?>