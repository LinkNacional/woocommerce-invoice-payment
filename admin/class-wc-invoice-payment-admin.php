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
    <h1> <?php esc_html_e('Welcome to my custom admin page.', 'my-plugin-textdomain'); ?> </h1>
    <form method="POST" action="options.php">
        
    <?php
        settings_fields('wc-invoice-payment');
        do_settings_sections('wc-invoice-payment');
        submit_button(); ?>
    </form>
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
        } ?>
      <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="<?php menu_page_url('wporg') ?>" method="post">
        <table class="form-table" role="presentation">
            <tbody>
            <?php wp_nonce_field('save_config_whmcs_login', 'nonce'); ?>
            <h2 class="title"><?php _e('Whmcs API information', 'whmcs-wordpress')?></h2>
            <p><?php _e('API information can be obtained by following the step by step ', 'whmcs-wordpress')?> <a href="https://docs.whmcs.com/API_Authentication_Credentials#Creating_Admin_API_Authentication_Credentials"><?php _e('here', 'whmcs-wordpress') ?></a> </p>
                <tr>
                    <th scope="row">
                        <label for="whmcs_login_identifier"><?php _e('WHMCS API identifier', 'whmcs-wordpress')?></label>
                    </th>
                    <td>
                        <input name="whmcs_login_identifier" type="password" id="whmcs_login_identifier" onfocus="this.value='';" value="<?php echo get_option('whmcs_login_identifier') ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="whmcs_login_secret"><?php _e('WHMCS API secret', 'whmcs-wordpress')?></label>
                    </th>
                    <td>
                        <input name="whmcs_login_secret" type="password" id="whmcs_login_secret" onfocus="this.value='';" value="<?php echo get_option('whmcs_login_secret') ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="whmcs_login_url"><?php _e('WHMCS url', 'whmcs-wordpress')?></label>
                    </th>
                    <td>
                        <input name="whmcs_login_url" type="text" id="whmcs_login_url" value="<?php echo get_option('whmcs_login_url') ?>" class="regular-text">
                    </td>
                </tr>
 
                    <th scope="row">
                        <label for="whmcs_login_register_user"><?php _e('link to register a new WHMCS user', 'whmcs-wordpress')?></label>
                    </th>
                    <td>
                        <input name="whmcs_login_register_user" type="text" id="whmcs_login_register_user" value="<?php echo get_option('whmcs_login_register_user') ?>" class="regular-text">
                    </td>
                </tr>
            </tbody>
        </table>
            <?php
        settings_fields('whmcs_login');
        do_settings_sections('whmcs_login_session');
        submit_button(__('Save'), 'textdomain'); ?>
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
