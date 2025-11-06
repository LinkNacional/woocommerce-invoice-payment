<?php

namespace LknWc\WcInvoicePayment\Includes;
use WC_Order;
use Exception;

final class WcPaymentInvoicePartial
{
    public function enqueueCheckoutScripts(){
        if ( is_checkout() && WC()->payment_gateways() && ! empty( WC()->payment_gateways()->get_available_payment_gateways() ) && get_option('lkn_wcip_partial_payments_enabled', '') == 'yes'){
            $currency_code =  get_woocommerce_currency();
            $currency_symbol = get_woocommerce_currency_symbol( $currency_code );
            wp_enqueue_script( 'wcInvoicePaymentPartialScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
            wp_localize_script('wcInvoicePaymentPartialScript', 'lknWcipPartialVariables', array(
                'minPartialAmount' => get_option('lkn_wcip_partial_interval_minimum', 0),
                'cart' => WC()->cart,
                'userId' => get_current_user_id(),
                'symbol' => $currency_symbol,
                'partialPaymentTitle' => __('Partial Payment', 'wc-invoice-payment'),
                'partialPaymentDescription' => __('Enter the amount you want to pay now, the rest can be paid later with other payment methods.', 'wc-invoice-payment'),
                'payPartialText' => __('Pay Partial', 'wc-invoice-payment'),
            ));
        }
    }

    public function registerStatus( $order_statuses ) {
        $order_statuses['wc-partial-pend'] = array(
            'label' => __('Pagamento parcial pendente', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-partial-comp'] = array(
            'label' => __('Pagamento parcial completo', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
        $order_statuses['wc-partial'] = array(
            'label' => __('Pagamento parcial', 'wc-invoice-payment'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true
        );
		return $order_statuses;
	}

    public function createStatus($order_statuses){
        $order_statuses['wc-partial-pend'] = __('Pagamento parcial pendente', 'wc-invoice-payment');
        $order_statuses['wc-partial-comp'] = __('Pagamento parcial completo', 'wc-invoice-payment');
        $order_statuses['wc-partial'] = __('Pagamento parcial', 'wc-invoice-payment');
        return $order_statuses;
    }

    public function allowStatusPayment($statuses) {
        $statuses[] = 'partial-pend';
        return $statuses;
    }

    public function allowStatusCancel($statuses) {
        $statuses[] = 'partial-pend';
        return $statuses;
    }

    public function showPartialFields($orderId): void {
        $partialOrder = wc_get_order( $orderId );
        if($partialOrder->get_meta('_wc_lkn_is_partial_order') == 'yes'){
            $order = wc_get_order( $partialOrder->get_meta('_wc_lkn_parent_id') );
            $totalToPay = $order->get_total() - floatval($order->get_meta('_wc_lkn_total_confirmed')) - floatval($order->get_meta('_wc_lkn_total_peding'));
    
            wc_get_template(
                '/partialTablesClient.php',
                array(
                    'donationId' => $partialOrder->get_id(),
                    'orderStatus' => $order->get_status(),
                    'totalPeding' => number_format(floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0, 2, ',', '.'),
                    'totalConfirmed' => number_format(floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0, 2, ',', '.'),
                    'total' => number_format($totalToPay ?: 0.0, 2, ',', '.'),
                    'partialsOrdersIds' => $order->get_meta('_wc_lkn_partials_id'),
                    'symbol' => get_woocommerce_currency_symbol( $order->get_currency() ),
                ),
                'woocommerce/pix/',
                plugin_dir_path( __FILE__ ) . 'templates/'
            );
            
            wp_enqueue_script( 'wcInvoicePaymentPartialScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-partial-table.js', array( 'jquery', 'wp-api' ), WC_PAYMENT_INVOICE_VERSION, false );
            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-table.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
            wp_localize_script('wcInvoicePaymentPartialScript', 'lknWcipPartialTableVariables', array(
                'orderId' => $order->get_id(),
                'totalToPay' => $totalToPay,
                'confirmPayment' => __('Are you sure you want to pay %s?', 'wc-invoice-payment'),
                'confirmCancel' => __('Are you sure you want to cancel this partial payment?', 'wc-invoice-payment'),
            ));
        }
    }

    public function statusChanged($orderId, $oldStatus, $newStatus, $order) {
        $order = wc_get_order( $orderId );
        if($order->get_meta('_wc_lkn_is_partial_order') == 'yes'){
            $parentOrder = wc_get_order( $order->get_meta('_wc_lkn_parent_id') );
            
            if($parentOrder){
                $paymentMethod = $order->get_payment_method();
                $savedStatus = get_option('lkn_wcip_partial_payment_methods_statuses', array());
                $totalPending = floatval($parentOrder->get_meta('_wc_lkn_total_peding')) ?: 0.0;
                $totalConfirmed = floatval($parentOrder->get_meta('_wc_lkn_total_confirmed')) ?: 0.0;
                $orderTotal = floatval($order->get_total()) ?: 0.0;
                $successStatuses = $savedStatus[$paymentMethod] ?? 'wc-completed';
                $newStatus = 'wc-' . $newStatus;
        
                switch ($newStatus) {
                    case 'wc-cancelled':
                        $parentOrder->update_meta_data('_wc_lkn_total_peding', $totalPending - $orderTotal);
                        break;
                    case $successStatuses:
                        $parentOrder->update_meta_data("_wc_lkn_total_peding", $totalPending - $orderTotal);
                        $parentOrder->update_meta_data("_wc_lkn_total_confirmed", $totalConfirmed + $orderTotal);
                        $order->update_status('wc-partial-comp');
                        if(($totalConfirmed + $orderTotal) >= $parentOrder->get_total()) {
                            $parentOrder->update_status(get_option('lkn_wcip_partial_complete_status', 'wc-processing'));
                        }
                        break;
                }
                
                $parentOrder->save();
                $order->save();
            }
        }
    }

    public function showPartialsPayments($order){
        $orderId = get_the_id();
        $order = wc_get_order( $orderId );
        if($order && $order->get_meta('_wc_lkn_is_partial_main_order') == 'yes'){
            $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';
            
            add_meta_box(
                'showPartialsPayments',
                'Pagamentos Parciais',
                array($this, 'showPartialOrders'),
                $screen,
                'normal',
                'high'
            );
        }
    }
    
    public function showPartialOrders($object): void
    {
        $order = is_a($object, 'WP_Post') ? wc_get_order($object->ID) : $object;
        
        if ($order->get_meta('_wc_lkn_is_partial_main_order') == 'yes') {
            wc_get_template(
                '/partialTablesAdmin.php',
                array(
                    'orderStatus' => $order->get_status(),
                    'totalPeding' => number_format(floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0, 2, ',', '.'),
                    'totalConfirmed' => number_format(floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0, 2, ',', '.'),
                    'total' => number_format(floatval($order->get_total()) ?: 0.0, 2, ',', '.'),
                    'partialsOrdersIds' => $order->get_meta('_wc_lkn_partials_id'),
                ),
                'woocommerce/pix/',
                plugin_dir_path( __FILE__ ) . 'templates/'
            );
            
            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-table.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        }
    }

    public function hidePartialOrdersRequest ( $queryArgs ) {
        $queryArgs['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => '_wc_lkn_is_partial_order',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_wc_lkn_is_partial_order',
                'value'   => 'yes',
                'compare' => '!=',
            ),
        );
        wp_enqueue_style('teste-admin-style', plugin_dir_url(__FILE__) . 'css/wc-teste21.css', array(), '', 'all');
        
        return $queryArgs;
    }

    public function fixTableCount($count, $statuses) {
        $statuses = array_map('sanitize_text_field', (array) $statuses);

        if (empty($statuses)) {
            return $count;
        }

        // Buscar todas as orders com a meta parcial
        $excluded_orders = wc_get_orders(array(
            'limit'        => -1,
            'status'       => $statuses,
            'return'       => 'ids',
            'meta_query'   => array(
                array(
                    'key'     => '_wc_lkn_is_partial_order',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            ),
        ));

        $excluded_count = count($excluded_orders);
        return max(0, $count - $excluded_count);
    }

    public function deletePartialOrders($orderId) {
        // Verifica se é uma order do WooCommerce
        $order = wc_get_order( $orderId );
        if ( ! $order ) {
            return;
        }

        // Recupera os IDs dos pedidos filhos (parciais)
        $partialsList = $order->get_meta( '_wc_lkn_partials_id', true );
        // Garante que é array
        if ( ! is_array( $partialsList ) ) {
            return;
        }
        
        // Sanitiza os IDs para garantir que são inteiros
        $partialsList = array_map( 'intval', $partialsList );
        
        // Remove duplicados e valores inválidos
        $partialsList = array_filter( array_unique( $partialsList ) );
        
        // Deleta cada pedido filho
        foreach ( $partialsList as $partial_id ) {
            // Confirma se é um pedido válido antes de deletar
            $partial_order = wc_get_order( $partial_id );
            if ( $partial_order && $partial_order->get_type() === 'shop_order' ) {
                
                // Remove do cache (WooCommerce 8+ com COT)
                if ( class_exists( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class ) ) {
                    $container = wc_get_container();
                    $order_data_store = $container->get( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class );
                    if ( method_exists( $order_data_store, 'delete_order_data_from_custom_order_tables' ) ) {
                        $order_data_store->delete_order_data_from_custom_order_tables( $partial_id );
                    }
                }

                // Limpa cache do WordPress
                clean_post_cache($partial_id);

                // Remove do banco de dados permanentemente
                wp_delete_post($partial_id, true); // true = deletar permanentemente (sem ir pra lixeira)

                // Pode forçar mais limpeza se desejar
                global $wpdb;
                $wpdb->delete( $wpdb->prefix . 'postmeta', array( 'post_id' => $partial_id ) );
                $wpdb->delete( $wpdb->prefix . 'woocommerce_order_items', array( 'order_id' => $partial_id ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id NOT IN (SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items)" ) );
            }
        }
    }

    /**
     * Adiciona páginas de faturas no menu do dashboard do Dokan
     *
     * @param array $urls Array de URLs do menu
     * @return array Array modificado com as novas páginas
     */
    public function addDokanInvoicesPage($urls) {
        $urls['faturas'] = array(
            'title' => __('Faturas', 'wc-invoice-payment'),
            'icon'  => '<i class="fas fa-file-invoice"></i>',
            'url'   => dokan_get_navigation_url('faturas'),
            'pos'   => 51
        );
        
        return $urls;
    }    /**
     * Carrega o template das páginas de faturas no dashboard do Dokan
     *
     * @return void
     */
    public function loadDokanInvoicesTemplate() {
        global $wp;

        // Verificar permissões
        if (!function_exists('dokan_is_user_seller') || !dokan_is_user_seller(get_current_user_id())) {
            if (function_exists('dokan_get_template_part')) {
                dokan_get_template_part('global/no-permission');
            } else {
                echo '<div class="dokan-alert dokan-alert-danger">' . __('Você não tem permissão para acessar esta página.', 'wc-invoice-payment') . '</div>';
            }
            return;
        }

        // Verifica se estamos na página de listagem de faturas
        if (isset($wp->query_vars['faturas']) || (isset($wp->query_vars['custom']) && $wp->query_vars['custom'] === 'faturas')) {
            $this->renderDokanInvoicesPage();
        }
        
        // Verifica se estamos na página de criação de nova fatura
        if (isset($wp->query_vars['nova-fatura']) || (isset($wp->query_vars['custom']) && $wp->query_vars['custom'] === 'nova-fatura')) {
            $this->renderDokanNewInvoicePage();
        }
    }

    /**
     * Renderiza a página de faturas do dashboard do Dokan
     *
     * @return void
     */
    private function renderDokanInvoicesPage() {
        // Carrega CSS específico para a página de faturas
        wp_enqueue_style('wcInvoicePaymentDokanInvoicesStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-dokan-invoices.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        
        // Carrega JavaScript específico para a página de faturas
        wp_enqueue_script('wcInvoicePaymentDokanInvoicesScript', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-dokan-invoices.js', array('jquery'), WC_PAYMENT_INVOICE_VERSION, true);
        
        // Localizar variáveis para o JavaScript
        wp_localize_script('wcInvoicePaymentDokanInvoicesScript', 'lknWcipDokanVars', array(
            'nonce' => wp_create_nonce('wp_rest'),
            'downloading' => __('Baixando...', 'wc-invoice-payment'),
            'invoice' => __('Fatura', 'wc-invoice-payment'),
            'pdfError' => __('Erro ao gerar PDF da fatura', 'wc-invoice-payment'),
            'itemName' => __('Nome', 'wc-invoice-payment'),
            'itemAmount' => __('Valor', 'wc-invoice-payment')
        ));
        
        // Obter faturas do vendedor atual
        $current_user_id = \get_current_user_id();
        $invoices = $this->getVendorInvoices($current_user_id);
        
        
        ?>
        <div class="dokan-dashboard-wrap">
            <?php
            /**
             * dokan_dashboard_content_before hook
             *
             * @hooked get_dashboard_side_navigation
             *
             * @since 2.4
             */
            \do_action('dokan_dashboard_content_before');
            ?>

            <div class="dokan-dashboard-content dokan-orders-content">
                <?php
                /**
                 * dokan_orders_content_inside_before hook
                 *
                 * @since 1.0.0
                 */
                \do_action('dokan_orders_content_inside_before');
                ?>

                <article class="dokan-orders-area">
                    <?php
                    /**
                     * dokan_orders_content_area_header hook
                     *
                     * @since 1.0.0
                     */
                    \do_action('dokan_orders_content_area_header');
                    ?>

                    <form action="" method="POST" class="dokan-right">
                        <div class="dokan-form-group">
                            <a href="<?php echo \dokan_get_navigation_url('nova-fatura'); ?>" class="dokan-btn dokan-btn-sm dokan-btn-theme">
                                <i class="fas fa-plus"></i> <?php \_e('Nova Fatura', 'wc-invoice-payment'); ?>
                            </a>
                        </div>
                    </form>

                    <form id="invoice-filter" method="POST" class="dokan-form-inline">
                        <div class="dokan-form-group">
                            <label for="bulk-invoice-action-selector" class="screen-reader-text"><?php \_e('Select bulk action', 'wc-invoice-payment'); ?></label>

                            <select name="status" id="bulk-invoice-action-selector" class="dokan-form-control chosen">
                                <option class="bulk-invoice-status" value="-1"><?php \_e('Bulk Actions', 'wc-invoice-payment'); ?></option>
                                <option class="bulk-invoice-status" value="wc-on-hold"><?php \_e('Change status to on-hold', 'wc-invoice-payment'); ?></option>
                                <option class="bulk-invoice-status" value="wc-processing"><?php \_e('Change status to processing', 'wc-invoice-payment'); ?></option>
                                <option class="bulk-invoice-status" value="wc-completed"><?php \_e('Change status to completed', 'wc-invoice-payment'); ?></option>
                            </select>
                        </div>

                        <div class="dokan-form-group">
                            <?php \wp_nonce_field('dokan_invoice_bulk_action', 'dokan_invoice_bulk_nonce'); ?>
                            <input type="submit" name="bulk_invoice_status_change" id="bulk-invoice-action" class="dokan-btn dokan-btn-theme" value="<?php \esc_attr_e('Apply', 'wc-invoice-payment'); ?>">
                        </div>

                        <table class="dokan-table dokan-table-striped">
                            <thead>
                                <tr>
                                    <th id="cb" class="manage-column column-cb check-column">
                                        <label for="cb-select-all"></label>
                                        <input id="cb-select-all" class="dokan-checkbox" type="checkbox">
                                    </th>
                                    <th><?php \_e('Invoice', 'wc-invoice-payment'); ?></th>
                                    <th><?php \_e('Invoice Total', 'wc-invoice-payment'); ?></th>
                                    <th><?php \_e('Status', 'wc-invoice-payment'); ?></th>
                                    <th><?php \_e('Customer', 'wc-invoice-payment'); ?></th>
                                    <th><?php \_e('Date', 'wc-invoice-payment'); ?></th>
                                    <th><?php \_e('Due Date', 'wc-invoice-payment'); ?></th>
                                    <th width="17%"><?php \_e('Action', 'wc-invoice-payment'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($invoices)) : ?>
                                    <?php foreach ($invoices as $invoice) : 
                                        $order_id = $invoice['id'];
                                        $orderInvoice = wc_get_order($order_id);

                                        $nonce = wp_create_nonce( 'dokan_view_order' );
                                        $view_url = add_query_arg(
                                            [
                                                'order_id' => $order_id,
                                                '_wpnonce' => $nonce,
                                            ],
                                            dokan_get_navigation_url( 'orders' )
                                        );
                                        ?>
                                        <tr>
                                            <th class="dokan-order-select check-column">
                                                <label for="cb-select-<?php echo \esc_attr($invoice['id']); ?>"></label>
                                                <input class="cb-select-items dokan-checkbox" type="checkbox" name="bulk_invoices[]" value="<?php echo \esc_attr($invoice['id']); ?>">
                                            </th>
                                            <td class="dokan-order-id column-primary" data-title="<?php \esc_attr_e('Invoice', 'wc-invoice-payment'); ?>">
                                                <a href="<?php echo \esc_url($view_url); ?>">
                                                    <strong><?php \printf(__('Invoice %s', 'wc-invoice-payment'), $invoice['number']); ?></strong>
                                                </a>
                                                <button type="button" class="toggle-row"></button>
                                            </td>
                                            <td class="dokan-order-total" data-title="<?php \esc_attr_e('Invoice Total', 'wc-invoice-payment'); ?>">
                                                <?php echo \wp_kses_post($invoice['total_formatted']); ?>
                                            </td>
                                            <td class="dokan-order-status" data-title="<?php \esc_attr_e('Status', 'wc-invoice-payment'); ?>">
                                                <?php echo $this->getStatusLabel($invoice['status']); ?>
                                            </td>
                                            <td class="dokan-order-customer" data-title="<?php \esc_attr_e('Customer', 'wc-invoice-payment'); ?>">
                                                <?php echo \esc_html($invoice['customer_name'] ?: __('Guest', 'wc-invoice-payment')); ?>
                                            </td>
                                            <td class="dokan-order-date" data-title="<?php \esc_attr_e('Date', 'wc-invoice-payment'); ?>">
                                                <abbr title="<?php echo \esc_attr($invoice['date_created']); ?>">
                                                    <?php echo \esc_html($invoice['date_created']); ?>
                                                </abbr>
                                            </td>
                                            <td class="dokan-order-date" data-title="<?php \esc_attr_e('Due Date', 'wc-invoice-payment'); ?>">
                                                <?php echo \esc_html($invoice['date_due']); ?>
                                            </td>
                                            <td class="dokan-order-action" width="17%" data-title="<?php \esc_attr_e('Action', 'wc-invoice-payment'); ?>">
                                                <?php if ($invoice['status'] === 'on-hold' || $invoice['status'] === 'pending' ) : ?>
                                                    <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \wp_nonce_url(\admin_url('admin-ajax.php?action=dokan-mark-order-processing&order_id=' . $invoice['id']), 'dokan-mark-order-processing'); ?>" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Mark Processing', 'wc-invoice-payment'); ?>">
                                                        <i class="far fa-clock">&nbsp;</i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($invoice['status'] === 'on-hold' || $invoice['status'] === 'pending' || $invoice['status'] === 'processing') : ?>
                                                    <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \wp_nonce_url(\admin_url('admin-ajax.php?action=dokan-mark-order-complete&order_id=' . $invoice['id']), 'dokan-mark-order-complete'); ?>" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Mark Complete', 'wc-invoice-payment'); ?>">
                                                        <i class="fas fa-check">&nbsp;</i>
                                                    </a>
                                                <?php endif; ?>
                                                <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \esc_url($view_url); ?>" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('View', 'wc-invoice-payment'); ?>">
                                                    <i class="far fa-eye">&nbsp;</i>
                                                </a>
                                                <button class="dokan-btn dokan-btn-default dokan-btn-sm tips lkn_wcip_generate_pdf_btn" data-invoice-id="<?php echo \esc_attr($invoice['id']); ?>" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Download Invoice', 'wc-invoice-payment'); ?>" type="button">
                                                    <i class="fas fa-download">&nbsp;</i>
                                                </button>
                                                <!-- Link de pagamento da fatura -->
                                                <a class="dokan-btn dokan-btn-default dokan-btn-sm tips" href="<?php echo \esc_url($orderInvoice->get_checkout_payment_url()); ?>" target="_blank" data-toggle="tooltip" data-placement="top" title="<?php \esc_attr_e('Payment Link', 'wc-invoice-payment'); ?>">
                                                    <i class="fas fa-credit-card"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="dokan-message">
                                                <?php \_e('No invoices found.', 'wc-invoice-payment'); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
                </article>

                <?php
                /**
                 * dokan_orders_content_inside_after hook
                 *
                 * @since 1.0.0
                 */
                \do_action('dokan_orders_content_inside_after');
                ?>
            </div>

            <?php
            /**
             * dokan_dashboard_content_after hook
             *
             * @since 2.4
             */
            \do_action('dokan_dashboard_content_after');
            ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Funcionalidade de select all (igual ao Dokan)
            $('#cb-select-all').on('change', function() {
                $('.cb-select-items').prop('checked', this.checked);
            });
            
            // Atualizar select all quando checkboxes individuais mudam
            $('.cb-select-items').on('change', function() {
                var total = $('.cb-select-items').length;
                var checked = $('.cb-select-items:checked').length;
                $('#cb-select-all').prop('indeterminate', checked > 0 && checked < total);
                $('#cb-select-all').prop('checked', checked === total);
            });
        });
        </script>
        <?php
    }

    /**
     * Renderiza a página de criação de nova fatura do dashboard do Dokan
     *
     * @return void
     */
    private function renderDokanNewInvoicePage() {
        // Carrega CSS específico para a página de faturas
        wp_enqueue_style('wcInvoicePaymentDokanInvoicesStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-dokan-invoices.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        
        // Processar formulário se foi enviado
        if (isset($_POST['dokan_create_invoice_nonce']) && wp_verify_nonce($_POST['dokan_create_invoice_nonce'], 'dokan_create_invoice_action')) {
            $this->processNewInvoiceForm();
        }
        
        ?>
        <div class="dokan-dashboard-wrap">
            <?php
            /**
             * dokan_dashboard_content_before hook
             *
             * @hooked get_dashboard_side_navigation
             *
             * @since 2.4
             */
            do_action('dokan_dashboard_content_before');
            ?>

            <div class="dokan-dashboard-content dokan-new-invoice-content">
                <?php
                /**
                 * dokan_new_invoice_content_inside_before hook
                 *
                 * @since 1.0.0
                 */
                do_action('dokan_new_invoice_content_inside_before');
                ?>

                <article class="dokan-new-invoice-area">
                    <?php
                    /**
                     * dokan_new_invoice_content_area_header hook
                     *
                     * @since 1.0.0
                     */
                    do_action('dokan_new_invoice_content_area_header');
                    ?>

                    <div class="dokan-new-invoice-dashboard">
                        <div class="dokan-new-invoice-header">
                            <h1 class="entry-title"><?php _e('Nova Fatura', 'wc-invoice-payment'); ?></h1>
                        </div>

                        <form method="post" class="wcip-form-wrap dokan-invoice-form">
                            <?php wp_nonce_field('dokan_create_invoice_action', 'dokan_create_invoice_nonce'); ?>
                            
                            <div class="wcip-invoice-data">
                                <div id="wcPaymentInvoiceTitles">
                                    <h3 class="title"><?php _e('Detalhes da fatura', 'wc-invoice-payment'); ?></h3>
                                    <h3 class="title"><?php _e('Dados do Pagador', 'wc-invoice-payment'); ?></h3>
                                </div>
                                <div class="invoice-row-wrap">
                                    <div class="invoice-column-wrap">
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_payment_status_input"><?php _e('Status', 'wc-invoice-payment'); ?></label>
                                            <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" class="regular-text">
                                                <option value="wc-pending"><?php _e('Pending payment', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-processing"><?php _e('Processing', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-on-hold"><?php _e('On hold', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-completed"><?php _e('Completed', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-cancelled"><?php _e('Cancelled', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-refunded"><?php _e('Refunded', 'wc-invoice-payment'); ?></option>
                                                <option value="wc-failed"><?php _e('Failed', 'wc-invoice-payment'); ?></option>
                                            </select>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_select_invoice_template"><?php _e('Template do PDF da fatura', 'wc-invoice-payment'); ?></label>
                                            <select name="lkn_wcip_select_invoice_template" id="lkn_wcip_select_invoice_template" class="regular-text" required>
                                                <option value="global"><?php _e('Template padrão', 'wc-invoice-payment'); ?></option>
                                                <?php
                                                // Buscar templates disponíveis
                                                $templates_dir = WC_PAYMENT_INVOICE_ROOT_DIR . 'Includes/templates/';
                                                if (is_dir($templates_dir)) {
                                                    $templates = array_diff(scandir($templates_dir), array('.', '..'));
                                                    foreach ($templates as $template) {
                                                        if (is_dir($templates_dir . $template) && $template !== 'myaccount') {
                                                            $preview_url = WC_PAYMENT_INVOICE_ROOT_URL . 'Includes/templates/' . $template . '/preview.webp';
                                                            echo '<option data-preview-url="' . esc_url($preview_url) . '" value="' . esc_attr($template) . '">' . esc_html(ucfirst($template)) . '</option>';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_select_invoice_language"><?php _e('Idioma do PDF da fatura', 'wc-invoice-payment'); ?></label>
                                            <select name="lkn_wcip_select_invoice_language" id="lkn_wcip_select_invoice_language" class="regular-text" required>
                                                <?php
                                                $languages = get_available_languages();
                                                $current_locale = get_locale();
                                                $locale_names = array(
                                                    'pt_BR' => 'Portuguese (Brazil)',
                                                    'en_US' => 'English (United States)'
                                                );
                                                
                                                // Adiciona idioma atual
                                                $current_name = isset($locale_names[$current_locale]) ? $locale_names[$current_locale] : $current_locale;
                                                echo '<option value="' . esc_attr($current_locale) . '" selected>' . esc_html($current_name) . '</option>';
                                                
                                                // Adiciona outros idiomas disponíveis
                                                foreach ($languages as $language) {
                                                    if ($language !== $current_locale) {
                                                        $name = isset($locale_names[$language]) ? $locale_names[$language] : $language;
                                                        echo '<option value="' . esc_attr($language) . '">' . esc_html($name) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_extra_data"><?php _e('Dados extra', 'wc-invoice-payment'); ?></label>
                                            <textarea name="lkn_wcip_extra_data" id="lkn_wcip_extra_data" class="regular-text"></textarea>
                                        </div>    
                                    </div>
                                    <div class="invoice-column-wrap">
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_name_input"><?php _e('Nome', 'wc-invoice-payment'); ?></label>
                                            <input name="lkn_wcip_name" type="text" id="lkn_wcip_name_input" class="regular-text" required>
                                        </div>
                                        <div class="input-row-wrap" id="lknWcipEmailInput">
                                            <label for="lkn_wcip_email_input"><?php _e('E-mail', 'wc-invoice-payment'); ?></label>
                                            <input name="lkn_wcip_email" type="email" id="lkn_wcip_email_input" class="regular-text" required>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label for="lkn_wcip_country_input"><?php _e('País', 'wc-invoice-payment'); ?></label>
                                            <select name="lkn_wcip_country" id="lkn_wcip_country_input" class="regular-text">
                                                <?php
                                                if (function_exists('WC')) {
                                                    $countries = WC()->countries->get_countries();
                                                    $base_country = WC()->countries->get_base_country();
                                                    foreach ($countries as $code => $name) {
                                                        $selected = ($code === $base_country) ? 'selected' : '';
                                                        echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wcip-invoice-data wcip-postbox">
                                <span class="text-bold"><?php _e('Ações de fatura', 'wc-invoice-payment'); ?></span>
                                <hr>
                                <div class="wcip-row">
                                    <div class="input-row-wrap">
                                        <select name="lkn_wcip_form_actions">
                                            <option value="no_action" selected><?php _e('Selecione uma ação...', 'wc-invoice-payment'); ?></option>
                                            <option value="send_email"><?php _e('Enviar fatura para o cliente', 'wc-invoice-payment'); ?></option>
                                        </select>
                                    </div>
                                    <div class="input-row-wrap">
                                        <label for="lkn_wcip_exp_date_input"><?php _e('Data de vencimento', 'wc-invoice-payment'); ?></label>
                                        <input id="lkn_wcip_exp_date_input" type="date" name="lkn_wcip_exp_date" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wcip-invoice-data">
                                <h3 class="title"><?php _e('Preço', 'wc-invoice-payment'); ?></h3>
                                <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                                    <div class="price-row-wrap price-row-0">
                                        <div class="input-row-wrap">
                                            <label><?php _e('Nome', 'wc-invoice-payment'); ?></label>
                                            <input name="lkn_wcip_name_invoice_0" type="text" id="lkn_wcip_name_invoice_0" class="regular-text" required>
                                        </div>
                                        <div class="input-row-wrap">
                                            <label><?php _e('Valor', 'wc-invoice-payment'); ?></label>
                                            <input name="lkn_wcip_amount_invoice_0" type="tel" id="lkn_wcip_amount_invoice_0" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');" required>
                                        </div>
                                        <div class="input-row-wrap">
                                            <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(0)">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="invoice-row-wrap">
                                    <button type="button" class="btn btn-add-line" onclick="lkn_wcip_add_amount_row()"><?php _e('Adicionar linha', 'wc-invoice-payment'); ?></button>
                                </div>
                            </div>
                            
                            <div class="wcip-invoice-data">
                                <h3 class="title"><?php _e('Notas do rodapé', 'wc-invoice-payment'); ?></h3>
                                <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                                    <div class="input-row-wrap">
                                        <label><?php _e('Detalhes em HTML', 'wc-invoice-payment'); ?></label>
                                        <textarea name="lkn-wc-invoice-payment-footer-notes" id="lkn-wc-invoice-payment-footer-notes" class="regular-text"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="wcip-invoice-actions">
                                <button type="submit" class="dokan-btn dokan-btn-primary"><?php _e('Criar Fatura', 'wc-invoice-payment'); ?></button>
                                <a href="<?php echo dokan_get_navigation_url('faturas'); ?>" class="dokan-btn dokan-btn-default"><?php _e('Cancelar', 'wc-invoice-payment'); ?></a>
                            </div>
                        </form>
                    </div>
                </article>

                <?php
                /**
                 * dokan_new_invoice_content_inside_after hook
                 *
                 * @since 1.0.0
                 */
                do_action('dokan_new_invoice_content_inside_after');
                ?>
            </div>

            <?php
            /**
             * dokan_dashboard_content_after hook
             *
             * @since 2.4
             */
            do_action('dokan_dashboard_content_after');
            ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Funções para adicionar/remover linhas de preço
            window.lkn_wcip_row_counter = 0;
            
            window.lkn_wcip_add_amount_row = function() {
                lkn_wcip_row_counter++;
                var row = '<div class="price-row-wrap price-row-' + lkn_wcip_row_counter + '">' +
                    '<div class="input-row-wrap">' +
                        '<label><?php _e("Nome", "wc-invoice-payment"); ?></label>' +
                        '<input name="lkn_wcip_name_invoice_' + lkn_wcip_row_counter + '" type="text" class="regular-text" required>' +
                    '</div>' +
                    '<div class="input-row-wrap">' +
                        '<label><?php _e("Valor", "wc-invoice-payment"); ?></label>' +
                        '<input name="lkn_wcip_amount_invoice_' + lkn_wcip_row_counter + '" type="tel" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, \'\').replace(/(\..*?)\..*/g, \'$1\');" required>' +
                    '</div>' +
                    '<div class="input-row-wrap">' +
                        '<button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(' + lkn_wcip_row_counter + ')">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                    '</div>' +
                '</div>';
                $('#wcip-invoice-price-row').append(row);
            };
            
            window.lkn_wcip_remove_amount_row = function(row_id) {
                $('.price-row-' + row_id).remove();
            };
        });
        </script>
        <?php
    }

    /**
     * Processa o formulário de criação de nova fatura
     *
     * @return void
     */
    private function processNewInvoiceForm() {
        $current_user_id = get_current_user_id();
        
        try {
            // Validar campos obrigatórios
            $required_fields = array(
                'lkn_wcip_name' => __('Nome', 'wc-invoice-payment'),
                'lkn_wcip_email' => __('E-mail', 'wc-invoice-payment'),
                'lkn_wcip_exp_date' => __('Data de vencimento', 'wc-invoice-payment'),
                'lkn_wcip_name_invoice_0' => __('Nome do item', 'wc-invoice-payment'),
                'lkn_wcip_amount_invoice_0' => __('Valor do item', 'wc-invoice-payment')
            );
            
            foreach ($required_fields as $field => $label) {
                if (empty($_POST[$field])) {
                    throw new Exception(sprintf(__('Campo obrigatório: %s', 'wc-invoice-payment'), $label));
                }
            }
            
            // Coletar dados do formulário
            $invoice_data = array(
                'payment_status' => sanitize_text_field($_POST['lkn_wcip_payment_status']),
                'payment_method' => 'multiplePayment', // Valor padrão fixo
                'currency' => \get_woocommerce_currency(), // Moeda padrão do WooCommerce
                'template' => sanitize_text_field($_POST['lkn_wcip_select_invoice_template']),
                'language' => sanitize_text_field($_POST['lkn_wcip_select_invoice_language']),
                'customer_name' => sanitize_text_field($_POST['lkn_wcip_name']),
                'customer_email' => sanitize_email($_POST['lkn_wcip_email']),
                'country' => sanitize_text_field($_POST['lkn_wcip_country']),
                'extra_data' => sanitize_textarea_field($_POST['lkn_wcip_extra_data']),
                'form_action' => sanitize_text_field($_POST['lkn_wcip_form_actions']),
                'due_date' => sanitize_text_field($_POST['lkn_wcip_exp_date']),
                'footer_notes' => wp_kses_post($_POST['lkn-wc-invoice-payment-footer-notes'])
            );
            
            // Coletar itens da fatura
            $invoice_items = array();
            $counter = 0;
            while (isset($_POST['lkn_wcip_name_invoice_' . $counter])) {
                if (!empty($_POST['lkn_wcip_name_invoice_' . $counter]) && !empty($_POST['lkn_wcip_amount_invoice_' . $counter])) {
                    $invoice_items[] = array(
                        'name' => sanitize_text_field($_POST['lkn_wcip_name_invoice_' . $counter]),
                        'amount' => floatval(str_replace(',', '.', str_replace('.', '', $_POST['lkn_wcip_amount_invoice_' . $counter])))
                    );
                }
                $counter++;
            }
            
            if (empty($invoice_items)) {
                throw new Exception(__('Pelo menos um item deve ser adicionado à fatura', 'wc-invoice-payment'));
            }
            
            // Criar a fatura
            $order_id = $this->createInvoiceOrder($invoice_data, $invoice_items, $current_user_id);
            
            if ($order_id) {
                // Redirecionar para página de faturas com mensagem de sucesso
                $redirect_url = add_query_arg(array(
                    'message' => 'invoice_created',
                    'order_id' => $order_id
                ), dokan_get_navigation_url('faturas'));
                
                wp_redirect($redirect_url);
                exit;
            } else {
                throw new Exception(__('Erro ao criar a fatura', 'wc-invoice-payment'));
            }
            
        } catch (Exception $e) {
            // Exibir mensagem de erro
            echo '<div class="dokan-alert dokan-alert-danger"><p>' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    /**
     * Cria uma ordem/fatura
     *
     * @param array $invoice_data Dados da fatura
     * @param array $invoice_items Itens da fatura
     * @param int $vendor_id ID do vendedor
     * @return int|false ID da ordem criada ou false em caso de erro
     */
    private function createInvoiceOrder($invoice_data, $invoice_items, $vendor_id) {
        try {
            // Criar ordem
            $order = \wc_create_order(array(
                'status' => \str_replace('wc-', '', $invoice_data['payment_status']),
                'customer_id' => 0 // Visitante por padrão
            ));
            
            if (!$order) {
                return false;
            }
            
            // Definir autor da ordem como o vendedor
            \wp_update_post(array(
                'ID' => $order->get_id(),
                'post_author' => $vendor_id
            ));
            
            // Adicionar dados de endereçamento
            $order->set_billing_first_name($invoice_data['customer_name']);
            $order->set_billing_email($invoice_data['customer_email']);
            $order->set_billing_country($invoice_data['country']);
            
            // Adicionar itens à ordem como produtos/line items (igual ao administrador)
            $total = 0;
            foreach ($invoice_items as $item) {
                // Criar item de linha do pedido
                $order_item = new \WC_Order_Item_Product();
                $order_item->set_name($item['name']);
                $order_item->set_quantity(1);
                $order_item->set_subtotal($item['amount']);
                $order_item->set_total($item['amount']);
                
                // Adicionar meta dados para identificar como item de fatura
                $order_item->add_meta_data('_lkn_wcip_is_invoice_item', 'yes');
                $order_item->add_meta_data('_lkn_wcip_invoice_item_name', $item['name']);
                $order_item->add_meta_data('_lkn_wcip_invoice_item_amount', $item['amount']);
                
                // Adicionar o item à ordem
                $order->add_item($order_item);
                $total += $item['amount'];
            }
            
            // Definir moeda
            $order->set_currency($invoice_data['currency']);
            
            // Salvar meta dados da fatura
            $order->update_meta_data('_lkn_wcip_invoice_data', $invoice_data);
            $order->update_meta_data('lkn_exp_date', $invoice_data['due_date']);
            $order->update_meta_data('_lkn_wcip_is_invoice', 'yes');
            $order->update_meta_data('_lkn_wcip_is_dokan_invoice', 'yes');
            $order->update_meta_data('_lkn_wcip_vendor_id', $vendor_id);
            $order->update_meta_data('_dokan_vendor_id', $vendor_id);
            $order->save();

            // Adicionar nota indicando que foi criada pelo vendedor (apenas para administradores)
            $vendor_info = \get_userdata($vendor_id);
            $vendor_name = $vendor_info ? $vendor_info->display_name : __('Vendedor', 'wc-invoice-payment');
            $edit_link = \admin_url('admin.php?page=edit-invoice&invoice=' . $order->get_id());
            $note = \sprintf(
                __('Esta <a href="%s" target="_blank">fatura</a> foi criada pelo vendedor: %s', 'wc-invoice-payment'),
                $edit_link,
                $vendor_name
            );
            $order->add_order_note($note, false, false); // false = apenas para administradores

            // Definir autor do pedido
            wp_update_post([
                'ID'          => $order->get_id(),
                'post_author' => $vendor_id,
            ]);

            // Criar entrada do Dokan
            if ( function_exists( 'dokan_sync_insert_order' ) ) {
                dokan_sync_insert_order( $order->get_id() );
            }

            // Corrigir seller_id na tabela dokan_orders
            global $wpdb;
            $wpdb->update(
                "{$wpdb->prefix}dokan_orders",
                [ 'seller_id' => (int) $vendor_id ],
                [ 'order_id'  => (int) $order->get_id() ],
                [ '%d' ],
                [ '%d' ]
            );
            
            
            
            if (!empty($invoice_data['footer_notes'])) {
                $order->update_meta_data('_lkn_wcip_footer_notes', $invoice_data['footer_notes']);
            }
            
            if (!empty($invoice_data['extra_data'])) {
                $order->update_meta_data('_lkn_wcip_extra_data', $invoice_data['extra_data']);
            }
            
            // Recalcular totais
            $order->calculate_totals();
            $order->save();
            
            // Enviar e-mail se solicitado
            if ($invoice_data['form_action'] === 'send_email') {
                // Enviar e-mail da fatura
                if (\function_exists('WC')) {
                    \WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger($order->get_id());
                }
            }
            
            return $order->get_id();
            
        } catch (Exception $e) {
            \error_log('Erro ao criar fatura: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter faturas do vendedor atual
     *
     * @param int $vendor_id ID do vendedor
     * @return array Array de faturas
     */
    private function getVendorInvoices($vendor_id) {
        // Buscar apenas faturas do Dokan (não pagamentos parciais)
        $args = array(
            'limit'        => -1,
            'status'       => array('completed', 'processing', 'pending', 'on-hold', 'cancelled', 'refunded', 'failed'),
            'meta_query'   => array(
                'relation' => 'AND',
                array(
                    'key'     => '_lkn_wcip_is_dokan_invoice',
                    'value'   => 'yes',
                    'compare' => '='
                ),
                array(
                    'key'     => '_dokan_vendor_id',
                    'value'   => $vendor_id,
                    'compare' => '='
                )
            )
        );
        
        $orders = \wc_get_orders($args);
        $invoices = array();
        
        foreach ($orders as $order) {
            if (!$order || !is_a($order, 'WC_Order')) {
                continue;
            }
            
            // Verificar se é realmente uma fatura do vendedor
            if (\function_exists('dokan_get_seller_id_by_order') && \dokan_get_seller_id_by_order($order->get_id()) != $vendor_id) {
                continue;
            }
            
            $invoice_data = array(
                'id'               => $order->get_id(),
                'number'           => $order->get_order_number(),
                'customer_name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'status'           => $order->get_status(),
                'status_label'     => \wc_get_order_status_name($order->get_status()),
                'total_formatted'  => $order->get_formatted_order_total(),
                'type'             => $this->getInvoiceType($order),
                'date_created'     => $order->get_date_created()->date_i18n('d/m/Y H:i'),
                'date_due'         => $this->getInvoiceDueDate($order),
                'edit_url'         => $order->get_edit_order_url(),
            );
            
            $invoices[] = $invoice_data;
        }
        
        // Ordenar por data de criação (mais recente primeiro)
        usort($invoices, function($a, $b) {
            return strcmp($b['date_created'], $a['date_created']);
        });
        
        return $invoices;
    }

    /**
     * Obter tipo da fatura
     *
     * @param WC_Order $order
     * @return string
     */
    private function getInvoiceType($order) {
        if (\get_post_meta($order->get_id(), '_lkn_wcip_is_partial_payment', true) === 'yes') {
            return \__('Pagamento Parcial', 'wc-invoice-payment');
        }
        
        if (\get_post_meta($order->get_id(), '_lkn_wcip_invoice_data', true)) {
            return \__('Fatura', 'wc-invoice-payment');
        }
        
        return \__('Pedido', 'wc-invoice-payment');
    }

    /**
     * Obter data de vencimento da fatura
     *
     * @param WC_Order $order
     * @return string
     */
    private function getInvoiceDueDate($order) {
        $due_date = \get_post_meta($order->get_id(), 'lkn_exp_date', true);
        
        if ($due_date) {
            $date = new \DateTime($due_date);
            return $date->format('d/m/Y');
        }
        
        // Se não tem data de vencimento específica, usar data de criação + 30 dias como padrão
        $created_date = $order->get_date_created();
        if ($created_date) {
            $due_date = clone $created_date;
            $due_date->modify('+30 days');
            return $due_date->date_i18n('d/m/Y');
        }
        
        return '-';
    }

    /**
     * Obter contagem de faturas por status
     *
     * @param int $vendor_id ID do vendedor
     * @return array Array com contagem por status
     */
    private function getInvoiceStatusCounts($vendor_id) {
        // Buscar apenas faturas do Dokan (não pagamentos parciais)
        $args = array(
            'limit'        => -1,
            'status'       => 'any',
            'meta_query'   => array(
                'relation' => 'AND',
                array(
                    'key'     => '_lkn_wcip_is_dokan_invoice',
                    'value'   => 'yes',
                    'compare' => '='
                ),
                array(
                    'key'     => '_dokan_vendor_id',
                    'value'   => $vendor_id,
                    'compare' => '='
                )
            )
        );
        
        $orders = \wc_get_orders($args);
        $counts = array(
            'all' => 0,
            'by_status' => array()
        );
        
        foreach ($orders as $order) {
            if (!$order || !is_a($order, 'WC_Order')) {
                continue;
            }
            
            $status = $order->get_status();
            $counts['all']++;
            
            if (!isset($counts['by_status'][$status])) {
                $counts['by_status'][$status] = 0;
            }
            $counts['by_status'][$status]++;
        }
        
        return $counts;
    }

    /**
     * Obter label do status formatado (similar ao Dokan)
     *
     * @param string $status Status da ordem
     * @return string HTML do label do status
     */
    private function getStatusLabel($status) {
        $status_classes = array(
            'pending'    => 'dokan-label dokan-label-warning',
            'on-hold'    => 'dokan-label dokan-label-warning',
            'processing' => 'dokan-label dokan-label-info',
            'completed'  => 'dokan-label dokan-label-success',
            'cancelled'  => 'dokan-label dokan-label-danger',
            'refunded'   => 'dokan-label dokan-label-danger',
            'failed'     => 'dokan-label dokan-label-danger',
            'partial-pend' => 'dokan-label dokan-label-warning',
            'partial-comp' => 'dokan-label dokan-label-success',
            'partial'    => 'dokan-label dokan-label-info',
        );
        
        $class = isset($status_classes[$status]) ? $status_classes[$status] : 'dokan-label dokan-label-default';
        $label = \wc_get_order_status_name($status);
        
        return sprintf('<span class="%s">%s</span>', $class, $label);
    }

    /**
     * Adiciona query variables para as páginas de faturas do Dokan
     *
     * @param array $query_vars Array de query variables
     * @return array Array modificado com as novas query variables
     */
    public function addDokanInvoicesQueryVar($query_vars) {
        $query_vars['faturas'] = 'faturas';
        $query_vars['nova-fatura'] = 'nova-fatura';
        return $query_vars;
    }

    /**
     * Gera URL segura para download de fatura
     *
     * @param int $invoice_id ID da fatura
     * @return string URL de download com nonce
     */
    private function getInvoiceDownloadUrl($invoice_id) {
        return add_query_arg(array(
            'action' => 'lkn_wcip_download_invoice',
            'invoice_id' => $invoice_id,
            'nonce' => wp_create_nonce('lkn_wcip_download_invoice_' . $invoice_id)
        ), admin_url('admin-ajax.php'));
    }

    /**
     * Registra handlers AJAX para download de faturas
     */
    private function registerDownloadInvoiceAjax() {
        add_action('wp_ajax_lkn_wcip_download_invoice', array($this, 'handleInvoiceDownload'));
        add_action('wp_ajax_nopriv_lkn_wcip_download_invoice', array($this, 'handleInvoiceDownload'));
    }

    /**
     * Handler AJAX para processar download de faturas
     */
    public function handleInvoiceDownload() {
        // Verificar nonce
        $invoice_id = intval($_GET['invoice_id'] ?? 0);
        $nonce = sanitize_text_field($_GET['nonce'] ?? '');
        
        if (!wp_verify_nonce($nonce, 'lkn_wcip_download_invoice_' . $invoice_id)) {
            wp_die(__('Security check failed', 'wc-invoice-payment'));
        }

        // Verificar se usuário pode baixar esta fatura
        if (!$this->canUserDownloadInvoice($invoice_id)) {
            wp_die(__('You do not have permission to download this invoice', 'wc-invoice-payment'));
        }

        // Gerar e servir o PDF
        $this->generateAndServeInvoicePdf($invoice_id);
    }

    /**
     * Verifica se o usuário atual pode baixar a fatura
     *
     * @param int $invoice_id ID da fatura
     * @return bool True se pode baixar, false caso contrário
     */
    private function canUserDownloadInvoice($invoice_id) {
        global $wpdb;
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return false;
        }

        // Verificar se a fatura pertence ao vendedor atual
        $vendor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT seller_id FROM {$wpdb->prefix}dokan_orders WHERE order_id = %d",
            $invoice_id
        ));

        return ($vendor_id && intval($vendor_id) === $current_user_id);
    }

    /**
     * Gera e serve o PDF da fatura
     *
     * @param int $invoice_id ID da fatura
     */
    private function generateAndServeInvoicePdf($invoice_id) {
        // Usar a mesma API REST que já existe para gerar PDFs
        $rest_request = new \WP_REST_Request('GET', '/wc-invoice-payment/v1/generate-pdf');
        $rest_request->set_param('invoice_id', $invoice_id);
        
        // Processar a requisição
        $rest_server = rest_get_server();
        $response = $rest_server->dispatch($rest_request);
        
        if (is_wp_error($response)) {
            wp_die(__('Error generating PDF', 'wc-invoice-payment'));
        }

        // Se chegou até aqui, o PDF foi gerado e servido pela API REST
        exit;
    }

    /**
     * Adiciona botões de fatura na página de detalhes do pedido do Dokan
     *
     * @param WC_Order $order
     * @return void
     */
    public function addInvoiceButtonsToOrderDetails($order) {
        // Verificar se é uma fatura (pedido criado pelo plugin)
        $is_invoice = $order->get_meta('_lkn_wcip_invoice_data');
        
        if (!$is_invoice) {
            return;
        }
        
        // Carregar CSS específico para os botões
        wp_enqueue_style('wcInvoicePaymentDokanInvoicesStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-dokan-invoices.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        
        // Verificar se o usuário tem permissão (é o vendedor da fatura)
        $current_user_id = get_current_user_id();
        if (!function_exists('dokan_is_user_seller') || !dokan_is_user_seller($current_user_id)) {
            return;
        }
        
        // Verificar se a fatura pertence ao vendedor
        $vendor_id = $order->get_meta('_dokan_vendor_id');
        if (!$vendor_id || (int) $vendor_id !== $current_user_id) {
            // Verificar na tabela wp_dokan_orders se existe relação
            global $wpdb;
            $dokan_order = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}dokan_orders WHERE order_id = %d AND seller_id = %d",
                    $order->get_id(),
                    $current_user_id
                )
            );
            
            if (empty($dokan_order)) {
                return;
            }
        }
        
        $payment_url = $order->get_checkout_payment_url();
        $order_id = $order->get_id();
        
        ?>
        <div class="" style="width:100%; margin-top: 20px;">
            <div class="dokan-panel dokan-panel-default lkn-wcip-invoice-actions">
                <div class="dokan-panel-heading">
                    <strong><?php esc_html_e('Ações da Fatura', 'wc-invoice-payment'); ?></strong>
                </div>
                <div class="dokan-panel-body">
                    <div class="lkn-wcip-invoice-buttons" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <button class="dokan-btn dokan-btn-success dokan-btn-sm lkn_wcip_generate_pdf_btn" 
                                data-invoice-id="<?php echo esc_attr($order_id); ?>" 
                                type="button">
                            <i class="fas fa-download"></i> <?php esc_html_e('Baixar Fatura', 'wc-invoice-payment'); ?>
                        </button>
                        
                        <a class="dokan-btn dokan-btn-info dokan-btn-sm" 
                           href="<?php echo esc_url($payment_url); ?>" 
                           target="_blank">
                            <i class="fas fa-credit-card"></i> <?php esc_html_e('Link de Pagamento da Fatura', 'wc-invoice-payment'); ?>
                        </a>
                        
                        <button class="dokan-btn dokan-btn-default dokan-btn-sm" 
                                onclick="lkn_wcip_display_dokan_modal()" 
                                type="button">
                            <i class="fas fa-share-alt"></i> <?php esc_html_e('Compartilhar Link', 'wc-invoice-payment'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de compartilhamento -->
        <div id="lkn-wcip-dokan-share-modal" style="display: none;">
            <div id="lkn-wcip-dokan-share-modal-content">
                    <?php esc_html_e('Compartilhe com', 'wc-invoice-payment'); ?>
                </h3>
                <div id="lkn-wcip-share-buttons" style="display: flex; gap: 15px; justify-content: center; margin: 20px 0;">
                    <a href="#" class="lkn-wcip-share-icon dashicons dashicons-whatsapp" 
                       onclick="lkn_wcip_open_dokan_popup('whatsapp', '<?php echo esc_js($payment_url); ?>')" 
                       style="font-size: 32px; color: #25D366; text-decoration: none;">
                    </a>
                    <a href="#" class="lkn-wcip-share-icon dashicons dashicons-twitter" 
                       onclick="lkn_wcip_open_dokan_popup('twitter', '<?php echo esc_js($payment_url); ?>')" 
                       style="font-size: 32px; color: #1DA1F2; text-decoration: none;">
                    </a>
                    <a href="mailto:?subject=<?php echo esc_attr(__('Link de fatura', 'wc-invoice-payment')); ?>&body=<?php echo esc_attr($payment_url); ?>" 
                       class="lkn-wcip-share-icon dashicons dashicons-email-alt" 
                       target="_blank"
                       style="font-size: 32px; color: #34495e; text-decoration: none;">
                    </a>
                </div>
                <h3 id="lkn-wcip-share-title" style="color: #333;">
                    <?php esc_html_e('Ou copie o link', 'wc-invoice-payment'); ?>
                </h3>
                <div id="lkn-wcip-copy-link-div" style="display: flex; gap: 5px; align-items: center;">
                    <input id="lkn-wcip-dokan-copy-input" type="text" value="<?php echo esc_attr($payment_url); ?>" readonly 
                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                    <span onclick="lkn_wcip_copy_dokan_link()" class="lkn-wcip-copy-button">
                        <span class="dashicons dashicons-clipboard" style="color: white;"></span>
                    </span>
                </div>
                <a href="#" onclick="lkn_wcip_display_dokan_modal()" 
                   style="position: absolute; top: 10px; right: 15px; font-size: 24px; font-weight: bold; color: #aaa; text-decoration: none;">×</a>
            </div>
        </div>

        <script type="text/javascript">
        function lkn_wcip_display_dokan_modal() {
            var modal = document.getElementById('lkn-wcip-dokan-share-modal');
            if (modal.style.display === 'none' || modal.style.display === '') {
                modal.style.display = 'flex';
            } else {
                modal.style.display = 'none';
            }
        }

        function lkn_wcip_open_dokan_popup(type, url) {
            var share_url = '';
            var message = '<?php echo esc_js(__("Confira este link de pagamento: ", "wc-invoice-payment")); ?>';
            
            switch(type) {
                case 'whatsapp':
                    share_url = 'https://wa.me/?text=' + encodeURIComponent(message + url);
                    break;
                case 'twitter':
                    share_url = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(message + url);
                    break;
            }
            
            if (share_url) {
                window.open(share_url, '_blank', 'width=600,height=400');
            }
        }

        function lkn_wcip_copy_dokan_link() {
            var copyInput = document.getElementById('lkn-wcip-dokan-copy-input');
            copyInput.select();
            copyInput.setSelectionRange(0, 99999); // Para dispositivos móveis
            
            try {
                document.execCommand('copy');
                
                var copyButton = event.target.closest('.lkn-wcip-copy-button');
                var originalHTML = copyButton.innerHTML;
                copyButton.innerHTML = '<span class="dashicons dashicons-yes" style="color: white;"></span>';
                
                setTimeout(function() {
                    copyButton.innerHTML = originalHTML;
                }, 2000);
                
            } catch (err) {
                console.error('Erro ao copiar: ', err);
            }
        }

        // Funcionalidade do botão de download PDF
        jQuery(document).ready(function($) {
            $('.lkn_wcip_generate_pdf_btn').on('click', function(e) {
                e.preventDefault();
                
                var invoiceId = $(this).data('invoice-id');
                if (!invoiceId) {
                    return;
                }
                
                var downloadUrl = '<?php echo esc_url(rest_url('wc-invoice-payment/v1/generate-pdf')); ?>?invoice_id=' + invoiceId;
                
                // Criar link temporário para download
                var link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'invoice-' + invoiceId + '.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
        </script>
        <?php
    }

    /**
     * Inicializa sistema de faturas do Dokan
     */
    public function initDokanInvoicesSystem() {
        $this->registerDownloadInvoiceAjax();
    }
}