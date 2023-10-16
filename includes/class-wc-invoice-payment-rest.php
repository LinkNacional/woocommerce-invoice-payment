<?php

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * @see       https://www.linknacional.com/
 * @since      1.6.0
 */
final class Wc_Payment_Invoice_Loader_Rest {
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
    }

    public function check_permission() {
        return true;
        return current_user_can('administrator');
    }

    public function generate_invoice(WP_REST_Request $request): void {
        $invoice_id = $request->get_param( 'invoice_id' );

        $getHtml = function () use ($invoice_id) {
            return require_once __DIR__ . '/templates/linknacional/main.php';
        };

        $dompdf = new Dompdf();
        $dompdf->loadHtml($getHtml());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        $file_name = 'invoice.pdf';

        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . strlen($output));
        header('Content-Transfer-Encoding: binary');
        echo $output;
        exit;
    }
}
