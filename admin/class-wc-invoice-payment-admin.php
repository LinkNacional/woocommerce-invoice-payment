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
            __('List invoices', 'wc-invoice-payment'),
            __('WooCommerce Invoice Payment', 'wc-invoice-payment'),
            'manage_options',
            'wc-invoice-payment',
            false,
            'dashicons-money-alt',
            50
        );

        add_submenu_page(
            'wc-invoice-payment',
            __('List invoices', 'wc-invoice-payment'),
            __('Invoices', 'wc-invoice-payment'),
            'manage_options',
            'wc-invoice-payment',
            [$this, 'render_invoice_list_page'],
            1
        );
    }

    /**
     * Render html page for invoice edit
     *
     * @return void
     */
    public function render_edit_invoice_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $invoiceId = $_GET['invoice'];

        $statusWc = [];
        $statusWc[] = ['status' => 'wc-pending', 'label' => _x('Pending payment', 'Order status', 'woocommerce')];
        $statusWc[] = ['status' => 'wc-processing', 'label' => _x('Processing', 'Order status', 'woocommerce')];
        $statusWc[] = ['status' => 'wc-on-hold', 'label' => _x('On hold', 'Order status', 'woocommerce')];
        $statusWc[] = ['status' => 'wc-completed', 'label' => _x('Completed', 'Order status', 'woocommerce')];
        $statusWc[] = ['status' => 'wc-cancelled', 'label' => _x('Cancelled', 'Order status', 'woocommerce')];
        $statusWc[] = ['status' => 'wc-refunded', 'label' => _x('Refunded', 'Order status', 'woocommerce')];
        $statusWc[] = ['status' => 'wc-failed', 'label' => _x('Failed', 'Order status', 'woocommerce')];

        $c = 0;
        $order = wc_get_order($invoiceId);
        $items = $order->get_items();
        $checkoutUrl = $order->get_checkout_payment_url();
        $orderStatus = $order->get_status();

        $currencies = get_woocommerce_currencies();

        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $enabled_gateways = [];

        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ($gateway->enabled == 'yes') {
                    $enabled_gateways[] = $gateway;
                }
            }
        } ?>
    <div class="wrap">
        <h1><?php _e('Edit invoice', 'wc-invoice-payment'); ?></h1>
        <?php settings_errors(); ?>
        <form action="<?php menu_page_url('edit-invoice&invoice=' . $invoiceId) ?>" method="post" class="wcip-form-wrap">
        <?php wp_nonce_field('lkn_wcip_edit_invoice', 'nonce'); ?>
        <div class="wcip-invoice-data">    
            <h2 class="title"><?php _e('Invoice details', 'wc-invoice-payment')?> <?php echo '#' . $invoiceId ?></h2>
            <div class="invoice-row-wrap">
                <div class="invoice-column-wrap">
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_payment_status_input"><?php _e('Status', 'wc-invoice-payment')?></label>
                        <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" class="regular-text" value="<?php echo 'wc-' . $order->get_status(); ?>">
                            <?php
                                for ($i = 0; $i < count($statusWc); $i++) {
                                    if ($orderStatus === explode('-', $statusWc[$i]['status'])[1]) {
                                        echo '<option value="' . $statusWc[$i]['status'] . '" selected>' . $statusWc[$i]['label'] . '</option>';
                                    } else {
                                        echo '<option value="' . $statusWc[$i]['status'] . '">' . $statusWc[$i]['label'] . '</option>';
                                    }
                                } ?>
                        </select>
                    </div>
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_default_payment_method_input"><?php _e('Default payment method', 'wc-invoice-payment')?></label>
                        <select name="lkn_wcip_default_payment_method" id="lkn_wcip_default_payment_method_input" class="regular-text">
                            <?php
                            foreach ($enabled_gateways as $key => $gateway) {
                                if ($gateway->id === $order->get_payment_method()) {
                                    echo '<option value="' . $gateway->id . '" selected>' . $gateway->title . '</option>';
                                } else {
                                    echo '<option value="' . $gateway->id . '">' . $gateway->title . '</option>';
                                }
                            } ?>
                        </select>
                    </div>
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_currency_input"><?php _e('Currency', 'wc-invoice-payment')?></label>
                        <select name="lkn_wcip_currency" id="lkn_wcip_currency_input" class="regular-text">
                            <?php
                                foreach ($currencies as $code => $currency) {
                                    if ($order->get_currency() === $code) {
                                        echo '<option value="' . $code . '" selected>' . $currency . ' - ' . $code . '</option>';
                                    } else {
                                        echo '<option value="' . $code . '">' . $currency . ' - ' . $code . '</option>';
                                    }
                                } ?>
                        </select>
                    </div>
                </div>
                <div class="invoice-column-wrap">
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_name_input"><?php _e('Name', 'wc-invoice-payment')?></label>
                        <input name="lkn_wcip_name" type="text" id="lkn_wcip_name_input" class="regular-text" required value="<?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?>">
                    </div>
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_email_input"><?php _e('E-mail', 'wc-invoice-payment')?></label>
                        <input name="lkn_wcip_email" type="email" id="lkn_wcip_email_input" class="regular-text" required value="<?php echo $order->get_billing_email(); ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="wcip-invoice-data wcip-postbox">
            <span class="text-bold"><?php _e('Invoice actions', 'wc-invoice-payment'); ?></span>
            <hr>
            <div class="wcip-row">
                <div class="input-row-wrap">
                    <select name="lkn_wcip_form_actions">
                        <option value="no_action" selected><?php _e('Select an action...', 'wc-invoice-payment'); ?></option>
                        <option value="send_email"><?php _e('Send invoice to customer', 'wc-invoice-payment') ?></option>
                    </select>
                </div>
                <?php
                if ($orderStatus === 'pending') {
                    ?>
                    <div class="input-row-wrap">
                        <a href="<?php echo $checkoutUrl; ?>" target="_blank"><?php _e('Customer payment page &rarr;', 'woocommerce'); ?></a>
                    </div>
                    <?php
                } ?>
            </div>
            <div class="action-btn">
                <?php submit_button(__('Update')) ?>
            </div>
        </div>
        <div class="wcip-invoice-data">
        <h2 class="title"><?php _e('Price', 'wc-invoice-payment')?></h2>
            <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                <?php
                foreach ($items as $item_id => $item) {
                    ?>
                    <div class="price-row-wrap price-row-<?php echo $c ?>">
                        <div class="input-row-wrap">
                            <label><?php _e('Name', 'wc-invoice-payment')?></label>
                            <input name="lkn_wcip_name_invoice_<?php echo $c ?>" type="text" id="lkn_wcip_name_invoice_<?php echo $c ?>" class="regular-text" required value="<?php echo $item->get_name(); ?>">
                        </div>
                        <div class="input-row-wrap">
                            <label><?php _e('Amount', 'wc-invoice-payment')?></label>
                            <input name="lkn_wcip_amount_invoice_<?php echo $c ?>" type="tel" id="lkn_wcip_amount_invoice_<?php echo $c ?>" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');" required value="<?php echo $item->get_total(); ?>">
                        </div>
                        <div class="input-row-wrap">
                            <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(<?php echo $c ?>)"><span class="dashicons dashicons-trash"></span></button>
                        </div>
                    </div>
                <?php
                $c++;
                } ?>
            </div>
            <hr>
            <div class="invoice-row-wrap">
                <button type="button" class="btn btn-add-line" onclick="lkn_wcip_add_amount_row()"><?php _e('Add line', 'wc-invoice-payment') ?></button>
            </div>
        </div>
        </form>
    </div>
    <?php
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
    <form id="invoices-filter" method="POST">
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div>        
        <?php
        $object = new Lkn_Wcip_List_Table();
        $object->prepare_items();
        $object->display(); ?>
            </div>
        </div>
    </form>
    <?php
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
     * Adds new invoice submenu page and edit invoice submenu page
     *
     * @return void
     */
    public function add_new_invoice_submenu_section() {
        $hookname = add_submenu_page(
            'wc-invoice-payment',
            __('Add invoice', 'wc-invoice-payment'),
            __('Add invoice', 'wc-invoice-payment'),
            'manage_options',
            'new-invoice',
            [$this, 'new_invoice_form'],
            2
        );

        add_action('load-' . $hookname, [$this, 'form_submit_handle']);

        $editHookname = add_submenu_page(
            null,
            __('Edit invoice', 'wc-invoice-payment'),
            __('Edit invoice', 'wc-invoice-payment'),
            'manage_options',
            'edit-invoice',
            [$this, 'render_edit_invoice_page'],
            1
        );

        add_action('load-' . $editHookname, [$this, 'edit_invoice_form_submit_handle']);
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
        } ?>
      <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <?php settings_errors(); ?>
        <form action="<?php menu_page_url('new-invoice') ?>" method="post" class="wcip-form-wrap">
        <?php wp_nonce_field('lkn_wcip_add_invoice', 'nonce'); ?>
        <div class="wcip-invoice-data">    
            <h2 class="title"><?php _e('Invoice details', 'wc-invoice-payment')?></h2>
            <div class="invoice-row-wrap">
                <div class="invoice-column-wrap">
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_payment_status_input"><?php _e('Status', 'wc-invoice-payment')?></label>
                        <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" class="regular-text">
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
                        <label for="lkn_wcip_default_payment_method_input"><?php _e('Default payment method', 'wc-invoice-payment')?></label>
                        <select name="lkn_wcip_default_payment_method" id="lkn_wcip_default_payment_method_input" class="regular-text">
                            <?php
                            foreach ($enabled_gateways as $key => $gateway) {
                                echo '<option value="' . $gateway->id . '">' . $gateway->title . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_currency_input"><?php _e('Currency', 'wc-invoice-payment')?></label>
                        <select name="lkn_wcip_currency" id="lkn_wcip_currency_input" class="regular-text">
                            <?php
                                foreach ($currencies as $code => $currency) {
                                    if ($active_currency === $code) {
                                        echo '<option value="' . $code . '" selected>' . $currency . ' - ' . $code . '</option>';
                                    } else {
                                        echo '<option value="' . $code . '">' . $currency . ' - ' . $code . '</option>';
                                    }
                                } ?>
                        </select>
                    </div>
                </div>
                <div class="invoice-column-wrap">
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_name_input"><?php _e('Name', 'wc-invoice-payment')?></label>
                        <input name="lkn_wcip_name" type="text" id="lkn_wcip_name_input" class="regular-text" required>
                    </div>
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_email_input"><?php _e('E-mail', 'wc-invoice-payment')?></label>
                        <input name="lkn_wcip_email" type="email" id="lkn_wcip_email_input" class="regular-text" required>
                    </div>
                </div>
            </div>
        </div>
        <div class="wcip-invoice-data wcip-postbox">
            <span class="text-bold"><?php _e('Invoice actions', 'wc-invoice-payment'); ?></span>
            <hr>
            <div class="wcip-row">
                <div class="input-row-wrap">
                    <select name="lkn_wcip_form_actions">
                        <option value="no_action" selected><?php _e('Select an action...', 'wc-invoice-payment'); ?></option>
                        <option value="send_email"><?php _e('Send invoice to customer', 'wc-invoice-payment') ?></option>
                    </select>
                </div>
            </div>
            <div class="action-btn">
                <?php submit_button(__('Save')) ?>
            </div>
        </div>
        <div class="wcip-invoice-data">
        <h2 class="title"><?php _e('Price', 'wc-invoice-payment')?></h2>
            <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                <div class="price-row-wrap price-row-0">
                    <div class="input-row-wrap">
                        <label><?php _e('Name', 'wc-invoice-payment')?></label>
                        <input name="lkn_wcip_name_invoice_0" type="text" id="lkn_wcip_name_invoice_0" class="regular-text" required>
                    </div>
                    <div class="input-row-wrap">
                        <label><?php _e('Amount', 'wc-invoice-payment')?></label>
                        <input name="lkn_wcip_amount_invoice_0" type="tel" id="lkn_wcip_amount_invoice_0" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');" required>
                    </div>
                    <div class="input-row-wrap">
                        <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(0)"><span class="dashicons dashicons-trash"></span></button>
                    </div>
                </div>
            </div>
            <hr>
            <div class="invoice-row-wrap">
                <button type="button" class="btn btn-add-line" onclick="lkn_wcip_add_amount_row()"><?php _e('Add line', 'wc-invoice-payment') ?></button>
            </div>
        </div>
        </form>
    </div>
    <?php
    }

    /**
     * Handles submission from add invoice form
     *
     * @return void
     */
    public function form_submit_handle() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_POST['nonce'] && wp_verify_nonce($_POST['nonce'], 'lkn_wcip_add_invoice')) {
                $invoices = [];
                $totalAmount = 0;
                $c = 0;
                foreach ($_POST as $key => $value) {
                    // Get invoice description
                    if (preg_match('/lkn_wcip_name_invoice_/i', $key)) {
                        $invoices[$c]['desc'] = strip_tags($value);
                    }
                    // Get invoice amount
                    if (preg_match('/lkn_wcip_amount_invoice_/i', $key)) {
                        // Save amount and description in same index because they are related
                        $invoices[$c]['amount'] = number_format($value, 2, '.', '');
                        $totalAmount += number_format($value, 2, '.', '');
                        // Only increment when amount is found
                        $c++;
                    }
                }

                $paymentStatus = sanitize_text_field($_POST['lkn_wcip_payment_status']);
                $paymentMethod = sanitize_text_field($_POST['lkn_wcip_default_payment_method']);
                $currency = sanitize_text_field($_POST['lkn_wcip_currency']);
                $name = sanitize_text_field($_POST['lkn_wcip_name']);
                $firstName = explode(' ', $name)[0];
                $lastname = substr(strstr($name, ' '), 1);
                $email = sanitize_email($_POST['lkn_wcip_email']);

                $order = wc_create_order(
                    [
                        'status'        => $paymentStatus,
                        'customer_id'   => 0,
                        'customer_note' => '',
                        'total'         => $totalAmount,
                    ]
                );

                for ($i = 0; $i < count($invoices); $i++) {
                    $product = new WC_Product();
                    $product->set_name($invoices[$i]['desc']);
                    $product->set_regular_price($invoices[$i]['amount']);
                    $product->save();
                    $productId = wc_get_product($product->get_id());

                    $order->add_product($productId);

                    $product->delete(true);
                }

                $order->set_billing_email($email);
                $order->set_billing_first_name($firstName);
                $order->set_billing_last_name($lastname);
                $order->set_payment_method($paymentMethod);
                $order->set_currency($currency);

                $order->calculate_totals();
                $order->save();
                // $order->get_checkout_payment_url();
                $orderId = $order->get_id();

                $invoiceList = get_option('lkn_wcip_invoices');

                if ($invoiceList !== false) {
                    $invoiceList[] = $orderId;
                    update_option('lkn_wcip_invoices', $invoiceList);
                } else {
                    update_option('lkn_wcip_invoices', [$orderId]);
                }

                if (isset($_POST['lkn_wcip_form_actions']) && $_POST['lkn_wcip_form_actions'] === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    // Note the event.
                    $order->add_order_note(__('Order details manually sent to customer.', 'woocommerce'), false, true);
                }
            } else {
                echo 'Error nonce not found';
            }
        }
    }

    /**
     * Handles submission from edit invoice form
     *
     * @return void
     */
    public function edit_invoice_form_submit_handle() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_POST['nonce'] && wp_verify_nonce($_POST['nonce'], 'lkn_wcip_edit_invoice')) {
                $invoiceId = $_GET['invoice'];
                $order = wc_get_order($invoiceId);
                $order->remove_order_items();

                $invoices = [];
                $totalAmount = 0;
                $c = 0;
                foreach ($_POST as $key => $value) {
                    // Get invoice description
                    if (preg_match('/lkn_wcip_name_invoice_/i', $key)) {
                        $invoices[$c]['desc'] = strip_tags($value);
                    }
                    // Get invoice amount
                    if (preg_match('/lkn_wcip_amount_invoice_/i', $key)) {
                        // Save amount and description in same index because they are related
                        $invoices[$c]['amount'] = number_format($value, 2, '.', '');
                        $totalAmount += number_format($value, 2, '.', '');
                        // Only increment when amount is found
                        $c++;
                    }
                }

                $paymentStatus = sanitize_text_field($_POST['lkn_wcip_payment_status']);
                $paymentMethod = sanitize_text_field($_POST['lkn_wcip_default_payment_method']);
                $currency = sanitize_text_field($_POST['lkn_wcip_currency']);
                $name = sanitize_text_field($_POST['lkn_wcip_name']);
                $firstName = explode(' ', $name)[0];
                $lastname = substr(strstr($name, ' '), 1);
                $email = sanitize_email($_POST['lkn_wcip_email']);

                for ($i = 0; $i < count($invoices); $i++) {
                    $product = new WC_Product();
                    $product->set_name($invoices[$i]['desc']);
                    $product->set_regular_price($invoices[$i]['amount']);
                    $product->save();
                    $productId = wc_get_product($product->get_id());

                    $order->add_product($productId);

                    $product->delete(true);
                }

                $order->set_billing_email($email);
                $order->set_billing_first_name($firstName);
                $order->set_billing_last_name($lastname);
                $order->set_payment_method($paymentMethod);
                $order->set_currency($currency);
                $order->set_status($paymentStatus);

                $order->calculate_totals();
                $order->save();
                // $checkoutUrl = $order->get_checkout_payment_url();

                if (isset($_POST['lkn_wcip_form_actions']) && $_POST['lkn_wcip_form_actions'] === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    // Note the event.
                    $order->add_order_note(__('Order details manually sent to customer.', 'woocommerce'), false, true);
                }
            } else {
                echo 'Error nonce not found';
            }
        }
    }
}
