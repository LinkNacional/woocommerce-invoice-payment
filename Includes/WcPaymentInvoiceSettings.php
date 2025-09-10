<?php

namespace LknWc\WcInvoicePayment\Includes;

use LknWc\WcInvoicePayment\Admin\WcPaymentInvoicePdfTemplates;

final class WcPaymentInvoiceSettings
{

    public $loader;
    private $handler_invoice_templates;

    public function __construct($loader)
    {
        $this->loader = $loader;
        $this->handler_invoice_templates = new WcPaymentInvoicePdfTemplates('wc-invoice-payment', WC_PAYMENT_INVOICE_VERSION);

        // Registra todas as abas de configuração
        $this->register_all_settings_tabs();
        $this->loader->add_action('admin_head', $this, 'fixSettingsTabs');
    }

    function fixSettingsTabs() {
        global $plugin_page;

        if (isset($_GET['subscription']) && $_GET['subscription'] === 'true' || isset($_GET['invoiceChecked'])){
            // Força o WordPress a marcar o submenu e o menu pai como ativos
            $plugin_page = 'wc-invoice-payment-subscriptions-add';

            add_filter('submenu_file', fn() => 'wc-invoice-payment-subscriptions-add');
            add_filter('parent_file', fn() => 'wc-invoice-payment-subscriptions');
        }
    }

    private function register_all_settings_tabs()
    {
        // Registra o filtro uma única vez
        $this->loader->add_filter('woocommerce_settings_tabs_array', $this, 'add_settings_tab', 50);

        // Aba Faturas
        $this->register_settings_tab(
            'wc_payment_invoice_settings',
            __('Faturas', 'wc-invoice-payment'),
            'showInvoiceSettingTabContent',
            'saveInvoiceSettings'
        );

        // Aba Assinaturas
        $this->register_settings_tab(
            'wc_payment_subscription_settings',
            __('Assinaturas', 'wc-invoice-payment'),
            'showSubscriptionSettingTabContent',
            'saveSubscriptionSettings'
        );

        // Aba Pagamento Parcial
        $this->register_settings_tab(
            'wc_payment_partial_settings',
            __('Pagamento Parcial', 'wc-invoice-payment'),
            'showPartialPaymentSettingTabContent',
            'savePartialPaymentSettings'
        );

        // Aba Taxa ou Descontos
        $this->register_settings_tab(
            'wc_payment_fees_discounts_settings',
            __('Taxa ou Descontos', 'wc-invoice-payment'),
            'showFeesDiscountsSettingTabContent',
            'saveFeesDiscountsSettings'
        );

        $this->register_settings_tab(
            'wc_payment_quote_settings',
            __('Orçamentos', 'wc-invoice-payment'),
            'quoteSettingTabContent',
            'saveQuoteSettings'
        );
    }


    private $registered_tabs = array();

    public function register_settings_tab($id, $label, $settings_callback, $save_callback)
    {
        // Armazena as informações da aba
        $this->registered_tabs[$id] = array(
            'label' => $label
        );

        // Mostra conteúdo
        $this->loader->add_action("woocommerce_settings_tabs_{$id}", $this, $settings_callback);

        // Salva configurações
        $this->loader->add_action("woocommerce_update_options_{$id}", $this, $save_callback);
    }

    public function add_settings_tab($tabs)
    {
        // Adiciona todas as abas registradas
        foreach ($this->registered_tabs as $tab_id => $tab_info) {
            $tabs[$tab_id] = $tab_info['label'];
        }
        return $tabs;
    }

    public function showInvoiceSettingTabContent()
    {
        // Carrega os campos de configuração
        wp_enqueue_script('woocommerce_admin');
        $this->loadScriptsAndStyles();
        woocommerce_admin_fields($this->getInvoiceSettings());
    }

    public function saveInvoiceSettings()
    {
        woocommerce_update_options($this->getInvoiceSettings());
    }

    /**
     * Renderiza um campo customizado com editor TinyMCE
     */
    public function render_wp_editor_field($value)
    {
        $option_value = get_option($value['id'], $value['default']);
        $description = $value['desc'] ?? '';
        $desc_tip = $value['desc_tip'] ?? false;

?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['name']); ?></label>
                <?php if ($desc_tip && $description): ?>
                    <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr($description); ?>"></span>
                <?php endif; ?>
            </th>
            <td class="forminp">
                <?php
                wp_editor($option_value, $value['id'], array(
                    'textarea_name' => $value['id'],
                    'textarea_rows' => 6,
                    'editor_height' => 150,
                    'media_buttons' => false,
                    'tinymce' => array(
                        'toolbar1' => 'bold,italic,underline,forecolor,backcolor,fontsizeselect,link',
                        'toolbar2' => '',
                        'height' => 150
                    ),
                    'quicktags' => false
                ));
                ?>
                <?php if (!$desc_tip && $description): ?>
                    <p class="description"><?php echo wp_kses_post($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
<?php
    }

    public function render_payment_gateway_config_field($value)
    {
        $gateway_id = $value['gateway_id'];
        $gateway_title = $value['gateway_title'];
        $slug = 'lkn_wcip_fee_or_discount_';
        
        // Valores das opções
        $type_value = get_option($slug . 'type_' . $gateway_id, 'none');
        $method_value = get_option($slug . 'percent_fixed_' . $gateway_id, 'percentage');
        $amount_value = get_option($slug . 'value_' . $gateway_id, '0');
        $active_value = get_option($slug . 'method_activated_' . $gateway_id, 'no');
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($slug . 'type_' . $gateway_id); ?>"><?php echo esc_html($gateway_title); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php echo esc_html(__('Configure fee or discount for this payment method', 'wc-invoice-payment')); ?></span>
                    </legend>
                    
                    <div style="display: flex;gap: 15px;align-items: flex-start;flex-wrap: wrap;flex-direction: column;">
                        <!-- Adicionando checkbox -->
                        <div>
                            <input type="checkbox" name="<?php echo esc_attr($slug . 'method_activated_' . $gateway_id); ?>" id="<?php echo esc_attr($slug . 'method_activated_' . $gateway_id); ?>" <?php checked($active_value, 'yes'); ?> />
                            <label for="<?php echo esc_attr($slug . 'method_activated_' . $gateway_id); ?>"><?php echo esc_html(__('Enable this option', 'wc-invoice-payment')); ?></label>
                            <p class="description"><?php echo esc_html(__('Enables fee/discount payment for the payment method.', 'wc-invoice-payment')); ?></p>        
                        </div>

                        <!-- Tipo: Taxa ou Desconto -->
                        <div>
                            <select name="<?php echo esc_attr($slug . 'type_' . $gateway_id); ?>" id="<?php echo esc_attr($slug . 'type_' . $gateway_id); ?>" class="wc-enhanced-select">
                                <option value="fee" <?php selected($type_value, 'fee'); ?>><?php echo esc_html(__('Fee', 'wc-invoice-payment')); ?></option>
                                <option value="discount" <?php selected($type_value, 'discount'); ?>><?php echo esc_html(__('Discount', 'wc-invoice-payment')); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html(__('Select fee or discount to be applied when user uses this payment method.', 'wc-invoice-payment')); ?></p>
                        </div>
                        
                        <!-- Método: Porcentagem ou Valor Fixo -->
                        <div>
                            <select name="<?php echo esc_attr($slug . 'percent_fixed_' . $gateway_id); ?>" id="<?php echo esc_attr($slug . 'percent_fixed_' . $gateway_id); ?>" class="wc-enhanced-select">
                                <option value="percentage" <?php selected($method_value, 'percentage'); ?>><?php echo esc_html(__('Percentage', 'wc-invoice-payment')); ?></option>
                                <option value="fixed" <?php selected($method_value, 'fixed'); ?>><?php echo esc_html(__('Fixed Value', 'wc-invoice-payment')); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html(__('Select Percentage or Fixed Value to use in checkout and order calculation.', 'wc-invoice-payment')); ?></p>
                        </div>
                        
                        <!-- Valor -->
                        <div>
                            <input name="<?php echo esc_attr($slug . 'value_' . $gateway_id); ?>" id="<?php echo esc_attr($slug . 'value_' . $gateway_id); ?>" type="number" value="<?php echo esc_attr($amount_value); ?>" min="0" step="0.01" />
                            <p class="description"><?php echo esc_html(__('Only integer or decimal numbers are allowed. Examples of allowed numbers: 10 or 10.55. For 30% percentage use 30.', 'wc-invoice-payment')); ?></p>
                        </div>                        
                    </div>
                </fieldset>
            </td>
        </tr>
        <?php
    }

    public function render_partial_payment_gateway_config_field($value)
    {
        $gateway_id = $value['gateway_id'];
        $gateway_title = $value['gateway_title'];
        
        // Configurações para método habilitado
        $method_slug = 'lkn_wcip_partial_payments_method_';
        $method_enabled = get_option($method_slug . $gateway_id, 'no');
        // Migração da configuração antiga para métodos habilitados (uma opção para todos) para a nova (uma por gateway)
        $old_methods = get_option('lkn_wcip_partial_payment_methods_enabled', array());
        if (!empty($old_methods) && isset($old_methods[$gateway_id])) {
            // Se existe na configuração antiga e não existe na nova, migra o valor
            if (get_option($method_slug . $gateway_id, false) === false) {
                update_option($method_slug . $gateway_id, $old_methods[$gateway_id]);
            }
            $method_enabled = get_option($method_slug . $gateway_id, 'no');
            
            // Remove este gateway da configuração antiga
            unset($old_methods[$gateway_id]);
            
            // Se não há mais gateways na configuração antiga, deleta ela completamente
            if (empty($old_methods)) {
                delete_option('lkn_wcip_partial_payment_methods_enabled');
            } else {
                update_option('lkn_wcip_partial_payment_methods_enabled', $old_methods);
            }
        }
        
        // Configurações para status
        $status_slug = 'lkn_wcip_partial_complete_status_';
        $selected_status = get_option($status_slug . $gateway_id, 'wc-processing');
        
        // Migração da configuração antiga para status (uma opção para todos) para a nova (uma por gateway)
        $old_statuses = get_option('lkn_wcip_partial_payment_methods_statuses', array());
        if (!empty($old_statuses) && isset($old_statuses[$gateway_id])) {
            // Se existe na configuração antiga e não existe na nova, migra o valor
            if (get_option($status_slug . $gateway_id, false) === false) {
                update_option($status_slug . $gateway_id, $old_statuses[$gateway_id]);
            }
            $selected_status = get_option($status_slug . $gateway_id, 'wc-processing');
            
            // Remove este gateway da configuração antiga
            unset($old_statuses[$gateway_id]);
            
            // Se não há mais gateways na configuração antiga, deleta ela completamente
            if (empty($old_statuses)) {
                delete_option('lkn_wcip_partial_payment_methods_statuses');
            } else {
                update_option('lkn_wcip_partial_payment_methods_statuses', $old_statuses);
            }
        }

        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($method_slug . $gateway_id); ?>"><?php echo esc_html($gateway_title); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php echo esc_html(__('Configure partial payment settings for this payment method', 'wc-invoice-payment')); ?></span>
                    </legend>
                    
                    <div style="display: flex;gap: 15px;align-items: flex-start;flex-wrap: wrap;flex-direction: column;">
                        <!-- Checkbox para habilitar método -->
                        <div>
                            <input type="checkbox" name="<?php echo esc_attr($method_slug . $gateway_id); ?>" id="<?php echo esc_attr($method_slug . $gateway_id); ?>" value="yes" <?php checked($method_enabled, 'yes'); ?> />
                            <label for="<?php echo esc_attr($method_slug . $gateway_id); ?>"><?php echo esc_html(__('Habilitar pagamento parcial', 'wc-invoice-payment')); ?></label>
                            <p class="description"><?php echo esc_html(__('Habilita o pagamento parcial para o método de pagamento.', 'wc-invoice-payment')); ?></p>
                        </div>
                        
                        <!-- Status de confirmação -->
                        <div>
                            <select name="<?php echo esc_attr($status_slug . $gateway_id); ?>" id="<?php echo esc_attr($status_slug . $gateway_id); ?>" class="wc-enhanced-select">
                                <?php 
                                    $status = wc_get_order_statuses();
                                    foreach ( $status as $key => $value ) {
                                        echo "<option value='" . esc_attr($key) . "' " . selected($selected_status, $key, false) . ">" . esc_html($value) . "</option>";
                                    }
                                ?>
                            </select>
                            <p class="description"><?php echo esc_html(__('Selecione o status de pagamento confirmado nesse método. Assim o pagamento parcial será confirmado apenas quando o status for igual ao definido.', 'wc-invoice-payment')); ?></p>
                        </div>
                    </div>
                </fieldset>
            </td>
        </tr>
        <?php
    }

    // === MÉTODOS DA ABA ASSINATURAS ===
    public function showSubscriptionSettingTabContent()
    {
        wp_enqueue_script('woocommerce_admin');
        $this->loadScriptsAndStyles();
        woocommerce_admin_fields($this->getSubscriptionSettings());
    }

    public function saveSubscriptionSettings()
    {
        woocommerce_update_options($this->getSubscriptionSettings());
    }

    // === MÉTODOS DA ABA PAGAMENTO PARCIAL ===
    public function showPartialPaymentSettingTabContent()
    {
        wp_enqueue_script('woocommerce_admin');
        $this->loadScriptsAndStyles();
        woocommerce_admin_fields($this->getPartialPaymentSettings());
    }

    public function savePartialPaymentSettings()
    {
        woocommerce_update_options($this->getPartialPaymentSettings());
    }

    // === MÉTODOS DA ABA TAXA OU DESCONTOS ===
    public function showFeesDiscountsSettingTabContent()
    {
        wp_enqueue_script('woocommerce_admin');
        $this->loadScriptsAndStyles();
        woocommerce_admin_fields($this->getFeesDiscountsSettings());
    }

    public function saveFeesDiscountsSettings()
    {
        woocommerce_update_options($this->getFeesDiscountsSettings());
    }

    private function getInvoiceSettings()
    {
        $slug = 'lkn_wcip_';

        // Obtém a lista de templates disponíveis
        $templates_list = $this->handler_invoice_templates->get_templates_list();
        $template_options = array();

        // Converte a lista de templates para o formato esperado pelo WooCommerce
        foreach ($templates_list as $template) {
            $template_options[$template['id']] = $template['friendly_name'];
        }

        $settingsFields = array(
            'sectionTitle' => array(
                'type'     => 'title',
                'name'     => __('Invoice Settings', 'wc-invoice-payment'),
                'desc'     => __('Configure default settings for invoices.', 'wc-invoice-payment'),
            ),
            $slug . 'global_pdf_template_id' => array(
                'name'     => __('Default PDF template for invoices', 'wc-invoice-payment'),
                'type'     => 'select',
                'options'  => $template_options,
                'desc'     => __('Select the default PDF template to use for invoices.', 'wc-invoice-payment'),
                'id'       => $slug . 'global_pdf_template_id',
                'default'  => 'linknacional',
            ),
            $slug . 'template_logo_url' => array(
                'name'     => __('Logo URL', 'wc-invoice-payment'),
                'type'     => 'text',
                'desc'     => __('Maximum recommended width of 460 pixels', 'wc-invoice-payment'),
                'id'       => $slug . 'template_logo_url',
                'custom_attributes' => array(
                    'data-media-uploader-target' => '#' . $slug . 'template_logo_url',
                    'style' => 'display: none;'
                )
            ),
            $slug . 'default_footer' => array(
                'name'     => __('Default footer', 'wc-invoice-payment'),
                'type'     => 'lkn_wp_editor',
                'desc'     => __('Conteúdo HTML do rodapé padrão para as faturas', 'wc-invoice-payment'),
                'id'       => $slug . 'default_footer',
            ),
            $slug . 'sender_details' => array(
                'name'     => __('Sender details', 'wc-invoice-payment'),
                'type'     => 'lkn_wp_editor',
                'desc'     => __('Conteúdo HTML dos detalhes do remetente para as faturas', 'wc-invoice-payment'),
                'id'       => $slug . 'sender_details',
            ),
            $slug . 'text_before_payment_link' => array(
                'name'     => __('Text before payment link', 'wc-invoice-payment'),
                'type'     => 'lkn_wp_editor',
                'desc'     => __('Conteúdo HTML do texto antes do link de pagamento para as faturas', 'wc-invoice-payment'),
                'id'       => $slug . 'text_before_payment_link',
            ),
            $slug . 'after_save_button_email_check' => array(
                'name'     => __('Email Verification', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'desc'     => __('Enable email verification on the invoice.', 'wc-invoice-payment'),
                'desc_tip' => __('This feature will enable a text box for the user to enter their email address before displaying the invoice.', 'wc-invoice-payment'),
                'id'       => $slug . 'after_save_button_email_check',
                'default'  => 'no',
            ),
            $slug . 'subscription_active_product_invoices' => array(
                'name'     => __('Create invoices for products', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'description'     => __('Create invoices for products', 'wc-invoice-payment'),
                'desc_tip' => __('By enabling this setting, every purchase order in WooCommerce will have an invoice available in the invoice lists. This feature makes it easier to send a payment link to the user who made a product purchase in the WooCommerce store.', 'wc-invoice-payment'),
                'id'       => $slug . 'subscription_active_product_invoices',
                'default'  => 'no',
                'custom_attributes' => array(
                    'data-title-description' => __('Select "Development" to test transactions in sandbox mode. Use "Production" for real transactions.', 'wc-invoice-payment')
                )
            ),
            'sectionEnd' => array(
                'type' => 'sectionend'
            )
        );

        return $settingsFields;
    }

    private function getSubscriptionSettings()
    {
        $slug = 'lkn_wcip_';
        
        $settingsFields = array(
            'sectionTitle' => array(
                'type'     => 'title',
                'name'     => __('Subscription Settings', 'wc-invoice-payment'),
                'desc'     => __('Configure settings for subscription invoices.', 'wc-invoice-payment'),
            ),
            $slug . 'interval_number' => array(
                'title' => __('Invoice issuance lead time', 'wc-invoice-payment'),
                'type' => 'number',
                'desc' => __('Set the lead time for invoice generation relative to the due date.', 'wc-invoice-payment'),
                'default' => '0',
                'custom_attributes' => array(
                    'min' => '0',
                    'step' => '1',
                ),
                'id'       => $slug . 'interval_number',
            ),
            $slug . 'interval_type' => array(
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => array(
                    'day' => __('Days', 'wc-invoice-payment'),
                    'week' => __('Weeks', 'wc-invoice-payment'),
                    'month' => __('Months', 'wc-invoice-payment'),
                ),
                'id'       => $slug . 'interval_type',
                'default' => 'interest'
            ),
            'sectionEnd' => array(
                'type' => 'sectionend'
            )
        );

        return $settingsFields;
    }

    private function getPartialPaymentSettings()
    {
        $slug = 'lkn_wcip_';

        // Obtém os gateways de pagamento disponíveis
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $gateway_options = array();

        foreach ($payment_gateways as $gateway_id => $gateway) {
            $gateway_options[$gateway_id] = $gateway->get_title();
        }

        // Get available order statuses
        $status = wc_get_order_statuses();

        // Statuses to be ignored
        $excluded_statuses = array(
            'wc-partial-pend',
            'wc-partial'
        );
        
        // Remove unwanted statuses
        foreach ( $excluded_statuses as $excluded ) {
            unset( $status[ $excluded ] );
        }

        $settingsFields = array(
            'sectionTitle' => array(
                'type'     => 'title',
                'name'     => __('Partial Payment Settings', 'wc-invoice-payment'),
                'desc'     => __('Configure settings for partial payments.', 'wc-invoice-payment'),
            ),
            $slug . 'partial_payments_enabled' => array(
                'name'     => __('Partial payments', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'desc_tip'     => __('Enables the partial payment option for the customer at checkout.', 'wc-invoice-payment'),
                'id'       => $slug . 'partial_payments_enabled',
                'default'  => 'no',
                'custom_attributes' => array(
                    'title' => __('Enable partial payments', 'wc-invoice-payment'),
                )
            ),
            $slug . 'partial_complete_status' => array(
                'name'     => __('Order status when partial payment is complete', 'wc-invoice-payment'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => $status,
                'desc'     => __('Select the order status to be applied when partial payment is completed. WooCommerce default: Processing.', 'wc-invoice-payment'),
                'id'       => $slug . 'partial_complete_status',
                'default' => 'wc-processing'
            ),
            $slug . 'partial_interval_minimum' => array(
                'name' => __('Enable partial payment for orders above', 'wc-invoice-payment'),
                'type' => 'number',
                'desc' => __('Define the minimum order value for the partial payment option to be displayed at checkout.', 'wc-invoice-payment'),
                'default' => '0',
                'custom_attributes' => array(
                    'min' => '0',
                    'step' => '0.01',
                ),
                'id'       => $slug . 'partial_interval_minimum',
            )
        );

        // Adiciona configurações para cada gateway de pagamento
        foreach ($payment_gateways as $gateway_id => $gateway) {
            // Configuração principal para o gateway (tipo: taxa ou desconto)
            $settingsFields[$slug . 'gateway_' . $gateway_id . '_type'] = array(
                'name'     => $gateway->get_title(),
                'type'     => 'lkn_partial_payment_gateway_config',
                'gateway_id' => $gateway_id,
                'gateway_title' => $gateway->get_title(),
                'id'       => $slug . 'gateway_' . $gateway_id . '_type',
            );
        }

        $settingsFields['sectionEnd'] = array(
            'type' => 'sectionend'
        );

        return $settingsFields;
    }

    private function getFeesDiscountsSettings()
    {
        $slug = 'lkn_wcip_';

        // Obtém os gateways de pagamento disponíveis
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        $settingsFields = array(
            'sectionTitle' => array(
                'type'     => 'title',
                'name'     => __('Fees and Discounts Settings', 'wc-invoice-payment'),
                'desc'     => __('Configure fees and discounts for each payment method.', 'wc-invoice-payment'),
            ),
            $slug . 'show_fee_activated' => array(
                'name'     => __('Show payment discount', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'desc_tip'     => __('Enables the display of discounts for each payment method at checkout.', 'wc-invoice-payment'),
                'id'       => $slug . 'show_fee_activated',
                'default'  => 'no',
            ),
            $slug . 'show_discount_activated' => array(
                'name'     => __('Show fee at payment', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'desc_tip'     => __('Enables the display of fees for each payment method at checkout.', 'wc-invoice-payment'),
                'id'       => $slug . 'show_discount_activated',
                'default'  => 'no',
            ),
        );

        // Adiciona configurações para cada gateway de pagamento
        foreach ($payment_gateways as $gateway_id => $gateway) {
            // Configuração principal para o gateway (tipo: taxa ou desconto)
            $settingsFields[$slug . 'gateway_' . $gateway_id . '_type'] = array(
                'name'     => $gateway->get_title(),
                'type'     => 'lkn_payment_gateway_config',
                'gateway_id' => $gateway_id,
                'gateway_title' => $gateway->get_title(),
                'id'       => $slug . 'gateway_' . $gateway_id . '_type',
            );
        }

        $settingsFields['sectionEnd'] = array(
            'type' => 'sectionend'
        );

        return $settingsFields;
    }

    public function quoteSettingTabContent()
    {
        wp_enqueue_script('woocommerce_admin');
        $this->loadScriptsAndStyles();
        woocommerce_admin_fields($this->getQuoteSettings());
    }

    public function saveQuoteSettings()
    {
        woocommerce_update_options($this->getQuoteSettings());
    }

    private function getQuoteSettings()
    {
        $slug = 'lkn_wcip_';

        // Obtém as páginas do WordPress
        $wpPages = array();
        $pages = get_pages();
        foreach ($pages as $page) {
            $wpPages[$page->ID] = $page->post_title;
        }
        //Por padrão selecionado a página de Finalização de compra
        $checkout_page_id = get_option('woocommerce_checkout_page_id');

        $settingsFields = array(
            'sectionTitle' => array(
                'type'     => 'title',
                'name'     => __('Configuração de orçamento', 'wc-invoice-payment'),
            ),
            $slug . 'quote_mode' => array(
                'name'     => __('Loja em modo orçamento', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'desc_tip'     => __('Ativa o modo de orçamento global na loja virtual. Quando habilitado, nenhum pagamento poderá ser feito até que o orçamento seja aprovado pelo administrador. Somente após a aprovação, o cliente poderá finalizar o pagamento do orçamento normalmente.', 'wc-invoice-payment'),
                'id'       => $slug . 'quote_mode',
                'default'  => 'no',
            ),
            $slug . 'show_products_price' => array(
                'name'     => __('Mostrar preço dos produtos', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'desc_tip'     => __('Habilita a exibição dos produtos para o cliente mesmo que o orçamento ainda não tenha sido aprovado.', 'wc-invoice-payment'),
                'id'       => $slug . 'show_products_price',
                'default'  => 'no',
            ),
            $slug . 'create_invoice_automatically' => array(
                'name'     => __('Criar fatura automaticamente', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'desc_tip'     => __('Essa configuração irá gerar automaticamente uma fatura paga pagamento com detalhes do orçamento logo após o orçamento ser aprovado.', 'wc-invoice-payment'),
                'id'       => $slug . 'create_invoice_automatically',
                'default'  => 'no',
            ),
            $slug . 'display_coupon' => array(
                'name'     => __('Cupom de desconto', 'wc-invoice-payment'),
                'type'     => 'checkbox',
                'desc_tip'     => __('Essa configuração exibe o campo para o cliente inserir um cupom de desconto ao solicitar um orçamento.', 'wc-invoice-payment'),
                'id'       => $slug . 'display_coupon',
                'default'  => 'no',
            ),
            $slug . 'quote_expiration' => array(
                'name' => __('Vencimento Padrão de Orçamento', 'wc-invoice-payment'),
                'type' => 'number',
                'desc' => __('Informe a quantidade em dias que o orçamento deve ser definido como vencido.', 'wc-invoice-payment'),
                'default' => '10',
                'custom_attributes' => array(
                    'min' => '1',
                    'step' => '1',
                ),
                'id'       => $slug . 'quote_expiration',
            )
        );
        $settingsFields['sectionEnd'] = array(
            'type' => 'sectionend'
        );

        return $settingsFields;
    }

    public function loadScriptsAndStyles(){
        // Carrega a biblioteca de mídia do WordPress
        wp_enqueue_media();

        // Carrega o script para upload de arquivos
        wp_enqueue_script('cpt-admin-script', WC_PAYMENT_INVOICE_ROOT_URL . 'Admin/js/wc-invoice-payment-public-input-file.js', array('jquery'), WC_PAYMENT_INVOICE_VERSION, true);
        wp_enqueue_style('cpt-admin-style', WC_PAYMENT_INVOICE_ROOT_URL . 'Admin/css/wc-invoice-payment-settings.css', array(), WC_PAYMENT_INVOICE_VERSION);
        wp_enqueue_style('admin-layout-style', WC_PAYMENT_INVOICE_ROOT_URL . 'Admin/css/wc-invoice-payment-settings-layout.css', array(), WC_PAYMENT_INVOICE_VERSION);
        wp_enqueue_script('admin-layout-script', WC_PAYMENT_INVOICE_ROOT_URL . 'Admin/js/wc-invoice-payment-settings-layout.js', array('jquery'), WC_PAYMENT_INVOICE_VERSION, true);
        wp_localize_script('admin-layout-script', 'lknWcInvoicesTranslationsInput', array(
            'modern' => __('Modern version', 'wc-invoice-payment'),
            'standard' => __('Standard version', 'wc-invoice-payment'),
            'enable' => __('Enable', 'wc-invoice-payment'),
            'disable' => __('Disable', 'wc-invoice-payment'),
        ));

        // Adiciona suporte aos tipos customizados de campos
        add_action('woocommerce_admin_field_lkn_wp_editor', array($this, 'render_wp_editor_field'));
        add_action('woocommerce_admin_field_lkn_payment_gateway_config', array($this, 'render_payment_gateway_config_field'));
        add_action('woocommerce_admin_field_lkn_partial_payment_gateway_config', array($this, 'render_partial_payment_gateway_config_field'));
    }
}
