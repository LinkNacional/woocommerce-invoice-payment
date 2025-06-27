<?php
if (! defined('ABSPATH')) {
    exit();
}

if(isset($invoicePage) && $invoicePage == 'true'){
    ?>
    <div class="wcip-invoice-data">
    <h2 class="title">
        Pagamentos parciais                
    </h2>
    <hr>
    <?php
}
?>
<table class="wp-list-table widefat fixed striped table-view-list orders wc-orders-list-table wc-orders-list-table-shop_order wcPaymentInvoicePartialTableAdmin">
    <thead>
        <tr>
            <th scope="col" id="order_number" class="manage-column column-order_number column-primary  "><span>Pedido</span></th>
            <th scope="col" id="order_date" class="manage-column column-order_date sortable desc"><span>Data</span></th>
            <th scope="col" id="order_status" class="manage-column column-order_status">Status</th>
            <th scope="col" id="order_total" class="manage-column column-order_total sortable desc"><span>Total</span></th>
        </tr>
    </thead>

    <tbody id="the-list" data-wp-lists="list:order">

        <?php foreach ($partialsOrdersIds as $order_id) :
            $partial_order = wc_get_order($order_id);
            if (! $partial_order) {
                continue;
            }

            $created_date = $partial_order->get_date_created() ? $partial_order->get_date_created()->date_i18n('d/m/Y') : '-';
            $payment_method = $partial_order->get_payment_method_title() ?: '-';
            $status_slug = $partial_order->get_status();
            $status_label = wc_get_order_status_name($status_slug);
            $total = wc_price($partial_order->get_total());

            $billing = $partial_order->get_formatted_billing_full_name() . ', ' . $partial_order->get_billing_address_1() . ', ' . $partial_order->get_billing_city() . ', ' . $partial_order->get_billing_state() . ', ' . $partial_order->get_billing_postcode();
            $shipping = $partial_order->get_formatted_shipping_full_name() . ', ' . $partial_order->get_shipping_address_1() . ', ' . $partial_order->get_shipping_city() . ', ' . $partial_order->get_shipping_state() . ', ' . $partial_order->get_shipping_postcode();

            $order_number = $partial_order->get_order_number();
            $customer_name = $partial_order->get_formatted_billing_full_name();
            $order_link = admin_url("admin.php?page=edit-invoice&invoice={$order_id}");
        ?>
            <tr class="type-shop_order status-<?php echo esc_attr($status_slug); ?>">
                <td class="order_number column-order_number has-row-actions column-primary" data-colname="Pedido">
                    <a href="<?php echo esc_url($order_link); ?>" class="order-view">
                        <strong>#<?php echo esc_html($order_number); ?> <?php echo esc_html($customer_name); ?></strong>
                    </a>
                </td>
                <td class="order_date column-order_date" data-colname="Data"><?php echo esc_html($created_date); ?></td>
                <td class="order_status column-order_status" data-colname="Status">
                    <mark class="order-status status-<?php echo esc_attr($status_slug); ?> tips">
                        <span><?php echo esc_html($status_label); ?></span>
                    </mark>
                </td>
                <td class="billing_address column-billing_address hidden" data-colname="Cobrança">
                    <?php echo esc_html($billing); ?><span class="description">via <?php echo esc_html($payment_method); ?></span>
                </td>
                <td class="shipping_address column-shipping_address hidden" data-colname="Enviar para">
                    <a target="_blank" href="https://maps.google.com/maps?q=<?php echo urlencode($shipping); ?>&z=16">
                        <?php echo esc_html($shipping); ?>
                    </a>
                </td>
                <td class="order_total column-order_total" data-colname="Total"><?php echo wp_kses_post($total); ?></td>
                <td class="wc_actions column-wc_actions hidden" data-colname="Ações">
                    <p><a class="button wc-action-button wc-action-button-complete complete"
                            href="<?php echo esc_url(admin_url("admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id={$order_id}&_wpnonce=" . wp_create_nonce('woocommerce-mark-order-status'))); ?>"
                            aria-label="Concluído">Concluído</a></p>
                </td>
            </tr>
        <?php endforeach; ?>

    </tbody>
</table>

<?php 
    if(isset($invoicePage) && $invoicePage == 'true'){
        ?>
        </div>
        <?php
    }
?>
