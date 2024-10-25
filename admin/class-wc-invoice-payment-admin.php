<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @see       https://www.linknacional.com/
 * @since      1.0.0
 */

use Give\Framework\FieldsAPI\Date;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @author     Link Nacional
 */
final class Wc_Payment_Invoice_Admin
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

        $this->handler_invoice_templates = new Wc_Payment_Invoice_Pdf_Templates($this->plugin_name, $this->version);
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
         */
        if (
            strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_new-invoice' === $hook
            || strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_settings' === $hook
            || strtolower(__('Invoices', 'wc-invoice-payment')) . '_page_wc-subscription-payment' === $hook
            || 'toplevel_page_wc-invoice-payment' === $hook
            || 'admin_page_edit-invoice' === $hook
            || 'admin_page_edit-subscription' === $hook
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
            wp_enqueue_script($this->plugin_name . '-admin-js', plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-admin.js', array('wp-i18n'), $this->version, false);
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
            wp_enqueue_script('cpt-admin-script', WC_PAYMENT_INVOICE_ROOT_URL . 'public/js/wc-invoice-payment-public-input-file.js', array('jquery'), '1.0', true);
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
        if (! current_user_can('manage_woocommerce') && !wp_verify_nonce($_POST['lkn_wcip_settings_nonce'], 'settings_nonce')) {
            return;
        }
        wp_enqueue_style('my-tailwind-plugin-styles', WC_PAYMENT_INVOICE_ROOT_URL . 'public/css/style.css');
        wp_enqueue_editor();

        $current_tab = isset($_GET['settings']) ? $_GET['settings'] : 'Invoices';

        if (! empty($_POST)) {
            if ($current_tab !== 'Subscriptions') {
                $global_pdf_template = sanitize_text_field($_POST['lkn_wcip_payment_global_template']);
                $product_invoices = $_POST["lkn_wcip_subscription_active_product_invoices"];
                $template_logo_url = sanitize_text_field($_POST['lkn_wcip_template_logo_url']);

                $default_footer = wp_kses_post($_POST['lkn_wcip_default_footer']);
                $sender_details = wp_kses_post($_POST['lkn_wcip_sender_details']);
                $text_before_payment_link = wp_kses_post($_POST['lkn_wcip_text_before_payment_link']);
                $email_verify = isset($_POST["lkn_wcip_after_save_button_email_check"]);
                update_option('lkn_wcip_global_pdf_template_id', $global_pdf_template);
                update_option('lkn_wcip_template_logo_url', $template_logo_url);
                update_option('lkn_wcip_default_footer', $default_footer);
                update_option('lkn_wcip_sender_details', $sender_details);
                update_option('lkn_wcip_text_before_payment_link', $text_before_payment_link);
                update_option('lkn_wcip_subscription_active_product_invoices', $product_invoices);
                update_option("lkn_wcip_after_save_button_email_check", $email_verify);
            } else {
                $interval_number = $_POST["lkn_wcip_subscription_interval_number"];
                $interval_type = $_POST["lkn_wcip_subscription_interval_type"];

                update_option('lkn_wcip_interval_number', $interval_number);
                update_option('lkn_wcip_interval_type', $interval_type);
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

        $html_templates_list = implode(array_map(function ($template) use ($global_template): string {
            $template_id = esc_attr($template['id']);
            $friendly_template_name = esc_html($template['friendly_name']);
            $preview_url = esc_url(WC_PAYMENT_INVOICE_ROOT_URL . "includes/templates/$template_id/preview.webp");

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
            <style>
                .flex-row {
                    display: flex;
                    flex-direction: row;
                }

                .tooltip {
                    position: relative;
                    display: inline-block;
                    background-color: #a99f93;
                    border-radius: 70%;
                    order: 3;
                    width: 20px;
                    height: 20px;
                }

                .tooltip .tooltiptext {
                    visibility: hidden;
                    width: 200px;
                    background-color: #555;
                    color: #fff;
                    text-align: center;
                    border-radius: 6px;
                    padding: 5px;
                    position: absolute;
                    z-index: 1;
                    bottom: 125%;
                    /* Adjust this value to control the position of the tooltip */
                    left: 50%;
                    margin-left: -100px;
                    /* Adjust this value to center the tooltip */
                    opacity: 0;
                    transition: opacity 0.3s;
                }

                .tooltip:hover .tooltiptext {
                    visibility: visible;
                    opacity: 1;
                }

                #lkn_wcip_subscription_active {
                    padding-top: 23px;
                }

                #lkn_wcip_subscription_interval_div_tip {
                    padding-left: 6px;
                }
            </style>
            <h1><?php esc_attr_e('Settings', 'wc-invoice-payment'); ?>
            </h1>
            <?php settings_errors(); ?>
            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <a href="admin.php?page=settings&settings=Invoices" class="nav-tab <?php echo is_active_tab('Invoices', $current_tab); ?>">
                    <?php esc_attr_e('Invoices', 'wc-invoice-payment') ?>
                </a>
                <a href="admin.php?page=settings&settings=Subscriptions" class="nav-tab <?php echo is_active_tab('Subscriptions', $current_tab); ?>">
                    <?php esc_attr_e('Subscriptions', 'wc-invoice-payment') ?>
                </a>
            </nav>
            <form
                action="<?php echo menu_page_url('settings', false) . "&settings=$current_tab"; ?>"
                method="post" class="wcip-form-wrap">
                <?php wp_nonce_field('lkn_wcip_edit_invoice', 'nonce'); ?>

                <input
                    name="lkn_wcip_settings_nonce"
                    id="lkn_wcip_settings_nonce"
                    type="hidden"
                    value="<?php echo esc_attr(wp_create_nonce('settings_nonce')) ?>">

                <div class="wcip-invoice-data">
                    <?php
                    if ($current_tab == 'Invoices') {
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
                                        <select name="lkn_wcip_payment_global_template" id="lkn_wcip_payment_global_template"
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
                                        <label class="lkn_wcip_payment_global_template_label" for="lkn_wcip_payment_global_template">
                                            <?php esc_attr_e('Logo URL', 'wc-invoice-payment'); ?>
                                            <div class="lkn_wcip_payment_global_template_label_description">
                                                <?php esc_attr_e('Maximum recommended width of 460 pixels', 'wc-invoice-payment'); ?>
                                            </div>
                                        </label>


                                        <div class="flex gap-2 items-center">
                                            <button type="button" value="<?php echo esc_attr($template_logo_url); ?>" class="button button-primary" id="lkn_wcip_template_logo_url_btn" data-media-uploader-target="#lkn_wcip_template_logo_url">
                                                <?php esc_attr_e('Upload image', 'wc-invoice-payment') ?>
                                            </button>
                                            <h3 id="lkn_wcip_template_logo_desc"></h3>
                                        </div>
                                        <div style="display: none;">
                                            <input type="text" class="large-text" value="<?php echo esc_attr($template_logo_url); ?>" name="lkn_wcip_template_logo_url" id="lkn_wcip_template_logo_url" />
                                        </div>
                                    </div>


                                    <div class="input-row-wrap input-row-wrap-global-settings">
                                        <label for="lkn_wcip_default_footer">
                                            <?php esc_attr_e('Default footer', 'wc-invoice-payment'); ?>
                                        </label>
                                        <textarea name="lkn_wcip_default_footer"
                                            id="lkn_wcip_default_footer"><?php echo esc_html($default_footer); ?></textarea>
                                    </div>

                                    <div class="input-row-wrap input-row-wrap-global-settings">
                                        <label for="lkn_wcip_sender_details">
                                            <?php esc_attr_e('Sender details', 'wc-invoice-payment'); ?>
                                        </label>
                                        <textarea name="lkn_wcip_sender_details"
                                            id="lkn_wcip_sender_details"><?php echo esc_html($sender_details); ?></textarea>
                                    </div>

                                    <div class="input-row-wrap input-row-wrap-global-settings">
                                        <label for="lkn_wcip_text_before_payment_link">
                                            <?php esc_attr_e('Text before payment link', 'wc-invoice-payment'); ?>
                                        </label>
                                        <textarea name="lkn_wcip_text_before_payment_link"
                                            id="lkn_wcip_text_before_payment_link"><?php echo esc_html($text_before_payment_link); ?></textarea>

                                    </div>
                                    <div class="input-column-wrap input-row-wrap-global-settings">
                                        <div class="input-column-wrap">                                            
                                            <label for="lkn_wcip_after_save_button_email_check">
                                                <input type="checkbox"
                                                    name="lkn_wcip_after_save_button_email_check"
                                                    id="lkn_wcip_after_save_button_email_check"
                                                    <?php if ($email_verify) echo 'checked'; ?>>
                                                <?php esc_attr_e('Enable email verification on the invoice.', 'wc-invoice-payment'); ?>
    
                                                <div class="tooltip">
                                                    <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
                                                    <span class="tooltiptext">
                                                        <?php esc_attr_e('This feature will enable a text box for the user to enter their email address before displaying the invoice.', 'wc-invoice-payment'); ?>
                                                    </span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="input-column-wrap input-row-wrap-global-settings">
                                        <div class="input-column-wrap" id="lkn_wcip_subscription_active_product">
                                            <label for="lkn_wcip_subscription_active_product_invoices">
                                                <input
                                                    name="lkn_wcip_subscription_active_product_invoices"
                                                    id="lkn_wcip_subscription_active_product_invoices"
                                                    type="checkbox"
                                                    class=""
                                                    value="1"
                                                    <?php if ($product_invoices) echo 'checked'; ?>>
                                                <?php esc_attr_e('Create invoices for products', 'wc-invoice-payment') ?>

                                                <div class="tooltip">
                                                    <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
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
                    if ($current_tab == 'Subscriptions') {
                    ?>
                        <div class="invoice_settings">
                            <h2 class="title">
                                <?php esc_attr_e('Subscription settings', 'wc-invoice-payment'); ?>
                            </h2>
                            <div class="input-row-wrap" id="lkn_wcip_subscription_interval">
                                <label
                                    for="lkn_wcip_subscription_interval_number"><?php esc_attr_e('Invoice issuance lead time', 'wc-invoice-payment'); ?></label>

                                <div class="flex-row">

                                    <div class="lkn_wcip_subscription_interval_div">
                                        <input type="number" min="0" name="lkn_wcip_subscription_interval_number"
                                            id="lkn_wcip_subscription_interval_number" value="<?php echo esc_attr($interval_number); ?>">
                                        <select name="lkn_wcip_subscription_interval_type">
                                            <option value="day" <?php echo esc_attr($interval_type == 'day' ? esc_attr("selected") : '') ?>>
                                                <?php esc_attr_e('Days', 'wc-invoice-payment'); ?>
                                            </option>
                                            <option value="week" <?php echo esc_attr($interval_type == 'week' ? esc_attr("selected") : '') ?>>
                                                <?php esc_attr_e('Weeks', 'wc-invoice-payment'); ?>
                                            </option>
                                            <option value="month" <?php echo esc_attr($interval_type == 'month' ? esc_attr("selected") : '') ?>>
                                                <?php esc_attr_e('Months', 'wc-invoice-payment'); ?>
                                            </option>
                                        </select>
                                        <div class="flex items-center justify-center" id="lkn_wcip_subscription_interval_div_tip">
                                            <div class="tooltip">
                                                <span class="tootip w-5 h-5 flex items-center justify-center text-white rounded-full cursor-pointer">?</span>
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
                            <div class="action-btn">
                                <?php submit_button(__('Save')); ?>
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
        if (! empty($_POST) && ! wp_verify_nonce($_POST['wcip_rest_nonce'], 'wp_rest')) {
            return;
        }

        wp_enqueue_editor();
        wp_create_nonce('wp_rest');

        $invoiceId = sanitize_text_field($_GET['invoice']);

        $decimalSeparator = wc_get_price_decimal_separator();
        $thousandSeparator = wc_get_price_thousand_separator();
        $decimalQtd = wc_get_price_decimals();

        // Get all translated WooCommerce order status
        $statusWc = array();
        $statusWc[] = array('status' => 'wc-pending', 'label' => _x('Pending payment', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-processing', 'label' => _x('Processing', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-on-hold', 'label' => _x('On hold', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-completed', 'label' => _x('Completed', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-cancelled', 'label' => _x('Cancelled', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-refunded', 'label' => _x('Refunded', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-failed', 'label' => _x('Failed', 'Order status', 'woocommerce'));

        $c = 0;
        $order = wc_get_order($invoiceId);

        $items = $order->get_items();
        $checkoutUrl = $order->get_checkout_payment_url();
        $orderStatus = $order->get_status();

        $invoice_template = $order->get_meta('wcip_select_invoice_template_id') ?? get_option('lkn_wcip_global_pdf_template_id', 'global');

        $templates_list = $this->handler_invoice_templates->get_templates_list();

        $html_templates_list = implode(array_map(function ($template) use ($invoice_template): string {
            $template_id = esc_attr($template['id']);
            $friendly_template_name = esc_html($template['friendly_name']);
            $preview_url = esc_url(WC_PAYMENT_INVOICE_ROOT_URL . "includes/templates/$template_id/preview.webp");

            $selected = $invoice_template === $template_id ? 'selected' : '';

            return "<option $selected data-preview-url='$preview_url' value='$template_id'>$friendly_template_name</option>";
        }, $templates_list));

        $currencies = get_woocommerce_currencies();
        $currency_codes = array_keys($currencies);
        sort($currency_codes);

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

    ?>
        <div class="wrap">
            <h1><?php esc_attr_e('Edit invoice', 'wc-invoice-payment'); ?>
            </h1>
            <?php settings_errors(); ?>
            <form
                action="<?php menu_page_url('edit-invoice&invoice=' . $invoiceId); ?>"
                method="post" class="wcip-form-wrap">
                <input name="wcip_rest_nonce" id="wcip_rest_nonce" type="hidden"
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
                                <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" class="regular-text"
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
                                <label
                                    for="lkn_wcip_default_payment_method_input"><?php esc_attr_e('Default payment method', 'wc-invoice-payment'); ?></label>
                                <select name="lkn_wcip_default_payment_method" id="lkn_wcip_default_payment_method_input"
                                    class="regular-text">
                                    <option value="multiplePayment" selected>
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
                                <select name="lkn_wcip_currency" id="lkn_wcip_currency_input" class="regular-text">
                                    <?php
                                    foreach ($currency_codes as $code) {
                                        $currency_name = $currencies[$code];
                                        $selected = ($order->get_currency() === $code) ? 'selected' : ''; // Verifica se a opção deve ser selecionada

                                        if ($order->get_currency() === $code) {
                                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_select_invoice_template">
                                    <?php esc_attr_e('Invoice PDF template', 'wc-invoice-payment'); ?>
                                </label>
                                <select name="lkn_wcip_select_invoice_template" id="lkn_wcip_select_invoice_template"
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
                                <div style="position: relative;"><img id="lkn-wcip-preview-img" /></div>
                            </div>
                        </div>
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_name_input"><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                <input name="lkn_wcip_name" type="text" id="lkn_wcip_name_input" class="regular-text" required
                                    value="<?php echo esc_attr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>">
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_email_input"><?php esc_attr_e('Email', 'wc-invoice-payment'); ?></label>
                                <input name="lkn_wcip_email" type="email" id="lkn_wcip_email_input" class="regular-text"
                                    required
                                    value="<?php echo esc_html($order->get_billing_email()); ?>">
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_extra_data"><?php esc_attr_e('Extra data', 'wc-invoice-payment'); ?></label>
                                <textarea name="lkn_wcip_extra_data" id="lkn_wcip_extra_data"
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
                                <option value="no_action" selected>
                                    <?php esc_attr_e('Select an action...', 'wc-invoice-payment'); ?>
                                </option>
                                <option value="send_email">
                                    <?php esc_attr_e('Send invoice to customer', 'wc-invoice-payment'); ?>
                                </option>
                            </select>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_exp_date_input"><?php esc_attr_e('Due date', 'wc-invoice-payment'); ?></label>
                                <input id="lkn_wcip_exp_date_input" type="date" name="lkn_wcip_exp_date"
                                    value="<?php echo esc_attr($order->get_meta('lkn_exp_date')); ?>"
                                    min="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                            </div>
                            <div class="input-column-wrap">
                                <a class="lkn_wcip_generate_pdf_btn" href="#"
                                    data-invoice-id="<?php echo esc_attr($invoiceId); ?>"><?php esc_attr_e('Download invoice', 'wc-invoice-payment'); ?></a>
                                &nbsp
                                <span class="dashicons dashicons-image-rotate"></span>
                            </div>
                        </div>
                        <?php
                        if ('pending' === $orderStatus) {
                        ?>
                            <div class="input-row-wrap">
                                <a href="<?php echo esc_url($checkoutUrl); ?>"
                                    target="_blank"><?php esc_attr_e('Invoice payment link', 'wc-invoice-payment'); ?></a>
                            </div>
                        <?php
                        } ?>
                    </div>
                    <div id="lkn-wcip-share-modal" style="display: none;">
                        <div id="lkn-wcip-share-modal-content">
                            <h3 id="lkn-wcip-share-title">
                                <?php esc_attr_e('Share with', 'wc-invoice-payment'); ?>
                            </h3>
                            <div id="lkn-wcip-share-buttons">
                                <a href="#" id="lkn-wcip-whatsapp-share"
                                    class="lkn-wcip-share-icon dashicons dashicons-whatsapp"
                                    onclick="lkn_wcip_open_popup('whatsapp', '<?php echo esc_url($checkoutUrl); ?>')"></a>
                                <a href="#" id="lkn-wcip-twitter-share" class="lkn-wcip-share-icon dashicons dashicons-twitter"
                                    onclick="lkn_wcip_open_popup('twitter', '<?php echo esc_url($checkoutUrl); ?>')"></a>
                                <a href="mailto:?subject=Link de fatura&body=<?php echo esc_url($checkoutUrl); ?>"
                                    id="lkn-wcip-email-share" class="lkn-wcip-share-icon dashicons dashicons-email-alt"
                                    target="_blank">
                                </a>
                            </div>
                            <h3 id="lkn-wcip-share-title">
                                <?php esc_attr_e('Or copy link', 'wc-invoice-payment'); ?>
                            </h3>
                            <div id="lkn-wcip-copy-link-div">
                                <input id="lkn-wcip-copy-input" type="text"
                                    value="<?php echo esc_url($checkoutUrl); ?>"
                                    readonly>
                                <span onclick="lkn_wcip_copy_link()" class="lkn-wcip-copy-button"><span
                                        class="dashicons dashicons-clipboard"></span>
                            </div>
                            <a href="#" id="lkn-wcip-close-modal-btn" onclick="lkn_wcip_display_modal()">&times;</a>
                        </div>
                    </div>
                    <div class="action-btn">
                        <p class="submit">
                            <button type="button" class="button lkn_swcip_share_btn_form"
                                onclick="lkn_wcip_display_modal()"><?php esc_attr_e('Share payment link', 'wc-invoice-payment'); ?></button>
                        </p>
                        <p class="submit">
                            <button type="button" class="button lkn_wcip_delete_btn_form"
                                onclick="lkn_wcip_delete_invoice()"><?php esc_attr_e('Delete'); ?></button>
                        </p>
                        <?php submit_button(__('Update')); ?>
                    </div>
                </div>
                <!-- Invoice charges -->
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Price', 'wc-invoice-payment'); ?>
                    </h2>
                    <div id="wcip-invoice-price-row" class="invoice-column-wrap">
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
                                            class="regular-text" required
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
                                            class="regular-text" required readonly
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
                                            required readonly
                                            value="<?php echo esc_attr(number_format($item->get_total(), $decimalQtd, $decimalSeparator, $thousandSeparator)); ?>">
                                    </div>
                                <?php
                                }

                                if ('pending' === $orderStatus) {
                                ?>
                                    <div class="input-row-wrap">
                                        <button type="button" class="btn btn-delete"
                                            onclick="lkn_wcip_remove_amount_row(<?php echo esc_attr($c); ?>)"><span
                                                class="dashicons dashicons-trash"></span></button>
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
                            <button type="button" class="btn btn-add-line"
                                onclick="lkn_wcip_add_amount_row()"><?php esc_attr_e('Add line', 'wc-invoice-payment'); ?></button>
                        </div>
                    <?php
                    } ?>
                </div>
                <div style="width: 100%;"></div>
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Footer notes', 'wc-invoice-payment'); ?>
                    </h2>
                    <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                        <div class="input-row-wrap">
                            <label><?php esc_attr_e('Details in HTML', 'wc-invoice-payment'); ?></label>
                            <textarea name="lkn-wc-invoice-payment-footer-notes"
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
        wp_enqueue_script($this->plugin_name . '-edit', plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-invoice-edit.js', array(), $this->version, 'all');
        wp_enqueue_style($this->plugin_name . '-edit', plugin_dir_url(__FILE__) . 'css/wc-invoice-payment-invoice-edit.css', array(), $this->version, 'all');

        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        if (! empty($_POST) && ! wp_verify_nonce($_POST['wcip_rest_nonce'], 'wp_rest')) {
            return;
        }

        wp_enqueue_editor();
        wp_create_nonce('wp_rest');

        $invoiceId = sanitize_text_field($_GET['invoice']);

        $decimalSeparator = wc_get_price_decimal_separator();
        $thousandSeparator = wc_get_price_thousand_separator();
        $decimalQtd = wc_get_price_decimals();

        // Get all translated WooCommerce order status
        $statusWc = array();
        $statusWc[] = array('status' => 'wc-pending', 'label' => _x('Pending payment', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-processing', 'label' => _x('Processing', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-on-hold', 'label' => _x('On hold', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-completed', 'label' => _x('Completed', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-cancelled', 'label' => _x('Cancelled', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-refunded', 'label' => _x('Refunded', 'Order status', 'woocommerce'));
        $statusWc[] = array('status' => 'wc-failed', 'label' => _x('Failed', 'Order status', 'woocommerce'));

        $c = 0;
        $order = wc_get_order($invoiceId);

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
            $preview_url = esc_url(WC_PAYMENT_INVOICE_ROOT_URL . "includes/templates/$template_id/preview.webp");

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

        $gateways = WC()->payment_gateways->payment_gateways();
        $enabled_gateways = array();

        // Get all WooCommerce enabled gateways
        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ('yes' == $gateway->enabled) {
                    $enabled_gateways[] = $gateway;
                }
            }
        } ?>
        <div class="wrap">
            <h1><?php esc_attr_e('Edit subscription', 'wc-invoice-payment'); ?>
            </h1>
            <?php settings_errors(); ?>
            <form
                action="<?php menu_page_url('edit-invoice&invoice=' . $invoiceId); ?>"
                method="post" class="wcip-form-wrap">
                <input id="wcip_rest_nonce" name="wcip_rest_nonce" type="hidden"
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
                                <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" class="regular-text"
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
                                <label
                                    for="lkn_wcip_default_payment_method_input"><?php esc_attr_e('Default payment method', 'wc-invoice-payment'); ?></label>
                                <select name="lkn_wcip_default_payment_method" id="lkn_wcip_default_payment_method_input"
                                    class="regular-text">
                                    <option value="multiplePayment" selected>
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
                                <select name="lkn_wcip_currency" id="lkn_wcip_currency_input" class="regular-text">
                                    <?php
                                    foreach ($currency_codes as $code) {
                                        $currency_name = $currencies[$code];
                                        $selected = ($order->get_currency() === $code) ? 'selected' : ''; // Verifica se a opção deve ser selecionada

                                        if ($order->get_currency() === $code) {
                                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_attr($code) . ' - ' . esc_attr($currency_name) . '</option>';
                                        }
                                    } ?>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label for="lkn_wcip_select_invoice_template">
                                    <?php esc_attr_e('Invoice PDF template', 'wc-invoice-payment'); ?>
                                </label>
                                <select name="lkn_wcip_select_invoice_template" id="lkn_wcip_select_invoice_template"
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
                                <div style="position: relative;"><img id="lkn-wcip-preview-img" /></div>
                            </div>
                        </div>
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_name_input"><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                <input name="lkn_wcip_name" type="text" id="lkn_wcip_name_input" class="regular-text" required
                                    value="<?php echo esc_attr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>">
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_email_input"><?php esc_attr_e('Email', 'wc-invoice-payment'); ?></label>
                                <input name="lkn_wcip_email" type="email" id="lkn_wcip_email_input" class="regular-text"
                                    required
                                    value="<?php echo esc_html($order->get_billing_email()); ?>">
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_extra_data"><?php esc_attr_e('Extra data', 'wc-invoice-payment'); ?></label>
                                <textarea name="lkn_wcip_extra_data" id="lkn_wcip_extra_data"
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
                                <input id="lkn_wcip_exp_date_input" type="date" name="lkn_wcip_exp_date"
                                    value="<?php echo esc_attr($order->get_meta('lkn_exp_date')); ?>"
                                    min="<?php echo esc_attr(gmdate('Y-m-d')); ?>"
                                    readonly>
                            </div>
                            <div class="input-column-wrap">
                                <a class="lkn_wcip_generate_pdf_btn" href="#"
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
                                    <a class="lkn_wcip_cancel_subscription_btn" href="#" onclick="lkn_wcip_cancel_subscription()"
                                        data-invoice-id="<?php echo esc_attr($invoiceId); ?>">
                                        <?php esc_attr_e('Cancel subscription', 'wc-invoice-payment'); ?>
                                    </a>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div id="lkn-wcip-share-modal" style="display: none;">
                        <div id="lkn-wcip-share-modal-content">
                            <h3 id="lkn-wcip-share-title">
                                <?php esc_attr_e('Share with', 'wc-invoice-payment'); ?>
                            </h3>
                            <div id="lkn-wcip-share-buttons">
                                <a href="#" id="lkn-wcip-whatsapp-share"
                                    class="lkn-wcip-share-icon dashicons dashicons-whatsapp"
                                    onclick="lkn_wcip_open_popup('whatsapp', '<?php echo esc_url($checkoutUrl); ?>')"></a>
                                <a href="#" id="lkn-wcip-twitter-share" class="lkn-wcip-share-icon dashicons dashicons-twitter"
                                    onclick="lkn_wcip_open_popup('twitter', '<?php echo esc_url($checkoutUrl); ?>')"></a>
                                <a href="mailto:?subject=Link de fatura&body=<?php echo esc_url($checkoutUrl); ?>"
                                    id="lkn-wcip-email-share" class="lkn-wcip-share-icon dashicons dashicons-email-alt"
                                    target="_blank">
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="action-btn">
                        <p class="submit">
                            <button type="button" class="button lkn_wcip_delete_btn_form"
                                onclick="lkn_wcip_delete_invoice()"><?php esc_attr_e('Delete'); ?></button>
                        </p>
                        <?php submit_button(__('Update')); ?>
                    </div>
                </div>
                <!-- Generated invoices  -->
                <div class="wcip-invoice-data wcip-postbox" id="lknListGeneratedInvoicesPostBox">
                    <span class="text-bold"><?php esc_attr_e('Generated invoices', 'wc-invoice-payment'); ?></span>
                    <span><?php echo esc_attr($order->get_meta('lkn_wcip_subscription_initial_limit')) ?><?php echo esc_attr($limit) ?></span>
                    <hr>
                    <div class="wcip-row">
                        <div class="input-row-wrap" id="lknListGeneratedInvoices">
                            <?php
                            $index = 1;
                            //Lista as faturas geradas por essa assinatura
                            foreach ($orders as $order) {
                            ?>
                                <p>
                                    <?php
                                    if ($i != 0) {
                                        echo ' | ';
                                    }
                                    ?>
                                    <a target="_blank">
                                        <?php
                                        echo esc_attr($order->get_id());
                                        ?>
                                    </a>
                                    <?php
                                    if ($index == count($orders)) {
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
                    <div id="wcip-invoice-price-row" class="invoice-column-wrap">
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
                                            class="regular-text" required
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
                                            class="regular-text" required
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
                                        <button type="button" class="btn btn-delete"
                                            onclick="lkn_wcip_remove_amount_row(<?php echo esc_attr($c); ?>)"><span
                                                class="dashicons dashicons-trash"></span></button>
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
                    <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                        <div class="input-row-wrap">
                            <label><?php esc_attr_e('Details in HTML', 'wc-invoice-payment'); ?></label>
                            <textarea name="lkn-wc-invoice-payment-footer-notes"
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
            $decoded_message = urldecode($_GET['message']);

            echo '<div class="lkn_wcip_notice_positive">' . esc_html($decoded_message) . '</div>';
        }
    ?>
        <form id="invoices-filter" method="POST">
            <input id="wcip_rest_nonce" type="hidden"
                value="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">

            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div>
                    <?php
                    $object = new Lkn_Wcip_List_Table();
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
        <form id="invoices-filter" method="POST">
            <input id="wcip_rest_nonce" type="hidden"
                value="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">

            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div>
                    <?php
                    $object = new Lkn_Wcip_List_Table();
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

        $currencies = get_woocommerce_currencies();
        $currency_codes = array_keys($currencies);
        sort($currency_codes);

        $active_currency = get_woocommerce_currency();

        $gateways = WC()->payment_gateways->payment_gateways();
        $enabled_gateways = array();

        $templates_list = $this->handler_invoice_templates->get_templates_list();

        $html_templates_list = implode(array_map(function ($template): string {
            $template_id = esc_attr($template['id']);
            $friendly_template_name = esc_html($template['friendly_name']);
            $preview_url = esc_url(WC_PAYMENT_INVOICE_ROOT_URL . "includes/templates/$template_id/preview.webp");

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
        } ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors(); ?>
            <form
                action="<?php menu_page_url('new-invoice'); ?>"
                method="post" class="wcip-form-wrap">
                <?php wp_nonce_field('lkn_wcip_add_invoice', 'nonce'); ?>
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Invoice details', 'wc-invoice-payment'); ?>
                    </h2>
                    <div class="invoice-row-wrap">
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_payment_status_input"><?php esc_attr_e('Status', 'wc-invoice-payment'); ?></label>
                                <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" class="regular-text">
                                    <option value="wc-pending">
                                        <?php echo esc_html(_x('Pending payment', 'Order status', 'woocommerce')); ?>
                                    </option>
                                    <option value="wc-processing">
                                        <?php echo esc_html(_x('Processing', 'Order status', 'woocommerce')); ?>
                                    </option>
                                    <option value="wc-on-hold">
                                        <?php echo esc_html(_x('On hold', 'Order status', 'woocommerce')); ?>
                                    </option>
                                    <option value="wc-completed">
                                        <?php echo esc_html(_x('Completed', 'Order status', 'woocommerce')); ?>
                                    </option>
                                    <option value="wc-cancelled">
                                        <?php echo esc_html(_x('Cancelled', 'Order status', 'woocommerce')); ?>
                                    </option>
                                    <option value="wc-refunded">
                                        <?php echo esc_html(_x('Refunded', 'Order status', 'woocommerce')); ?>
                                    </option>
                                    <option value="wc-failed">
                                        <?php echo esc_html(_x('Failed', 'Order status', 'woocommerce')); ?>
                                    </option>
                                </select>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_default_payment_method_input"><?php esc_attr_e('Default payment method', 'wc-invoice-payment'); ?></label>
                                <select name="lkn_wcip_default_payment_method" id="lkn_wcip_default_payment_method_input"
                                    class="regular-text">
                                    <option value="multiplePayment" selected>
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
                                <select name="lkn_wcip_currency" id="lkn_wcip_currency_input" class="regular-text">
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
                                <select name="lkn_wcip_select_invoice_template" id="lkn_wcip_select_invoice_template"
                                    class="regular-text" required>
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
                                <div style="position: relative;"><img id="lkn-wcip-preview-img" /></div>
                            </div>
                        </div>
                        <div class="invoice-column-wrap">
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_name_input"><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                <input name="lkn_wcip_name" type="text" id="lkn_wcip_name_input" class="regular-text" required>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_email_input"><?php esc_attr_e('Email', 'wc-invoice-payment'); ?></label>
                                <input name="lkn_wcip_email" type="email" id="lkn_wcip_email_input" class="regular-text"
                                    required>
                            </div>
                            <div class="input-row-wrap">
                                <label
                                    for="lkn_wcip_extra_data"><?php esc_attr_e('Extra data', 'wc-invoice-payment'); ?></label>
                                <textarea name="lkn_wcip_extra_data" id="lkn_wcip_extra_data" class="regular-text"></textarea>
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
                                <option value="no_action" selected>
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
                            <input id="lkn_wcip_exp_date_input" type="date" name="lkn_wcip_exp_date"
                                min="<?php echo esc_attr(gmdate('Y-m-d')); ?>" required>
                        </div>
                        <div class="input-row-wrap" id="twoCheckboxDiv">
                            <label for="lkn_wcip_subscription_product">
                                <input type="checkbox" name="lkn_wcip_subscription_product" id="lkn_wcip_subscription_product" <?php echo esc_attr($invoiceChecked) ?>>
                                <?php esc_attr_e('Subscription', 'wc-invoice-payment'); ?>
                            </label>
                        </div>
                        <div class="input-row-wrap" id="lkn_wcip_subscription_interval">
                            <label
                                for="lkn_wcip_subscription_interval_number"><?php esc_attr_e('Subscription Interval', 'wc-invoice-payment'); ?></label>
                            <div class="lkn_wcip_subscription_interval_div">
                                <input type="number" min="1" name="lkn_wcip_subscription_interval_number"
                                    id="lkn_wcip_subscription_interval_number" value="1">
                                <select name="lkn_wcip_subscription_interval_type">
                                    <option value="day">
                                        <?php esc_attr_e('Days', 'wc-invoice-payment'); ?>
                                    </option>
                                    <option value="week">
                                        <?php esc_attr_e('Weeks', 'wc-invoice-payment'); ?>
                                    </option>
                                    <option value="month" selected>
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
                                <input type="checkbox" name="lkn_wcip_subscription_limit_checkbox" id="lkn_wcip_subscription_limit_checkbox">
                                <?php esc_attr_e('Limit number of invoices', 'wc-invoice-payment'); ?>
                            </label>
                        </div>
                        <?php
                        woocommerce_wp_text_input(
                            array(
                                'id' => 'lkn_wcip_subscription_limit',
                                'name' => 'lkn_wcip_subscription_limit',
                                'label' => __('Subscription limit', 'wc-invoice-payment'),
                                'desc_tip' => 'true',
                                'description' => __('Set a limit for the number of invoices that will be generated for the subscription, by default,  there is no limit.', 'wc-invoice-payment'),
                                'value' => 0,
                                'type' => 'number',
                                'custom_attributes' => array(
                                    'min'  => '0',
                                    'step' => '1.0',
                                ),
                            )
                        );
                        ?>
                    </div>
                    <script>
                        //Valida se a checkbox de assinatura está ativada para mostrar campos
                        lkn_wcip_display_subscription_inputs()
                    </script>
                    <div class="action-btn">
                        <?php submit_button(__('Save')); ?>
                    </div>
                </div>
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Price', 'wc-invoice-payment'); ?>
                    </h2>
                    <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                        <div class="price-row-wrap price-row-0">
                            <div class="input-row-wrap">
                                <label><?php esc_attr_e('Name', 'wc-invoice-payment'); ?></label>
                                <input name="lkn_wcip_name_invoice_0" type="text" id="lkn_wcip_name_invoice_0"
                                    class="regular-text" required>
                            </div>
                            <div class="input-row-wrap">
                                <label><?php esc_attr_e('Amount', 'wc-invoice-payment'); ?></label>
                                <input name="lkn_wcip_amount_invoice_0" type="tel" id="lkn_wcip_amount_invoice_0"
                                    class="regular-text lkn_wcip_amount_input"
                                    oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');"
                                    required>
                            </div>
                            <div class="input-row-wrap">
                                <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(0)"><span
                                        class="dashicons dashicons-trash"></span></button>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="invoice-row-wrap">
                        <button type="button" class="btn btn-add-line"
                            onclick="lkn_wcip_add_amount_row()"><?php esc_attr_e('Add line', 'wc-invoice-payment'); ?></button>
                    </div>
                </div>
                <div style="width: 100%;"></div>
                <div class="wcip-invoice-data">
                    <h2 class="title">
                        <?php esc_attr_e('Footer notes', 'wc-invoice-payment'); ?>
                    </h2>
                    <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                        <div class="input-row-wrap">
                            <label><?php esc_attr_e('Details in HTML', 'wc-invoice-payment'); ?></label>
                            <textarea name="lkn-wc-invoice-payment-footer-notes" id="lkn-wc-invoice-payment-footer-notes"
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
        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            if (wp_verify_nonce($_POST['nonce'], 'lkn_wcip_add_invoice') && $_POST['nonce']) {
                $decimalSeparator = wc_get_price_decimal_separator();
                $thousandSeparator = wc_get_price_thousand_separator();

                $invoices = array();
                $totalAmount = 0;
                $c = 0;

                // Invoice items
                foreach ($_POST as $key => $value) {
                    // Get invoice description
                    if (preg_match('/lkn_wcip_name_invoice_/i', $key)) {
                        $invoices[$c]['desc'] = sanitize_text_field($value);
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
                $paymentStatus = sanitize_text_field($_POST['lkn_wcip_payment_status']);
                $paymentMethod = sanitize_text_field($_POST['lkn_wcip_default_payment_method']);
                $isSubscription = sanitize_text_field($_POST['lkn_wcip_subscription_product']);
                $intarvalNumber = sanitize_text_field($_POST['lkn_wcip_subscription_interval_number']);
                $intarvalType = sanitize_text_field($_POST['lkn_wcip_subscription_interval_type']);
                $subscriptionLimit = sanitize_text_field($_POST['lkn_wcip_subscription_limit']);
                $currency = sanitize_text_field($_POST['lkn_wcip_currency']);
                $name = sanitize_text_field($_POST['lkn_wcip_name']);
                $firstName = explode(' ', $name)[0];
                $lastname = substr(strstr($name, ' '), 1);
                $email = sanitize_email($_POST['lkn_wcip_email']);
                $expDate = sanitize_text_field($_POST['lkn_wcip_exp_date']);
                $iniDate = new DateTime();
                $extraData = sanitize_text_field($_POST['lkn_wcip_extra_data']);
                $footerNotes = wp_kses_post($_POST['lkn-wc-invoice-payment-footer-notes']);

                $order = wc_create_order(
                    array(
                        'status' => $paymentStatus,
                        'customer_id' => 0,
                        'customer_note' => '',
                        'total' => $totalAmount,
                    )
                );

                $order->update_meta_data('wcip_extra_data', $extraData);
                $order->update_meta_data('wcip_footer_notes', $footerNotes);
                $order->update_meta_data('wcip_footer_notes', $footerNotes);

                $pdfTemplateId = sanitize_text_field($_POST['lkn_wcip_select_invoice_template']);
                $order->update_meta_data('wcip_select_invoice_template_id', $pdfTemplateId);

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
                    $isSubscription = sanitize_text_field($_POST['lkn_wcip_subscription_product']);
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
                    $subscription_class = new Wc_Payment_Invoice_Subscription();
                    $subscription_class->validate_product($orderId);
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
                if (isset($_POST['lkn_wcip_form_actions']) && sanitize_text_field($_POST['lkn_wcip_form_actions']) === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    $order->add_order_note(__('Order details manually sent to customer.', 'woocommerce'), false, true);
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
        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            // Validates WP nonce
            if (wp_verify_nonce($_POST['nonce'], 'lkn_wcip_edit_invoice') && $_POST['nonce']) {
                $decimalSeparator = wc_get_price_decimal_separator();
                $thousandSeparator = wc_get_price_thousand_separator();

                $invoiceId = sanitize_text_field($_GET['invoice']);
                $order = wc_get_order($invoiceId);
                $order->remove_order_items();

                $invoices = array();
                $totalAmount = 0;
                $c = 0;

                // Invoice items
                foreach ($_POST as $key => $value) {
                    // Get invoice description
                    if (preg_match('/lkn_wcip_name_invoice_/i', $key)) {
                        $invoices[$c]['desc'] = sanitize_text_field($value);
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
                $paymentStatus = sanitize_text_field($_POST['lkn_wcip_payment_status']);
                $paymentMethod = sanitize_text_field($_POST['lkn_wcip_default_payment_method']);
                $currency = sanitize_text_field($_POST['lkn_wcip_currency']);
                $name = sanitize_text_field($_POST['lkn_wcip_name']);
                $firstName = explode(' ', $name)[0];
                $lastname = substr(strstr($name, ' '), 1);
                $email = sanitize_email($_POST['lkn_wcip_email']);
                $expDate = sanitize_text_field($_POST['lkn_wcip_exp_date']);
                $pdfTemplateId = sanitize_text_field($_POST['lkn_wcip_select_invoice_template']);
                $extraData = wp_kses($_POST['lkn_wcip_extra_data'], array('br' => array()));
                $footerNotes = wp_kses_post($_POST['lkn-wc-invoice-payment-footer-notes']);

                $order->update_meta_data('wcip_extra_data', $extraData);
                $order->update_meta_data('wcip_footer_notes', $footerNotes);
                $order->update_meta_data('wcip_select_invoice_template_id', $pdfTemplateId);

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
                $order->set_billing_email($email);
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
                if (isset($_POST['lkn_wcip_form_actions']) && sanitize_text_field($_POST['lkn_wcip_form_actions']) === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    // Note the event.
                    $order->add_order_note(__('Order details manually sent to customer.', 'woocommerce'), false, true);
                }

                // Success message
                echo '<div class="lkn_wcip_notice_positive">' . esc_html(__('Invoice successfully saved', 'wc-invoice-payment')) . '</div>';
            } else {
                // Error message
                echo '<div class="lkn_wcip_notice_negative">' . esc_html(__('Error on invoice generation', 'wc-invoice-payment')) . '</div>';
            }
        } elseif ('GET' == $_SERVER['REQUEST_METHOD'] && isset($_GET['lkn_wcip_delete'])) {
            // Validates request for deleting invoice
            if ('true' === $_GET['lkn_wcip_delete']) {
                $invoiceDelete = array(sanitize_text_field($_GET['invoice']));
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
        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            // Validates WP nonce
            if (wp_verify_nonce($_POST['nonce'], 'lkn_wcip_edit_invoice') && $_POST['nonce']) {
                $decimalSeparator = wc_get_price_decimal_separator();
                $thousandSeparator = wc_get_price_thousand_separator();

                $invoiceId = sanitize_text_field($_GET['invoice']);
                $order = wc_get_order($invoiceId);
                $order->remove_order_items();

                $invoices = array();
                $totalAmount = 0;
                $c = 0;

                // Invoice items
                foreach ($_POST as $key => $value) {
                    // Get invoice description
                    if (preg_match('/lkn_wcip_name_invoice_/i', $key)) {
                        $invoices[$c]['desc'] = sanitize_text_field($value);
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
                $paymentStatus = sanitize_text_field($_POST['lkn_wcip_payment_status']);
                $paymentMethod = sanitize_text_field($_POST['lkn_wcip_default_payment_method']);
                $currency = sanitize_text_field($_POST['lkn_wcip_currency']);
                $name = sanitize_text_field($_POST['lkn_wcip_name']);
                $firstName = explode(' ', $name)[0];
                $lastname = substr(strstr($name, ' '), 1);
                $email = sanitize_email($_POST['lkn_wcip_email']);
                $expDate = sanitize_text_field($_POST['lkn_wcip_exp_date']);
                $pdfTemplateId = sanitize_text_field($_POST['lkn_wcip_select_invoice_template']);
                $extraData = wp_kses($_POST['lkn_wcip_extra_data'], array('br' => array()));
                $footerNotes = wp_kses_post($_POST['lkn-wc-invoice-payment-footer-notes']);

                $order->update_meta_data('wcip_extra_data', $extraData);
                $order->update_meta_data('wcip_footer_notes', $footerNotes);
                $order->update_meta_data('wcip_select_invoice_template_id', $pdfTemplateId);

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
                $order->set_billing_email($email);
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
                if (isset($_POST['lkn_wcip_form_actions']) && sanitize_text_field($_POST['lkn_wcip_form_actions']) === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    // Note the event.
                    $order->add_order_note(__('Order details manually sent to customer.', 'woocommerce'), false, true);
                }

                // Success message
                echo '<div class="lkn_wcip_notice_positive">' . esc_html(__('Invoice successfully saved', 'wc-invoice-payment')) . '</div>';
            } else {
                // Error message
                echo '<div class="lkn_wcip_notice_negative">' . esc_html(__('Error on invoice generation', 'wc-invoice-payment')) . '</div>';
            }
        } elseif ('GET' == $_SERVER['REQUEST_METHOD'] && isset($_GET['lkn_wcip_delete'])) {
            // Validates request for deleting invoice
            if ('true' === $_GET['lkn_wcip_delete']) {
                $invoiceDelete = array(sanitize_text_field($_GET['invoice']));
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