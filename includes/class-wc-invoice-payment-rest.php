<?php

use Dompdf\Dompdf;

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

    public function generate_invoice(): void {
        $html = '<html><body><h1>Invoice</h1><p>This is a sample invoice.</p></body></html>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        $file_name = 'invoice.pdf';

        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . strlen($output));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        echo $output;
        exit;
    }
}
