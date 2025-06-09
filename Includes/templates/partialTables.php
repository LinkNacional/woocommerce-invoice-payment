<?php
if (! defined('ABSPATH')) {
    exit();
}
?>
<h2 class="wp-block-heading" style="font-size:clamp(15.747px, 0.984rem + ((1vw - 3.2px) * 0.809), 24px);">Pagamento Parcial</h2>
<table cellspacing="0" style="">
    <tbody>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                Pagamento parcial confirmado:
            </td>
            <td class="wc-block-order-confirmation-totals__total">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">R$</span> <?php echo esc_attr($totalConfirmed); ?></span>
            </td>
        </tr>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                Pagamento parcial pendente:
            </td>
            <td class="wc-block-order-confirmation-totals__total">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">R$</span> <?php echo esc_attr($totalPeding); ?></span>
            </td>
        </tr>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                Restante:
            </td>
            <td class="wc-block-order-confirmation-totals__total">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">R$</span> <?php echo esc_attr($total); ?></span>
            </td>
        </tr>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                Ações:
            </td>
            <td class="wc-block-order-confirmation-totals__total wcPaymentInvoiceTableInputs">
                <div class="wc-block-components-text-input wcPaymentInvoiceInputWrapper">
                    <input id="wcPaymentInvoicePartialAmountFormatted" type="text" placeholder="R$ 0,00">
                    <input id="wcPaymentInvoicePartialAmount" type="number" max="1" step="0.01" min="0.01" style="display: none;">
                </div>
                <button class="wc-block-components-button wp-element-button wc-block-components-checkout-place-order-button contained wcPaymentInvoiceButton" type="button">
                    <span class="wc-block-components-button__text">
                        <div aria-hidden="false" class="wc-block-components-checkout-place-order-button__text">
                            Pagar
                        </div>
                    </span>
                </button>
                <button class="wc-block-components-button wp-element-button wc-block-components-checkout-place-order-button contained wcPaymentInvoiceTotalButton" type="button">
                    <span class="wc-block-components-button__text">
                        <div aria-hidden="false" class="wc-block-components-checkout-place-order-button__text">
                            Pagar restante
                        </div>
                    </span>
                </button>
            </td>

        </tr>
</table>
<?php
if (! defined('ABSPATH')) {
    exit();
}
?>

<h2 class="wp-block-heading" style="font-size:clamp(15.747px, 0.984rem + ((1vw - 3.2px) * 0.809), 24px);">Detalhes de pagamento parcial</h2>
<table cellspacing="0" class="">
    <thead>
        <tr>
            <th>Data</th>
            <th class="wcPaymentInvoiceCenter">Método</th>
            <th class="wcPaymentInvoiceCenter">Status</th>
            <th class="wcPaymentInvoiceCenter">Valor parcial</th>
            <th class="wcPaymentThActions">Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($partialsOrdersIds as $order_id) :
            $partial_order = wc_get_order($order_id);
            if (! $partial_order) {
                continue;
            }

            $created_date = $partial_order->get_date_created() ? $partial_order->get_date_created()->date_i18n('d/m/Y') : '-';
            $payment_method = $partial_order->get_payment_method_title() ?: '-';
            $status = wc_get_order_status_name($partial_order->get_status());
            $total = wc_price($partial_order->get_total());
            $pay_url = $partial_order->get_checkout_payment_url();
            $cancel_url = $partial_order->get_cancel_order_url(wc_get_page_permalink('cart'));
        ?>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wcPaymentInvoiceCenter"><?php echo esc_html($created_date); ?></td>
                <td class="wcPaymentInvoiceCenter"><?php echo esc_html($payment_method); ?></td>
                <td class="wcPaymentInvoiceCenter"><?php echo esc_html($status); ?></td>
                <td class="wcPaymentInvoiceCenter"><?php echo wp_kses_post($total); ?></td>
                <td class="wc-block-order-confirmation-totals__total wcPaymentInvoiceTableInputs">
                    <?php if ($partial_order->get_status() == 'partial-pend') : ?>
                        <a class="" href="<?php echo esc_url($cancel_url); ?>" class="button cancel">Cancelar</a>
                        <a class="wc-block-components-button wp-element-button wc-block-components-checkout-place-order-button contained wcPaymentInvoiceActionsButtons" href="<?php echo esc_url($pay_url); ?>" class="button pay">Pagar</a>
                    <?php else : ?>
                        <span>-</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>