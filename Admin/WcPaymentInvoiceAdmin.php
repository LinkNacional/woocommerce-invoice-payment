<?php

namespace LknWc\WcInvoicePayment\Admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * @see       https://www.linknacional.com/
 * @since      1.0.0
 */

use DateTime;
use LknWc\WcInvoicePayment\Admin\LknWcipListTable;
use LknWc\WcInvoicePayment\Admin\WcPaymentInvoicePdfTemplates;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceSubscription;
use WC_Customer;
use WC_Product;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @author     Link Nacional
 */
final class WcPaymentInvoiceAdmin
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     *
     * @var string the ID of this plugin
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     *
     * @var string the current version of this plugin
     */
    private $version;

    /**
     * @since 1.2.0
     * @var Wc_Payment_Invoice_Pdf_Templates
     */
    private $handler_invoice_templates;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param string $plugin_name the name of this plugin
     * @param string $version     the version of this plugin
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', array($this, 'add_setting_session'));
        add_action('admin_menu', array($this, 'add_new_invoice_submenu_section'));

        $this->handler_invoice_templates = new WcPaymentInvoicePdfTemplates($this->plugin_name, $this->version);
    }

    /**
     * Check if invoice is expired and mark as cancelled.
     *
     * @param string $orderId
     *
     * @return bool
     */
    public function check_invoice_exp_date($orderId)
    {
        $order = wc_get_order($orderId);
        if ($order) {
            $todayObj = new DateTime();
            $expDate = $order->get_meta('lkn_exp_date') . ' 23:59'; // Needs to set the hour to not cancel invoice in the last day of payment
            $format = 'Y-m-d H:i';
            $expDateObj = DateTime::createFromFormat($format, $expDate);
            if ($todayObj > $expDateObj && $order->get_status() == 'pending') {
                $order->set_status('wc-cancelled', __('Invoice expired', 'wc-invoice-payment'));
                $order->save();

                $timestamp = wp_next_scheduled('lkn_wcip_cron_hook', array($orderId));
                wp_unschedule_event($timestamp, 'lkn_wcip_cron_hook', array($orderId));

                return false;
            }

            return true;
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     *
     * @param mixed $hook
     */
    public function enqueue_styles($hook): void
    {
        /*
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wc_Payment_Invoice_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wc_Payment_Invoice_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         * 
         * 
         */

        if (
            strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_new-invoice' === $hook
            || strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_settings' === $hook
            || strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_wc-subscription-payment' === $hook
            || 'toplevel_page_wc-invoice-payment' === $hook
            || 'admin_page_edit-invoice' === $hook
            || 'admin_page_edit-subscription' === $hook
            || 'woocommerce_page_wc-orders' === $hook
        ) {
            wp_enqueue_style($this->plugin_name . '-admin-style', plugin_dir_url(__FILE__) . 'css/wc-invoice-payment-admin.css', array(), $this->version, 'all');
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     *
     * @param mixed $hook
     */
    public function enqueue_scripts($hook): void
    {
        /*
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wc_Payment_Invoice_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wc_Payment_Invoice_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */
        if (
            strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_new-invoice' === $hook
            || strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_settings' === $hook
            || strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_wc-subscription-payment' === $hook
            || 'toplevel_page_wc-invoice-payment' === $hook
            || 'admin_page_edit-invoice' === $hook
            || 'admin_page_edit-subscription' === $hook
            || 'wc-invoice-payment_page_wc-subscription-payment' === $hook
        ) {
            wp_enqueue_script($this->plugin_name . '-admin-js', plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-admin.js', array('wp-i18n', 'jquery'), $this->version, false);
            wp_set_script_translations($this->plugin_name . '-admin-js', 'wc-invoice-payment', WC_PAYMENT_INVOICE_TRANSLATION_PATH);
            wp_localize_script(
                $this->plugin_name . '-admin-js',
                'phpattributes',
                array(
                    'downloadInvoice' => __('Download invoice', 'wc-invoice-payment'),
                    'downloading' => __('Downloading...', 'wc-invoice-payment'),
                    'name' => __('Name', 'wc-invoice-payment'),
                    'amount' => __('Amount', 'wc-invoice-payment'),
                    'deleteConfirm' => __('Are you sure you want to delete the invoice?', 'wc-invoice-payment'),
                    'invoice' => __('Invoice', 'wc-invoice-payment'),
                    'pdfError' => __('Unable to generate the PDF. Please, contact support.', 'wc-invoice-payment'),
                    'cancelConfirm' => __('Are you sure you want to cancel the invoice?', 'wc-invoice-payment')
                )
            );
            wp_enqueue_media();
            wp_enqueue_script('cpt-admin-script', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/js/wc-invoice-payment-public-input-file.js', array('jquery'), '1.0', true);
        }
    }

    /**
     * Generates custom menu section and setting page.
     */
    public function add_setting_session(): void
    {
        add_menu_page(
            __('List invoices', 'wc-invoice-payment'),
            __('Invoices', 'wc-invoice-payment'),
            'manage_woocommerce',
            'wc-invoice-payment',
            false,
            'dashicons-money-alt',
            50
        );

        add_submenu_page(
            'wc-invoice-payment',
            __('List invoices', 'wc-invoice-payment'),
            __('Invoices', 'wc-invoice-payment'),
            'manage_woocommerce',
            'wc-invoice-payment',
            array($this, 'render_invoice_list_page'),
            1
        );

        add_submenu_page(
            'wc-invoice-payment',
            __('List Subscriptions', 'wc-invoice-payment'),
            __('Subscriptions', 'wc-invoice-payment'),
            'manage_woocommerce',
            'wc-subscription-payment',
            array($this, 'render_subscription_list_page'),
            1
        );

        add_submenu_page(
            'wc-invoice-payment',
            __('Settings', 'wc-invoice-payment'),
            __('Settings', 'wc-invoice-payment'),
            'manage_woocommerce',
            'settings',
            array($this, 'render_settings_page'),
            2
        );
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_woocommerce') && isset($_POST['lkn_wcip_settings_nonce']) && ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lkn_wcip_settings_nonce'])), 'settings_nonce')) {
            return;
        }
        wp_enqueue_style('my-tailwind-plugin-styles', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/style.css', array(), $this->version);
        wp_enqueue_editor();

        $current_tab = isset($_GET['settings']) ? sanitize_text_field(wp_unslash($_GET['settings'])) : 'Invoices';

        if (! empty($_POST)) {
            if ('Invoices' === $current_tab) {
                $global_pdf_template = sanitize_text_field(wp_unslash($_POST['lkn_wcip_payment_global_template']));
                $product_invoices = sanitize_text_field(wp_unslash($_POST["lkn_wcip_subscription_active_product_invoices"]));
                $template_logo_url = sanitize_text_field(wp_unslash($_POST['lkn_wcip_template_logo_url']));

                $default_footer = wp_kses_post(wp_unslash($_POST['lkn_wcip_default_footer']));
                $sender_details = wp_kses_post(wp_unslash($_POST['lkn_wcip_sender_details']));
                $text_before_payment_link = wp_kses_post(wp_unslash($_POST['lkn_wcip_text_before_payment_link']));
                $email_verify = isset($_POST["lkn_wcip_after_save_button_email_check"]);
                update_option('lkn_wcip_global_pdf_template_id', $global_pdf_template);
                update_option('lkn_wcip_template_logo_url', $template_logo_url);
                update_option('lkn_wcip_default_footer', $default_footer);
                update_option('lkn_wcip_sender_details', $sender_details);
                update_option('lkn_wcip_text_before_payment_link', $text_before_payment_link);
                update_option('lkn_wcip_subscription_active_product_invoices', $product_invoices);
                update_option("lkn_wcip_after_save_button_email_check", $email_verify);
            } else if( 'Subscriptions' === $current_tab) {
                $interval_number = sanitize_text_field(wp_unslash($_POST["lkn_wcip_subscription_interval_number"]));
                $interval_type = sanitize_text_field(wp_unslash($_POST["lkn_wcip_subscription_interval_type"]));

                update_option('lkn_wcip_interval_number', $interval_number);
                update_option('lkn_wcip_interval_type', $interval_type);
            }else if('Partial' === $current_tab) {
                $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
                $saved_methods = [];
                $saved_statuses = [];

                foreach ( $payment_gateways as $gateway_id => $gateway ) {
                    $method_key = 'lkn_wcip_partial_payments_method_' . $gateway_id;
                    $status_key = 'lkn_wcip_partial_complete_status_' . $gateway_id;

                    // Salvar se está habilitado
                    $enabled = isset($_POST[$method_key]) ? 'yes' : 'no';
                    $saved_methods[$gateway_id] = $enabled;

                    // Salvar status selecionado
                    if (isset($_POST[$status_key])) {
                        $saved_statuses[$gateway_id] = sanitize_text_field(wp_unslash($_POST[$status_key]));
                    }
                }

                update_option('lkn_wcip_partial_payment_methods_enabled', $saved_methods);
                update_option('lkn_wcip_partial_payment_methods_statuses', $saved_statuses);

                $partial_complete_status = sanitize_text_field(wp_unslash($_POST['lkn_wcip_partial_complete_status'] ?? ''));
                $partial_minimum_value = sanitize_text_field(wp_unslash($_POST['lkn_wcip_partial_interval_minimum'] ?? '0.00'));
                $partial_payments_enabled = sanitize_text_field(wp_unslash($_POST['lkn_wcip_partial_payments_enabled'] ?? ''));

                update_option('lkn_wcip_partial_complete_status', $partial_complete_status);
                update_option('lkn_wcip_partial_interval_minimum', $partial_minimum_value);
                update_option('lkn_wcip_partial_payments_enabled', $partial_payments_enabled);
            }else if('FeesOrDiscounts' === $current_tab) {
                $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

                foreach ( $payment_gateways as $gateway_id => $gateway ) {
                    if(isset($_POST['lkn_wcip_fee_or_discount_method_activated_' . $gateway_id])) {
                        $isActive = sanitize_text_field(wp_unslash($_POST['lkn_wcip_fee_or_discount_method_activated_' . $gateway_id]));
                        update_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id, $isActive);
                    }else{
                        update_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id, 'off');
                    }
                    if(isset($_POST['lkn_wcip_fee_or_discount_type_' . $gateway_id])) {
                        $type = sanitize_text_field(wp_unslash($_POST['lkn_wcip_fee_or_discount_type_' . $gateway_id]));
                        update_option('lkn_wcip_fee_or_discount_type_' . $gateway_id, $type);
                    }
                    if(isset($_POST['lkn_wcip_fee_or_discount_percent_fixed_' . $gateway_id])) {
                        $percentOrFixed = sanitize_text_field(wp_unslash($_POST['lkn_wcip_fee_or_discount_percent_fixed_' . $gateway_id]));
                        update_option('lkn_wcip_fee_or_discount_percent_fixed_' . $gateway_id, $percentOrFixed);
                    }
                    if(isset($_POST['lkn_wcip_fee_or_discount_value_' . $gateway_id])) {
                        $value = sanitize_text_field(wp_unslash($_POST['lkn_wcip_fee_or_discount_value_' . $gateway_id]));
                        update_option('lkn_wcip_fee_or_discount_value_' . $gateway_id, $value);
                    }
                }
                
                if(isset($_POST['lkn_wcip_show_discount_activated'])) {
                    $isDiscountActive = sanitize_text_field(wp_unslash($_POST['lkn_wcip_show_discount_activated']));
                    update_option('lkn_wcip_show_discount_activated', $isDiscountActive);
                }else{
                    update_option('lkn_wcip_show_discount_activated', 'off');
                }
                if(isset($_POST['lkn_wcip_show_fee_activated'])) {
                    $isFeeActive = sanitize_text_field(wp_unslash($_POST['lkn_wcip_show_fee_activated']));
                    update_option('lkn_wcip_show_fee_activated', $isFeeActive);
                }else{
                    update_option('lkn_wcip_show_fee_activated', 'off');
                }
            }
        }

        $templates_list = $this->handler_invoice_templates->get_templates_list();
        $global_template = get_option('lkn_wcip_global_pdf_template_id', 'linknacional');

        $template_logo_url = get_option('lkn_wcip_template_logo_url');
        $interval_number = get_option('lkn_wcip_interval_number');
        $interval_type = get_option('lkn_wcip_interval_type');
        $default_footer = get_option('lkn_wcip_default_footer');
        $sender_details = get_option('lkn_wcip_sender_details');
        $text_before_payment_link = get_option('lkn_wcip_text_before_payment_link');
        $email_verify = get_option("lkn_wcip_after_save_button_email_check");
        $product_invoices = get_option("lkn_wcip_subscription_active_product_invoices");
        $saved_methods = get_option('lkn_wcip_partial_payment_methods_enabled', []);
        $saved_statuses = get_option('lkn_wcip_partial_payment_methods_statuses', []);
        $partial_complete_status = get_option('lkn_wcip_partial_complete_status', 'wc-processing');
        $partial_minimum_value = get_option('lkn_wcip_partial_interval_minimum', '0.00');
        $partial_payments_enabled = get_option('lkn_wcip_partial_payments_enabled', '')  == 'on' ? 'checked' : '';
        $show_discount_active = get_option('lkn_wcip_show_discount_activated')  == 'on' ? 'checked' : '';
        $show_fee_active = get_option('lkn_wcip_show_fee_activated')  == 'on' ? 'checked' : '';
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $disabled =  !empty($payment_gateways) ? '' : 'disabled';


        $html_templates_list = implode(array_map(function ($template) use ($global_template): string {
            $template_id = esc_attr($template['id']);
            $friendly_template_name = esc_html($template['friendly_name']);
            $preview_url = esc_url(WC_PAYMENT_INVOICE_ROOT_URL . "Includes/templates/$template_id/preview.webp");

            $selected = $global_template === $template_id ? 'selected' : '';

            return "<option $selected data-preview-url='$preview_url' value='$template_id'>$friendly_template_name</option>";
        }, $templates_list));

        wp_create_nonce('wp_rest');

        // Função para verificar qual aba está ativa
        function is_active_tab($tab_name, $current_tab)
        {
            return $tab_name === $current_tab ? 'nav-tab-active' : '';
        }

?>
        <div class="wrap">
            <h1><?php esc_attr_e('Settings', 'wc-invoice-payment'); ?>
            </h1>
            <?php settings_errors(); ?>
            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <a
                    href="admin.php?page=settings&settings=Invoices"
                    class="nav-tab <?php echo esc_attr(is_active_tab('Invoices', $current_tab)); ?>">
                    <?php esc_attr_e('Invoices', 'wc-invoice-payment') ?>
                </a>
                <a
                    href="admin.php?page=settings&settings=Subscriptions"
                    class="nav-tab <?php echo esc_attr(is_active_tab('Subscriptions', $current_tab)); ?>">
                    <?php esc_attr_e('Subscriptions', 'wc-invoice-payment') ?>
                </a>
                <a
                    href="admin.php?page=settings&settings=Partial"
                    class="nav-tab <?php echo esc_attr(is_active_tab('Partial', $current_tab)); ?>">
                    <?php esc_attr_e('Pagamento parcial', 'wc-invoice-payment') ?>
                </a>
                <a
                    href="admin.php?page=settings&settings=FeesOrDiscounts"
                    class="nav-tab <?php echo esc_attr(is_active_tab('FeesOrDiscounts', $current_tab)); ?>">
                    <?php esc_attr_e('Fees or Discounts', 'wc-invoice-payment') ?>
                </a>
            </nav>
            <form
                action="<?php echo esc_attr(menu_page_url('settings', false)) . "&settings=" . esc_attr($current_tab); ?>"
                method="post"
                class="wcip-form-wrap">
                <?php wp_nonce_field('lkn_wcip_edit_invoice', 'nonce'); ?>

                <input
                    name="lkn_wcip_settings_nonce"
                    id="lkn_wcip_settings_nonce"
                    type="hidden"
                    value="<?php echo esc_attr(wp_create_nonce('settings_nonce')) ?>">

                <div class="wcip-invoice-data">
                    <?php
                    if ('Invoices' == $current_tab) {
                        ?>
                        <div class="invoice_settings">
                            <h2 class="title">
                                <?php esc_attr_e('Invoice settings', 'wc-invoice-payment'); ?>
                            </h2>
                            <div class="invoice-row-wrap">
                                <div class="invoice-column-wrap">
                                    <div class="input-row-wrap input-row-wrap-global-settings">
                                        <label for="lkn_wcip_payment_global_template">
                                            <?php esc_attr_e('Default PDF template for invoices', 'wc-invoice-payment'); ?>
                                        </label>
                                        <select
                                            name="lkn_wcip_payment_global_template"
                                            id="lkn_wcip_payment_global_template"
                                            class="regular-text">
                                            <?php echo wp_kses($html_templates_list, array(
                                                'option' => array(
                                                    'data-preview-url' => true,
                                                    'value' => true,
                                                    'selected' => true,
                                                ),
                                            )); ?>
                                        </select>
                                    </div>
                                    <div class="input-row-wrap">
                                        <div style="position: relative;"><img id="lkn-wcip-preview-img" /></div>
                                    </div>


                                    <div class="input-row-wrap input-row-wrap-global-settings">
                                        <label
                                            class="lkn_wcip_payment_global_template_label"
                                            for="lkn_wcip_payment_global_template">
                                            <?php esc_attr_e('Logo URL', 'wc-invoice-payment'); ?>
                                            <div class="lkn_wcip_payment_global_template_label_description">
                                                <?php esc_attr_e('Maximum recommended width of 460 pixels', 'wc-invoice-payment'); ?>
                                            </div>
                                        </label>


                                        <div class="flex gap-2 items-center">
                                            <button
                                                type="button"
                                                value="<?php echo esc_attr($template_logo_url); ?>"
                                                class="button button-primary"
                                                id="lkn_wcip_template_logo_url_btn"
                                                data-media-uploader-target="#lkn_wcip_template_logo_url">
                                                <?php esc_attr_e('Upload image', 'wc-invoice-payment') ?>
                                            </button>
                                            <h3 id="lkn_wcip_template_logo_desc"></h3>
                                        </div>
                                        <div style="display: none;">
                                            <input
                                                type="text"
                                                class="large-text"
                                                value="<?php echo esc_attr($template_logo_url); ?>"
                                                name="lkn_wcip_template_logo_url"
                                                id="lkn_wcip_template_logo_url" />
                                        </div>
                                    </div>


                                    <div class="input-row-wrap input-row-wrap-global-settings">
                                        <label for="lkn_wcip_default_footer">
                                            <?php esc_attr_e('Default footer', 'wc-invoice-payment'); ?>
                                        </label>
                                        <textarea
                                            name="lkn_wcip_default_footer"
                                            id="lkn_wcip_default_footer"><?php echo esc_html($default_footer); ?></textarea>
                                    </div>

                                    <div class="input-row-wrap input-row-wrap-global-settings">
                                        <label for="lkn_wcip_sender_details">
                                            <?php esc_attr_e('Sender details', 'wc-invoice-payment'); ?>
                                        </label>
                                        <textarea
                                            name="lkn_wcip_sender_details"
                                            id="lkn_wcip_sender_details"><?php echo esc_html($sender_details); ?></textarea>
                                    </div>

                                    <div class="input-row-wrap input-row-wrap-global-settings">
                                        <label for="lkn_wcip_text_before_payment_link">
                                            <?php esc_attr_e('Text before payment link', 'wc-invoice-payment'); ?>
                                        </label>
                                        <textarea
                                            name="lkn_wcip_text_before_payment_link"
                                            id="lkn_wcip_text_before_payment_link"><?php echo esc_html($text_before_payment_link); ?></textarea>

                                    </div>
                                    <div class="input-column-wrap input-row-wrap-global-settings">
                                        <div class="input-column-wrap">
                                            <label for="lkn_wcip_after_save_button_email_check">
                                                <input
                                                    type="checkbox"
                                                    name="lkn_wcip_after_save_button_email_check"
                                                    id="lkn_wcip_after_save_button_email_check"
                                                    <?php if ($email_verify) {
                                                        echo 'checked';
                                                    } ?>
                                                    <i></i>
                                                <?php esc_attr_e('Enable email verification on the invoice.', 'wc-invoice-payment'); ?>

                                                <div class="tooltip">
                                                    <span
                                                        class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                    <span class="tooltiptext">
                                                        <?php esc_attr_e('This feature will enable a text box for the user to enter their email address before displaying the invoice.', 'wc-invoice-payment'); ?>
                                                    </span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="input-column-wrap input-row-wrap-global-settings">
                                        <div
                                            class="input-column-wrap"
                                            id="lkn_wcip_subscription_active_product">
                                            <label for="lkn_wcip_subscription_active_product_invoices">
                                                <input
                                                    name="lkn_wcip_subscription_active_product_invoices"
                                                    id="lkn_wcip_subscription_active_product_invoices"
                                                    type="checkbox"
                                                    class=""
                                                    value="1"
                                                    <?php if ($product_invoices) {
                                                        echo 'checked';
                                                    } ?>>
                                                <?php esc_attr_e('Create invoices for products', 'wc-invoice-payment') ?>

                                                <div class="tooltip">
                                                    <span
                                                        class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                    <span class="tooltiptext">
                                                        <?php esc_attr_e('By enabling this setting, every purchase order in WooCommerce will have an invoice available in the invoice lists. This feature makes it easier to send a payment link to the user who made a product purchase in the WooCommerce store.', 'wc-invoice-payment'); ?>
                                                    </span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>

                    <?php
                    if ('Subscriptions' == $current_tab) {
                        ?>
                        <div class="invoice_settings">
                            <h2 class="title">
                                <?php esc_attr_e('Subscription settings', 'wc-invoice-payment'); ?>
                            </h2>
                            <div
                                class="input-row-wrap"
                                id="lkn_wcip_subscription_interval">
                                <label
                                    for="lkn_wcip_subscription_interval_number"><?php esc_attr_e('Invoice issuance lead time', 'wc-invoice-payment'); ?></label>

                                <div class="flex-row">

                                    <div class="lkn_wcip_subscription_interval_div">
                                        <input
                                            type="number"
                                            min="0"
                                            name="lkn_wcip_subscription_interval_number"
                                            id="lkn_wcip_subscription_interval_number"
                                            value="<?php echo esc_attr($interval_number); ?>">
                                        <select name="lkn_wcip_subscription_interval_type">
                                            <option
                                                value="day"><?php echo esc_attr('day' == $interval_type ? esc_attr("selected") : '') ?>
                                                <?php esc_attr_e('Days', 'wc-invoice-payment'); ?>
                                            </option>
                                            <option
                                                value="week"><?php echo esc_attr('week' == $interval_type ? esc_attr("selected") : '') ?>
                                                <?php esc_attr_e('Weeks', 'wc-invoice-payment'); ?>
                                            </option>
                                            <option
                                                value="month"><?php echo esc_attr('month' == $interval_type ? esc_attr("selected") : '') ?>
                                                <?php esc_attr_e('Months', 'wc-invoice-payment'); ?>
                                            </option>
                                        </select>
                                        <div
                                            class="flex items-center justify-center"
                                            id="lkn_wcip_subscription_interval_div_tip">
                                            <div class="tooltip">
                                                <span
                                                    class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                <span class="tooltiptext">
                                                    <?php esc_attr_e('Set the lead time for invoice generation relative to the due date.', 'wc-invoice-payment'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
                    }
                    ?>

                    <?php
                    if ('Partial' == $current_tab) {
                        ?>
                    <div class="invoice_settings">
                        <h2 class="title">
                            <?php esc_attr_e('Configuração de pagamento parcial', 'wc-invoice-payment'); ?>
                        </h2>
                        <div class="input-row-wrap">
                            <div class="lkn_wcip_partial_payments_method_div_fields firstField">
                                <label class="lkn_wcip_partial_payments_method_label" for="lkn_wcip_partial_payments_enabled">
                                    <input 
                                        name="lkn_wcip_partial_payments_enabled" 
                                        id="lkn_wcip_partial_payments_enabled" 
                                        type="checkbox" <?php echo esc_attr($partial_payments_enabled); ?> <?php echo esc_attr($disabled); ?>>
                                    <p>
                                        Habilitar pagamentos parciais
                                    </p>
                                </label>
                                <div class="tooltip">
                                    <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                    <span class="tooltiptext">
                                        <?php esc_html_e( 'Habilita o pagamento parcial', 'wc-invoice-payment' ); ?>
                                    </span>
                                </div>
                            </div>
                            <div id="lkn_wcip_partial_interval_fields">
                                <div>
                                    <label for="lkn_wcip_partial_interval_number">
                                        <?php esc_attr_e('Status de pedido com pagamento completo', 'wc-invoice-payment'); ?>
                                    </label>
        
                                    <div class="flex-row">
                                        <div class="lkn_wcip_partial_field_div">
                                            <select class="lkn_wcip_partial_field" name="lkn_wcip_partial_complete_status">
                                                <?php 
                                                    $status = wc_get_order_statuses();

                                                    // Status a serem ignorados
                                                    $excluded_statuses = array(
                                                        'wc-partial-pend',
                                                        'wc-partial'
                                                    );
                                                    
                                                    // Remove os status indesejados
                                                    foreach ( $excluded_statuses as $excluded ) {
                                                        unset( $status[ $excluded ] );
                                                    }
                                                    
                                                    // Gera as opções
                                                    foreach ($status as $key => $value) {
                                                        $selected = ($key == $partial_complete_status) ? 'selected' : '';
                                                        echo "<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($value) . "</option>";
                                                    }
                                                ?>
                                            </select>
                                            <div
                                                class="flex items-center justify-center"
                                                id="lkn_wcip_partial_complete_status_tip">
                                                <div class="tooltip">
                                                    <span
                                                        class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                    <span class="tooltiptext">
                                                        <?php esc_attr_e('Selecione o status do pedido após confirmação pagamento total do pedido. Padrão WooCommerce: Processando.', 'wc-invoice-payment'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="lkn_wcip_partial_interval_number">
                                        <?php esc_attr_e('Habilitar pagamento parcial para pedido superior', 'wc-invoice-payment'); ?>
                                    </label>
        
                                    <div class="flex-row">
                                        <div class="lkn_wcip_partial_field_div">
                                            <input
                                                type="number"
                                                min="0"
                                                default="0.00"
                                                step="0.01"
                                                name="lkn_wcip_partial_interval_minimum"
                                                placeholder=""
                                                value="<?php echo esc_attr($partial_minimum_value); ?>"
                                                class="lkn_wcip_partial_field">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="lkn_wcip_partial_payments_method_div">
                                <h2 class="lkn_wcip_partial_title">
                                    <?php esc_attr_e('Métodos de pagamentos', 'wc-invoice-payment'); ?>
                                </h2>
                                <div class="lkn_wcip_partial_payments_methods_div">
                                    <?php
                                        
                                        if(empty($payment_gateways)){
                                            ?>
                                            <div id="message" class="error">
                                                <p>
                                                    Nenhum método de pagamento disponível. Por favor, ative pelo menos um método de pagamento para utilizar o pagamento parcial.
                                                </p>
                                            </div>
                                            <?php
                                        }
                                        foreach ( $payment_gateways as $gateway_id => $gateway ) :
                                            $checked = isset($saved_methods[$gateway_id]) ? checked($saved_methods[$gateway_id], 'yes', false) : 'checked'; // checked por padrão
                                            $selected_status = $saved_statuses[$gateway_id] ?? 'wc-processing';
                                            ?>
                                            <div class="lkn_wcip_partial_payments_method_div">
                                                <div class="lkn_wcip_partial_payments_method_div_fields">
                                                    <label class="lkn_wcip_partial_payments_method_label" for="lkn_wcip_partial_payments_method_<?php echo esc_attr($gateway_id); ?>">
                                                        <input 
                                                            name="lkn_wcip_partial_payments_method_<?php echo esc_attr($gateway_id); ?>" 
                                                            id="lkn_wcip_partial_payments_method_<?php echo esc_attr($gateway_id); ?>" 
                                                            type="checkbox" <?php echo esc_attr($checked); ?>>
                                                        <p>
                                                            <?php echo esc_html( $gateway->get_title() ); ?>
                                                        </p>
                                                    </label>
                                                    <div class="tooltip">
                                                        <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                        <span class="tooltiptext">
                                                            <?php esc_html_e( 'Habilita o pagamento parcial para o método de pagamento', 'wc-invoice-payment' ); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php esc_html_e( 'Status de pagamento completo', 'wc-invoice-payment' ); ?>

                                                    <div class="flex-row">
                                                        <div class="lkn_wcip_partial_payments_method_div_fields">
                                                            <select 
                                                                class="lkn_wcip_partial_field" 
                                                                name="lkn_wcip_partial_complete_status_<?php echo esc_attr($gateway_id); ?>">
                                                                <?php 
                                                                    $status = wc_get_order_statuses();
                                                                    foreach ( $status as $key => $value ) {
                                                                        $selected = $selected_status === $key ? 'selected' : '';
                                                                        echo "<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($value) . "</option>";
                                                                    }
                                                                ?>
                                                            </select>
                                                            <div class="flex items-center justify-center" id="lkn_wcip_partial_complete_status_tip_<?php echo esc_attr($gateway_id); ?>">
                                                                <div class="tooltip">
                                                                    <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                                    <span class="tooltiptext">
                                                                        <?php esc_html_e( 'Selecione o status de pagamento confirmado nesse método. Assim o pagamento parcial será confirmado apenas quando o status for igual ao definido.', 'wc-invoice-payment' ); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php
                            }
                            ?>

                    <?php
                    if ('FeesOrDiscounts' == $current_tab) {
                        ?>
                        <div class="invoice_settings">
                            <h2 class="title">
                                <b><?php esc_attr_e('Payment Methods', 'wc-invoice-payment'); ?></b>
                            </h2>
                            <p>
                                <?php esc_attr_e('Configure fees or discounts for each payment method', 'wc-invoice-payment'); ?>
                            </p>
                            <div class="lkn_wcip_show_fee_or_discount_method_div_fields">
                                <label class="lkn_wcip_show_fee_label" for="lkn_wcip_show_fee_activated">
                                    <input 
                                        name="lkn_wcip_show_fee_activated" 
                                        id="lkn_wcip_show_fee_activated" 
                                        type="checkbox" <?php echo esc_attr($show_fee_active); ?>>
                                    <p>
                                        <b>
                                            <?php echo esc_attr_e('Show fee on payment', 'wc-invoice-payment'); ?>
                                        </b>
                                    </p>
                                    <br>
                                </label>
                                <label class="lkn_wcip_show_discount_label" for="lkn_wcip_show_discount_activated">
                                    <input 
                                        name="lkn_wcip_show_discount_activated" 
                                        id="lkn_wcip_show_discount_activated" 
                                        type="checkbox" <?php echo esc_attr($show_discount_active); ?>>
                                    <p>
                                        <b>
                                            <?php echo esc_attr_e('Show discount on payment', 'wc-invoice-payment'); ?>
                                        </b>
                                    </p>
                                    <br>
                                </label>
                            </div>
                            <div class="input-row-wrap">
                                <?php
                                            if(empty($payment_gateways)){
                                                ?>
                                                <div id="message" class="error">
                                                    <p>
                                                        <?php echo esc_attr_e('No payment methods available. Please enable at least one payment method to use partial payment.', 'wc-invoice-payment'); ?>
                                                    </p>
                                                </div>
                                                <?php
                                            }
                                            foreach ( $payment_gateways as $gateway_id => $gateway ) :
                                                $checked = get_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id, 'off') === 'on' ? 'checked' : '';
                                                $type = get_option('lkn_wcip_fee_or_discount_type_' . $gateway_id, 'fee');
                                                $percentOrFixed = get_option('lkn_wcip_fee_or_discount_percent_fixed_' . $gateway_id, 'percent');
                                                $value = get_option('lkn_wcip_fee_or_discount_value_' . $gateway_id, 0);
                                                ?>
                                                <div id="lkn_wcip_partial_payments_fees_or_discounts">
                                                    <div class="lkn_wcip_partial_payments_methods_div">
                                                        <div class="lkn_wcip_fee_or_discounts_method_div">
                                                            <div class="lkn_wcip_fee_or_discounts_method_div_fields">
                                                                <label class="lkn_wcip_fee_or_discounts_method_label" for="lkn_wcip_fee_or_discount_method_activated_<?php echo esc_attr($gateway_id); ?>">
                                                                    <input 
                                                                        name="lkn_wcip_fee_or_discount_method_activated_<?php echo esc_attr($gateway_id); ?>" 
                                                                        id="lkn_wcip_fee_or_discount_method_activated_<?php echo esc_attr($gateway_id); ?>" 
                                                                        type="checkbox" <?php echo esc_attr($checked); ?>>
                                                                    <p>
                                                                        <b>
                                                                            <?php echo esc_html( $gateway->get_title() ); ?>
                                                                        </b>
                                                                    </p>
                                                                    <br>
                                                                </label>
                                                                <div class="tooltip">
                                                                    <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                                    <span class="tooltiptext">
                                                                        <?php esc_html_e( 'Enables fee/discount payment for the payment method.', 'wc-invoice-payment' ); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="lkn_wcip_fee_or_discounts_method_fields_div">
                                                                <p>
                                                                    <?php esc_html_e( 'Configure fee or discount for this payment method.', 'wc-invoice-payment' ); ?>
                                                                </p>
                                                                <div class="lkn_wcip_fee_or_discounts_method_fields_div">
                                                                    <div class="lkn_wcip_fee_or_discounts_method_div_fields">
                                                                        <select 
                                                                            class="lkn_wcip_fee_or_discount_field" 
                                                                            name="lkn_wcip_fee_or_discount_type_<?php echo esc_attr($gateway_id); ?>">
                                                                            <option <?php echo esc_attr($type == 'fee' ? 'selected' : ''); ?> value="fee"><?php esc_html_e('Fee', 'wc-invoice-payment') ?></option>
                                                                            <option <?php echo esc_attr($type == 'discount' ? 'selected' : ''); ?>  value="discount"><?php esc_html_e('Discount', 'wc-invoice-payment') ?></option>
                                                                        </select>
                                                                        <div class="flex items-center justify-center">
                                                                            <div class="tooltip">
                                                                                <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                                                <span class="tooltiptext">
                                                                                    <?php esc_html_e( 'Select fee or discount to be applied when the user uses this payment method.', 'wc-invoice-payment' ); ?>
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="lkn_wcip_fee_or_discounts_method_div_fields">
                                                                        <select 
                                                                            class="lkn_wcip_fee_or_discount_field" 
                                                                            name="lkn_wcip_fee_or_discount_percent_fixed_<?php echo esc_attr($gateway_id); ?>">
                                                                            <option <?php echo esc_attr($percentOrFixed == 'percent' ? 'selected' : ''); ?> value="percent"><?php esc_html_e('Percent', 'wc-invoice-payment') ?></option>
                                                                            <option <?php echo esc_attr($percentOrFixed == 'fixed' ? 'selected' : ''); ?> value="fixed"><?php esc_html_e('Fixed Value', 'wc-invoice-payment') ?></option>
                                                                        </select>
                                                                        <div class="flex items-center justify-center">
                                                                            <div class="tooltip">
                                                                                <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                                                <span class="tooltiptext">
                                                                                    <?php esc_html_e( 'Select Percentage or Fixed Value to be used in checkout and order calculation.', 'wc-invoice-payment' ); ?>
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div> 
                                                                    <div class="lkn_wcip_fee_or_discounts_method_div_fields">

                                                                        <input 
                                                                            type="number" 
                                                                            min="0" 
                                                                            value=<?php echo esc_attr($value); ?>
                                                                            class="lkn_wcip_fee_or_discount_field" 
                                                                            name="lkn_wcip_fee_or_discount_value_<?php echo esc_attr($gateway_id); ?>"
                                                                        >
                                                                        <div class="flex items-center justify-center">
                                                                            <div class="tooltip">
                                                                                <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                                                <span class="tooltiptext">
                                                                                    <?php esc_html_e( 'Only integer or decimal numbers are allowed. Examples of allowed numbers: 10 or 10.55. For percentage 30% use 30.', 'wc-invoice-payment' ); ?>
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                        <?php endforeach; ?>
                                    
                    <?php
                        }
                    ?>

                            <div class="action-btn">
                                <?php submit_button(__('Save', 'wc-invoice-payment'), 'primary', 'submit', true, $disabled == 'disabled' ? ['disabled' => ''] : ''); ?>
                            </div>
                        </div>
                    </div>
            </form>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', () => {
                startTinyMce('lkn_wcip_default_footer', 'submit')
                startTinyMce('lkn_wcip_sender_details', 'submit')
                startTinyMce('lkn_wcip_text_before_payment_link', 'submit')
            })
        </script>
    <?php
    }

    public function settings_page_form_submit_handle(): void {}

    /**
     * Render html page for invoice edit.
     */
    public function render_edit_invoice_page(): void
    {
        wp_enqueue_style($this->plugin_name . '-admin-style', plugin_dir_url(__FILE__) . 'css/wc-invoice-payment-admin.css', array(), $this->version, 'all');
        wp_enqueue_script($this->plugin_name . '-edit', plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-invoice-edit.js', array(), $this->version, 'all');

        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        if (! empty($_POST) && ! isset($_POST['wcip_rest_nonce']) && ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcip_rest_nonce'])), 'wp_rest')) {
            return;
        }

        wp_enqueue_editor();
        wp_create_nonce('wp_rest');
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('woocommerce_admin_styles');

        $invoiceId = sanitize_text_field(wp_unslash($_GET['invoice']));

        $decimalSeparator = wc_get_price_decimal_separator();
        $thousandSeparator = wc_get_price_thousand_separator();
        $decimalQtd = wc_get_price_decimals();

        // Get all translated WooCommerce order status
        $statusWc = array();
        $statusWc[] = array('status' => 'wc-pending', 'label' => _x('Pending payment', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-processing', 'label' => _x('Processing', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-on-hold', 'label' => _x('On hold', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-completed', 'label' => _x('Completed', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-cancelled', 'label' => _x('Cancelled', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-refunded', 'label' => _x('Refunded', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-failed', 'label' => _x('Failed', 'Order status', 'wc-invoice-payment'));

        $c = 0;
        $order = wc_get_order($invoiceId);
        $parentOrderId = $order->get_meta('_wc_lkn_parent_id', $order);
        $parentOrder = wc_get_order($parentOrderId);
        if ($order->get_user_id()) {
            $userId = absint($order->get_user_id());
            $user = get_userdata($userId);
            $userInfos = sprintf(
                '%s (#%d – %s)',
                $user->display_name, // Nome completo do usuário
                $userId, // ID do usuário
                $user->user_email // Email do usuário
            );
        } else {
            $userId = '';
            $userInfos = '';
        }

        if ($order->get_meta('lkn_subscription_id')) {
            $subscription_id = $order->get_meta('lkn_subscription_id');
        }
        $items = $order->get_items();
        $checkoutUrl = $order->get_checkout_payment_url();
        $orderStatus = $order->get_status();

        $invoice_template = $order->get_meta('wcip_select_invoice_template_id') ?? get_option('lkn_wcip_global_pdf_template_id', 'global');

        $templates_list = $this->handler_invoice_templates->get_templates_list();

        $html_templates_list = implode(array_map(function ($template) use ($invoice_template): string {
            $template_id = esc_attr($template['id']);
            $friendly_template_name = esc_html($template['friendly_name']);
            $preview_url = esc_url(WC_PAYMENT_INVOICE_ROOT_URL . "Includes/templates/$template_id/preview.webp");

            $selected = $invoice_template === $template_id ? 'selected' : '';

            return "<option " . esc_attr($selected) . " data-preview-url='" . esc_attr($preview_url) . "' value='" . esc_attr($template_id) . "'>" . esc_html($friendly_template_name) . "</option>";
        }, $templates_list));

        $currencies = get_woocommerce_currencies();
        $currency_codes = array_keys($currencies);
        sort($currency_codes);

        $countries = WC()->countries->get_countries(); // Pega os países
        $currency_codes = array_keys($currencies); // Pega os códigos de moeda
        $current_country = $order->get_billing_country();
        // Coloca o Brasil no início, se estiver no array
        if (isset($countries['BR'])) {
            $brazil = $countries['BR'];
            unset($countries['BR']);
            $countries = array('BR' => $brazil) + $countries; // Coloca 'BR' no início
        }

        $gateways = WC()->payment_gateways->payment_gateways();
        $enabled_gateways = array();

        // Get all WooCommerce enabled gateways
        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ('yes' == $gateway->enabled) {
                    $enabled_gateways[] = $gateway;
                }
            }
        }

        $languages = get_available_languages();
        // Adiciona manualmente o inglês à lista de idiomas
        array_unshift($languages, 'en_US');
        $orderLanguage = $order->get_meta('wcip_select_invoice_language');
        // Remove o idioma atual da lista
        if (false !== ($key = array_search($orderLanguage, $languages, true))) {
            unset($languages[$key]);
        }
        // Ordena os idiomas restantes em ordem alfabética
        sort($languages);
        // Adiciona o idioma atual no início da lista
        array_unshift($languages, $orderLanguage);
    ?>
        <div class="wrap">
            <h1><?php esc_attr_e('Edit invoice', 'wc-invoice-payment'); ?>
            </h1>
            <?php settings_errors(); ?>
            <form
                action="<?php menu_page_url('edit-invoice&invoice=' . $invoiceId); ?>"
                method="post"
                class="wcip-form-wrap">
                <input
                    name="wcip_rest_nonce"
                    id="wcip_rest_nonce"
                    type="hidden"
                    value="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
                <?php wp_nonce_field('lkn_wcip_edit_invoice', 'nonce'); ?>
                <div class="wcip-invoice-data">
                    <!-- Invoice details -->
                    <h2 class="title">

                        <?php esc_attr_e('Invoice details', 'wc-invoice-payment'); ?>
                        <?php echo esc_html('#' . $invoiceId); ?>
                    </h2>
                    <div class="invoice-row-wrap">
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_payment_status_input"><?php esc_attr_e('Status', 'wc-invoice-payment'); ?></label>
                                <select
                                    name="lkn_wcip_payment_status"
                                    id="lkn_wcip_payment_status_input"
                                    class="regular-text"
                                    value="<?php echo esc_html('wc-' . $order->get_status()); ?>">
                                    <?php
                                    for ($i = 0; $i < count($statusWc); ++$i) {
                                        if (explode('-', $statusWc[$i]['status'])[1] === $orderStatus) {
                                            echo '<option value="' . esc_attr($statusWc[$i]['status']) . '" selected>' . esc_attr($statusWc[$i]['label']) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($statusWc[$i]['status']) . '">' . esc_attr($statusWc[$i]['label']) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_default_payment_method_input">
                                    <?php esc_attr_e('Default payment method', 'wc-invoice-payment'); ?>
                                    <div class="tooltip">
                                        <span>?</span>
                                        <span class="tooltiptext">
                                            <?php esc_attr_e('To automate charges, choose a compatible payment method (e.g., Cielo Pro Plugin). If multiple payments are selected, the charge will not be automatic.', 'wc-invoice-payment'); ?>
                                        </span>
                                    </div>
                                </label>
                                <select
                                    name="lkn_wcip_default_payment_method"
                                    id="lkn_wcip_default_payment_method_input"
                                    class="regular-text">
                                    <option
                                        value="multiplePayment"
                                        selected>
                                        <?php esc_attr_e('Multiple payment option', 'wc-invoice-payment'); ?>
                                    </option>
                                    <?php
                                    foreach ($enabled_gateways as $key => $gateway) {
                                        if ($order->get_payment_method() === $gateway->id) {
                                            echo '<option value="' . esc_attr($gateway->id) . '" selected>' . esc_attr($gateway->title) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($gateway->id) . '">' . esc_attr($gateway->title) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_currency_input"><?php esc_attr_e('Currency', 'wc-invoice-payment'); ?></label>
                                <select
                                    name="lkn_wcip_currency"
                                    id="lkn_wcip_currency_input"
                                    class="regular-text">
                                    <?php
                                    foreach ($currency_codes as $code) {
                                        $currency_name = $currencies[$code];
                                        $selected = ($order->get_currency() === $code) ? 'selected' : ''; // Verifica se a opção deve ser selecionada

                                        if ($order->get_currency() === $code) {
                                            echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_select_invoice_template">
                                    <?php esc_attr_e('Invoice PDF template', 'wc-invoice-payment'); ?>
                                </label>
                                <select
                                    name="lkn_wcip_select_invoice_template"
                                    id="lkn_wcip_select_invoice_template"
                                    class="regular-text"
                                    value="<?php echo esc_attr($invoice_template); ?>"
                                    required>
                                    <option value="global">
                                        <?php esc_attr_e('Default template', 'wc-invoice-payment'); ?>
                                    </option>
                                    <?php echo wp_kses($html_templates_list, array(
                                        'option' => array(
                                            'data-preview-url' => true,
                                            'value' => true,
                                            'selected' => true,
                                        ),
                                    )); ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_select_invoice_language">
                                    <?php esc_attr_e('Invoice PDF language', 'wc-invoice-payment'); ?>
                                    <div class="tooltip">
                                        <span>?</span>
                                        <span class="tooltiptext">
                                            <?php esc_attr_e('To add other languages, install the language in your WordPress.', 'wc-invoice-payment'); ?>
                                        </span>
                                    </div>
                                </label>
                                <select
                                    name="lkn_wcip_select_invoice_language"
                                    id="lkn_wcip_select_invoice_language"
                                    class="regular-text"
                                    required>
                                    <?php
                                    // Gera as opções do select
                                    foreach ($languages as $language) {
                                        $language_name = locale_get_display_name($language, 'en');
                                        if(!empty($language)){
                                            $selected = ($language === $orderLanguage) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($language) . '" ' . esc_attr($selected) . '>' . esc_html($language_name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <div style="position: relative;"><img id="lkn-wcip-preview-img" /></div>
                            </div>
                        </div>
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_name_input"><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                <input
                                    name="lkn_wcip_name"
                                    type="text"
                                    id="lkn_wcip_name_input"
                                    class="regular-text"
                                    required
                                    value="<?php echo esc_attr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>">
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_customer_input">
                                    <div>
                                        <?php esc_attr_e('Customer', 'wc-invoice-payment'); ?>
                                        <div class="tooltip">
                                            <span>?</span>
                                            <span class="tooltiptext">
                                                <?php esc_attr_e('Select a user to generate subscriptions. Guests cannot process automatic charges.', 'wc-invoice-payment'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a target="_blank" href="<?php echo esc_attr(admin_url('user-new.php')); ?>"><?php esc_attr_e('Create user', 'wc-invoice-payment'); ?></a>
                                </label>
                                <select class="wc-customer-search" id="lkn_wcip_customer_input" name="lkn_wcip_customer" data-placeholder="Visitante" data-allow_clear="true">
                                    <option value="<?php echo esc_attr($userId); ?>" selected="selected"><?php echo esc_html($userInfos); ?></option>
                                </select>
                            </div>
                            <div class="input-row-wrap" id="lknWcipEmailInput" <?php echo (!empty($userId)) ? 'style="display: none;"' : ''; ?>>
                                <label
                                    for="lkn_wcip_email_input"><?php esc_attr_e('Email', 'wc-invoice-payment'); ?></label>
                                <input
                                    name="lkn_wcip_email"
                                    type="email"
                                    id="lkn_wcip_email_input"
                                    class="regular-text"
                                    required
                                    value="<?php echo esc_html($order->get_billing_email()); ?>"
                                    <?php echo esc_attr(!empty($userId) ? '' : 'required'); ?>>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_country_input">
                                    <?php esc_attr_e('Country', 'wc-invoice-payment'); ?>
                                </label>
                                <select
                                    name="lkn_wcip_country"
                                    id="lkn_wcip_country_input"
                                    class="regular-text">
                                    <?php
                                    foreach ($countries as $code => $currency_name) {
                                        $selected = ($current_country === $code) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_attr($currency_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_extra_data"><?php esc_attr_e('Extra data', 'wc-invoice-payment'); ?></label>
                                <textarea
                                    name="lkn_wcip_extra_data"
                                    id="lkn_wcip_extra_data"
                                    class="regular-text"><?php echo esc_html($order->get_meta('wcip_extra_data')); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Form actions -->
                <div class="wcip-invoice-data wcip-postbox">
                    <span
                        class="text-bold"><?php esc_attr_e('Invoice actions', 'wc-invoice-payment'); ?></span>
                    <hr>
                    <div class="wcip-row">
                        <div class="input-row-wrap">
                            <select name="lkn_wcip_form_actions">
                                <option
                                    value="no_action"
                                    selected>
                                    <?php esc_attr_e('Select an action...', 'wc-invoice-payment'); ?>
                                </option>
                                <option value="send_email">
                                    <?php esc_attr_e('Send invoice to customer', 'wc-invoice-payment'); ?>
                                </option>
                            </select>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_exp_date_input"><?php esc_attr_e('Due date', 'wc-invoice-payment'); ?></label>
                                <input
                                    id="lkn_wcip_exp_date_input"
                                    type="date"
                                    name="lkn_wcip_exp_date"
                                    value="<?php echo esc_attr($order->get_meta('lkn_exp_date')); ?>"
                                    min="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                            </div>
                            <div class="input-column-wrap">
                                <a
                                    class="lkn_wcip_generate_pdf_btn"
                                    href="#"
                                    data-invoice-id="<?php echo esc_attr($invoiceId); ?>"><?php esc_attr_e('Download invoice', 'wc-invoice-payment'); ?></a>
                                &nbsp
                                <span class="dashicons dashicons-image-rotate"></span>
                            </div>
                        </div>
                        <?php
                        if ('pending' === $orderStatus) {
                        ?>
                            <div class="input-row-wrap">
                                <a
                                    href="<?php echo esc_url($checkoutUrl); ?>"
                                    target="_blank"><?php esc_attr_e('Invoice payment link', 'wc-invoice-payment'); ?></a>
                            </div>
                        <?php
                        } ?>
                    </div>
                    <div
                        id="lkn-wcip-share-modal"
                        style="display: none;">
                        <div id="lkn-wcip-share-modal-content">
                            <h3 id="lkn-wcip-share-title">
                                <?php esc_attr_e('Share with', 'wc-invoice-payment'); ?>
                            </h3>
                            <div id="lkn-wcip-share-buttons">
                                <a
                                    href="#"
                                    id="lkn-wcip-whatsapp-share"
                                    class="lkn-wcip-share-icon dashicons dashicons-whatsapp"
                                    onclick="lkn_wcip_open_popup('whatsapp', '<?php echo esc_url($checkoutUrl); ?>')"></a>
                                <a
                                    href="#"
                                    id="lkn-wcip-twitter-share"
                                    class="lkn-wcip-share-icon dashicons dashicons-twitter"
                                    onclick="lkn_wcip_open_popup('twitter', '<?php echo esc_url($checkoutUrl); ?>')"></a>
                                <a
                                    href="mailto:?subject=Link de fatura&body=<?php echo esc_url($checkoutUrl); ?>"
                                    id="lkn-wcip-email-share"
                                    class="lkn-wcip-share-icon dashicons dashicons-email-alt"
                                    target="_blank">
                                </a>
                            </div>
                            <h3 id="lkn-wcip-share-title">
                                <?php esc_attr_e('Or copy link', 'wc-invoice-payment'); ?>
                            </h3>
                            <div id="lkn-wcip-copy-link-div">
                                <input
                                    id="lkn-wcip-copy-input"
                                    type="text"
                                    value="<?php echo esc_url($checkoutUrl); ?>"
                                    readonly>
                                <span
                                    onclick="lkn_wcip_copy_link()"
                                    class="lkn-wcip-copy-button"><span class="dashicons dashicons-clipboard"></span>
                            </div>
                            <a
                                href="#"
                                id="lkn-wcip-close-modal-btn"
                                onclick="lkn_wcip_display_modal()">&times;</a>
                        </div>
                    </div>
                    <div class="action-btn">
                        <p class="submit">
                            <button
                                type="button"
                                class="button lkn_swcip_share_btn_form"
                                onclick="lkn_wcip_display_modal()"><?php esc_attr_e('Share payment link', 'wc-invoice-payment'); ?></button>
                        </p>
                        <p class="submit">
                            <button
                                type="button"
                                class="button lkn_wcip_delete_btn_form"
                                onclick="lkn_wcip_delete_invoice()"><?php esc_attr_e('Delete', 'wc-invoice-payment'); ?></button>
                        </p>
                        <?php submit_button(__('Update', 'wc-invoice-payment')); ?>
                    </div>
                </div>
                <!-- Subscription  -->
                <?php
                if ($subscription_id = $order->get_meta('lkn_subscription_id')) {
                ?>
                    <div
                        class="wcip-invoice-data wcip-postbox"
                        id="lknShowSubscription">
                        <span
                            class="text-bold"><?php esc_attr_e('Assinatura', 'wc-invoice-payment'); ?></span>
                        <span><?php echo esc_attr($order->get_meta('lkn_wcip_subscription_initial_limit')) ?></span>
                        <hr>
                        <div class="wcip-row">
                            <div
                                class="input-row-wrap"
                                id="lknShowSubscription">
                                <?php
                                //Lista as faturas geradas por essa assinatura
                                ?>
                                <p>
                                    <?php
                                    echo esc_attr(' | ');
                                    ?>
                                    <a>
                                        <?php
                                        echo esc_attr($subscription_id);
                                        ?>
                                    </a>
                                    <?php
                                    echo esc_attr(' | ');
                                    ?>
                                </p>
                                <?php
                                ?>
                            </div>
                        </div>
                    </div>
                <?php
                }
                ?>
                <!-- Invoice charges -->
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Price', 'wc-invoice-payment'); ?>
                    </h2>
                    <div
                        id="wcip-invoice-price-row"
                        class="invoice-column-wrap">
                        <?php
                        foreach ($items as $item_id => $item) {
                        ?>
                            <div
                                class="price-row-wrap price-row-<?php echo esc_attr($c); ?>">
                                <?php
                                if ('pending' === $orderStatus) {
                                ?>
                                    <div class="input-row-wrap">
                                        <label><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                        <input
                                            name="lkn_wcip_name_invoice_<?php echo esc_attr($c); ?>"
                                            type="text"
                                            id="lkn_wcip_name_invoice_<?php echo esc_attr($c); ?>"
                                            class="regular-text"
                                            required
                                            value="<?php echo esc_attr($item->get_name()); ?>">
                                    </div>
                                    <div class="input-row-wrap">
                                        <label><?php esc_attr_e('Amount', 'wc-invoice-payment'); ?></label>
                                        <input
                                            name="lkn_wcip_amount_invoice_<?php echo esc_attr($c); ?>"
                                            type="text"
                                            id="lkn_wcip_amount_invoice_<?php echo esc_attr($c); ?>"
                                            class="regular-text lkn_wcip_amount_input"
                                            oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');"
                                            required
                                            value="<?php echo esc_attr(number_format($item->get_total(), $decimalQtd, $decimalSeparator, $thousandSeparator)); ?>">
                                    </div>
                                <?php
                                } else {
                                ?>
                                    <div class="input-row-wrap">
                                        <label><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                        <input
                                            name="lkn_wcip_name_invoice_<?php echo esc_attr($c); ?>"
                                            type="text"
                                            id="lkn_wcip_name_invoice_<?php echo esc_attr($c); ?>"
                                            class="regular-text"
                                            required
                                            readonly
                                            value="<?php echo esc_attr($item->get_name()); ?>">
                                    </div>
                                    <div class="input-row-wrap">
                                        <label><?php esc_attr_e('Amount', 'wc-invoice-payment'); ?></label>
                                        <input
                                            name="lkn_wcip_amount_invoice_<?php echo esc_attr($c); ?>"
                                            type="tel"
                                            id="lkn_wcip_amount_invoice_<?php echo esc_attr($c); ?>"
                                            class="regular-text lkn_wcip_amount_input"
                                            oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');"
                                            required
                                            readonly
                                            value="<?php echo esc_attr(number_format($item->get_total(), $decimalQtd, $decimalSeparator, $thousandSeparator)); ?>">
                                    </div>
                                <?php
                                }

                                if ('pending' === $orderStatus) {
                                ?>
                                    <div class="input-row-wrap">
                                        <button
                                            type="button"
                                            class="btn btn-delete"
                                            onclick="lkn_wcip_remove_amount_row(<?php echo esc_attr($c); ?>)"><span class="dashicons dashicons-trash"></span></button>
                                    </div>
                                <?php
                                } ?>
                            </div>
                        <?php
                            ++$c;
                        } ?>
                    </div>
                    <hr>
                    <?php
                    if ('pending' === $orderStatus) {
                    ?>
                        <div class="invoice-row-wrap">
                            <button
                                type="button"
                                class="btn btn-add-line"
                                onclick="lkn_wcip_add_amount_row()"><?php esc_attr_e('Add line', 'wc-invoice-payment'); ?></button>
                        </div>
                    <?php
                    } ?>
                </div>
                <?php
                if ($parentOrder || $order->get_meta('_wc_lkn_is_partial_main_order') == 'yes') {
                ?>
                    <div class="wcip-invoice-data wcip-postbox">
                        <h2 class="title">
                            <?php esc_attr_e('Pagamento Parcial', 'wc-invoice-payment'); ?>
                        </h2>
                        <div class="input-column-wrap">
                            <?php
                            if ($parentOrder) {
                            ?>
                            <h4>
                                Pagamento parcial referente ao pedido
                                <a href="<?php echo esc_attr(admin_url("admin.php?page=wc-orders&action=edit&id={$parentOrderId}")); ?>">#<?php echo esc_attr($parentOrderId); ?></a>
                            </h4>
                            <?php
                            }
                            ?>

<?php
                            if ($order->get_meta('_wc_lkn_is_partial_main_order') == 'yes') {
                            ?>
                            <h4>
                                Fatura referente ao pedido
                                <a href="<?php echo esc_attr(admin_url("admin.php?page=wc-orders&action=edit&id={$invoiceId}")); ?>">#<?php echo esc_attr($invoiceId); ?></a>
                            </h4>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                <?php
                }
                ?>
                <div style="width: 100%;"></div>
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Footer notes', 'wc-invoice-payment'); ?>
                    </h2>
                    <div
                        id="wcip-invoice-price-row"
                        class="invoice-column-wrap">
                        <div class="input-row-wrap">
                            <label><?php esc_attr_e('Details in HTML', 'wc-invoice-payment'); ?></label>
                            <textarea
                                name="lkn-wc-invoice-payment-footer-notes"
                                id="lkn-wc-invoice-payment-footer-notes"><?php echo esc_html($order->get_meta('wcip_footer_notes')); ?></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', () => {
                startTinyMce('lkn-wc-invoice-payment-footer-notes', 'submit')
            })
        </script>
    <?php

        if ($order->get_meta('_wc_lkn_is_partial_main_order') == 'yes') {
            wc_get_template(
                '../../Includes/templates/partialTablesAdmin.php',
                array(
                    'orderStatus' => $order->get_status(),
                    'totalPeding' => number_format(floatval($order->get_meta('_wc_lkn_total_peding')) ?: 0.0, 2, ',', '.'),
                    'totalConfirmed' => number_format(floatval($order->get_meta('_wc_lkn_total_confirmed')) ?: 0.0, 2, ',', '.'),
                    'total' => number_format(floatval($order->get_total()) ?: 0.0, 2, ',', '.'),
                    'partialsOrdersIds' => $order->get_meta('_wc_lkn_partials_id'),
                    'invoicePage' => 'true',
                ),
                'woocommerce/pix/',
                plugin_dir_path( __FILE__ ) . 'templates/'
            );
    
            wp_enqueue_style('wcInvoicePaymentPartialStyle', WC_PAYMENT_INVOICE_ROOT_URL . 'Public/css/wc-invoice-payment-partial-table.css', array(), WC_PAYMENT_INVOICE_VERSION, 'all');
        }
    }

    public function is_invoice_id_scheduled($invoice_id)
    {
        // Recupere todos os eventos agendados do WP Cron
        $scheduled_events = _get_cron_array();

        // Itere sobre todos os eventos agendados
        foreach ($scheduled_events as $timestamp => $cron_events) {
            foreach ($cron_events as $hook => $events) {
                foreach ($events as $event) {
                    // Verifique se o evento está associado ao seu gancho (hook)
                    if ('generate_invoice_event' === $hook) {
                        // Verifique se os argumentos do evento contêm o invoiceId
                        $event_args = $event['args'];
                        if (is_array($event_args) && in_array($invoice_id, $event_args)) {
                            // O invoiceId está agendado, então retorne verdadeiro
                            return true;
                        }
                    }
                }
            }
        }
        // Se chegou até aqui, o invoiceId não está agendado
        return false;
    }

    /**
     * Render html page for subscription edit.
     */
    public function render_edit_subscription_page(): void
    {
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('woocommerce_admin_styles');

        wp_enqueue_script($this->plugin_name . '-edit', plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-invoice-edit.js', array(), $this->version, 'all');
        wp_enqueue_style($this->plugin_name . '-edit', plugin_dir_url(__FILE__) . 'css/wc-invoice-payment-invoice-edit.css', array(), $this->version, 'all');

        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        if (! empty($_POST) && ! isset($_POST['wcip_rest_nonce']) && ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcip_rest_nonce'])), 'wp_rest')) {
            return;
        }

        wp_enqueue_editor();
        wp_create_nonce('wp_rest');

        $invoiceId = sanitize_text_field(wp_unslash($_GET['invoice']));

        $decimalSeparator = wc_get_price_decimal_separator();
        $thousandSeparator = wc_get_price_thousand_separator();
        $decimalQtd = wc_get_price_decimals();

        // Get all translated WooCommerce order status
        $statusWc = array();
        $statusWc[] = array('status' => 'wc-pending', 'label' => _x('Pending payment', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-processing', 'label' => _x('Processing', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-on-hold', 'label' => _x('On hold', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-completed', 'label' => _x('Completed', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-cancelled', 'label' => _x('Cancelled', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-refunded', 'label' => _x('Refunded', 'Order status', 'wc-invoice-payment'));
        $statusWc[] = array('status' => 'wc-failed', 'label' => _x('Failed', 'Order status', 'wc-invoice-payment'));

        $c = 0;
        $order = wc_get_order($invoiceId);

        if ($order->get_user_id()) {
            $userId = absint($order->get_user_id());
            $user = get_userdata($userId);
            $userInfos = sprintf(
                '%s (#%d – %s)',
                $user->display_name, // Nome completo do usuário
                $userId, // ID do usuário
                $user->user_email // Email do usuário
            );
        } else {
            $userId = '';
            $userInfos = '';
        }

        $args = array(
            'meta_key' => 'lkn_subscription_id',
            'meta_value' => $invoiceId,
            'limit' => -1
        );

        $orders = wc_get_orders($args);

        $items = $order->get_items();
        $checkoutUrl = $order->get_checkout_payment_url();
        $orderStatus = $order->get_status();

        $invoice_template = $order->get_meta('wcip_select_invoice_template_id') ?? get_option('lkn_wcip_global_pdf_template_id', 'global');

        $templates_list = $this->handler_invoice_templates->get_templates_list();

        $html_templates_list = implode(array_map(function ($template) use ($invoice_template): string {
            $template_id = esc_attr($template['id']);
            $friendly_template_name = esc_html($template['friendly_name']);
            $preview_url = esc_url(WC_PAYMENT_INVOICE_ROOT_URL . "Includes/templates/$template_id/preview.webp");

            $selected = $invoice_template === $template_id ? 'selected' : '';

            return "<option $selected data-preview-url='$preview_url' value='$template_id'>$friendly_template_name</option>";
        }, $templates_list));

        $currencies = get_woocommerce_currencies();
        $currency_codes = array_keys($currencies);
        $limit = '/' . $order->get_meta('lkn_wcip_subscription_limit');
        if ($order->get_meta('lkn_wcip_subscription_limit') == 0) {
            $limit = '';
        }
        sort($currency_codes);

        $countries = WC()->countries->get_countries(); // Pega os países
        $currency_codes = array_keys($currencies); // Pega os códigos de moeda
        $current_country = $order->get_billing_country();
        // Coloca o Brasil no início, se estiver no array
        if (isset($countries['BR'])) {
            $brazil = $countries['BR'];
            unset($countries['BR']);
            $countries = array('BR' => $brazil) + $countries; // Coloca 'BR' no início
        }

        $gateways = WC()->payment_gateways->payment_gateways();
        $enabled_gateways = array();

        // Get all WooCommerce enabled gateways
        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ('yes' == $gateway->enabled) {
                    $enabled_gateways[] = $gateway;
                }
            }
        }

        $languages = get_available_languages();
        // Adiciona manualmente o inglês à lista de idiomas
        array_unshift($languages, 'en_US');
        $orderLanguage = $order->get_meta('wcip_select_invoice_language');
        // Remove o idioma atual da lista
        if (false !== ($key = array_search($orderLanguage, $languages, true))) {
            unset($languages[$key]);
        }
        // Ordena os idiomas restantes em ordem alfabética
        sort($languages);
        // Adiciona o idioma atual no início da lista
        array_unshift($languages, $orderLanguage);
    ?>
        <div class="wrap">
            <h1><?php esc_attr_e('Edit subscription', 'wc-invoice-payment'); ?>
            </h1>
            <?php settings_errors(); ?>
            <form
                action="<?php menu_page_url('edit-invoice&invoice=' . $invoiceId); ?>"
                method="post"
                class="wcip-form-wrap">
                <input
                    id="wcip_rest_nonce"
                    name="wcip_rest_nonce"
                    type="hidden"
                    value="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
                <?php wp_nonce_field('lkn_wcip_edit_invoice', 'nonce'); ?>
                <div class="wcip-invoice-data">
                    <!-- Invoice details -->
                    <h2 class="title">
                        <?php esc_attr_e('Subscription details', 'wc-invoice-payment'); ?>
                        <?php echo esc_html('#' . $invoiceId); ?>
                    </h2>
                    <div class="invoice-row-wrap">
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_payment_status_input"><?php esc_attr_e('Status', 'wc-invoice-payment'); ?></label>
                                <select
                                    name="lkn_wcip_payment_status"
                                    id="lkn_wcip_payment_status_input"
                                    class="regular-text"
                                    value="<?php echo esc_html('wc-' . $order->get_status()); ?>">
                                    <?php
                                    for ($i = 0; $i < count($statusWc); ++$i) {
                                        if (explode('-', $statusWc[$i]['status'])[1] === $orderStatus) {
                                            echo '<option value="' . esc_attr($statusWc[$i]['status']) . '" selected>' . esc_attr($statusWc[$i]['label']) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($statusWc[$i]['status']) . '">' . esc_attr($statusWc[$i]['label']) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_default_payment_method_input">
                                    <?php esc_attr_e('Default payment method', 'wc-invoice-payment'); ?>
                                    <div class="tooltip">
                                        <span>?</span>
                                        <span class="tooltiptext">
                                            <?php esc_attr_e('To automate charges, choose a compatible payment method (e.g., Cielo Pro Plugin). If multiple payments are selected, the charge will not be automatic.', 'wc-invoice-payment'); ?>
                                        </span>
                                    </div>
                                </label>
                                <select
                                    name="lkn_wcip_default_payment_method"
                                    id="lkn_wcip_default_payment_method_input"
                                    class="regular-text">
                                    <option
                                        value="multiplePayment"
                                        selected>
                                        <?php esc_attr_e('Multiple payment option', 'wc-invoice-payment'); ?>
                                    </option>
                                    <?php
                                    foreach ($enabled_gateways as $key => $gateway) {
                                        if ($order->get_payment_method() === $gateway->id) {
                                            echo '<option value="' . esc_attr($gateway->id) . '" selected>' . esc_attr($gateway->title) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($gateway->id) . '">' . esc_attr($gateway->title) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_currency_input"><?php esc_attr_e('Currency', 'wc-invoice-payment'); ?></label>
                                <select
                                    name="lkn_wcip_currency"
                                    id="lkn_wcip_currency_input"
                                    class="regular-text">
                                    <?php
                                    foreach ($currency_codes as $code) {
                                        $currency_name = $currencies[$code];
                                        $selected = ($order->get_currency() === $code) ? 'selected' : ''; // Verifica se a opção deve ser selecionada

                                        if ($order->get_currency() === $code) {
                                            echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_select_invoice_template">
                                    <?php esc_attr_e('Invoice PDF template', 'wc-invoice-payment'); ?>
                                </label>
                                <select
                                    name="lkn_wcip_select_invoice_template"
                                    id="lkn_wcip_select_invoice_template"
                                    class="regular-text"
                                    value="<?php echo esc_attr($invoice_template); ?>"
                                    required>
                                    <option value="global">
                                        <?php esc_attr_e('Default template', 'wc-invoice-payment'); ?>
                                    </option>
                                    <?php echo wp_kses($html_templates_list, array(
                                        'option' => array(
                                            'data-preview-url' => true,
                                            'value' => true,
                                            'selected' => true,
                                        ),
                                    )); ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_select_invoice_language">
                                    <?php esc_attr_e('Invoice PDF language', 'wc-invoice-payment'); ?>
                                    <div class="tooltip">
                                        <span>?</span>
                                        <span class="tooltiptext">
                                            <?php esc_attr_e('To add other languages, install the language in your WordPress.', 'wc-invoice-payment'); ?>
                                        </span>
                                    </div>
                                </label>
                                <select
                                    name="lkn_wcip_select_invoice_language"
                                    id="lkn_wcip_select_invoice_language"
                                    class="regular-text"
                                    required>
                                    <?php
                                    // Gera as opções do select
                                    foreach ($languages as $language) {
                                        $language_name = locale_get_display_name($language, 'en');
                                        $selected = ($language === $orderLanguage) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($language) . '" ' . esc_attr($selected) . '>' . esc_html($language_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <div style="position: relative;"><img id="lkn-wcip-preview-img" /></div>
                            </div>
                        </div>
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_name_input"><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                <input
                                    name="lkn_wcip_name"
                                    type="text"
                                    id="lkn_wcip_name_input"
                                    class="regular-text"
                                    required
                                    value="<?php echo esc_attr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>">
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_customer_input">
                                    <div>
                                        <?php esc_attr_e('Customer', 'wc-invoice-payment'); ?>
                                        <div class="tooltip">
                                            <span>?</span>
                                            <span class="tooltiptext">
                                                <?php esc_attr_e('Select a user to generate subscriptions. Guests cannot process automatic charges.', 'wc-invoice-payment'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a target="_blank" href="<?php echo esc_attr(admin_url('user-new.php')); ?>"><?php esc_attr_e('Create user', 'wc-invoice-payment'); ?></a>
                                </label>
                                <select class="wc-customer-search" id="lkn_wcip_customer_input" name="lkn_wcip_customer" data-placeholder="Visitante" data-allow_clear="true">
                                    <option value="<?php echo esc_attr($userId); ?>" selected="selected"><?php echo esc_html($userInfos); ?></option>
                                </select>
                            </div>
                            <div class="input-row-wrap" id="lknWcipEmailInput" <?php echo (!empty($userId)) ? 'style="display: none;"' : ''; ?>>
                                <label
                                    for="lkn_wcip_email_input"><?php esc_attr_e('Email', 'wc-invoice-payment'); ?></label>
                                <input
                                    name="lkn_wcip_email"
                                    type="email"
                                    id="lkn_wcip_email_input"
                                    class="regular-text"
                                    required
                                    value="<?php echo esc_html($order->get_billing_email()); ?>"
                                    <?php echo esc_attr(!empty($userId) ? '' : 'required'); ?>>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_country_input">
                                    <?php esc_attr_e('Country', 'wc-invoice-payment'); ?>
                                </label>
                                <select
                                    name="lkn_wcip_country"
                                    id="lkn_wcip_country_input"
                                    class="regular-text">
                                    <?php
                                    foreach ($countries as $code => $currency_name) {
                                        $selected = ($current_country === $code) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_attr($currency_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_extra_data"><?php esc_attr_e('Extra data', 'wc-invoice-payment'); ?></label>
                                <textarea
                                    name="lkn_wcip_extra_data"
                                    id="lkn_wcip_extra_data"
                                    class="regular-text"><?php echo esc_html($order->get_meta('wcip_extra_data')); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Form actions -->
                <div class="wcip-invoice-data wcip-postbox">
                    <span
                        class="text-bold"><?php esc_attr_e('Subscription actions', 'wc-invoice-payment'); ?></span>
                    <hr>
                    <div class="wcip-row">
                        <div class="input-row-wrap">

                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_exp_date_input"><?php esc_attr_e('Due date', 'wc-invoice-payment'); ?></label>
                                <input
                                    id="lkn_wcip_exp_date_input"
                                    type="date"
                                    name="lkn_wcip_exp_date"
                                    value="<?php echo esc_attr($order->get_meta('lkn_exp_date')); ?>"
                                    min="<?php echo esc_attr(gmdate('Y-m-d')); ?>"
                                    readonly>
                            </div>
                            <div class="input-column-wrap">
                                <a
                                    class="lkn_wcip_generate_pdf_btn"
                                    href="#"
                                    data-invoice-id="<?php echo esc_attr($invoiceId); ?>"><?php esc_attr_e('Download invoice', 'wc-invoice-payment'); ?></a>
                                &nbsp
                                <span class="dashicons dashicons-image-rotate"></span>
                            </div>
                            <div class="input-row-wrap">
                                <?php
                                // Verifique se o invoiceId está agendado

                                if ($this->is_invoice_id_scheduled($invoiceId)) {
                                    // O invoiceId está agendado, exiba o link para cancelar a assinatura
                                ?>
                                    <a
                                        class="lkn_wcip_cancel_subscription_btn"
                                        href="#"
                                        onclick="lkn_wcip_cancel_subscription()"
                                        data-invoice-id="<?php echo esc_attr($invoiceId); ?>">
                                        <?php esc_attr_e('Cancel subscription', 'wc-invoice-payment'); ?>
                                    </a>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div
                        id="lkn-wcip-share-modal"
                        style="display: none;">
                        <div id="lkn-wcip-share-modal-content">
                            <h3 id="lkn-wcip-share-title">
                                <?php esc_attr_e('Share with', 'wc-invoice-payment'); ?>
                            </h3>
                            <div id="lkn-wcip-share-buttons">
                                <a
                                    href="#"
                                    id="lkn-wcip-whatsapp-share"
                                    class="lkn-wcip-share-icon dashicons dashicons-whatsapp"
                                    onclick="lkn_wcip_open_popup('whatsapp', '<?php echo esc_url($checkoutUrl); ?>')"></a>
                                <a
                                    href="#"
                                    id="lkn-wcip-twitter-share"
                                    class="lkn-wcip-share-icon dashicons dashicons-twitter"
                                    onclick="lkn_wcip_open_popup('twitter', '<?php echo esc_url($checkoutUrl); ?>')"></a>
                                <a
                                    href="mailto:?subject=Link de fatura&body=<?php echo esc_url($checkoutUrl); ?>"
                                    id="lkn-wcip-email-share"
                                    class="lkn-wcip-share-icon dashicons dashicons-email-alt"
                                    target="_blank">
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="action-btn">
                        <p class="submit">
                            <button
                                type="button"
                                class="button lkn_wcip_delete_btn_form"
                                onclick="lkn_wcip_delete_invoice()"><?php esc_attr_e('Delete', 'wc-invoice-payment'); ?></button>
                        </p>
                        <?php submit_button(__('Update', 'wc-invoice-payment')); ?>
                    </div>
                </div>
                <!-- Generated invoices  -->
                <div
                    class="wcip-invoice-data wcip-postbox"
                    id="lknListGeneratedInvoicesPostBox">
                    <span
                        class="text-bold"><?php esc_attr_e('Generated invoices', 'wc-invoice-payment'); ?></span>
                    <span><?php echo esc_attr($order->get_meta('lkn_wcip_subscription_initial_limit')) ?><?php echo esc_attr($limit) ?></span>
                    <hr>
                    <div class="wcip-row">
                        <div
                            class="input-row-wrap"
                            id="lknListGeneratedInvoices">
                            <?php
                            $index = 1;
                            //Lista as faturas geradas por essa assinatura
                            foreach ($orders as $order) {
                            ?>
                                <p>
                                    <?php
                                    if (0 != $i) {
                                        echo ' | ';
                                    }
                                    ?>
                                    <a target="_blank">
                                        <?php
                                        echo esc_attr($order->get_id());
                                        ?>
                                    </a>
                                    <?php
                                    if (count($orders) == $index) {
                                        echo ' | ';
                                    }
                                    $index++;
                                    ?>
                                </p>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <!-- Invoice charges -->
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Price', 'wc-invoice-payment'); ?>
                    </h2>
                    <div
                        id="wcip-invoice-price-row"
                        class="invoice-column-wrap">
                        <?php
                        foreach ($items as $item_id => $item) {
                        ?>
                            <div
                                class="price-row-wrap price-row-<?php echo esc_attr($c); ?>">
                                <?php
                                if ('pending' === $orderStatus) {
                                ?>
                                    <div class="input-row-wrap">
                                        <label><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                        <input
                                            name="lkn_wcip_name_invoice_<?php echo esc_attr($c); ?>"
                                            type="text"
                                            id="lkn_wcip_name_invoice_<?php echo esc_attr($c); ?>"
                                            class="regular-text"
                                            required
                                            value="<?php echo esc_attr($item->get_name()); ?>"
                                            readonly>
                                    </div>
                                    <div class="input-row-wrap">
                                        <label><?php esc_attr_e('Amount', 'wc-invoice-payment'); ?></label>
                                        <input
                                            name="lkn_wcip_amount_invoice_<?php echo esc_attr($c); ?>"
                                            type="tel"
                                            id="lkn_wcip_amount_invoice_<?php echo esc_attr($c); ?>"
                                            class="regular-text lkn_wcip_amount_input"
                                            oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');"
                                            required
                                            value="<?php echo esc_attr(number_format($item->get_total(), $decimalQtd, $decimalSeparator, $thousandSeparator)); ?>"
                                            readonly>
                                    </div>
                                <?php
                                } else {
                                ?>
                                    <div class="input-row-wrap">
                                        <label><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                        <input
                                            name="lkn_wcip_name_invoice_<?php echo esc_attr($c); ?>"
                                            type="text"
                                            id="lkn_wcip_name_invoice_<?php echo esc_attr($c); ?>"
                                            class="regular-text"
                                            required
                                            value="<?php echo esc_attr($item->get_name()); ?>">
                                    </div>
                                    <div class="input-row-wrap">
                                        <label><?php esc_attr_e('Amount', 'wc-invoice-payment'); ?></label>
                                        <input
                                            name="lkn_wcip_amount_invoice_<?php echo esc_attr($c); ?>"
                                            type="text"
                                            id="lkn_wcip_amount_invoice_<?php echo esc_attr($c); ?>"
                                            class="regular-text lkn_wcip_amount_input"
                                            oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');"
                                            required
                                            value="<?php echo esc_attr(number_format($item->get_total(), $decimalQtd, $decimalSeparator, $thousandSeparator)); ?>">
                                    </div>
                                <?php
                                }

                                if ('pending' === $orderStatus) {
                                ?>
                                    <div class="input-row-wrap">
                                        <button
                                            type="button"
                                            class="btn btn-delete"
                                            onclick="lkn_wcip_remove_amount_row(<?php echo esc_attr($c); ?>)"><span class="dashicons dashicons-trash"></span></button>
                                    </div>
                                <?php
                                } ?>
                            </div>
                        <?php
                            ++$c;
                        } ?>
                    </div>
                    <hr>
                </div>
                <div style="width: 100%;"></div>
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Footer notes', 'wc-invoice-payment'); ?>
                    </h2>
                    <div
                        id="wcip-invoice-price-row"
                        class="invoice-column-wrap">
                        <div class="input-row-wrap">
                            <label><?php esc_attr_e('Details in HTML', 'wc-invoice-payment'); ?></label>
                            <textarea
                                name="lkn-wc-invoice-payment-footer-notes"
                                id="lkn-wc-invoice-payment-footer-notes"><?php echo esc_html($order->get_meta('wcip_footer_notes')); ?></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', () => {
                startTinyMce('lkn-wc-invoice-payment-footer-notes', 'submit')
            })
        </script>
    <?php
    }

    /**
     * Render html page for invoice listing.
     */
    public function render_invoice_list_page(): void
    {
        $validate_nonce = wp_create_nonce('validate_nonce');
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_GET['message'])) {
            // Decodifica a mensagem recebida na URL
            $decoded_message = urldecode(wp_unslash($_GET['message']));

            echo '<div class="lkn_wcip_notice_positive">' . esc_html($decoded_message) . '</div>';
        }
    ?>
        <form
            id="invoices-filter"
            method="POST">
            <input
                id="wcip_rest_nonce"
                type="hidden"
                value="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">

            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div>
                    <?php
                    $object = new LknWcipListTable();
                    $object->prepare_items($validate_nonce);
                    $object->display();
                    ?>
                </div>
            </div>
        </form>
    <?php
    }

    /**
     * Render html page for subscription listing.
     */
    public function render_subscription_list_page(): void
    {
        $validate_nonce = wp_create_nonce('validate_nonce');
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_GET['message'])) {
            // Decodifica a mensagem recebida na URL
            $decoded_message = urldecode($_GET['message']);

            echo '<div class="lkn_wcip_notice_positive">' . esc_html($decoded_message) . '</div>';
        }
    ?>
        <form
            id="invoices-filter"
            method="POST">
            <input
                id="wcip_rest_nonce"
                type="hidden"
                value="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">

            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div>
                    <?php
                    $object = new LknWcipListTable();
                    $object->prepare_items($validate_nonce, true);
                    $object->display();
                    ?>
                </div>
            </div>
        </form>
    <?php
    }

    /**
     * Adds new invoice submenu page and edit invoice submenu page.
     */
    public function add_new_invoice_submenu_section(): void
    {
        $hookname = add_submenu_page(
            'wc-invoice-payment',
            __('Add invoice', 'wc-invoice-payment'),
            __('Add invoice', 'wc-invoice-payment'),
            'manage_woocommerce',
            'new-invoice',
            array($this, 'new_invoice_form'),
            2
        );

        add_action('load-' . $hookname, array($this, 'add_invoice_form_submit_handle'));

        $editHookname = add_submenu_page(
            null,
            __('Edit invoice', 'wc-invoice-payment'),
            __('Edit invoice', 'wc-invoice-payment'),
            'manage_woocommerce',
            'edit-invoice',
            array($this, 'render_edit_invoice_page'),
            1
        );

        add_action('load-' . $editHookname, array($this, 'edit_invoice_form_submit_handle'));

        $editHookname = add_submenu_page(
            null,
            __('Edit subscription', 'wc-invoice-payment'),
            __('Edit subscription', 'wc-invoice-payment'),
            'manage_woocommerce',
            'edit-subscription',
            array($this, 'render_edit_subscription_page'),
            1
        );

        add_action('load-' . $editHookname, array($this, 'edit_subscription_form_submit_handle'));
    }

    /**
     * Generates new form for invoice creation.
     */
    public function new_invoice_form(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        if (isset($_GET['invoiceChecked'])) {
            $invoiceChecked = 'checked';
        } else {
            $invoiceChecked = '';
        }

        wp_enqueue_editor();
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('woocommerce_admin_styles');

        $currencies = get_woocommerce_currencies();
        $currency_codes = array_keys($currencies);
        sort($currency_codes);

        $countries = WC()->countries->get_countries(); // Pega os países
        $currency_codes = array_keys($currencies); // Pega os códigos de moeda

        // Coloca o Brasil no início, se estiver no array
        if (isset($countries['BR'])) {
            $brazil = $countries['BR'];
            unset($countries['BR']);
            $countries = array('BR' => $brazil) + $countries; // Coloca 'BR' no início
        }

        $active_currency = get_woocommerce_currency();

        $gateways = WC()->payment_gateways->payment_gateways();
        $enabled_gateways = array();

        $templates_list = $this->handler_invoice_templates->get_templates_list();

        $html_templates_list = implode(array_map(function ($template): string {
            $template_id = esc_attr($template['id']);
            $friendly_template_name = esc_html($template['friendly_name']);
            $preview_url = esc_url(WC_PAYMENT_INVOICE_ROOT_URL . "Includes/templates/$template_id/preview.webp");

            return "<option data-preview-url='$preview_url' value='$template_id'>$friendly_template_name</option>";
        }, $templates_list));

        $default_footer = get_option('lkn_wcip_default_footer');
        // Get all WooCommerce enabled gateways
        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ('yes' == $gateway->enabled) {
                    $enabled_gateways[] = $gateway;
                }
            }
        }

        $languages = get_available_languages();
        // Adiciona manualmente o inglês à lista de idiomas
        array_unshift($languages, 'en_US');
        $orderLanguage = get_locale();
        // Remove o idioma atual da lista
        if (false !== ($key = array_search($orderLanguage, $languages, true))) {
            unset($languages[$key]);
        }
        // Ordena os idiomas restantes em ordem alfabética
        sort($languages);
        // Adiciona o idioma atual no início da lista
        array_unshift($languages, $orderLanguage);
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors(); ?>
            <form
                action="<?php menu_page_url('new-invoice'); ?>"
                method="post"
                class="wcip-form-wrap">
                <?php wp_nonce_field('lkn_wcip_add_invoice', 'nonce'); ?>
                <div class="wcip-invoice-data">
                    <div id="wcPaymentInvoiceTitles">
                        <h2 class="title">
                            <?php esc_attr_e('Invoice details', 'wc-invoice-payment'); ?>
                        </h2>
                        <h2 class="title">
                            <?php esc_attr_e('Payer Data', 'wc-invoice-payment'); ?>
                        </h2>
                    </div>
                    <div class="invoice-row-wrap">
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_payment_status_input"><?php esc_attr_e('Status', 'wc-invoice-payment'); ?></label>
                                <select
                                    name="lkn_wcip_payment_status"
                                    id="lkn_wcip_payment_status_input"
                                    class="regular-text">
                                    <option value="wc-pending">
                                        <?php echo esc_html(_x('Pending payment', 'Order status', 'wc-invoice-payment')); ?>
                                    </option>
                                    <option value="wc-processing">
                                        <?php echo esc_html(_x('Processing', 'Order status', 'wc-invoice-payment')); ?>
                                    </option>
                                    <option value="wc-on-hold">
                                        <?php echo esc_html(_x('On hold', 'Order status', 'wc-invoice-payment')); ?>
                                    </option>
                                    <option value="wc-completed">
                                        <?php echo esc_html(_x('Completed', 'Order status', 'wc-invoice-payment')); ?>
                                    </option>
                                    <option value="wc-cancelled">
                                        <?php echo esc_html(_x('Cancelled', 'Order status', 'wc-invoice-payment')); ?>
                                    </option>
                                    <option value="wc-refunded">
                                        <?php echo esc_html(_x('Refunded', 'Order status', 'wc-invoice-payment')); ?>
                                    </option>
                                    <option value="wc-failed">
                                        <?php echo esc_html(_x('Failed', 'Order status', 'wc-invoice-payment')); ?>
                                    </option>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_default_payment_method_input">
                                    <?php esc_attr_e('Default payment method', 'wc-invoice-payment'); ?>
                                    <div class="tooltip">
                                        <span>?</span>
                                        <span class="tooltiptext">
                                            <?php esc_attr_e('To automate charges, choose a compatible payment method (e.g., Cielo Pro Plugin). If multiple payments are selected, the charge will not be automatic.', 'wc-invoice-payment'); ?>
                                        </span>
                                    </div>
                                </label>
                                <select
                                    name="lkn_wcip_default_payment_method"
                                    id="lkn_wcip_default_payment_method_input"
                                    class="regular-text">
                                    <option
                                        value="multiplePayment"
                                        selected>
                                        <?php esc_attr_e('Multiple payment option', 'wc-invoice-payment'); ?>
                                    </option>
                                    <?php
                                    foreach ($enabled_gateways as $key => $gateway) {
                                        echo '<option value="' . esc_attr($gateway->id) . '">' . esc_html($gateway->title) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_currency_input"><?php esc_attr_e('Currency', 'wc-invoice-payment'); ?></label>
                                <select
                                    name="lkn_wcip_currency"
                                    id="lkn_wcip_currency_input"
                                    class="regular-text">
                                    <?php
                                    foreach ($currency_codes as $code) {
                                        $currency_name = $currencies[$code];
                                        if ($active_currency === $code) {
                                            echo '<option value="' . esc_attr($code) . '" ' . 'selected' . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($code) . '">' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_select_invoice_template">
                                    <?php esc_attr_e('Invoice PDF template', 'wc-invoice-payment'); ?>
                                </label>
                                <select
                                    name="lkn_wcip_select_invoice_template"
                                    id="lkn_wcip_select_invoice_template"
                                    class="regular-text"
                                    required>
                                    <option value="global">
                                        <?php esc_attr_e('Default template', 'wc-invoice-payment'); ?>
                                    </option>
                                    <?php echo wp_kses($html_templates_list, array(
                                        'option' => array(
                                            'data-preview-url' => true,
                                            'value' => true,
                                            'selected' => true,
                                        ),
                                    )); ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_select_invoice_language">
                                    <?php esc_attr_e('Invoice PDF language', 'wc-invoice-payment'); ?>
                                    <div class="tooltip">
                                        <span>?</span>
                                        <span class="tooltiptext">
                                            <?php esc_attr_e('To add other languages, install the language in your WordPress.', 'wc-invoice-payment'); ?>
                                        </span>
                                    </div>
                                </label>
                                <select
                                    name="lkn_wcip_select_invoice_language"
                                    id="lkn_wcip_select_invoice_language"
                                    class="regular-text"
                                    required>
                                    <?php
                                    // Gera as opções do select
                                    foreach ($languages as $language) {
                                        $language_name = locale_get_display_name($language, 'en');
                                        $selected = ($language === $orderLanguage) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($language) . '" ' . esc_attr($selected) . '>' . esc_html($language_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <div style="position: relative;"><img id="lkn-wcip-preview-img" /></div>
                            </div>
                        </div>
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_name_input"><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                <input
                                    name="lkn_wcip_name"
                                    type="text"
                                    id="lkn_wcip_name_input"
                                    class="regular-text"
                                    required>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_customer_input">
                                    <div>
                                        <?php esc_attr_e('Customer', 'wc-invoice-payment'); ?>
                                        <div class="tooltip">
                                            <span>?</span>
                                            <span class="tooltiptext">
                                                <?php esc_attr_e('Select a user to generate subscriptions. Guests cannot process automatic charges.', 'wc-invoice-payment'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a target="_blank" href="<?php echo esc_attr(admin_url('user-new.php')); ?>"><?php esc_attr_e('Create user', 'wc-invoice-payment'); ?></a>
                                </label>
                                <select class="wc-customer-search" id="lkn_wcip_customer_input" name="lkn_wcip_customer" data-placeholder="Visitante" data-allow_clear="true">
                                </select>
                            </div>
                            <div class="input-row-wrap" id="lknWcipEmailInput">
                                <label
                                    for="lkn_wcip_email_input"><?php esc_attr_e('Email', 'wc-invoice-payment'); ?></label>
                                <input
                                    name="lkn_wcip_email"
                                    type="email"
                                    id="lkn_wcip_email_input"
                                    class="regular-text"
                                    required>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_country_input">
                                    <?php esc_attr_e('Country', 'wc-invoice-payment'); ?>
                                </label>
                                <select
                                    name="lkn_wcip_country"
                                    id="lkn_wcip_country_input"
                                    class="regular-text">
                                    <?php
                                    foreach ($countries as $code => $currency_name) {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_attr($currency_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_extra_data"><?php esc_attr_e('Extra data', 'wc-invoice-payment'); ?></label>
                                <textarea
                                    name="lkn_wcip_extra_data"
                                    id="lkn_wcip_extra_data"
                                    class="regular-text"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wcip-invoice-data wcip-postbox">
                    <span
                        class="text-bold"><?php esc_attr_e('Invoice actions', 'wc-invoice-payment'); ?></span>
                    <hr>
                    <div class="wcip-row">
                        <div class="input-row-wrap">
                            <select name="lkn_wcip_form_actions">
                                <option
                                    value="no_action"
                                    selected>
                                    <?php esc_attr_e('Select an action...', 'wc-invoice-payment'); ?>
                                </option>
                                <option value="send_email">
                                    <?php esc_attr_e('Send invoice to customer', 'wc-invoice-payment'); ?>
                                </option>
                            </select>
                        </div>
                        <div class="input-row-wrap">
                            <label
                                for="lkn_wcip_exp_date_input"><?php esc_attr_e('Due date', 'wc-invoice-payment'); ?></label>
                            <input
                                id="lkn_wcip_exp_date_input"
                                type="date"
                                name="lkn_wcip_exp_date"
                                min="<?php echo esc_attr(gmdate('Y-m-d')); ?>"
                                required>
                        </div>
                        <div
                            class="input-row-wrap"
                            id="twoCheckboxDiv">
                            <label for="lkn_wcip_subscription_product">
                                <input
                                    type="checkbox"
                                    name="lkn_wcip_subscription_product"
                                    id="lkn_wcip_subscription_product"
                                    <?php echo esc_attr($invoiceChecked) ?>>
                                <?php esc_attr_e('Subscription', 'wc-invoice-payment'); ?>
                            </label>
                            <div class="tooltip">
                                <span>?</span>
                                <span class="tooltiptext">
                                    <?php esc_attr_e('Feature available for registered user.', 'wc-invoice-payment'); ?>
                                </span>
                            </div>
                        </div>
                        <div
                            class="input-row-wrap"
                            id="lkn_wcip_subscription_interval">
                            <label
                                for="lkn_wcip_subscription_interval_number"><?php esc_attr_e('Subscription Interval', 'wc-invoice-payment'); ?></label>
                            <div class="lkn_wcip_subscription_interval_div">
                                <input
                                    type="number"
                                    min="1"
                                    name="lkn_wcip_subscription_interval_number"
                                    id="lkn_wcip_subscription_interval_number"
                                    value="1">
                                <select name="lkn_wcip_subscription_interval_type">
                                    <option value="day">
                                        <?php esc_attr_e('Days', 'wc-invoice-payment'); ?>
                                    </option>
                                    <option value="week">
                                        <?php esc_attr_e('Weeks', 'wc-invoice-payment'); ?>
                                    </option>
                                    <option
                                        value="month"
                                        selected>
                                        <?php esc_attr_e('Months', 'wc-invoice-payment'); ?>
                                    </option>
                                    <option value="year">
                                        <?php esc_attr_e('Years', 'wc-invoice-payment'); ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div id="lkn_wcip_subscription_limit_checkbox_div">
                            <label for="lkn_wcip_subscription_limit_checkbox">
                                <input
                                    type="checkbox"
                                    name="lkn_wcip_subscription_limit_checkbox"
                                    id="lkn_wcip_subscription_limit_checkbox">
                                <?php esc_attr_e('Limit number of invoices', 'wc-invoice-payment'); ?>
                            </label>
                        </div>
                        <?php
                        woocommerce_wp_text_input(
                            array(
                                'id' => 'lkn_wcip_subscription_limit',
                                'name' => 'lkn_wcip_subscription_limit',
                                'label' => __('Subscription limit', 'wc-invoice-payment'),
                                'value' => 0,
                                'type' => 'number',
                                'custom_attributes' => array(
                                    'min' => '0',
                                    'step' => '1.0',
                                ),
                            )
                        );
                        ?>
                        <div class="tooltip" id="subscriptionLimitTooltip">
                            <span>?</span>
                            <span class="tooltiptext">
                                <?php esc_attr_e('Set a limit for the number of invoices that will be generated for the subscription, by default,  there is no limit.', 'wc-invoice-payment'); ?>
                            </span>
                        </div>
                    </div>
                    <script>
                        //Valida se a checkbox de assinatura está ativada para mostrar campos
                        lkn_wcip_display_subscription_inputs()
                    </script>
                    <div class="action-btn">
                        <?php submit_button(__('Save', 'wc-invoice-payment')); ?>
                    </div>
                </div>
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Price', 'wc-invoice-payment'); ?>
                    </h2>
                    <div
                        id="wcip-invoice-price-row"
                        class="invoice-column-wrap">
                        <div class="price-row-wrap price-row-0">
                            <div class="input-row-wrap">
                                <label><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                <input
                                    name="lkn_wcip_name_invoice_0"
                                    type="text"
                                    id="lkn_wcip_name_invoice_0"
                                    class="regular-text"
                                    required>
                            </div>
                            <div class="input-row-wrap">
                                <label><?php esc_attr_e('Amount', 'wc-invoice-payment'); ?></label>
                                <input
                                    name="lkn_wcip_amount_invoice_0"
                                    type="tel"
                                    id="lkn_wcip_amount_invoice_0"
                                    class="regular-text lkn_wcip_amount_input"
                                    oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');"
                                    required>
                            </div>
                            <div class="input-row-wrap">
                                <button
                                    type="button"
                                    class="btn btn-delete"
                                    onclick="lkn_wcip_remove_amount_row(0)"><span class="dashicons dashicons-trash"></span></button>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="invoice-row-wrap">
                        <button
                            type="button"
                            class="btn btn-add-line"
                            onclick="lkn_wcip_add_amount_row()"><?php esc_attr_e('Add line', 'wc-invoice-payment'); ?></button>
                    </div>
                </div>
                <div style="width: 100%;"></div>
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Footer notes', 'wc-invoice-payment'); ?>
                    </h2>
                    <div
                        id="wcip-invoice-price-row"
                        class="invoice-column-wrap">
                        <div class="input-row-wrap">
                            <label><?php esc_attr_e('Details in HTML', 'wc-invoice-payment'); ?></label>
                            <textarea
                                name="lkn-wc-invoice-payment-footer-notes"
                                id="lkn-wc-invoice-payment-footer-notes"
                                class="regular-text"><?php echo esc_html($default_footer); ?></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', () => {
                startTinyMce('lkn-wc-invoice-payment-footer-notes', 'submit')
            })
        </script>
<?php
    }

    /**
     * Handles submission from add invoice form.
     */
    public function add_invoice_form_submit_handle(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ('POST' == $method) {
            if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'lkn_wcip_add_invoice')) {
                $decimalSeparator = wc_get_price_decimal_separator();
                $thousandSeparator = wc_get_price_thousand_separator();

                $invoices = array();
                $totalAmount = 0;
                $c = 0;

                // Invoice items
                foreach ($_POST as $key => $value) {
                    // Get invoice description
                    if (preg_match('/lkn_wcip_name_invoice_/i', $key)) {
                        $invoices[$c]['desc'] = sanitize_text_field(wp_unslash($value));
                    }
                    // Get invoice amount
                    if (preg_match('/lkn_wcip_amount_invoice_/i', $key)) {
                        // Format the amount attribute with default float value representation 00000.00
                        $amount = str_replace($thousandSeparator, '', $value);
                        $amount = str_replace($decimalSeparator, '.', $amount);

                        // Save amount and description in same index because they are related
                        $invoices[$c]['amount'] = $amount;
                        $totalAmount += $amount;
                        // Only increment when amount is found
                        ++$c;
                    }
                }

                // Filter all order attributes before saving in the DB
                $paymentStatus = sanitize_text_field(wp_unslash($_POST['lkn_wcip_payment_status']));
                $paymentMethod = sanitize_text_field(wp_unslash($_POST['lkn_wcip_default_payment_method']));
                $isSubscription = sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_product']));
                $intarvalNumber = sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_interval_number']));
                $intarvalType = sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_interval_type']));
                $subscriptionLimit = sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_limit']));
                $currency = sanitize_text_field(wp_unslash($_POST['lkn_wcip_currency']));
                $country = sanitize_text_field(wp_unslash($_POST['lkn_wcip_country']));
                $name = sanitize_text_field(wp_unslash($_POST['lkn_wcip_name']));
                $firstName = explode(' ', $name)[0];
                $lastname = substr(strstr($name, ' '), 1);
                $userId = isset($_POST['lkn_wcip_customer']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_customer'])) : '';
                $email = sanitize_email(wp_unslash($_POST['lkn_wcip_email']));
                $expDate = sanitize_text_field(wp_unslash($_POST['lkn_wcip_exp_date']));
                $iniDate = new DateTime();
                $extraData = sanitize_text_field(wp_unslash($_POST['lkn_wcip_extra_data']));
                $footerNotes = wp_kses_post(wp_unslash($_POST['lkn-wc-invoice-payment-footer-notes']));

                $order = wc_create_order(
                    array(
                        'status' => $paymentStatus,
                        'customer_id' => 0,
                        'customer_note' => '',
                        'total' => $totalAmount,
                    )
                );
                $order->set_billing_country($country);
                $order->update_meta_data('wcip_extra_data', $extraData);
                $order->update_meta_data('wcip_footer_notes', $footerNotes);
                $order->update_meta_data('wcip_footer_notes', $footerNotes);

                $pdfTemplateId = sanitize_text_field(wp_unslash($_POST['lkn_wcip_select_invoice_template']));
                $order->update_meta_data('wcip_select_invoice_template_id', $pdfTemplateId);

                $pdfLanguage = sanitize_text_field(wp_unslash($_POST['lkn_wcip_select_invoice_language']));
                $order->update_meta_data('wcip_select_invoice_language', $pdfLanguage);

                // Saves all charges as products inside the order object
                for ($i = 0; $i < count($invoices); ++$i) {
                    $product = new WC_Product();
                    $product->set_name($invoices[$i]['desc']);
                    $product->set_regular_price($invoices[$i]['amount']);
                    $product->save();
                    $productId = wc_get_product($product->get_id());

                    $order->add_product($productId);
                    // Delete after adding to prevent residue
                    $product->delete(true);
                }

                // Set all order attributes
                if (!empty($userId)) {
                    $user = get_user_by('ID', $userId);
                    if ($user) {
                        $email = $user->user_email;
                        $order->set_billing_email($email);
                        $order->set_customer_id($userId);
                    }
                }

                $order->set_billing_email($email);
                $order->set_billing_first_name($firstName);
                $order->set_billing_last_name($lastname);
                $order->set_payment_method($paymentMethod);
                $order->set_currency($currency);
                $order->add_meta_data('lkn_exp_date', $expDate);
                $order->add_meta_data('lkn_ini_date', $iniDate->format('Y-m-d'));

                $order->calculate_totals();

                //Seta valores para serem usados na criação do evento cron
                if (isset($_POST['lkn_wcip_subscription_product'])) {
                    $isSubscription = sanitize_text_field(wp_unslash($_POST['lkn_wcip_subscription_product']));
                    $order->add_meta_data('lkn_is_subscription', $isSubscription);
                    $order->add_meta_data('lkn_wcip_subscription_interval_number', $intarvalNumber);
                    $order->add_meta_data('lkn_wcip_subscription_interval_type', $intarvalType);
                    $order->add_meta_data('lkn_wcip_subscription_limit', $subscriptionLimit);
                    $order->add_meta_data('lkn_wcip_subscription_initial_limit', 0);
                    $order->add_meta_data('lkn_wcip_subscription_is_manual', true);
                }

                $order->save();

                $orderId = $order->get_id();

                //Chama a função que configura o evento cron
                if ($isSubscription) {
                    $subscription_class = new WcPaymentInvoiceSubscription();
                    $subscription_class->validate_product($orderId, true);
                }

                $invoiceList = get_option('lkn_wcip_invoices');

                if (false !== $invoiceList) {
                    $invoiceList[] = $orderId;
                    update_option('lkn_wcip_invoices', $invoiceList);
                } else {
                    update_option('lkn_wcip_invoices', array($orderId));
                }

                if (! empty($expDate) && 'wc-pending' === $paymentStatus) {
                    $todayTime = time();
                    $expDateTime = strtotime($expDate);
                    $nextVerification = 0;

                    if ($todayTime > $expDateTime) {
                        $nextVerification = $todayTime - $expDateTime;
                    } else {
                        $nextVerification = $expDateTime - $todayTime;
                    }

                    wp_schedule_event(time() + $nextVerification, 'daily', 'lkn_wcip_cron_hook', array($orderId));
                }

                // If the action 'send email' is set send a notification email to the customer
                if (isset($_POST['lkn_wcip_form_actions']) && sanitize_text_field(wp_unslash($_POST['lkn_wcip_form_actions'])) === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    $order->add_order_note(__('Order details manually sent to customer.', 'wc-invoice-payment'), false, true);
                }

                if ($isSubscription) {
                    $message = urlencode(__('Subscription successfully saved', 'wc-invoice-payment'));

                    // Redireciona para a página desejada com o parâmetro 'message'
                    wp_redirect(admin_url('admin.php?page=wc-subscription-payment&message=' . $message));
                    exit;
                } else {
                    $message = urlencode(__('Invoice successfully saved', 'wc-invoice-payment'));

                    // Redireciona para a página desejada com o parâmetro 'message'
                    wp_redirect(admin_url('admin.php?page=wc-invoice-payment&message=' . $message));
                    exit;
                }
            } else {
                // Error messages
                echo '<div class="lkn_wcip_notice_negative">' . esc_html(__('Error on invoice generation', 'wc-invoice-payment')) . '</div>';
            }
        }
    }

    /**
     * Handles submission from edit invoice form and delete invoice action.
     */
    public function edit_invoice_form_submit_handle(): void
    {
        // Validates request method
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';

        if ('POST' == $method) {
            // Validates WP nonce
            if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'lkn_wcip_edit_invoice')) {
                $decimalSeparator = wc_get_price_decimal_separator();
                $thousandSeparator = wc_get_price_thousand_separator();

                $invoiceId = sanitize_text_field(wp_unslash($_GET['invoice']));
                $order = wc_get_order($invoiceId);
                $order->remove_order_items();

                $invoices = array();
                $totalAmount = 0;
                $c = 0;

                // Invoice items
                foreach ($_POST as $key => $value) {
                    // Get invoice description
                    if (preg_match('/lkn_wcip_name_invoice_/i', $key)) {
                        $invoices[$c]['desc'] = sanitize_text_field(wp_unslash($value));
                    }
                    // Get invoice amount
                    if (preg_match('/lkn_wcip_amount_invoice_/i', $key)) {
                        // Format the amount attribute with default float value representation 00000.00
                        $amount = str_replace($thousandSeparator, '', $value);
                        $amount = str_replace($decimalSeparator, '.', $amount);

                        // Save amount and description in same index because they are related
                        $invoices[$c]['amount'] = $amount;
                        $totalAmount += $amount;
                        // Only increment when amount is found
                        ++$c;
                    }
                }

                // Filter all order attributes before saving in the DB
                $paymentStatus = sanitize_text_field(wp_unslash($_POST['lkn_wcip_payment_status']));
                $paymentMethod = sanitize_text_field(wp_unslash($_POST['lkn_wcip_default_payment_method']));
                $currency = sanitize_text_field(wp_unslash($_POST['lkn_wcip_currency']));
                $name = sanitize_text_field(wp_unslash($_POST['lkn_wcip_name']));
                $country = sanitize_text_field(wp_unslash($_POST['lkn_wcip_country']));
                $firstName = explode(' ', $name)[0];
                $lastname = substr(strstr($name, ' '), 1);
                $userId = isset($_POST['lkn_wcip_customer']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_customer'])) : '';
                $email = sanitize_email(wp_unslash($_POST['lkn_wcip_email']));
                $expDate = sanitize_text_field(wp_unslash($_POST['lkn_wcip_exp_date']));
                $pdfTemplateId = sanitize_text_field(wp_unslash($_POST['lkn_wcip_select_invoice_template']));
                $pdfLanguage = sanitize_text_field(wp_unslash($_POST['lkn_wcip_select_invoice_language']));
                $extraData = wp_kses(wp_unslash($_POST['lkn_wcip_extra_data']), array('br' => array()));
                $footerNotes = wp_kses_post(wp_unslash($_POST['lkn-wc-invoice-payment-footer-notes']));

                $order->update_meta_data('wcip_extra_data', $extraData);
                $order->update_meta_data('wcip_footer_notes', $footerNotes);
                $order->update_meta_data('wcip_select_invoice_template_id', $pdfTemplateId);
                $order->update_meta_data('wcip_select_invoice_language', $pdfLanguage);

                // Saves all charges as products inside the order object
                for ($i = 0; $i < count($invoices); ++$i) {
                    $product = new WC_Product();
                    $product->set_name($invoices[$i]['desc']);
                    $product->set_regular_price($invoices[$i]['amount']);
                    $product->save();
                    $productId = wc_get_product($product->get_id());

                    $order->add_product($productId);

                    // Delete after adding to prevent residue
                    $product->delete(true);
                }

                // Set all order attributes

                if (!empty($userId)) {
                    $user = get_user_by('ID', $userId);
                    if ($user) {
                        $email = $user->user_email;
                        $order->set_billing_email($email);
                        $order->set_customer_id($userId);
                    }
                } else {
                    $order->set_customer_id(0);
                }

                $order->set_billing_email($email);
                $order->set_billing_country($country);
                $order->set_billing_first_name($firstName);
                $order->set_billing_last_name($lastname);
                $order->set_payment_method($paymentMethod);
                $order->set_currency($currency);
                $order->set_status($paymentStatus);
                $order->update_meta_data('lkn_exp_date', $expDate);

                // Get order total and saves in the DB
                $order->calculate_totals();
                $order->save();

                if (! empty($expDate) && 'wc-pending' === $paymentStatus) {
                    $todayTime = time();
                    $expDateTime = strtotime($expDate);
                    $nextVerification = 0;

                    if ($todayTime > $expDateTime) {
                        $nextVerification = $todayTime - $expDateTime;
                    } else {
                        $nextVerification = $expDateTime - $todayTime;
                    }

                    wp_schedule_event(time() + $nextVerification, 'daily', 'lkn_wcip_cron_hook', array($invoiceId));
                } else {
                    $timestamp = wp_next_scheduled('lkn_wcip_cron_hook', array($invoiceId));
                    wp_unschedule_event($timestamp, 'lkn_wcip_cron_hook', array($invoiceId));
                }

                // If the action 'send email' is set send a notification email to the customer
                if (isset($_POST['lkn_wcip_form_actions']) && sanitize_text_field(wp_unslash($_POST['lkn_wcip_form_actions'])) === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    // Note the event.
                    $order->add_order_note(__('Order details manually sent to customer.', 'wc-invoice-payment'), false, true);
                }

                // Success message
                echo '<div class="lkn_wcip_notice_positive">' . esc_html(__('Invoice successfully saved', 'wc-invoice-payment')) . '</div>';
            } else {
                // Error message
                echo '<div class="lkn_wcip_notice_negative">' . esc_html(__('Error on invoice generation', 'wc-invoice-payment')) . '</div>';
            }
        } elseif ('GET' == $method && isset($_GET['lkn_wcip_delete'])) {
            $invoiceDeleteSanitized = sanitize_text_field(wp_unslash($_GET['lkn_wcip_delete']));
            // Validates request for deleting invoice
            if ('true' === $invoiceDeleteSanitized) {
                $invoiceDelete = array(sanitize_text_field(wp_unslash($_GET['invoice'])));
                $invoices = get_option('lkn_wcip_invoices');

                $invoices = array_diff($invoices, $invoiceDelete);

                $order = wc_get_order($invoiceDelete[0]);
                $order->delete();

                update_option('lkn_wcip_invoices', $invoices);

                // Redirect to invoice list
                wp_redirect(home_url('wp-admin/admin.php?page=wc-invoice-payment'));
            } else {
                // Show error message
                echo '<div class="lkn_wcip_notice_negative">' . esc_html(__('Error on invoice generation', 'wc-invoice-payment')) . '</div>';
            }
        }
    }

    /**
     * Handles submission from edit subscription form and delete subscription action.
     */
    public function edit_subscription_form_submit_handle(): void
    {
        // Validates request method
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';

        if ('POST' == $method) {
            // Validates WP nonce
            if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'lkn_wcip_edit_invoice')) {
                $decimalSeparator = wc_get_price_decimal_separator();
                $thousandSeparator = wc_get_price_thousand_separator();

                $invoiceId = isset($_GET['invoice']) ? sanitize_text_field(wp_unslash($_GET['invoice'])) : '';

                if (empty($invoiceId)) {
                    return;
                }

                $order = wc_get_order($invoiceId);
                $order->remove_order_items();

                $invoices = array();
                $totalAmount = 0;
                $c = 0;

                // Invoice items
                foreach ($_POST as $key => $value) {
                    // Get invoice description
                    if (preg_match('/lkn_wcip_name_invoice_/i', $key)) {
                        $invoices[$c]['desc'] = sanitize_text_field(wp_unslash($value));
                    }
                    // Get invoice amount
                    if (preg_match('/lkn_wcip_amount_invoice_/i', $key)) {
                        // Format the amount attribute with default float value representation 00000.00
                        $amount = str_replace($thousandSeparator, '', $value);
                        $amount = str_replace($decimalSeparator, '.', $amount);

                        // Save amount and description in same index because they are related
                        $invoices[$c]['amount'] = $amount;
                        $totalAmount += $amount;
                        // Only increment when amount is found
                        ++$c;
                    }
                }

                // Filter all order attributes before saving in the DB
                $paymentStatus = sanitize_text_field(wp_unslash($_POST['lkn_wcip_payment_status']));
                $paymentMethod = sanitize_text_field(wp_unslash($_POST['lkn_wcip_default_payment_method']));
                $currency = sanitize_text_field(wp_unslash($_POST['lkn_wcip_currency']));
                $name = sanitize_text_field(wp_unslash($_POST['lkn_wcip_name']));
                $country = sanitize_text_field(wp_unslash($_POST['lkn_wcip_country']));
                $firstName = explode(' ', $name)[0];
                $lastname = substr(strstr($name, ' '), 1);
                $userId = isset($_POST['lkn_wcip_customer']) ? sanitize_text_field(wp_unslash($_POST['lkn_wcip_customer'])) : '';
                $email = sanitize_email(wp_unslash($_POST['lkn_wcip_email']));
                $expDate = sanitize_text_field(wp_unslash($_POST['lkn_wcip_exp_date']));
                $pdfTemplateId = sanitize_text_field(wp_unslash($_POST['lkn_wcip_select_invoice_template']));
                $pdfLanguage = sanitize_text_field(wp_unslash($_POST['lkn_wcip_select_invoice_language']));
                $extraData = wp_kses(wp_unslash($_POST['lkn_wcip_extra_data']), array('br' => array()));
                $footerNotes = wp_kses_post(wp_unslash($_POST['lkn-wc-invoice-payment-footer-notes']));

                $order->update_meta_data('wcip_extra_data', $extraData);
                $order->update_meta_data('wcip_footer_notes', $footerNotes);
                $order->update_meta_data('wcip_select_invoice_template_id', $pdfTemplateId);
                $order->update_meta_data('wcip_select_invoice_language', $pdfLanguage);

                // Saves all charges as products inside the order object
                for ($i = 0; $i < count($invoices); ++$i) {
                    $product = new WC_Product();
                    $product->set_name($invoices[$i]['desc']);
                    $product->set_regular_price($invoices[$i]['amount']);
                    $product->save();
                    $productId = wc_get_product($product->get_id());

                    $order->add_product($productId);

                    // Delete after adding to prevent residue
                    $product->delete(true);
                }

                // Set all order attributes
                if (!empty($userId)) {
                    $user = get_user_by('ID', $userId);
                    if ($user) {
                        $email = $user->user_email;
                        $order->set_billing_email($email);
                        $order->set_customer_id($userId);
                    }
                } else {
                    $order->set_customer_id(0);
                }

                $order->set_billing_email($email);
                $order->set_billing_country($country);
                $order->set_billing_first_name($firstName);
                $order->set_billing_last_name($lastname);
                $order->set_payment_method($paymentMethod);
                $order->set_currency($currency);
                $order->set_status($paymentStatus);
                $order->update_meta_data('lkn_exp_date', $expDate);

                // Get order total and saves in the DB
                $order->calculate_totals();
                $order->save();

                if (! empty($expDate) && 'wc-pending' === $paymentStatus) {
                    $todayTime = time();
                    $expDateTime = strtotime($expDate);
                    $nextVerification = 0;

                    if ($todayTime > $expDateTime) {
                        $nextVerification = $todayTime - $expDateTime;
                    } else {
                        $nextVerification = $expDateTime - $todayTime;
                    }

                    wp_schedule_event(time() + $nextVerification, 'daily', 'lkn_wcip_cron_hook', array($invoiceId));
                } else {
                    $timestamp = wp_next_scheduled('lkn_wcip_cron_hook', array($invoiceId));
                    wp_unschedule_event($timestamp, 'lkn_wcip_cron_hook', array($invoiceId));
                }

                // If the action 'send email' is set send a notification email to the customer
                if (isset($_POST['lkn_wcip_form_actions']) && sanitize_text_field(wp_unslash($_POST['lkn_wcip_form_actions'])) === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    // Note the event.
                    $order->add_order_note(__('Order details manually sent to customer.', 'wc-invoice-payment'), false, true);
                }

                // Success message
                echo '<div class="lkn_wcip_notice_positive">' . esc_html(__('Invoice successfully saved', 'wc-invoice-payment')) . '</div>';
            } else {
                // Error message
                echo '<div class="lkn_wcip_notice_negative">' . esc_html(__('Error on invoice generation', 'wc-invoice-payment')) . '</div>';
            }
        } elseif ('GET' == $method && isset($_GET['lkn_wcip_delete'])) {
            // Validates request for deleting invoice
            $invoiceDeleteSanitized = sanitize_text_field(wp_unslash($_GET['lkn_wcip_delete']));
            if ('true' === $invoiceDeleteSanitized) {
                $invoiceDelete = array(sanitize_text_field(wp_unslash($_GET['invoice'])));
                $invoices = get_option('lkn_wcip_invoices');

                $invoices = array_diff($invoices, $invoiceDelete);

                $order = wc_get_order($invoiceDelete[0]);
                $order->delete();

                update_option('lkn_wcip_invoices', $invoices);

                $scheduled_events = _get_cron_array();
                // verifica todos os eventos agendados
                foreach ($scheduled_events as $timestamp => $cron_events) {
                    foreach ($cron_events as $hook => $events) {
                        foreach ($events as $event) {
                            // Verifique se o evento está associado ao seu gancho (hook)
                            if ("generate_invoice_event" === $hook || 'lkn_wcip_cron_hook' === $hook) {
                                // Verifique se os argumentos do evento contêm o ID da ordem que você deseja remover
                                $event_args = $event['args'];
                                if (is_array($event_args) && in_array($invoiceDelete[0], $event_args)) {
                                    // Remova o evento do WP Cron
                                    wp_unschedule_event($timestamp, $hook, $event_args);
                                }
                            }
                        }
                    }
                }
                // Redirect to invoice list
                wp_redirect(home_url('wp-admin/admin.php?page=wc-subscription-payment'));
            } else {
                // Show error message
                echo '<div class="lkn_wcip_notice_negative">' . esc_html(__('Error on invoice generation', 'wc-invoice-payment')) . '</div>';
            }
        }
    }
}
?>