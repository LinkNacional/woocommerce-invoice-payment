<?php

namespace LknWc\WcInvoicePayment\Includes;

use DateTime;
use Exception;

final class WcPaymentInvoiceSubscription
{
    public function cancel_subscription_callback(): void
    {
        if (isset($_POST['wcip_rest_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcip_rest_nonce'])), 'wp_rest')) {
            // Obter o ID da fatura do pedido
            $invoice_id = isset($_POST['invoice_id']) ? sanitize_text_field(wp_unslash($_POST['invoice_id'])) : '';

            if (empty($invoice_id)) {
                return;
            }

            $invoice = wc_get_order($invoice_id);

            if (! $invoice) {
                wp_die();
            }

            $this->unschedule_subscription_events((int) $invoice_id);

            $invoice->update_status('cancelled');
            $invoice->save();
            
            // Remove da lista do schedule quando cancelar
            $this->remove_subscription_from_schedule($invoice_id);
            
            wp_die();
        }
    }

    /**
     * Remove os eventos de assinatura (single) de um pedido, preservando os recorrentes criados na tela de faturas
     */
    public function unschedule_subscription_events($order_id): void
    {
        $hooks = array('generate_invoice_event', 'lkn_wcip_cron_hook');

        foreach (_get_cron_array() as $timestamp => $cron_events) {
            foreach ($cron_events as $hook => $events) {
                if (! in_array($hook, $hooks, true)) {
                    continue;
                }

                foreach ($events as $event) {
                    // Evento recorrente (daily) da expiração de fatura: não é da assinatura
                    if (! empty($event['schedule'])) {
                        continue;
                    }

                    $event_args = isset($event['args']) ? $event['args'] : array();

                    if (is_array($event_args) && in_array((string) $order_id, array_map('strval', $event_args), true)) {
                        wp_unschedule_event($timestamp, $hook, $event_args);
                    }
                }
            }
        }
    }

    public function add_checkbox($products_type)
    {
        global $post;
        //Criando uma nova checkbox no formulário de criação de produtos
        if (isset($post->ID)) {
            $subscription_product = get_post_meta($post->ID, '_lkn-wcip-subscription-product', true);

            $products_type['subscriptionCheckbox'] = array(
                'id' => '_lkn-wcip-subscription-product',
                'wrapper_class' => 'hide_if_external',
                'label' => __('Subscription', 'wc-invoice-payment'),
                'description' => __('This is a subscription product.', 'wc-invoice-payment'),
                'default' => $subscription_product ? 'yes' : 'no',
            );
        }
        return $products_type;
    }

    public function add_tab($tabs)
    {
        //Criando uma nova guia no formulário de criação de produtos
        $tabs['subscriptionTab'] = array(
            'label' => __('Subscription', 'wc-invoice-payment'),
            'target' => 'lkn-wcip-subscription-data',
            'class' => array(),
            'priority' => 90,
        );
        return apply_filters('subscriptionTab', $tabs);
    }

    public function add_text_field_to_subscription_tab(): void
    {
        global $post;
        $subscription_number = get_post_meta($post->ID, 'lkn_wcip_subscription_interval_number', true);
        $subscription_interval = get_post_meta($post->ID, 'lkn_wcip_subscription_interval_type', true);
        $subscription_limit = get_post_meta($post->ID, 'lkn_wcip_subscription_limit', true);
        $subscription_limit = empty($subscription_limit) ? 0 : $subscription_limit;
        //Caso nenhum exista o padrão será 1 mês
        if (empty($subscription_number) && empty($subscription_interval)) {
            $subscription_number = 1;
            $subscription_interval = 'month';
        }
?>
        <div
            id="lkn-wcip-subscription-data"
            class="panel woocommerce_options_panel">
            <p class="form-field">
                <label
                    for="lkn_wcip_subscription_interval_number"><?php esc_attr_e('Subscription Interval', 'wc-invoice-payment'); ?></label>
                <input
                    type="number"
                    class="short wc_input_number"
                    min="1"
                    name="lkn_wcip_subscription_interval_number"
                    id="lkn_wcip_subscription_interval_number"
                    value="<?php echo esc_attr($subscription_number); ?>">
                <input
                    type="hidden"
                    name="lkn_wcip_subscription_nonce"
                    id="lkn_wcip_subscription_nonce"
                    value="<?php echo esc_attr(wp_create_nonce('subscription_nonce')); ?>">
                <select
                    id="lkn_wcip_subscription_interval_type"
                    name="lkn_wcip_subscription_interval_type"
                    class="lkn_wcip_subscription_interval_type">
                    <?php
                    $options = array(
                        'day' => __('Days', 'wc-invoice-payment'),
                        'week' => __('Weeks', 'wc-invoice-payment'),
                        'month' => __('Months', 'wc-invoice-payment'),
                        'year' => __('Years', 'wc-invoice-payment')
                    );

                    foreach ($options as $key => $label) {
                        echo '<option value="' . esc_attr($key) . '"';
                        echo ($subscription_interval == $key) ? ' selected="selected"' : '';
                        echo '>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>
            </p>
            <?php

            woocommerce_wp_text_input(
                array(
                    'id' => 'lkn_wcip_subscription_limit',
                    'name' => 'lkn_wcip_subscription_limit',
                    'label' => __('Subscription limit', 'wc-invoice-payment'),
                    'desc_tip' => 'true',
                    'description' => __('Set a limit for the number of invoices that will be generated for the subscription, by default,  there is no limit.', 'wc-invoice-payment'),
                    'value' => $subscription_limit,
                    'type' => 'number',
                    'custom_attributes' => array(
                        'min' => '0',
                        'step' => '1.0',
                    ),
                )
            );
            ?>
        </div>
<?php
        wp_enqueue_script('lkn-wcip-subscription-admin', plugin_dir_url(__FILE__) . '../Admin/js/wc-invoice-payment-subscription.js', array('jquery'), '1.5.0', true);
        wp_enqueue_style('lkn-wcip-subscription-admin', plugin_dir_url(__FILE__) . '../Admin/css/wc-invoice-payment-subscription.css', array(), '1.5.0');
    }

    /**
     * Campos de assinatura por variação (produto variável)
     */
    public function add_variation_fields($loop, $variation_data, $variation): void
    {
        $variation_id = isset($variation->ID) ? (int) $variation->ID : 0;

        $mode = get_post_meta($variation_id, 'lkn_wcip_variation_payment_mode', true);
        $mode = in_array($mode, array('subscription', 'one_time'), true) ? $mode : 'inherit';
        $is_subscription = 'subscription' === $mode;

        $parent_id = isset($variation->post_parent) ? (int) $variation->post_parent : 0;
        $parent_inherits = $parent_id && 'on' === get_post_meta($parent_id, '_lkn-wcip-subscription-product', true);
        $inherit_label = $parent_inherits
            ? __('Inherit from product (subscription)', 'wc-invoice-payment')
            : __('Inherit from product (one-time payment)', 'wc-invoice-payment');

        $subscription_number = get_post_meta($variation_id, 'lkn_wcip_subscription_interval_number', true);
        $subscription_interval = get_post_meta($variation_id, 'lkn_wcip_subscription_interval_type', true);
        $subscription_limit = get_post_meta($variation_id, 'lkn_wcip_subscription_limit', true);

        $subscription_number = empty($subscription_number) ? 1 : $subscription_number;
        $subscription_interval = empty($subscription_interval) ? 'month' : $subscription_interval;
        $subscription_limit = empty($subscription_limit) ? 0 : $subscription_limit;

        $id_mode = 'lkn_wcip_variation_payment_mode_' . $loop;
        $id_number = 'lkn_wcip_variation_interval_number_' . $loop;
        $id_type = 'lkn_wcip_variation_interval_type_' . $loop;
        $id_limit = 'lkn_wcip_variation_interval_limit_' . $loop;
        ?>
        <div class="lkn_wcip_variation_subscription options_group">
            <input
                type="hidden"
                name="lkn_wcip_variation_nonce"
                value="<?php echo esc_attr(wp_create_nonce('lkn_wcip_variation_nonce')); ?>">
            <p class="form-row form-row-first">
                <label for="<?php echo esc_attr($id_mode); ?>"><?php esc_html_e('Recurring payment', 'wc-invoice-payment'); ?></label>
                <select
                    id="<?php echo esc_attr($id_mode); ?>"
                    name="lkn_wcip_variation_payment_mode[<?php echo esc_attr($loop); ?>]"
                    class="lkn_wcip_variation_payment_mode">
                    <option value="inherit" <?php selected($mode, 'inherit'); ?>><?php echo esc_html($inherit_label); ?></option>
                    <option value="subscription" <?php selected($mode, 'subscription'); ?>><?php esc_html_e('This variation is a subscription', 'wc-invoice-payment'); ?></option>
                    <option value="one_time" <?php selected($mode, 'one_time'); ?>><?php esc_html_e('This variation is a one-time payment', 'wc-invoice-payment'); ?></option>
                </select>
            </p>
            <p class="form-row form-row-last lkn_wcip_variation_subscription_field<?php if (! $is_subscription) : ?> lkn_wcip_variation_subscription_hidden<?php endif; ?>">
                <label for="<?php echo esc_attr($id_number); ?>"><?php esc_html_e('Subscription Interval', 'wc-invoice-payment'); ?></label>
                <input
                    type="number"
                    class="short wc_input_number"
                    min="1"
                    id="<?php echo esc_attr($id_number); ?>"
                    name="lkn_wcip_variation_interval_number[<?php echo esc_attr($loop); ?>]"
                    value="<?php echo esc_attr($subscription_number); ?>">
                <select
                    id="<?php echo esc_attr($id_type); ?>"
                    name="lkn_wcip_variation_interval_type[<?php echo esc_attr($loop); ?>]"
                    class="lkn_wcip_subscription_interval_type">
                    <?php
                    $options = array(
                        'day' => __('Days', 'wc-invoice-payment'),
                        'week' => __('Weeks', 'wc-invoice-payment'),
                        'month' => __('Months', 'wc-invoice-payment'),
                        'year' => __('Years', 'wc-invoice-payment'),
                    );

                    foreach ($options as $key => $label) {
                        echo '<option value="' . esc_attr($key) . '"';
                        echo ($subscription_interval == $key) ? ' selected="selected"' : '';
                        echo '>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>
            </p>
            <p class="form-row form-row-full lkn_wcip_variation_subscription_field<?php if (! $is_subscription) : ?> lkn_wcip_variation_subscription_hidden<?php endif; ?>">
                <label for="<?php echo esc_attr($id_limit); ?>"><?php esc_html_e('Subscription limit', 'wc-invoice-payment'); ?></label>
                <input
                    type="number"
                    class="short wc_input_number"
                    min="0"
                    step="1"
                    id="<?php echo esc_attr($id_limit); ?>"
                    name="lkn_wcip_variation_interval_limit[<?php echo esc_attr($loop); ?>]"
                    value="<?php echo esc_attr($subscription_limit); ?>">
                <span class="description"><?php esc_html_e('Set a limit for the number of invoices that will be generated for the subscription, by default,  there is no limit.', 'wc-invoice-payment'); ?></span>
            </p>
        </div>
        <?php
    }

    /**
     * Salva os campos de assinatura da variação
     */
    public function save_variation_fields($variation_id, $loop = 0): void
    {
        if (! isset($_POST['lkn_wcip_variation_nonce'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lkn_wcip_variation_nonce'])), 'lkn_wcip_variation_nonce')
        ) {
            return;
        }

        if (! current_user_can('edit_product', $variation_id)) {
            return;
        }

        $modes = array('inherit' => '', 'subscription' => 'subscription', 'one_time' => 'one_time');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado na linha seguinte
        $posted_modes = isset($_POST['lkn_wcip_variation_payment_mode']) && is_array($_POST['lkn_wcip_variation_payment_mode']) ? wp_unslash($_POST['lkn_wcip_variation_payment_mode']) : array();
        $mode = isset($posted_modes[$loop]) ? sanitize_text_field($posted_modes[$loop]) : 'inherit';

        if (isset($modes[$mode]) && '' !== $modes[$mode]) {
            update_post_meta($variation_id, 'lkn_wcip_variation_payment_mode', $modes[$mode]);
        } else {
            delete_post_meta($variation_id, 'lkn_wcip_variation_payment_mode');
        }

        // A cadência só existe quando a variação é uma assinatura
        if ('subscription' !== $mode) {
            delete_post_meta($variation_id, 'lkn_wcip_subscription_interval_number');
            delete_post_meta($variation_id, 'lkn_wcip_subscription_interval_type');
            delete_post_meta($variation_id, 'lkn_wcip_subscription_limit');

            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado na linha seguinte
        $posted_numbers = isset($_POST['lkn_wcip_variation_interval_number']) && is_array($_POST['lkn_wcip_variation_interval_number']) ? wp_unslash($_POST['lkn_wcip_variation_interval_number']) : array();
        $subscription_number = max(1, (int) sanitize_text_field($posted_numbers[$loop] ?? ''));
        update_post_meta($variation_id, 'lkn_wcip_subscription_interval_number', $subscription_number);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado na linha seguinte
        $posted_types = isset($_POST['lkn_wcip_variation_interval_type']) && is_array($_POST['lkn_wcip_variation_interval_type']) ? wp_unslash($_POST['lkn_wcip_variation_interval_type']) : array();
        $subscription_interval = sanitize_text_field($posted_types[$loop] ?? '');
        $valid_intervals = array('day', 'week', 'month', 'year');

        if (in_array($subscription_interval, $valid_intervals, true)) {
            update_post_meta($variation_id, 'lkn_wcip_subscription_interval_type', $subscription_interval);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado na linha seguinte
        $posted_limits = isset($_POST['lkn_wcip_variation_interval_limit']) && is_array($_POST['lkn_wcip_variation_interval_limit']) ? wp_unslash($_POST['lkn_wcip_variation_interval_limit']) : array();
        $subscription_limit = max(0, (int) sanitize_text_field($posted_limits[$loop] ?? ''));
        update_post_meta($variation_id, 'lkn_wcip_subscription_limit', $subscription_limit);
    }

    public function save_subscription_fields($post_id): void
    {
        //Salva todos os campos criados na meta do post

        if (isset($_POST['lkn_wcip_subscription_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_nonce'])), 'subscription_nonce')) {
            if (isset($_POST['lkn_wcip_subscription_interval_number'])) {
                $subscription_number = sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_interval_number']));
                update_post_meta($post_id, 'lkn_wcip_subscription_interval_number', $subscription_number);
            }

            if (isset($_POST['lkn_wcip_subscription_interval_type'])) {
                $subscription_interval = sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_interval_type']));
                update_post_meta($post_id, 'lkn_wcip_subscription_interval_type', $subscription_interval);
            }

            if (isset($_POST['lkn_wcip_subscription_limit'])) {
                $subscription_limit = sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_limit']));
                update_post_meta($post_id, 'lkn_wcip_subscription_limit', $subscription_limit);
            }

            if (isset($_POST['_lkn-wcip-subscription-product'])) {
                $subscription_checkbox = sanitize_text_field(wp_unslash($_POST['_lkn-wcip-subscription-product']));
                update_post_meta($post_id, '_lkn-wcip-subscription-product', $subscription_checkbox);
            } else {
                update_post_meta($post_id, '_lkn-wcip-subscription-product', '');
            }
        }
    }

    /**
     * Registra a fatura (e a assinatura, quando for o caso) de um pedido.
     *
     * @param mixed $order_id           ID (ou objeto) do pedido.
     * @param bool  $manualSubscription Assinatura criada na tela de faturas do admin: processa mesmo em status
     *                                  de fatura pendente e usa o intervalo salvo no próprio pedido.
     */
    public function validate_product($order_id, $manualSubscription = false): void
    {
        if (gettype($order_id) == "object") {
            $order_id = $order_id->id;
        }

        
        $order = wc_get_order($order_id);

        if (! $order) {
            return;
        }

        $items = $order->get_items();
        $orderStatus = $order->get_status();
        $isRecurringDonation = $order->get_meta('_recurring_donation') == 'yes';
        // Status que não devem processar assinatura
        $excludedStatuses = array('pending', 'cancelled', 'refunded', 'failed');
        
        if ((! in_array($orderStatus, $excludedStatuses) || $manualSubscription) && ! $order->get_meta('lkn_wcip_subscription_already_processed')) {
            $is_subscription_manual = $order->get_meta('lkn_wcip_subscription_is_manual');

            // Procura o item de assinatura do pedido (variação tem prioridade sobre o produto)
            $subscription = array('enabled' => false, 'number' => '', 'type' => '', 'limit' => '', 'source_id' => 0);

            if ($is_subscription_manual) {
                //Se for uma assinatura adicionada manualmente será preciso pegar os valores de outra forma
                $subscription = array(
                    'enabled' => (bool) $order->get_meta('lkn_is_subscription') || $manualSubscription,
                    'number' => $order->get_meta('lkn_wcip_subscription_interval_number'),
                    'type' => $order->get_meta('lkn_wcip_subscription_interval_type'),
                    'limit' => $order->get_meta('lkn_wcip_subscription_limit'),
                    'source_id' => 0,
                );
            } else {
                foreach ($items as $item) {
                    $item_config = $this->get_item_subscription_config($item);

                    if ($item_config['enabled']) {
                        $subscription = $item_config;
                        break;
                    }
                }
            }

            $createInvoice = $order->get_meta('lkn_is_quote') != 'yes'
                && (get_option("lkn_wcip_subscription_active_product_invoices") == 'yes' || $subscription['enabled'] || $isRecurringDonation);

            if ($createInvoice) {

                $iniDate = new DateTime();
                $iniDateFormatted = $iniDate->format('Y-m-d');

                $order->add_meta_data('lkn_ini_date', gmdate("Y-m-d", strtotime($iniDateFormatted)));
                $order->add_meta_data('wcip_select_invoice_language', 'pt_BR');

                if (! $order->get_meta('lkn_exp_date')) {
                    $order->add_meta_data('lkn_exp_date', gmdate("Y-m-d", strtotime($iniDateFormatted)));
                }

                //Dados de assinatura só são gravados quando o item realmente é uma assinatura
                if ($subscription['enabled']) {
                    $result = $this->calculate_next_due_date($subscription['number'], $subscription['type']);

                    //seta data para ver quanto tempo foi removido para ser adicionado depois
                    $order->add_meta_data('lkn_time_removed', $result['time_removed']);
                    // update_meta_data: a tela de assinaturas do admin já pode ter gravado
                    // essas metas (add_meta_data criaria linhas duplicadas).
                    $order->update_meta_data('lkn_wcip_subscription_interval_number', $subscription['number']);
                    $order->update_meta_data('lkn_wcip_subscription_interval_type', $subscription['type']);
                    $order->update_meta_data('lkn_wcip_subscription_limit', $subscription['limit']);
                    // initial_limit é contador: só inicializa quando ainda não existe
                    if (! $order->get_meta('lkn_wcip_subscription_initial_limit')) {
                        $order->add_meta_data('lkn_wcip_subscription_initial_limit', 0);
                    }

                    //Marca como assinatura; o cron só é agendado quando o pedido estiver pago
                    $order->update_meta_data('lkn_is_subscription', true);
                    $order->update_meta_data('lkn_wcip_subscription_next_due_date', $result['next_due_date']);
                }

                $order->add_meta_data('lkn_wcip_subscription_already_processed', true);
                $order->save();

                //Caso não seja uma assinatura manual é preciso atualizar a lista
                if (! $is_subscription_manual) {
                    // Adicionar a nova ordem à lista de faturas
                    $invoice_list = get_option('lkn_wcip_invoices', array());
                    if (!in_array($order->get_id(), $invoice_list)) {
                        $invoice_list[] = $order->get_id();
                    }
                    update_option('lkn_wcip_invoices', $invoice_list);
                }
            }
        }
    }

    public function order_contains_subscription($order){
        if (! $order instanceof \WC_Order) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            $config = $this->get_item_subscription_config($item);
            if ($config['enabled']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a configuração de assinatura de um produto/variação (variação > produto pai)
     */
    private function get_product_subscription_config($product_id, $variation_id = 0): array
    {
        $config = array(
            'enabled' => false,
            'number' => '',
            'type' => '',
            'limit' => '',
            'source_id' => 0,
        );

        // A variação tem prioridade: pode ser assinatura própria ou pagamento único
        $mode = $variation_id ? get_post_meta($variation_id, 'lkn_wcip_variation_payment_mode', true) : '';

        if ('one_time' === $mode) {
            return $config;
        }

        if ('subscription' === $mode) {
            $number = get_post_meta($variation_id, 'lkn_wcip_subscription_interval_number', true);
            $type = get_post_meta($variation_id, 'lkn_wcip_subscription_interval_type', true);
            $limit = get_post_meta($variation_id, 'lkn_wcip_subscription_limit', true);

            // Variação marcada como assinatura sem cadência própria: usa a do produto pai
            if (empty($number) || empty($type)) {
                $number = get_post_meta($product_id, 'lkn_wcip_subscription_interval_number', true);
                $type = get_post_meta($product_id, 'lkn_wcip_subscription_interval_type', true);
                $limit = '' === $limit ? get_post_meta($product_id, 'lkn_wcip_subscription_limit', true) : $limit;
            }

            return array(
                'enabled' => true,
                'number' => empty($number) ? 1 : $number,
                'type' => empty($type) ? 'month' : $type,
                'limit' => $limit,
                'source_id' => (int) $variation_id,
            );
        }

        // Variação sem configuração própria: herda do produto pai
        if (empty($product_id) || 'on' !== get_post_meta($product_id, '_lkn-wcip-subscription-product', true)) {
            return $config;
        }

        $number = get_post_meta($product_id, 'lkn_wcip_subscription_interval_number', true);
        $type = get_post_meta($product_id, 'lkn_wcip_subscription_interval_type', true);

        return array(
            'enabled' => true,
            'number' => empty($number) ? 1 : $number,
            'type' => empty($type) ? 'month' : $type,
            'limit' => get_post_meta($product_id, 'lkn_wcip_subscription_limit', true),
            'source_id' => (int) $product_id,
        );
    }

    private function get_item_subscription_config($item): array
    {
        if (! $item instanceof \WC_Order_Item_Product) {
            return $this->get_product_subscription_config(0);
        }

        return $this->get_product_subscription_config($item->get_product_id(), $item->get_variation_id());
    }

    /**
     * Verifica se o pedido é de assinatura: produto marcado, assinatura manual ou doação recorrente
     */
    /**
     * Pedido originado no checkout do cliente (store-api/checkout), não no admin nem por integração
     */
    private function is_customer_checkout_order($order): bool
    {
        if (! $order instanceof \WC_Order) {
            return false;
        }

        return ! in_array($order->get_created_via(), array('', 'admin'), true);
    }

    private function is_subscription_order($order): bool
    {
        if (! $order instanceof \WC_Order) {
            return false;
        }

        // Pedido filho de pagamento parcial não é assinatura (os itens são copiados do pedido principal)
        if ('yes' === $order->get_meta('_wc_lkn_is_partial_order') || $order->get_meta('_wc_lkn_parent_id')) {
            return false;
        }

        if ($order->get_meta('lkn_wcip_subscription_is_manual') || 'yes' === $order->get_meta('_recurring_donation')) {
            return true;
        }

        return $this->order_contains_subscription($order);
    }

    public function calculate_next_due_date($interval_number, $interval_type)
    {
        $current_time = current_time('timestamp');
        $interval_number_option = get_option('lkn_wcip_interval_number');
        $interval_type_option = get_option('lkn_wcip_interval_type');
        //Pega a quantidade de tempo de intervalo da cobrança e diminui horas, dias, semanas e meses de acordo com o que foi escolhido        
        if (0 == $interval_number_option) {
            $return_array = $this->calcule_switch($interval_type, $interval_number, $current_time);
        } else {
            $next_due_date = strtotime("+{$interval_number} $interval_type", $current_time);
            $next_due_date = strtotime("-{$interval_number_option} $interval_type_option", $next_due_date);
            $time_removed = "{$interval_number_option} $interval_type_option";
            $return_array = array(
                'next_due_date' => $next_due_date,
                'time_removed' => $time_removed
            );
            //Caso o valor de antecedencia escolhido pelo usuário seja maior que o valor de recorrencia da fatura, é usado a lógica automatica do sistema
            if ($current_time >= $next_due_date) {
                $return_array = $this->calcule_switch($interval_type, $interval_number, $current_time);
            }
        }

        return array(
            'next_due_date' => $return_array['next_due_date'],
            'time_removed' => $return_array['time_removed']
        );
    }

    public function calcule_switch($interval_type, $interval_number, $current_time)
    {
        $time_removed = '';
        $next_due_date = '';

        switch ($interval_type) {
            case 'day':
                $next_due_date = strtotime("+{$interval_number} day", $current_time);
                if ($interval_number > 7) {
                    $next_due_date = strtotime("-1 week", $next_due_date);
                    //A variavel $time_removed salva o valor reduzido para ser somado mais tarde e gerar a fatura
                    $time_removed = '1 week';
                } elseif ($interval_number <= 7 && $interval_number > 1) {
                    $next_due_date = strtotime("-3 days", $next_due_date);
                    $time_removed = '3 days';
                } elseif (1 == $interval_number) {
                    $next_due_date = strtotime("-6 hours", $next_due_date);
                    $time_removed = '6 hours';
                }
                break;
            case 'week':
                $next_due_date = strtotime("+{$interval_number} week", $current_time);
                if ($interval_number > 1) {
                    $next_due_date = strtotime("-1 week", $next_due_date);
                    $time_removed = '1 week';
                } else {
                    $next_due_date = strtotime("-3 days", $next_due_date);
                    $time_removed = '3 days';
                }
                break;
            case 'month':
                $next_due_date = strtotime("+{$interval_number} month", $current_time);
                if ($interval_number > 1) {
                    $next_due_date = strtotime("-2 weeks", $next_due_date);
                    $time_removed = '2 weeks';
                } else {
                    $next_due_date = strtotime("-3 days", $next_due_date);
                    $time_removed = '3 days';
                }
                break;
            case 'year':
                $next_due_date = strtotime("+{$interval_number} year", $current_time);
                if ($interval_number > 1) {
                    $next_due_date = strtotime("-3 months", $next_due_date);
                    $time_removed = '3 months';
                } else {
                    $next_due_date = strtotime("-2 weeks", $next_due_date);
                    $time_removed = '2 weeks';
                }
                break;
            default:
                $next_due_date = '';
                break;
        }

        return array(
            'next_due_date' => $next_due_date,
            'time_removed' => $time_removed
        );
    }

    public function schedule_next_invoice_generation($order_id, $due_date): void
    {
        // Normaliza o ID: o wp-cron devolve os args como string e os eventos são indexados pelo hash dos args
        $order_id = (int) $order_id;

        $this->schedule_single_event_once($due_date, 'generate_invoice_event', array($order_id));
        $this->schedule_single_event_once($due_date + 86400, 'lkn_wcip_cron_hook', array($order_id));

        // Salva no banco como backup do sistema de cron
        $this->save_subscription_to_schedule($order_id, $due_date);
    }

    /**
     * Agenda um evento único sem empilhar duplicatas (reagenda quando a data muda)
     */
    private function schedule_single_event_once($timestamp, $hook, $args): void
    {
        $timestamp = (int) $timestamp;

        if ($timestamp <= 0) {
            return;
        }

        $scheduled = wp_get_scheduled_event($hook, $args);

        // Evento recorrente (daily, agendado na tela de faturas): não é duplicata, não mexe
        if ($scheduled && ! empty($scheduled->schedule)) {
            return;
        }

        if ($scheduled && (int) $scheduled->timestamp === $timestamp) {
            return;
        }

        if ($scheduled) {
            wp_unschedule_event((int) $scheduled->timestamp, $hook, $args);
        }

        wp_schedule_single_event($timestamp, $hook, $args);
    }

    public function create_next_invoice($order_id): void
    {
        $order_id = (int) $order_id;
        $order = wc_get_order($order_id);

        // Só gera fatura de assinatura paga (processing/completed). Pedidos de lojas que
        // usam "processing" como estado final (produtos virtuais) também contam.
        if (! $order || ! in_array($order->get_status(), wc_get_is_paid_statuses(), true)) {
            return;
        }

        // Trava: dois executores do wp-cron (HTTP + cron do servidor) não podem gerar a mesma fatura
        if (! $this->acquire_generation_lock($order_id)) {
            return;
        }

        try {
            $this->generate_next_invoice($order);
        } finally {
            $this->release_generation_lock($order_id);
        }
    }

    /**
     * Trava por pedido para a geração de fatura (add_option é atômico: a option_name é única)
     */
    private function acquire_generation_lock($order_id): bool
    {
        $option = 'lkn_wcip_generation_lock_' . $order_id;

        if (add_option($option, time(), '', 'no')) {
            return true;
        }

        // Trava órfã (processo morreu no meio): expira depois de 5 minutos
        $locked_at = (int) get_option($option);

        if ($locked_at && (time() - $locked_at) > 300) {
            delete_option($option);

            return (bool) add_option($option, time(), '', 'no');
        }

        return false;
    }

    private function release_generation_lock($order_id): void
    {
        delete_option('lkn_wcip_generation_lock_' . $order_id);
    }

    private function generate_next_invoice($order): void
    {
        $order_id = $order->get_id();

        // Sem cadência resolvível não dá para saber a próxima data: não gera fatura (evita cobrança repetida)
        $interval = $this->resolve_order_interval($order);
        if (empty($interval['number']) || empty($interval['type'])) {
            $order->add_order_note(__('Subscription paused: the recurrence interval could not be resolved (the subscription product may have been deleted). No new invoice was generated.', 'wc-invoice-payment'));
            $order->save();

            // Sai do backup: sem cadência não há próximo ciclo (evita repetir a nota todo dia)
            $this->remove_subscription_from_schedule($order_id);

            return;
        }

        // Já foi gerada uma fatura para este ciclo há poucos minutos (evento duplicado): garante só o próximo ciclo
        $last_generation = (int) $order->get_meta('lkn_wcip_last_generation');
        if ($last_generation && (current_time('timestamp') - $last_generation) < 300) {
            // Já existe próximo ciclo agendado: mantém a data, só garante o evento
            $scheduled = get_option('lkn_wcip_subscription_schedule', array());
            $next_due_date = isset($scheduled[$order_id]) ? (int) $scheduled[$order_id] : 0;

            if (! $next_due_date || $next_due_date <= current_time('timestamp')) {
                $next_due_date = $this->update_next_invoice_schedule($order_id);
            }

            if ($next_due_date) {
                $this->schedule_next_invoice_generation($order_id, $next_due_date);
            }

            return;
        }

        //Valida se a ordem está no limite de faturas
        $initialLimit = $order->get_meta('lkn_wcip_subscription_initial_limit');
        $limit = $order->get_meta('lkn_wcip_subscription_limit');
        if ($initialLimit == $limit && 0 != $limit) {
            $scheduled_events = _get_cron_array();
            // verifica todos os eventos agendados
            foreach ($scheduled_events as $timestamp => $cron_events) {
                foreach ($cron_events as $hook => $events) {
                    foreach ($events as $event) {
                        // Verifique se o evento está associado ao seu gancho (hook)
                        if ("generate_invoice_event" === $hook || 'lkn_wcip_cron_hook' === $hook) {
                            // Verifique se os argumentos do evento contêm o ID da ordem que você deseja remover
                            $event_args = $event['args'];
                            if (is_array($event_args) && in_array($order_id, $event_args)) {
                                // Remova o evento do WP Cron
                                wp_unschedule_event($timestamp, $hook, $event_args);
                            }
                        }
                    }
                }
            }
            return;
        }
        $order->update_meta_data('lkn_wcip_subscription_initial_limit', $initialLimit + 1);
        $order->update_meta_data('lkn_wcip_last_generation', current_time('timestamp'));

        $customerId = $order->get_customer_id();
        $billing_email = $order->get_billing_email();
        $billing_country = $order->get_billing_country();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $paymentMethod = $order->get_payment_method();
        $time_removed = $order->get_meta('lkn_time_removed');
        $iniDate = new DateTime();
        $iniDateFormatted = $iniDate->format('Y-m-d');
        //Soma o tempo removido anteriormente para colocar na data de vencimento
        if (!empty($time_removed) && is_string($time_removed)) {
            $iniDate->modify("+" . $time_removed);
        }
        $expDateFormatted = gmdate('Y-m-d', $iniDate->getTimestamp());

        $new_order = wc_create_order(array(
            'status' => 'wc-pending',
            'customerId' => $customerId,
        ));
        $new_order->set_billing_country($billing_country);
        $new_order->set_customer_id($customerId);
        $new_order->set_billing_first_name($billing_first_name);
        $new_order->set_billing_last_name($billing_last_name);
        $new_order->set_billing_email($billing_email);
        $new_order->set_payment_method($paymentMethod);
        $new_order->add_meta_data('lkn_ini_date', $iniDateFormatted);
        $new_order->add_meta_data('lkn_exp_date', $expDateFormatted);
        $new_order->add_meta_data('lkn_is_subscription', false);
        //ID da assinatura que criou essa fatura
        $new_order->add_meta_data('lkn_subscription_id', $order_id);
        $new_order->add_meta_data('lkn_current_limit', $initialLimit + 1);
        $newOrderId = $new_order->get_id();

        if (! $new_order) {
            return;
        }

        // Calcular e definir o total da nova ordem com base nos itens da ordem original
        $total_amount = 0;
        foreach ($order->get_items() as $item) {
            $total_amount += $item->get_total();

            $item_data = array(
                'name' => $item->get_name(),
                'quantity' => 1,
                'total' => $item->get_total(),
            );
            $new_order->add_product(wc_get_product('custom'), 1, $item_data);
        }

        $new_order->set_total($total_amount);

        //Atualiza data de expiração e id da assinatura para nova data de expiração da fatura gerada 
        $order->update_meta_data('lkn_exp_date', $expDateFormatted);
        $order->update_meta_data('lkn_invoice_id', $newOrderId);
        $order->save();

        $new_order->save();

        // Chama o método de processamento de assinatura
        if ($paymentMethod != 'multiplePayment' && $customerId != 0) {
            $subscriptionResult = apply_filters('lkn_process_subscription_' . $paymentMethod, $newOrderId, $customerId, false);
            
            $status = isset($subscriptionResult['status']) ? $subscriptionResult['status'] : false;
            $makeRetry = isset($subscriptionResult['makeRetry']) ? $subscriptionResult['makeRetry'] : false;
            $nextCronHours = isset($subscriptionResult['nextCronHours']) ? $subscriptionResult['nextCronHours'] : 0;

            if ($status == true) {
                $new_order->payment_complete();
            }

            if ($status == false && $makeRetry == true) {
                if ($nextCronHours > 0) {
                    wp_schedule_single_event(time() + ($nextCronHours * 3600), 'lkn_process_subscription_' . $paymentMethod, array($newOrderId, $customerId, true));
                }
            }
        }

        // Adicionar a nova ordem à lista de faturas
        $invoice_list = get_option('lkn_wcip_invoices', array());
        if (!in_array($new_order->get_id(), $invoice_list)) {
            $invoice_list[] = $new_order->get_id();
        }
        update_option('lkn_wcip_invoices', $invoice_list);
        
        // Atualiza a próxima data de geração no schedule para backup do cron
        $next_due_date = $this->update_next_invoice_schedule($order_id);

        // Reagenda o cron do próximo ciclo (o evento atual é removido ao ser executado)
        if ($next_due_date) {
            $this->schedule_next_invoice_generation($order_id, $next_due_date);
        }
    }

    public function forceUserRegistration($forceRegistration)
    {
        $cart = WC()->cart ? WC()->cart->get_cart() : null;

        if (!empty($cart)) {
            foreach ($cart as $cart_item) {
                $product_id = isset($cart_item['product_id']) ? $cart_item['product_id'] : 0;
                $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;

                if ($this->get_product_subscription_config($product_id, $variation_id)['enabled']) {
                    $forceRegistration = true;
                    break;
                }
            }
        }

        return $forceRegistration;
    }

    /**
     * Salva a assinatura no schedule do banco de dados
     */
    public function save_subscription_to_schedule($order_id, $next_due_date): void
    {
        $schedule = get_option('lkn_wcip_subscription_schedule', array());
        $schedule[$order_id] = $next_due_date;
        update_option('lkn_wcip_subscription_schedule', $schedule);
    }

    /**
     * Remove a assinatura do schedule do banco de dados
     */
    public function remove_subscription_from_schedule($order_id): void
    {
        $schedule = get_option('lkn_wcip_subscription_schedule', array());
        if (isset($schedule[$order_id])) {
            unset($schedule[$order_id]);
            update_option('lkn_wcip_subscription_schedule', $schedule);
        }
        
    }

    /**
     * Verifica se existe um cron agendado para a assinatura
     */
    public function is_cron_scheduled($order_id): bool
    {
        $scheduled_events = _get_cron_array();
        
        foreach ($scheduled_events as $timestamp => $cron_events) {
            foreach ($cron_events as $hook => $events) {
                if ('generate_invoice_event' === $hook) {
                    foreach ($events as $event) {
                        $event_args = $event['args'];
                        if (is_array($event_args) && in_array($order_id, $event_args)) {
                            return true;
                        }
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Verifica assinaturas pendentes e gera faturas se necessário
     * Executado no admin init como backup ao sistema de cron
     */
    public function check_pending_subscriptions(): void
    {
        // Verifica a última execução
        $last_check = get_option('lkn_wcip_last_subscription_check', 0);
        $current_time = current_time('timestamp');
        
        // Se já foi executado hoje, não executa novamente (24 horas = 86400 segundos)
        if (($current_time - $last_check) < 86400) {
            return;
        }
        
        $schedule = get_option('lkn_wcip_subscription_schedule', array());
        
        // Se não tem assinaturas no schedule, atualiza o timestamp e retorna
        if (empty($schedule)) {
            update_option('lkn_wcip_last_subscription_check', $current_time);
            return;
        }
        
        foreach ($schedule as $order_id => $next_due_date) {
            // Verifica se passou da data de vencimento
            if ($current_time >= $next_due_date) {
                // Verifica se não tem cron ativo para esta assinatura
                if (!$this->is_cron_scheduled($order_id)) {
                    // Verifica se a ordem ainda existe e é uma assinatura ativa
                    $order = wc_get_order($order_id);
                    if ($order && $order->get_meta('lkn_is_subscription')) {
                        // Gera a próxima fatura
                        $this->create_next_invoice($order_id);
                    } else {
                        // Remove da lista se a ordem não existe mais ou não é mais assinatura
                        $this->remove_subscription_from_schedule($order_id);
                    }
                }
            }
        }
        
        // Atualiza o timestamp da última verificação
        update_option('lkn_wcip_last_subscription_check', $current_time);
    }

    /**
     * Calcula e salva a próxima data de geração para a assinatura
     */
    /**
     * Resolve a cadência da assinatura.
     *
     * O intervalo gravado no pedido (snapshot da compra, também editável na tela de
     * assinaturas) é autoritativo: editar o produto/variação no catálogo não pode
     * mudar a recorrência de assinaturas que já estão rodando.
     */
    private function resolve_order_interval($order): array
    {
        $stored = array(
            'number' => $order->get_meta('lkn_wcip_subscription_interval_number'),
            'type' => $order->get_meta('lkn_wcip_subscription_interval_type'),
        );

        if (! empty($stored['number']) && ! empty($stored['type'])) {
            return $stored;
        }

        // Pedidos anteriores à 2.13.3 (e assinaturas manuais antigas) não têm o intervalo
        // gravado: resolve pelo item do pedido (variação > produto pai).
        foreach ($order->get_items() as $item) {
            $config = $this->get_item_subscription_config($item);

            if ($config['enabled'] && $config['number'] && $config['type']) {
                return array('number' => $config['number'], 'type' => $config['type']);
            }
        }

        // Último recurso: pedido de assinatura cujo produto perdeu o flag (ou loja que usa a
        // opção global de faturas de produto) mas ainda tem a cadência gravada no item.
        foreach ($order->get_items() as $item) {
            $legacy_interval = $this->get_item_stored_interval($item);

            if ($legacy_interval['number'] && $legacy_interval['type']) {
                return $legacy_interval;
            }
        }

        return $stored;
    }

    public function update_next_invoice_schedule($order_id): ?int
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }

        $interval = $this->resolve_order_interval($order);
        $subscription_interval_number = $interval['number'];
        $subscription_interval_type = $interval['type'];

        if (empty($subscription_interval_number) || empty($subscription_interval_type)) {
            return null;
        }

        $result = $this->calculate_next_due_date($subscription_interval_number, $subscription_interval_type);
        $next_due_date = (int) $result['next_due_date'];

        if (empty($next_due_date)) {
            return null;
        }

        // Assinaturas criadas antes do snapshot: grava a cadência resolvida no pedido para
        // que edições futuras no produto/variação não alterem esta assinatura.
        $this->persist_interval_snapshot($order, $interval);

        // Salva a próxima data no schedule
        $this->save_subscription_to_schedule($order_id, $next_due_date);

        return $next_due_date;
    }

    /**
     * Cadência gravada diretamente no item (variação > produto), mesmo sem o flag de
     * assinatura. Usado apenas como último recurso para assinaturas antigas.
     */
    private function get_item_stored_interval($item): array
    {
        $empty = array('number' => '', 'type' => '');

        if (! $item instanceof \WC_Order_Item_Product) {
            return $empty;
        }

        $variation_id = (int) $item->get_variation_id();
        $product_id = (int) $item->get_product_id();

        foreach (array($variation_id, $product_id) as $source_id) {
            if (! $source_id) {
                continue;
            }

            $number = get_post_meta($source_id, 'lkn_wcip_subscription_interval_number', true);
            $type = get_post_meta($source_id, 'lkn_wcip_subscription_interval_type', true);

            if ($number && $type) {
                return array('number' => $number, 'type' => $type);
            }
        }

        return $empty;
    }

    /**
     * Grava a cadência resolvida no pedido (snapshot) quando ela ainda veio do catálogo
     */
    private function persist_interval_snapshot($order, array $interval): void
    {
        if (empty($interval['number']) || empty($interval['type'])) {
            return;
        }

        $is_subscription = $order->get_meta('lkn_is_subscription') || $order->get_meta('lkn_wcip_subscription_is_manual');

        if (! $is_subscription) {
            return;
        }

        $stored_number = $order->get_meta('lkn_wcip_subscription_interval_number');
        $stored_type = $order->get_meta('lkn_wcip_subscription_interval_type');

        if ($stored_number && $stored_type) {
            return;
        }

        $order->update_meta_data('lkn_wcip_subscription_interval_number', $interval['number']);
        $order->update_meta_data('lkn_wcip_subscription_interval_type', $interval['type']);
        $order->save();
    }

    /**
     * Remove subscription do schedule quando um pedido é deletado pelo WooCommerce
     * É executado via hook 'woocommerce_before_delete_order'
     */
    public function cleanup_subscription_on_order_delete($order_id): void
    {
        // Verifica se o pedido que está sendo deletado é uma assinatura
        $order = wc_get_order($order_id);
        if ($order && $order->get_meta('lkn_is_subscription')) {
            // Remove do schedule do sistema de backup
            $this->remove_subscription_from_schedule($order_id);
            $scheduled_events = _get_cron_array();
                // verifica todos os eventos agendados
            foreach ($scheduled_events as $timestamp => $cron_events) {
                foreach ($cron_events as $hook => $events) {
                    foreach ($events as $event) {
                        // Verifique se o evento está associado ao seu gancho (hook)
                        if ("generate_invoice_event" === $hook || 'lkn_wcip_cron_hook' === $hook) {
                            // Verifique se os argumentos do evento contêm o ID da ordem que você deseja remover
                            $event_args = $event['args'];
                            if (is_array($event_args) && in_array($order_id, $event_args)) {
                                // Remova o evento do WP Cron
                                wp_unschedule_event($timestamp, $hook, $event_args);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Handle subscription when order status changes to completed
     * This is where we actually schedule the first invoice generation
     */
    public function handle_subscription_on_completed($order_id): void
    {
        $order = wc_get_order($order_id);

        // Pedido pago sem passar pela assinatura registrada (ex: boleto/pix confirmado depois da página de obrigado).
        // Faturas criadas no admin ficam de fora: a tela de faturas registra a assinatura por conta própria.
        if ($order && $this->is_customer_checkout_order($order) && $this->is_subscription_order($order) && !$order->get_meta('lkn_wcip_subscription_already_processed')) {
            $this->validate_product($order_id);
            $order = wc_get_order($order_id);
        }

        $this->schedule_subscription($order);
    }

    /**
     * Agenda a primeira (ou a próxima) fatura da assinatura.
     *
     * Idempotente: se já existe evento para o pedido, não empilha outro.
     */
    private function schedule_subscription($order): void
    {
        if (! $order instanceof \WC_Order || ! $order->get_meta('lkn_is_subscription')) {
            return;
        }

        $order_id = $order->get_id();

        // Verifica se é uma assinatura e se ainda não foi agendado o cron
        if ($this->is_subscription_scheduled($order_id)) {
            return;
        }

        $next_due_date = $order->get_meta('lkn_wcip_subscription_next_due_date');

        // Se a data de vencimento foi salva anteriormente, agenda o cron
        if ($next_due_date) {
            $this->schedule_next_invoice_generation($order_id, $next_due_date);

            // Adiciona nota na order sobre o agendamento
            $order->add_order_note(sprintf(
                /* translators: %s: scheduled invoice date */
                __('Subscription activated and first invoice scheduled for: %s', 'wc-invoice-payment'),
                gmdate('Y-m-d H:i:s', $next_due_date)
            ));

            return;
        }

        // Se não tem a data salva, calcula a partir do intervalo (pedido, variação ou produto)
        $next_due_date = $this->update_next_invoice_schedule($order_id);

        if (! $next_due_date) {
            return;
        }

        // Salva a data e agenda
        $order->update_meta_data('lkn_wcip_subscription_next_due_date', $next_due_date);
        $order->save();

        $this->schedule_next_invoice_generation($order_id, $next_due_date);

        // Adiciona nota na order sobre o agendamento
        $order->add_order_note(sprintf(
            /* translators: %s: scheduled invoice date */
            __('Subscription activated and first invoice scheduled for: %s', 'wc-invoice-payment'),
            gmdate('Y-m-d H:i:s', $next_due_date)
        ));
    }

    /**
     * Registra a assinatura quando o pedido é efetivamente pago (pix/boleto confirmam depois da página de obrigado).
     * O agendamento da primeira fatura fica com handle_subscription_on_completed.
     */
    public function handle_subscription_status_change($order_id, $status_from = '', $status_to = '', $order = null): void
    {
        // Só registra a assinatura quando o pedido é de fato pago (pending, on-hold, boleto aguardando, parcial... ficam de fora)
        if (! in_array($status_to, wc_get_is_paid_statuses(), true)) {
            return;
        }

        $order = $order instanceof \WC_Order ? $order : wc_get_order($order_id);

        if (! $order) {
            return;
        }

        // Pedido criado no admin/por integração não é checkout do cliente: a tela de faturas
        // já registra a assinatura por conta própria (validate_product com $manualSubscription)
        if (! $this->is_customer_checkout_order($order)) {
            return;
        }

        // Só registra pedidos que realmente são assinatura (produto marcado, manual ou doação recorrente)
        if (! $this->is_subscription_order($order)) {
            return;
        }

        // Registra a assinatura (idempotente via lkn_wcip_subscription_already_processed)
        $this->validate_product($order_id);

        // Agenda a próxima fatura assim que o pedido estiver pago (processing ou completed):
        // lojas que usam "processing" como estado final (produtos virtuais) também cobram.
        $this->schedule_subscription(wc_get_order($order_id));
    }

    /**
     * Check if subscription is already scheduled
     */
    private function is_subscription_scheduled($order_id): bool
    {
        $scheduled_events = _get_cron_array();
        
        foreach ($scheduled_events as $timestamp => $cron_events) {
            foreach ($cron_events as $hook => $events) {
                foreach ($events as $event) {
                    if ($hook === 'generate_invoice_event') {
                        $event_args = $event['args'];
                        if (is_array($event_args) && in_array($order_id, $event_args)) {
                            return true;
                        }
                    }
                }
            }
        }
        
        return false;
    }
}
