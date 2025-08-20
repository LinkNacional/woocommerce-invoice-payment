<?php

namespace LknWc\WcInvoicePayment\Includes;
/**
 * Fired during plugin activation.
 *
 * @see       https://www.linknacional.com/
 * @since      1.0.0
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 *
 * @author     Link Nacional
 */
final class WcPaymentInvoiceActivator {
    /**
     * Short Description. (use period).
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate(): void {
        // Adicionar endpoint para orçamentos
        add_rewrite_endpoint('quotes', EP_ROOT | EP_PAGES);
        
        // Atualizar as regras de rewrite
        flush_rewrite_rules();
    }
}
