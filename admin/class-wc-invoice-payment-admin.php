<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.linknacional.com/
 * @since      1.0.0
 *
 * @package    Wc_Payment_Invoice
 * @subpackage Wc_Payment_Invoice/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wc_Payment_Invoice
 * @subpackage Wc_Payment_Invoice/admin
 * @author     Link Nacional
 */
class Wc_Payment_Invoice_Admin {
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', [$this, 'add_setting_session']);
        add_action('admin_menu', [$this, 'add_new_invoice_submenu_section']);
        // add_action('admin_init', [$this, 'my_settings_init']);
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        /**
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

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wc-invoice-payment-admin.css', [], $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        /**
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

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-admin.js', ['jquery'], $this->version, false);
    }

    /**
     * Generates custom menu section and setting page
     *
     * @return void
     */
    public function add_setting_session() {
        add_menu_page(
            __('Listar faturas', 'wc-invoice-payment'),
            __('WooCommerce Invoice Payment', 'wc-invoice-payment'),
            'manage_options',
            'wc-invoice-payment',
            false,
            'dashicons-money-alt',
            50
        );

        add_submenu_page(
            'wc-invoice-payment',
            __('Listar faturas', 'wc-invoice-payment'),
            __('Faturas', 'wc-invoice-payment'),
            'manage_options',
            'wc-invoice-payment',
            [$this, 'render_invoice_list_page'],
            1
        );
    }

    /**
     * Render html page for invoice listing
     *
     * @return void
     */
    public function render_invoice_list_page() {
        if (!current_user_can('manage_options')) {
            return;
        } ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
    <?php
        settings_fields('wc-invoice-payment');
        do_settings_sections('wc-invoice-payment');
        submit_button(); ?>
    </div>
    <?php
    }

    /**
     * Add new settings to existing sections
     *
     * @return void
     */
    public function my_settings_init() {
        /* add_settings_section(
            'sample_page_setting_section',
            __('Custom settings', 'my-textdomain'),
            [$this, 'my_setting_section_callback_function'],
            'wc-invoice-payment'
        );

        add_settings_field(
            'my_setting_field',
            __('My custom setting field', 'my-textdomain'),
            [$this, 'my_setting_markup'],
            'wc-invoice-payment',
            'sample_page_setting_section'
        );

        register_setting('wc-invoice-payment', 'my_setting_field'); */
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function my_setting_section_callback_function() {
        echo '<p>Intro text for our settings section</p>';
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function my_setting_markup() {
        ?>
    <label for="my-input"><?php _e('My Input'); ?></label>
    <input type="text" id="my_setting_field" name="my_setting_field" value="<?php echo get_option('my_setting_field'); ?>">
    <?php
    }

    /**
     * Adds new invoice submenu page
     *
     * @return void
     */
    public function add_new_invoice_submenu_section() {
        $hookname = add_submenu_page(
            'wc-invoice-payment',
            'Nova fatura',
            'Nova fatura',
            'manage_options',
            'new-invoice',
            [$this, 'new_invoice_form'],
            2
        );

        add_action('load-' . $hookname, [$this, 'form_submit_handle']);
    }

    /**
     * Generates new form for invoice creation
     *
     * @return void
     */
    public function new_invoice_form() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $currencies = get_woocommerce_currencies();
        $active_currency = get_woocommerce_currency();
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $enabled_gateways = [];

        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ($gateway->enabled == 'yes') {
                    $enabled_gateways[] = $gateway;
                }
            }
        }

        // $enabled_gateways[1]->id;
        // $enabled_gateways[1]->title;?>
      <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="<?php menu_page_url('new-invoice') ?>" method="post" class="wc-invoice-data">
        <h2 class="title"><?php _e('Invoice details', 'whmcs-wordpress')?> <?php echo '#' . get_option('lkn_invoice_max_id', 1) ?></h2>
        <div class="invoice-row-wrap">
            <div class="invoice-column-wrap">
                <div class="input-row-wrap">
                    <label for="lkn_wcip_payment_status"><?php _e('Status', 'whmcs-wordpress')?></label>
                    <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" value="<?php echo get_option('lkn_wcip_payment_status') ?>" class="regular-text">
                        <option value="wc-pending"><?php echo _x('Pending payment', 'Order status', 'woocommerce'); ?></option>
                        <option value="wc-processing"><?php echo _x('Processing', 'Order status', 'woocommerce'); ?></option>
                        <option value="wc-on-hold"><?php echo _x('On hold', 'Order status', 'woocommerce'); ?></option>
                        <option value="wc-completed"><?php echo _x('Completed', 'Order status', 'woocommerce'); ?></option>
                        <option value="wc-cancelled"><?php echo _x('Cancelled', 'Order status', 'woocommerce'); ?></option>
                        <option value="wc-refunded"><?php echo _x('Refunded', 'Order status', 'woocommerce'); ?></option>
                        <option value="wc-failed"><?php echo _x('Failed', 'Order status', 'woocommerce'); ?></option>
                    </select>
                </div>
                <div class="input-row-wrap">
                    <label for="lkn_wcip_default_payment_method"><?php _e('Default payment method', 'whmcs-wordpress')?></label>
                    <select name="lkn_wcip_default_payment_method" id="lkn_wcip_default_payment_method_input" value="<?php echo get_option('lkn_wcip_default_payment_method') ?>" class="regular-text">
                        <option value="pagseguro">Cartão de crédito PagSeguro</option>
                    </select>
                </div>
                <div class="input-row-wrap">
                    <label for="lkn_wcip_currency"><?php _e('Currency', 'whmcs-wordpress')?></label>
                    <select name="lkn_wcip_currency" id="lkn_wcip_currency_input" value="<?php echo get_option('lkn_wcip_currency') ?>" class="regular-text">
                        <option value="BRL" selected>Real Brasileiro - R$</option>
                        <option value="USD">Dólar Americano - $</option>
                    </select>
                </div>
            </div>
            <div class="invoice-column-wrap">
                <div class="input-row-wrap">
                    <label for="whmcs_login_identifier"><?php _e('Status', 'whmcs-wordpress')?></label>
                    <input name="whmcs_login_identifier" type="password" id="whmcs_login_identifier" onfocus="this.value='';" value="<?php echo get_option('whmcs_login_identifier') ?>" class="regular-text">
                </div>
                <div class="input-row-wrap">
                    <label for="whmcs_login_identifier"><?php _e('Status', 'whmcs-wordpress')?></label>
                    <input name="whmcs_login_identifier" type="password" id="whmcs_login_identifier" onfocus="this.value='';" value="<?php echo get_option('whmcs_login_identifier') ?>" class="regular-text">
                </div>
            </div>
        </div>
            <?php
        // settings_fields('whmcs_login');
        // do_settings_sections('whmcs_login_session');
        // submit_button(__('Save'), 'textdomain');?>
        </form>
    </div>
    <?php
    }

    /**
     * Handles submission from invoice form
     *
     * @return void
     */
    public function form_submit_handle() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_POST['nonce'] && wp_verify_nonce($_POST['nonce'], 'save_config_whmcs_login')) {
                if (substr($_POST['whmcs_login_url'], -1) != '/') {
                    $_POST['whmcs_login_url'] = $_POST['whmcs_login_url'] . '/';
                }
                if ($_POST['whmcs_login_secret'] != '') {
                    update_option('whmcs_login_secret', $_POST['whmcs_login_secret']);
                }
                if ($_POST['whmcs_login_identifier'] != '') {
                    update_option('whmcs_login_identifier', $_POST['whmcs_login_identifier']);
                }
                update_option('whmcs_login_url', $_POST['whmcs_login_url']);
                update_option('whmcs_login_register_user', $_POST['whmcs_login_register_user']);
                update_option('whmcs_login_password_reset', $_POST['whmcs_login_password_reset']);
            }
        }
    }
}
