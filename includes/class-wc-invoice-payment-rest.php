<?php

use Dompdf\Dompdf;

/**
 * @see       https://www.linknacional.com/
 * @since      1.2.0
 */
final class Wc_Payment_Invoice_Loader_Rest
{
    public function register_routes(): void
    {
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

    public function check_permission()
    {
        return current_user_can('administrator');
    }

    public function generate_invoice(WP_REST_Request $request): void
    {
        $invoice_id = $request->get_param('invoice_id');

        $getHtml = function () use ($invoice_id) {
            $order = wc_get_order($invoice_id);

            $invoice_pdf_template_id = $order->get_meta('wcip_select_invoice_template_id');

            if (empty($invoice_pdf_template_id) || 'global' === $invoice_pdf_template_id) {
                $invoice_pdf_template_id = get_option('lkn_wcip_global_pdf_template_id', 'linknacional');
            }

            $template_file_path = __DIR__ . "/templates/$invoice_pdf_template_id/main.php";

            if (!file_exists($template_file_path)) {
                return require_once __DIR__ . "/templates/linknacional/main.php";
            }

            return require_once $template_file_path;
        };
        // Prints the HTML
        // header('Content-Type: text/html');
        // echo $getHtml();
        // exit;

        // Displays the PDF in the browser
        // $dompdf = new Dompdf();
        // $dompdf->loadHtml($getHtml());
        // $dompdf->setPaper('A4', 'portrait');
        // $dompdf->render();
        // $output = $dompdf->output();
        // $file_name = 'invoice_' . $invoice_id . '.pdf';
        // header('Content-Type: application/pdf');
        // header('Content-Disposition: inline; filename="' . $file_name . '"');
        // header('Content-Length: ' . strlen($output));
        // echo $output;
        // die();

        // Downloads the PDF
        $dompdf = new Dompdf();
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
        $base64_output = base64_encode($output);
        echo esc_html($base64_output);
        exit;
    }
}
