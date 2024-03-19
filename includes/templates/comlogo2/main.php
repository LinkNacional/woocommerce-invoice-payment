<?php

/**  @var WC_Order $order */
/**  @var int $invoice_id */
require_once WC_PAYMENT_INVOICE_ROOT_DIR . 'includes/libs/phpqrcode.php';

function to_wc_monetary_format(float $amount): string {
    return number_format(
        $amount,
        wc_get_price_decimals(),
        wc_get_price_decimal_separator(),
        wc_get_price_thousand_separator()
    );
}

$order_currency = $order->get_currency();
$order_total = $order->get_total();
$invoice_number = $order->get_order_number();
$invoice_created_at = $invoice_date = $order->get_date_created()->format('d/m/y');
$invoice_client_name = "{$order->get_billing_first_name()} {$order->get_billing_last_name()}";
$invoice_client_email = $order->get_billing_email();

$items = $order->get_items();
$invoice_payment_link = $order->get_checkout_payment_url();

// Generates the HTML rows for the invoice items.
$invoice_items_html = implode(
    array_map(function (WC_Order_Item $item) use ($order_currency): string {
        $item_descrip = $item->get_name();
        $item_price = to_wc_monetary_format($item->get_total());

        return <<<HTML
    <tr>
        <td>{$item_descrip}</td>
        <td>{$order_currency} {$item_price}</td>
    </tr>
HTML;
    }, $items )
);

$wcip_extra_data = $order->get_meta('wcip_extra_data');
$wcip_footer_notes = $order->get_meta('wcip_footer_notes');

// Load CSS styles.
$styles = file_get_contents(__DIR__ . '/styles.css');

// Load logo as base 64.
$logo_url_setting = get_option('lkn_wcip_template_logo_url');
$logo_path = empty($logo_url_setting) ? 'https://dummyimage.com/180x180/000/fff' : $logo_url_setting;
$response = wp_remote_get($logo_path, array(
    'timeout' => 10,
));

if (is_wp_error($response)) {
    $error_message = $response->get_error_message();
} else {
    $data = wp_remote_retrieve_body($response);
    $type = wp_remote_retrieve_header($response, 'content-type');
    
    $logo_base64 = 'data:' . $type . ';base64,' . base64_encode($data);
}

// Generates the QR Code as base 64 for the payment link.
ob_start();
QRcode::png($invoice_payment_link, null, QR_ECLEVEL_L, 10, 2, false, 0xFFFFFF, 0x000000);
$qrCodeData = ob_get_clean();

$payment_link_qr_code = base64_encode($qrCodeData);

$document_title = __('Invoice', 'wc-invoice-payment') . $invoice_id . '.pdf';

ob_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <title><?php echo $document_title; ?></title>
    <meta charset="utf-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, user-scalable=1"
    />
    <style>
        <?php echo $styles; ?>
    </style>
</head>

<body>
    <header>
        <table>
            <tr>
                <td id="invoice-details-column">
                    <p><strong><?php esc_html_e('Invoice', 'wc-invoice-payment'); ?></strong> <?php echo "#$invoice_number"; ?></p>
                    <p><strong><?php esc_html_e('Date', 'wc-invoice-payment'); ?></strong> <?php echo $invoice_created_at; ?></p>
                </td>
                <td id="logo-td-container">
                    <img
                        src="<?php echo $logo_base64; ?>"
                        width="160"
                    >
                </td>
            </tr>
        </table>
    </header>

    <table id="invoice-details-table">
        <tr>
            <td>
                <p><?php echo get_option('lkn_wcip_sender_details'); ?></p>
            </td>
        </tr>
        <tr>
            <td>
                <hr>
            </td>
        </tr>
        <tr>
            <td>
                <h1><?php esc_html_e('Bill To', 'wc-invoice-payment'); ?></h1>
            </td>
        </tr>
        <tr>
            <td id="bill-to-container">
                <div><?php echo $invoice_client_name; ?></div>
                <div><?php echo $invoice_client_email; ?></div>
                <div id="extra-data-container"><?php echo nl2br($wcip_extra_data); ?></div>
            </td>
        </tr>
    </table>

    <section id="order-items-table-container">
        <table id="order-items-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Description', 'wc-invoice-payment'); ?>
                    </th>
                    <th><?php esc_html_e('Price', 'wc-invoice-payment'); ?>
                    </th>
                </tr>
            </thead>

            <tbody>
                <?php echo $invoice_items_html; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th><?php esc_html_e('Total', 'wc-invoice-payment'); ?></th>
                    <td><?php echo "$order_currency " . to_wc_monetary_format($order_total); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </section>

    <section id="qr-code-container">
        <figure>
            <figcaption>
                <?php echo get_option('lkn_wcip_text_before_payment_link'); ?>
                <span id="payment-link-container"><?php echo $invoice_payment_link; ?></span>
            </figcaption>
            <img
                src="data:image/png;base64, <?php echo $payment_link_qr_code; ?>"
                width="140"
                height="140"
            >
        </figure>
    </section>

    <footer id="main-footer">
        <?php echo $wcip_footer_notes; ?>

        <div style="text-align: center; width: 100%; opacity: 0.2; font-size: 0.8em; margin-top: 12px;">
            <a href="https://www.linknacional.com.br/pagamento-internacional/" style="text-decoration: none;">
                <?php _e('Invoice By Link Nacional', 'wc-invoice-payment'); ?>
            </a>
        </div>
    </footer>
</body>

</html>

<?php

return ob_get_clean();

?>
