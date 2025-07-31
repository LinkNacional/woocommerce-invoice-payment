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
    }

    private function register_all_settings_tabs()
    {
        wp_enqueue_style('cpt-admin-style', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-settings.css', array(), '1.0');


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
        
        // Carrega a biblioteca de mídia do WordPress
        wp_enqueue_media();
        
        // Carrega o script para upload de arquivos
        wp_enqueue_script('cpt-admin-script', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-public-input-file.js', array('jquery'), '1.0', true);
        
        // Adiciona suporte ao tipo customizado de editor
        add_action('woocommerce_admin_field_lkn_wp_editor', array($this, 'render_wp_editor_field'));
        
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

    // === MÉTODOS DA ABA ASSINATURAS ===
    public function showSubscriptionSettingTabContent()
    {
        wp_enqueue_script('woocommerce_admin');
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
				'name'     => __( 'Logo URL', 'wc-invoice-payment' ),
				'type'     => 'text',
				'desc'     => __( 'Maximum recommended width of 460 pixels', 'wc-invoice-payment' ),
				'id'       => $slug . 'template_logo_url',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'data-media-uploader-target' => '#' . $slug . 'template_logo_url',
                    'style' => 'display: none;'
                )
			),
            $slug . 'default_footer' => array(
                'name'     => __( 'Default footer', 'wc-invoice-payment' ),
                'type'     => 'lkn_wp_editor',
                'desc'     => __( 'Conteúdo HTML do rodapé padrão para as faturas', 'wc-invoice-payment' ),
                'id'       => $slug . 'default_footer',
                'desc_tip' => true,
            ),
            $slug . 'sender_details' => array(
                'name'     => __( 'Sender details', 'wc-invoice-payment' ),
                'type'     => 'lkn_wp_editor',
                'desc'     => __( 'Conteúdo HTML dos detalhes do remetente para as faturas', 'wc-invoice-payment' ),
                'id'       => $slug . 'sender_details',
                'desc_tip' => true,
            ),
            $slug . 'text_before_payment_link' => array(
                'name'     => __( 'Text before payment link', 'wc-invoice-payment' ),
                'type'     => 'lkn_wp_editor',
                'desc'     => __( 'Conteúdo HTML do texto antes do link de pagamento para as faturas', 'wc-invoice-payment' ),
                'id'       => $slug . 'text_before_payment_link',
                'desc_tip' => true,
            ),
            $slug . 'after_save_button_email_check' => array(
				'name'     => __( 'Email Verification', 'wc-invoice-payment' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Enable email verification on the invoice.', 'wc-invoice-payment' ),
				'desc_tip' => __( 'This feature will enable a text box for the user to enter their email address before displaying the invoice.', 'wc-invoice-payment' ),
				'id'       => $slug . 'after_save_button_email_check',
				'default'  => 'no',
			),
            $slug . 'subscription_active_product_invoices' => array(
				'name'     => __( 'Create invoices for products', 'wc-invoice-payment' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Create invoices for products', 'wc-invoice-payment' ),
				'desc_tip' => __( 'By enabling this setting, every purchase order in WooCommerce will have an invoice available in the invoice lists. This feature makes it easier to send a payment link to the user who made a product purchase in the WooCommerce store.', 'wc-invoice-payment' ),
				'id'       => $slug . 'subscription_active_product_invoices',
				'default'  => 'no',
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

        // Obtém os status de pedido disponíveis
        $order_statuses = wc_get_order_statuses();

        $settingsFields = array(
            'sectionTitle' => array(
                'type'     => 'title',
                'name'     => __('Partial Payment Settings', 'wc-invoice-payment'),
                'desc'     => __('Configure settings for partial payments.', 'wc-invoice-payment'),
            ),
            'sectionEnd' => array(
                'type' => 'sectionend'
            )
        );

        return $settingsFields;
    }

    private function getFeesDiscountsSettings()
    {
        $slug = 'lkn_wcip_';

        $settingsFields = array(
            'sectionTitle' => array(
                'type'     => 'title',
                'name'     => __('Fees and Discounts Settings', 'wc-invoice-payment'),
                'desc'     => __('Configure settings for fees and discounts.', 'wc-invoice-payment'),
            ),
            'sectionEnd' => array(
                'type' => 'sectionend'
            )
        );

        return $settingsFields;
    }
}
