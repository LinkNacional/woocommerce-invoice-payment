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
$invoice_payment_method = wc_get_payment_gateway_by_order($order)->title;

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
                <td>
                    <h1><?php esc_html_e('Bill To', 'wc-invoice-payment'); ?>
                    </h1>
                </td>
            </tr>
            <tr>
                <td style="width: 50%;">
                    <section id="bill-to-container">
                        <div><?php echo nl2br($wcip_extra_data); ?>
                        </div>
                    </section>
                </td>
                <td id="invoice-details-column">
                    <table>
                        <tr>
                            <td style="width: 70%;">
                                <?php esc_html_e('Invoice', 'wc-invoice-payment'); ?>
                            </td>
                            <td>
                                <?php echo "#$invoice_number"; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?php esc_html_e('Date', 'wc-invoice-payment'); ?>
                            </td>
                            <td>
                                <?php echo $invoice_created_at; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?php esc_html_e('Payment method', 'wc-invoice-payment'); ?>
                            </td>
                            <td>
                                <?php echo $invoice_payment_method; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </header>

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
                    <th><?php esc_html_e('Total', 'wc-invoice-payment'); ?>
                    </th>
                    <td><?php echo "$order_currency " . to_wc_monetary_format($order_total); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </section>

    <section id="qr-code-container">
        <figure>
            <img
                src="data:image/png;base64, <?php echo $payment_link_qr_code; ?>"
                width="230"
                height="230"
            >
            <figcaption><?php echo $invoice_payment_link; ?>
            </figcaption>
        </figure>
    </section>

    <footer id="main-footer">
        <h1><?php esc_html_e('Payment details', 'wc-invoice-payment'); ?>
        </h1>
        <?php echo $wcip_footer_notes; ?>
    </footer>
</body>

</html>

<?php

return ob_get_clean();

?>
