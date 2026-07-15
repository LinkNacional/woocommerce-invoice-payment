<?php
if (! defined('ABSPATH')) {
    exit();
}
?>
<h2 class="wp-block-heading" style="font-size:clamp(15.747px, 0.984rem + ((1vw - 3.2px) * 0.809), 24px);">Pagamento Parcial</h2>
<table cellspacing="0" class="woocommerce-table woocommerce-table--order-details shop_table order_details wcPaymentInvoiceTable">
    <tbody>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                Valor total:
            </td>
            <td class="wc-block-order-confirmation-totals__total">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($totalConfirmed); ?></span>
            </td>
        </tr>
        <?php if ($isParent && (float) $restante == 0 && (float) $totalConfirmed > 0): ?>
            <?php $childPaid = (float) $totalConfirmed - (float) $myPaid; ?>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wc-block-order-confirmation-totals__product">
                    1ª parcela paga:
                </td>
                <td class="wc-block-order-confirmation-totals__total">
                    <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr(number_format((float) $myPaid, 2, ',', '.')); ?></span>
                </td>
            </tr>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wc-block-order-confirmation-totals__product">
                    2ª parcela paga:
                </td>
                <td class="wc-block-order-confirmation-totals__total">
                    <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr(number_format($childPaid, 2, ',', '.')); ?></span>
                </td>
            </tr>
        <?php elseif ($isParent): ?>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wc-block-order-confirmation-totals__product">
                    Valor pago:
                </td>
                <td class="wc-block-order-confirmation-totals__total">
                    <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($myPaid); ?></span>
                </td>
            </tr>
        <?php else: ?>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                1ª parcela paga:
            </td>
            <td class="wc-block-order-confirmation-totals__total">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($firstPaid); ?></span>
            </td>
        </tr>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                2ª parcela paga:
            </td>
            <td class="wc-block-order-confirmation-totals__total">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($myPaid); ?></span>
            </td>
        </tr>
        <?php endif; ?>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                Restante a pagar:
            </td>
            <td class="wc-block-order-confirmation-totals__total">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($restante); ?></span>
            </td>
        </tr>
        <?php  if ($orderStatus == 'partial') : ?>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wc-block-order-confirmation-totals__product">
                    Ações:
                </td>
                <td class="wc-block-order-confirmation-totals__total wcPaymentInvoiceTableInputs">
                    <div class="wc-block-components-text-input wcPaymentInvoiceInputWrapper">
                        <input id="wcPaymentInvoicePartialAmountFormatted" type="text" placeholder="<?php echo esc_attr($symbol); ?> 0,00">
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
        <?php endif; ?>
</table>

<h2 class="wp-block-heading" style="font-size:clamp(15.747px, 0.984rem + ((1vw - 3.2px) * 0.809), 24px);">Detalhes de pagamento parcial</h2>
<table cellspacing="0" class="woocommerce-table woocommerce-table--order-details shop_table order_details wcPaymentInvoiceTable">
    <thead>
        <tr>
            <th>Data</th>
            <th class="wcPaymentInvoiceCenter">Método</th>
            <th class="wcPaymentInvoiceCenter">Status</th>
            <th class="wcPaymentInvoiceCenter">Valor</th>
            <th class="wcPaymentThActions">Papel</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($allRelated as $relOrder) :
            $created_date   = $relOrder->get_date_created() ? $relOrder->get_date_created()->date_i18n('d/m/Y') : '-';
            $payment_method = $relOrder->get_payment_method_title() ?: '-';
            $status         = wc_get_order_status_name($relOrder->get_status());
            $total          = wc_price($relOrder->get_total());
            $rel_parent_id  = $relOrder->get_meta('_wc_lkn_parent_id');
            $role           = $rel_parent_id ? '2ª parcela' : '1ª parcela';
            $link           = add_query_arg('id', $relOrder->get_id(), admin_url('admin.php?page=wc-orders&action=edit'));
        ?>
            <tr class="woocommerce-table__line-item order_item">
                <td><?php echo esc_html($created_date); ?></td>
                <td class="wcPaymentInvoiceCenter"><?php echo esc_html($payment_method); ?></td>
                <td class="wcPaymentInvoiceCenter"><?php echo esc_html($status); ?></td>
                <td class="wcPaymentInvoiceCenter"><?php echo wp_kses_post($total); ?></td>
                <td class="wcPaymentThActions">
                    <span style="font-size:12px"><?php echo esc_html($role); ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>