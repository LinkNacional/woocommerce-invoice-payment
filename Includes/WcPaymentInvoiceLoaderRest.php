<?php
namespace LknWc\WcInvoicePayment\Includes;

use Dompdf\Dompdf;
use Dompdf\Options;
use WP_REST_Request;

/**
 * @see       https://www.linknacional.com/
 * @since      1.2.0
 */
final class WcPaymentInvoiceLoaderRest {
    public function register_routes(): void {
        register_rest_route(
            'wc-invoice-payment/v1',
            '/generate-pdf',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'generate_invoice'),
                'permission_callback' => array($this, 'check_permission'),
            )
        );
        register_rest_route(
            'wc-invoice-payment/v1', 
            '/redirect', 
            [
                'methods'  => 'GET',
                'callback' => function () {
                    wp_redirect(admin_url('admin.php?page=wc-invoice-payment'));
                    exit;
                },
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function check_permission(WP_REST_Request $request) {
        // Se for administrador, permitir
        if (current_user_can('administrator')) {
            return true;
        }
        
        // Verificar se é um vendedor do Dokan tentando acessar sua própria fatura
        $invoice_id = $request->get_param('invoice_id');
        if (!$invoice_id) {
            return false;
        }
        
        $order = wc_get_order($invoice_id);
        if (!$order) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return false;
        }
        
        // Verificar se o usuário é um vendedor do Dokan
        if (!function_exists('dokan_is_user_seller') || !dokan_is_user_seller($current_user_id)) {
            return false;
        }
        
        // Verificar se a fatura pertence ao vendedor
        $vendor_id = $order->get_meta('_dokan_vendor_id');
        if ($vendor_id && (int) $vendor_id === $current_user_id) {
            return true;
        }
        
        // Verificar na tabela wp_dokan_orders se existe relação
        global $wpdb;
        $dokan_order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dokan_orders WHERE order_id = %d AND seller_id = %d",
                $invoice_id,
                $current_user_id
            )
        );
        
        return !empty($dokan_order);
    }

    public function generate_invoice(WP_REST_Request $request): void {
        $invoice_id = $request->get_param('invoice_id');

        $getHtml = function () use ($invoice_id) {
            $order = wc_get_order($invoice_id);

            $invoice_pdf_template_id = $order->get_meta('wcip_select_invoice_template_id');

            if (empty($invoice_pdf_template_id) || 'global' === $invoice_pdf_template_id) {
                $invoice_pdf_template_id = get_option('lkn_wcip_global_pdf_template_id', 'linknacional');
            }

            $template_file_path = __DIR__ . "/templates/$invoice_pdf_template_id/main.php";

            if ( ! file_exists($template_file_path)) {
                return require_once __DIR__ . "/templates/linknacional/main.php";
            }

            return require_once $template_file_path;
        };

        // Configurações do Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('debugKeepTemp', true);
        $options->set('tempDir', sys_get_temp_dir());
        $options->set('chroot', realpath(__DIR__));

        $dompdf = new Dompdf($options);
        $dompdf->set_option('isHtml5ParserEnabled', true);
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($getHtml());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        $file_name = __('Invoice', 'wc-invoice-payment') . '-' . $invoice_id . '.pdf';

        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . strlen($output));
        header('Content-Transfer-Encoding: binary');

        // This is a binary file for download
        // Can't use esc_html here
        echo $output;
        exit;
    }
}
