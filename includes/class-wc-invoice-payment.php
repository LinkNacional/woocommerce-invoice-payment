<?php

/**
 * The file that defines the core plugin class.
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @see       https://www.linknacional.com/
 * @since      1.0.1
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.1
 *
 * @author     Link Nacional
 */
final class Wc_Payment_Invoice
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     *
     * @var Wc_Payment_Invoice_Loader maintains and registers all hooks for the plugin
     */
    private $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     *
     * @var string the string used to uniquely identify this plugin
     */
    private $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     *
     * @var string the current version of the plugin
     */
    private $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('WC_PAYMENT_INVOICE_VERSION')) {
            $this->version = WC_PAYMENT_INVOICE_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'wc-invoice-payment';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run(): void
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     *
     * @return string the name of the plugin
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     *
     * @return Wc_Payment_Invoice_Loader orchestrates the hooks of the plugin
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     *
     * @return string the version number of the plugin
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Wc_Payment_Invoice_Loader. Orchestrates the hooks of the plugin.
     * - Wc_Payment_Invoice_i18n. Defines internationalization functionality.
     * - Wc_Payment_Invoice_Admin. Defines all hooks for the admin area.
     * - Wc_Payment_Invoice_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     */
    private function load_dependencies(): void
    {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(__DIR__) . 'includes/class-wc-invoice-payment-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(__DIR__) . 'includes/class-wc-invoice-payment-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(__DIR__) . 'admin/class-wc-invoice-payment-admin.php';

        /**
         * The class responsible for rendering the invoice table.
         */
        require_once plugin_dir_path(__DIR__) . 'admin/class-wc-invoice-payment-table.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(__DIR__) . 'public/class-wc-invoice-payment-public.php';

        require_once plugin_dir_path(__DIR__) . 'includes/class-wc-invoice-payment-rest.php';
        require_once plugin_dir_path(__DIR__) . 'includes/class-wc-invoice-payment-subscription.php';
        require_once plugin_dir_path(__DIR__) . 'admin/class-wc-invoice-payment-pdf-templates.php';

        $this->loader = new Wc_Payment_Invoice_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Wc_Payment_Invoice_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     */
    private function set_locale(): void
    {
        $plugin_i18n = new Wc_Payment_Invoice_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     */
    private function define_admin_hooks(): void
    {
        $plugin_admin = new Wc_Payment_Invoice_Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('lkn_wcip_cron_hook', $plugin_admin, 'check_invoice_exp_date', 10, 1);

        $api_handler = new Wc_Payment_Invoice_Loader_Rest();
        $this->loader->add_action('rest_api_init', $api_handler, 'register_routes');
        $subscription_class = new Wc_Payment_Invoice_Subscription();
        $this->loader->add_action('product_type_options', $subscription_class, 'add_checkbox');
        $this->loader->add_filter('woocommerce_product_data_tabs', $subscription_class, 'add_tab');
        $this->loader->add_action('woocommerce_checkout_order_processed', $subscription_class, 'validate_product');
        $this->loader->add_action('woocommerce_store_api_checkout_order_processed', $subscription_class, 'validate_product');
        $this->loader->add_action('woocommerce_product_data_panels', $subscription_class, 'add_text_field_to_subscription_tab');
        $this->loader->add_action('woocommerce_process_product_meta', $subscription_class, 'save_subscription_fields');
        $this->loader->add_action('wp_ajax_cancel_subscription', $subscription_class, 'cancel_subscription_callback');

        $this->loader->add_action('generate_invoice_event', $subscription_class, 'create_next_invoice', 10, 1);
        
    }

    function custom_email_verification_required($verification_required)
    {
        $email_verify = get_option("lkn_wcip_after_save_button_email_check");
        if (!$email_verify) {
            $verification_required = false; // Defina como false para não exigir verificação de e-mail

        } else {
            $verification_required = true;
        }

        return $verification_required;
    }
    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     */
    private function define_public_hooks(): void
    {
        
        $plugin_public = new Wc_Payment_Invoice_Public($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_action('woocommerce_pay_order_before_submit', $plugin_public, 'check_invoice_exp_date', 10, 1);
        add_filter("woocommerce_order_email_verification_required", array($this, "custom_email_verification_required"), 10, 3);
        
    }    
}
