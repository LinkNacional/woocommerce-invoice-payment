<?php
namespace LknWc\WcInvoicePayment\Includes;

/**
 * Fired during plugin deactivation.
 *
 * @see       https://www.linknacional.com/
 * @since      1.0.0
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 *
 * @author     Link Nacional
 */
final class WcPaymentInvoiceDeactivator {
    /**
     * Short Description. (use period).
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate(): void {
        // Remover cron job dos orçamentos expirados
        $timestamp = wp_next_scheduled('lkn_wcip_check_expired_quotes');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'lkn_wcip_check_expired_quotes');
        }
    }
}
