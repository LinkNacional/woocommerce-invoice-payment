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
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($originalTotal ?: $totalConfirmed); ?></span>
            </td>
        </tr>
        <?php if ($isParent && (float) $restante == 0 && (float) $totalConfirmed > 0): ?>
            <?php foreach ($childrenDetails as $ci => $cd): ?>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wc-block-order-confirmation-totals__product">
                    <?php echo ($ci + 1) . '° Parcial pago:'; ?>
                </td>
                <td class="wc-block-order-confirmation-totals__total">
                    <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($cd['base']); ?></span>
                </td>
            </tr>
            <?php if (abs((float) $cd['fees']) > 0.01): ?>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wc-block-order-confirmation-totals__product" style="padding-left:16px;font-size:13px;color:#007cba">
                    + Taxas/Descontos:
                </td>
                <td class="wc-block-order-confirmation-totals__total" style="font-size:13px;color:#007cba">
                    <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($cd['fees']); ?></span>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        <?php elseif ($isParent): ?>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wc-block-order-confirmation-totals__product">
                    Valor pago:
                </td>
                <td class="wc-block-order-confirmation-totals__total">
                    <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($totalConfirmed); ?></span>
                </td>
            </tr>
        <?php else: ?>
            <?php if (!empty($childrenDetails)): ?>
                <?php foreach ($childrenDetails as $ci => $cd): ?>
                <tr class="woocommerce-table__line-item order_item">
                    <td class="wc-block-order-confirmation-totals__product">
                        <?php echo ($ci + 1) . 'ª parcela (base):'; ?>
                    </td>
                    <td class="wc-block-order-confirmation-totals__total">
                        <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($cd['base']); ?></span>
                    </td>
                </tr>
                <?php if (abs((float) $cd['fees']) > 0.01): ?>
                <tr class="woocommerce-table__line-item order_item">
                    <td class="wc-block-order-confirmation-totals__product" style="padding-left:16px;font-size:13px;color:#007cba">
                        + Taxas/Descontos:
                    </td>
                    <td class="wc-block-order-confirmation-totals__total" style="font-size:13px;color:#007cba">
                        <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($cd['fees']); ?></span>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
            <tr class="woocommerce-table__line-item order_item">
                <td class="wc-block-order-confirmation-totals__product">
                    Parcela paga:
                </td>
                <td class="wc-block-order-confirmation-totals__total">
                    <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($myPaid); ?></span>
                </td>
            </tr>
            <?php endif; ?>
        <?php endif; ?>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product">
                Restante a pagar:
            </td>
            <td class="wc-block-order-confirmation-totals__total">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($restante); ?></span>
            </td>
        </tr>
        <tr class="woocommerce-table__line-item order_item">
            <td class="wc-block-order-confirmation-totals__product" style="font-weight:bold">
                Total pago:
            </td>
            <td class="wc-block-order-confirmation-totals__total" style="font-weight:bold">
                <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo esc_attr($symbol); ?></span> <?php echo esc_attr($totalWithFees); ?></span>
            </td>
        </tr>
</table>

<h2 class="wp-block-heading" style="font-size:clamp(15.747px, 0.984rem + ((1vw - 3.2px) * 0.809), 24px);">Detalhes de pagamento parcial</h2>
<div class="lkn-wcip-partial-details-cards">
    <?php $parc_num = 0; foreach ($allRelated as $relOrder) :
        $created_date   = $relOrder->get_date_created() ? $relOrder->get_date_created()->date_i18n('d/m/Y') : '-';
        $payment_method = $relOrder->get_payment_method_title() ?: '-';
        $status         = wc_get_order_status_name($relOrder->get_status());
        $total          = wc_price($relOrder->get_total());
        $rel_parent_id  = $relOrder->get_meta('_wc_lkn_parent_id');
        $is_main        = !$rel_parent_id && $relOrder->get_meta('_wc_lkn_is_partial_main_order') === 'yes';
        if ($is_main) {
            $role = __('Principal', 'wc-invoice-payment');
            $total = wc_price((float) $relOrder->get_meta('_wc_lkn_original_total') ?: (float) $relOrder->get_total());
        } else {
            $parc_num++;
            $role = $parc_num . '° Parcial';
        }
    ?>
        <div class="lkn-wcip-detail-card">
            <div class="lkn-wcip-detail-card__top">
                <span class="lkn-wcip-detail-card__date"><?php echo esc_html($created_date); ?></span>
                <span class="lkn-wcip-detail-card__badge <?php echo $is_main ? 'lkn-wcip-detail-card__badge--main' : ''; ?>"><?php echo esc_html($role); ?></span>
            </div>
            <div class="lkn-wcip-detail-card__mid">
                <span class="lkn-wcip-detail-card__method"><?php echo esc_html($payment_method); ?></span>
            </div>
            <div class="lkn-wcip-detail-card__bottom">
                <span class="lkn-wcip-detail-card__status"><?php echo esc_html($status); ?></span>
                <span class="lkn-wcip-detail-card__total"><?php echo wp_kses_post($total); ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>