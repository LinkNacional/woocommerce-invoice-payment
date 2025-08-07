<?php
namespace LknWc\WcInvoicePayment\Includes;

use LknWc\WcInvoicePayment\Admin\WcPaymentInvoiceAdmin;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceLoader;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceLoaderRest;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceSettings;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceSubscription;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoicei18n;
use LknWc\WcInvoicePayment\PublicView\WcPaymentInvoicePublic;

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
final class WcPaymentInvoice {
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

    public $WcPaymentInvoicePartialClass;
    public $WcPaymentInvoiceEndpointClass;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
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
    public function run(): void {
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
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     *
     * @return Wc_Payment_Invoice_Loader orchestrates the hooks of the plugin
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     *
     * @return string the version number of the plugin
     */
    public function get_version() {
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
    private function load_dependencies(): void {
        $this->loader = new WcPaymentInvoiceLoader();
        $this->WcPaymentInvoicePartialClass = new WcPaymentInvoicePartial();
        $this->WcPaymentInvoiceEndpointClass = new WcPaymentInvoiceEndpoint();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Wc_Payment_Invoice_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     */
    private function set_locale(): void {
        $plugin_i18n = new WcPaymentInvoicei18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     */
    private function define_admin_hooks(): void {
        $plugin_admin = new WcPaymentInvoiceAdmin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('lkn_wcip_cron_hook', $plugin_admin, 'check_invoice_exp_date', 10, 1);

        // AJAX hooks for product search functionality
        $this->loader->add_action('wp_ajax_lkn_wcip_get_product_data', $plugin_admin, 'ajax_get_product_data');
        $this->loader->add_action('wp_ajax_nopriv_lkn_wcip_get_product_data', $plugin_admin, 'ajax_get_product_data');

        $api_handler = new WcPaymentInvoiceLoaderRest();
        $this->loader->add_action('rest_api_init', $api_handler, 'register_routes');
        $subscription_class = new WcPaymentInvoiceSubscription();
        $this->loader->add_action('product_type_options', $subscription_class, 'add_checkbox');
        $this->loader->add_filter('woocommerce_product_data_tabs', $subscription_class, 'add_tab');
        $this->loader->add_action('woocommerce_product_data_panels', $subscription_class, 'add_text_field_to_subscription_tab');
        $this->loader->add_action('woocommerce_checkout_order_processed', $subscription_class, 'validate_product');
        $this->loader->add_action('woocommerce_store_api_checkout_order_processed', $subscription_class, 'validate_product');
        $this->loader->add_action('woocommerce_init', $this, 'subscriptionNotice');
		$this->loader->add_filter( 'wc_order_statuses', $this->WcPaymentInvoicePartialClass, 'createStatus' );
		$this->loader->add_filter( 'woocommerce_register_shop_order_post_statuses', $this->WcPaymentInvoicePartialClass, 'registerStatus' );
		$this->loader->add_action( 'woocommerce_order_status_changed', $this->WcPaymentInvoicePartialClass, 'statusChanged', 10, 4);
        $this->loader->add_action( 'add_meta_boxes', $this->WcPaymentInvoicePartialClass, 'showPartialsPayments', 1);
        $this->loader->add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', $this->WcPaymentInvoicePartialClass, 'hidePartialOrdersRequest');
        $this->loader->add_filter( 'woocommerce_shop_order_list_table_order_count', $this->WcPaymentInvoicePartialClass, 'fixTableCount', 10, 2);
        $this->loader->add_action('woocommerce_before_delete_order', $this->WcPaymentInvoicePartialClass, 'deletePartialOrders');
        $this->loader->add_action('woocommerce_process_product_meta', $subscription_class, 'save_subscription_fields');
        $this->loader->add_action('wp_ajax_cancel_subscription', $subscription_class, 'cancel_subscription_callback');
        $this->loader->add_action('generate_invoice_event', $subscription_class, 'create_next_invoice', 10, 1);

        


        new WcPaymentInvoiceSettings($this->loader);
    }

   

    public function subscriptionNotice(): void {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $invoice = isset($_GET['invoice']) ? sanitize_text_field(wp_unslash($_GET['invoice'])) : 0;
        if($page == 'edit-subscription' && $invoice != 0){
            $message = "
            <div style='display: none;' id='message' class='notice notice-warning'>
                <p>
                    <b>Atenção: </b> Pagamentos automáticos desativados, por favor crie um usuário para o cliente.
                </p>
            </div>";
        
            echo $message;
        }

        // Compatibilidade com configurações antigas
        if(get_option('lkn_wcip_after_save_button_email_check') == '1'){
            update_option('lkn_wcip_after_save_button_email_check', 'yes');
        }
        if(get_option('lkn_wcip_subscription_active_product_invoices') == '1'){
            update_option('lkn_wcip_subscription_active_product_invoices', 'yes');
        }
        if(get_option('lkn_wcip_show_fee_activated') == 'on'){
            update_option('lkn_wcip_show_fee_activated', 'yes');
        }
        if(get_option('lkn_wcip_show_discount_activated') == 'on'){
            update_option('lkn_wcip_show_discount_activated', 'yes');
        }
        if(get_option('lkn_wcip_partial_payments_enabled') == 'on'){
            update_option('lkn_wcip_partial_payments_enabled', 'yes');
        }
        // agora preciso fazer o if "on" para get_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id, 'no') para cada metodo de pagamento ativado
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        foreach ($gateways as $gateway_id => $gateway) {
            if(get_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id, 'no') == 'on'){
                update_option('lkn_wcip_fee_or_discount_method_activated_' . $gateway_id, 'yes');
            }
        }
    }

    public function custom_email_verification_required($verification_required) {
        $email_verify = get_option("lkn_wcip_after_save_button_email_check");
        if ( ! $email_verify) {
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
    private function define_public_hooks(): void {
        $plugin_public = new WcPaymentInvoicePublic($this->get_plugin_name(), $this->get_version());
        $subscription_class = new WcPaymentInvoiceSubscription();
        $feeOrDiscountClass = new WcPaymentInvoiceFeeOrDiscount();
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_action('woocommerce_pay_order_before_submit', $plugin_public, 'check_invoice_exp_date', 10, 1);
        $this->loader->add_filter( 'woocommerce_checkout_registration_enabled', $subscription_class, 'forceUserRegistration' );
        $this->loader->add_filter( 'woocommerce_checkout_registration_required', $subscription_class, 'forceUserRegistration' );
		$this->loader->add_action( 'enqueue_block_assets', $this->WcPaymentInvoicePartialClass, 'enqueueCheckoutScripts');
        $this->loader->add_action('woocommerce_order_details_after_order_table', $this->WcPaymentInvoicePartialClass, "showPartialFields");
		$this->loader->add_filter( 'woocommerce_valid_order_statuses_for_cancel', $this->WcPaymentInvoicePartialClass, 'allowStatusCancel');
		$this->loader->add_action( 'woocommerce_valid_order_statuses_for_payment', $this->WcPaymentInvoicePartialClass, 'allowStatusPayment');
        $this->loader->add_action('rest_api_init', $this->WcPaymentInvoiceEndpointClass, 'registerEndpoints');
        $this->loader->add_action('woocommerce_cart_calculate_fees', $feeOrDiscountClass, 'caclulateCart', 999);
        $this->loader->add_action('enqueue_block_assets', $feeOrDiscountClass, 'loadScripts');
        
        add_filter("woocommerce_order_email_verification_required", array($this, "custom_email_verification_required"), 10, 3);
    }
}
