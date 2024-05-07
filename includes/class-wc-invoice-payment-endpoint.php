<?php
use WP_Error;
use WP_REST_Response;

final class Wc_Payment_Invoice_Endpoint {
    public function createInvoiceEndpoint(): void {
        register_rest_route('invoice_payments', '/create_invoice', array(
            'methods' => 'POST',
            'callback' => array($this, 'createInvoice'),
        ));
    }
    
    public function createInvoice($request) {
        $parameters = $request->get_params();
        
        return new WP_REST_Response(wp_json_encode($parameters), 200);
    }
}

