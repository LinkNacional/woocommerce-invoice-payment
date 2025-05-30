<?php

/**  @var WC_Order $order */
/**  @var int $invoice_id */
require_once WC_PAYMENT_INVOICE_ROOT_DIR . 'Includes/libs/phpqrcode.php';

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
$invoice_client_name = "{$order->get_billing_first_name()} {$order->get_billing_last_name()}";
$invoice_client_email = $order->get_billing_email();

$items = $order->get_items();
$invoice_payment_link = $order->get_checkout_payment_url();

// Generates the HTML rows for the invoice items.
$invoice_items_html = implode(
    array_map(function (WC_Order_Item $item) use ($order_currency): string {
        $item_descrip = $item->get_name();
        $item_price = to_wc_monetary_format($item->get_total());

        return "
    <tr>
        <td>$item_descrip</td>
        <td>$order_currency $item_price</td>
    </tr>";
    }, $items)
);

$wcip_extra_data = $order->get_meta('wcip_extra_data');
$wcip_footer_notes = $order->get_meta('wcip_footer_notes');

// Generates the QR Code as base 64 for the payment link.
ob_start();
QRcode::png($invoice_payment_link, null, QR_ECLEVEL_L, 10, 2, false, 0xFFFFFF, 0x000000);
$qrCodeData = ob_get_clean();
$order_data = $order->get_meta("lkn_exp_date") == "1" ? false : $order->get_meta("lkn_exp_date");

$payment_link_qr_code = base64_encode($qrCodeData);

$document_title = __('Invoice', 'wc-invoice-payment') . $invoice_id . '.pdf';

$orderLanguage = $order->get_meta('wcip_select_invoice_language');
switch_to_locale($orderLanguage);

ob_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <title><?php echo esc_attr($document_title); ?></title>
    <meta charset="utf-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, user-scalable=1"
    />
    <style>
        <?php include __DIR__ . '/styles.css'; ?>
    </style>
</head>

<body>
    <table id="sender-details-table">
        <tr>
            <td>
                <p><?php echo wp_kses_post(get_option('lkn_wcip_sender_details')); ?>
                </p>
            </td>
        </tr>
        <tr>
            <td>
                <hr>
            </td>
        </tr>
    </table>

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
                        <div>
                            <?php echo esc_attr($invoice_client_name); ?>
                        </div>
                        <div>
                            <?php echo esc_attr($invoice_client_email); ?>
                        </div>
                        <div id="extra-data-container">
                            <?php echo wp_kses_post(nl2br($wcip_extra_data)); ?>
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
                                <?php echo esc_attr("#$invoice_number"); ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?php esc_html_e('Date', 'wc-invoice-payment'); ?>
                            </td>
                            <td>
                                <?php echo esc_attr($invoice_created_at); ?>
                            </td>
                        </tr>
                        <?php if ($order_data) : ?>
                        <?php $order_date = new DateTime($order_data) ?>
                        <tr>
                            <td>
                                <?php esc_html_e("Invoice due date", 'wc-invoice-payment'); ?>
                            </td>
                            <td>
                                <?php echo esc_html($order_date->format("d/m/y")); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>
                                <?php esc_html_e('Payment method', 'wc-invoice-payment'); ?>
                            </td>
                            <td>
                                <?php echo esc_attr($invoice_payment_method) ?>
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
                <?php echo wp_kses_post($invoice_items_html); ?>
            </tbody>

            <tfoot>
                <tr>
                    <th><?php esc_html_e('Total', 'wc-invoice-payment'); ?>
                    </th>
                    <td><?php echo esc_attr("$order_currency " . to_wc_monetary_format($order_total)); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </section>

    <section id="qr-code-container">
        <figure>
            <img
                src="data:image/png;base64, <?php echo esc_attr($payment_link_qr_code); ?>"
                width="230"
                height="230"
            >
            <figcaption>
                <?php echo wp_kses_post(get_option('lkn_wcip_text_before_payment_link')); ?>
                <span
                    id="payment-link-container"><?php echo esc_url($invoice_payment_link); ?></span>
            </figcaption>
        </figure>
    </section>

    <footer id="main-footer">
        <h1><?php esc_html_e('Payment details', 'wc-invoice-payment'); ?>
        </h1>
        <?php echo wp_kses_post($wcip_footer_notes); ?>

        <div style="text-align: center; width: 100%; opacity: 0.2; font-size: 0.8em; margin-top: 12px;">
            <a
                href="https://www.linknacional.com.br/pagamento-internacional/"
                style="text-decoration: none;"
            >
                <?php esc_html_e('Invoice By Link Nacional', 'wc-invoice-payment'); ?>
            </a>
        </div>
    </footer>
</body>

</html>

<?php
restore_previous_locale();
return ob_get_clean();

?>