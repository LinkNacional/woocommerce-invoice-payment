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

        wp_enqueue_style($this->plugin_name . '-admin-style', plugin_dir_url(__FILE__) . 'css/wc-invoice-payment-admin.css', [], $this->version, 'all');
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

        wp_enqueue_script($this->plugin_name . '-admin-js', plugin_dir_url(__FILE__) . 'js/wc-invoice-payment-admin.js', ['wp-i18n'], $this->version, false);
        wp_set_script_translations($this->plugin_name . '-admin-js', 'wc-invoice-payment', WC_PAYMENT_INVOICE_TRANSLATION_PATH);
    }

    /**
     * Generates custom menu section and setting page
     *
     * @return void
     */
    public function add_setting_session() {
        add_menu_page(
            __('List invoices', 'wc-invoice-payment'),
            'Invoice Payment for WooCommerce',
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
        $invoiceId = sanitize_text_field($_GET['invoice']);

        $decimalSeparator = wc_get_price_decimal_separator();
        $thousandSeparator = wc_get_price_thousand_separator();
        $decimalQtd = wc_get_price_decimals();

        // Get all translated WooCommerce order status
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

        // Get all WooCommerce enabled gateways
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
            <!-- Invoice details -->
            <h2 class="title"><?php _e('Invoice details', 'wc-invoice-payment')?> <?php esc_html_e('#' . $invoiceId) ?></h2>
            <div class="invoice-row-wrap">
                <div class="invoice-column-wrap">
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_payment_status_input"><?php _e('Status', 'wc-invoice-payment')?></label>
                        <select name="lkn_wcip_payment_status" id="lkn_wcip_payment_status_input" class="regular-text" value="<?php esc_html_e('wc-' . $order->get_status()); ?>">
                            <?php
                                for ($i = 0; $i < count($statusWc); $i++) {
                                    if ($orderStatus === explode('-', $statusWc[$i]['status'])[1]) {
                                        echo '<option value="' . esc_attr($statusWc[$i]['status']) . '" selected>' . esc_attr($statusWc[$i]['label']) . '</option>';
                                    } else {
                                        echo '<option value="' . esc_attr($statusWc[$i]['status']) . '">' . esc_attr($statusWc[$i]['label']) . '</option>';
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
                                    echo '<option value="' . esc_attr($gateway->id) . '" selected>' . esc_attr($gateway->title) . '</option>';
                                } else {
                                    echo '<option value="' . esc_attr($gateway->id) . '">' . esc_attr($gateway->title) . '</option>';
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
                                        echo '<option value="' . esc_attr($code) . '" selected>' . esc_attr($currency) . ' - ' . esc_attr($code) . '</option>';
                                    } else {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_attr($currency) . ' - ' . esc_attr($code) . '</option>';
                                    }
                                } ?>
                        </select>
                    </div>
                </div>
                <div class="invoice-column-wrap">
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_name_input"><?php _e('Name', 'wc-invoice-payment')?></label>
                        <input name="lkn_wcip_name" type="text" id="lkn_wcip_name_input" class="regular-text" required value="<?php esc_html_e($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>">
                    </div>
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_email_input"><?php _e('Email', 'wc-invoice-payment')?></label>
                        <input name="lkn_wcip_email" type="email" id="lkn_wcip_email_input" class="regular-text" required value="<?php esc_html_e($order->get_billing_email()); ?>">
                    </div>
                </div>
            </div>
        </div>
        <!-- Form actions -->
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
                        <a href="<?php echo esc_url($checkoutUrl); ?>" target="_blank"><?php _e('Invoice payment link', 'wc-invoice-payment'); ?></a>
                    </div>
                    <?php
                } ?>
            </div>
            <div class="action-btn">
                <p class="submit">
                    <button type="button" class="button lkn_wcip_delete_btn_form" onclick="lkn_wcip_delete_invoice()"><?php _e('Delete') ?></button>
                </p>
                <?php submit_button(__('Update')) ?>
            </div>
        </div>
        <!-- Invoice charges -->
        <div class="wcip-invoice-data">
        <h2 class="title"><?php _e('Price', 'wc-invoice-payment')?></h2>
            <div id="wcip-invoice-price-row" class="invoice-column-wrap">
                <?php
                foreach ($items as $item_id => $item) {
                    ?>
                    <div class="price-row-wrap price-row-<?php esc_attr_e($c) ?>">
                        <?php
                        if ($orderStatus === 'pending') {
                            ?>
                        <div class="input-row-wrap">
                            <label><?php _e('Name', 'wc-invoice-payment')?></label>
                            <input name="lkn_wcip_name_invoice_<?php esc_attr_e($c) ?>" type="text" id="lkn_wcip_name_invoice_<?php esc_attr_e($c) ?>" class="regular-text" required value="<?php esc_attr_e($item->get_name()); ?>">
                        </div>
                        <div class="input-row-wrap">
                            <label><?php _e('Amount', 'wc-invoice-payment')?></label>
                            <input name="lkn_wcip_amount_invoice_<?php esc_attr_e($c) ?>" type="tel" id="lkn_wcip_amount_invoice_<?php esc_attr_e($c) ?>" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');" required value="<?php esc_attr_e(number_format($item->get_total()), $decimalQtd, $decimalSeparator, $thousandSeparator); ?>">
                        </div>
                        <?php
                        } else {
                            ?>
                        <div class="input-row-wrap">
                            <label><?php _e('Name', 'wc-invoice-payment')?></label>
                            <input name="lkn_wcip_name_invoice_<?php esc_attr_e($c) ?>" type="text" id="lkn_wcip_name_invoice_<?php esc_attr_e($c) ?>" class="regular-text" required readonly value="<?php esc_attr_e($item->get_name()); ?>">
                        </div>
                        <div class="input-row-wrap">
                            <label><?php _e('Amount', 'wc-invoice-payment')?></label>
                            <input name="lkn_wcip_amount_invoice_<?php esc_attr_e($c) ?>" type="tel" id="lkn_wcip_amount_invoice_<?php esc_attr_e($c) ?>" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');" required readonly value="<?php esc_attr_e(number_format($item->get_total()), $decimalQtd, $decimalSeparator, $thousandSeparator); ?>">
                        </div>
                        <?php
                        }

                    if ($orderStatus === 'pending') {
                        ?>
                        <div class="input-row-wrap">
                            <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(<?php esc_attr_e($c) ?>)"><span class="dashicons dashicons-trash"></span></button>
                        </div>
                        <?php
                    } ?>
                    </div>
                <?php
                $c++;
                } ?>
            </div>
            <hr>
            <?php
            if ($orderStatus === 'pending') {
                ?>
            <div class="invoice-row-wrap">
                <button type="button" class="btn btn-add-line" onclick="lkn_wcip_add_amount_row()"><?php _e('Add line', 'wc-invoice-payment') ?></button>
            </div>
            <?php
            } ?>
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
            <h1><?php esc_html_e(get_admin_page_title()); ?></h1>
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

        add_action('load-' . $hookname, [$this, 'add_invoice_form_submit_handle']);

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

        // Get all WooCommerce enabled gateways
        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ($gateway->enabled == 'yes') {
                    $enabled_gateways[] = $gateway;
                }
            }
        } ?>
      <div class="wrap">
        <h1><?php esc_html_e(get_admin_page_title()); ?></h1>
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
                                echo '<option value="' . esc_attr($gateway->id) . '">' . esc_html($gateway->title) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="input-row-wrap">
                        <label for="lkn_wcip_currency_input"><?php _e('Currency', 'wc-invoice-payment')?></label>
                        <select name="lkn_wcip_currency" id="lkn_wcip_currency_input" class="regular-text">
                            <?php
                                foreach ($currencies as $code => $currency) {
                                    if ($active_currency === $code) {
                                        echo '<option value="' . esc_attr($code) . '" selected>' . esc_html($currency . ' - ' . $code) . '</option>';
                                    } else {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($currency . ' - ' . $code) . '</option>';
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
                        <label for="lkn_wcip_email_input"><?php _e('Email', 'wc-invoice-payment')?></label>
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
    public function add_invoice_form_submit_handle() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_POST['nonce'] && wp_verify_nonce($_POST['nonce'], 'lkn_wcip_add_invoice')) {
                $decimalSeparator = wc_get_price_decimal_separator();
                $thousandSeparator = wc_get_price_thousand_separator();

                $invoices = [];
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
                        $c++;
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

                $order = wc_create_order(
                    [
                        'status'        => $paymentStatus,
                        'customer_id'   => 0,
                        'customer_note' => '',
                        'total'         => $totalAmount,
                    ]
                );

                // Saves all charges as products inside the order object
                for ($i = 0; $i < count($invoices); $i++) {
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

                $order->calculate_totals();
                $order->save();

                $orderId = $order->get_id();

                $invoiceList = get_option('lkn_wcip_invoices');

                if ($invoiceList !== false) {
                    $invoiceList[] = $orderId;
                    update_option('lkn_wcip_invoices', $invoiceList);
                } else {
                    update_option('lkn_wcip_invoices', [$orderId]);
                }

                // If the action 'send email' is set send a notification email to the customer
                if (isset($_POST['lkn_wcip_form_actions']) && sanitize_text_field($_POST['lkn_wcip_form_actions']) === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    // Note the event.
                    $order->add_order_note(__('Order details manually sent to customer.', 'woocommerce'), false, true);
                }
                // Success message

                echo '<div class="lkn_wcip_notice_positive">' . __('Invoice successfully saved', 'wc-invoice-payment') . '</div>';
            } else {
                // Error message

                echo '<div class="lkn_wcip_notice_negative">' . __('Error on invoice generation', 'wc-invoice-payment') . '</div>';
            }
        }
    }

    /**
     * Handles submission from edit invoice form and delete invoice action
     *
     * @return void
     */
    public function edit_invoice_form_submit_handle() {
        // Validates request method
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Validates WP nonce
            if ($_POST['nonce'] && wp_verify_nonce($_POST['nonce'], 'lkn_wcip_edit_invoice')) {
                $decimalSeparator = wc_get_price_decimal_separator();
                $thousandSeparator = wc_get_price_thousand_separator();

                $invoiceId = sanitize_text_field($_GET['invoice']);
                $order = wc_get_order($invoiceId);
                $order->remove_order_items();

                $invoices = [];
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
                        $c++;
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

                // Saves all charges as products inside the order object
                for ($i = 0; $i < count($invoices); $i++) {
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

                // Get order total and saves in the DB
                $order->calculate_totals();
                $order->save();

                // If the action 'send email' is set send a notification email to the customer
                if (isset($_POST['lkn_wcip_form_actions']) && sanitize_text_field($_POST['lkn_wcip_form_actions']) === 'send_email') {
                    WC()->mailer()->customer_invoice($order);

                    // Note the event.
                    $order->add_order_note(__('Order details manually sent to customer.', 'woocommerce'), false, true);
                }

                // Success message
                echo '<div class="lkn_wcip_notice_positive">' . __('Invoice successfully saved', 'wc-invoice-payment') . '</div>';
            } else {
                // Error message
                echo '<div class="lkn_wcip_notice_negative">' . __('Error on invoice generation', 'wc-invoice-payment') . '</div>';
            }
        } elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lkn_wcip_delete'])) {
            // Validates request for deleting invoice
            if ($_GET['lkn_wcip_delete'] === 'true') {
                $invoiceDelete = [sanitize_text_field($_GET['invoice'])];
                $invoices = get_option('lkn_wcip_invoices');

                $invoices = array_diff($invoices, $invoiceDelete);

                $order = wc_get_order($invoiceDelete[0]);
                $order->delete();

                update_option('lkn_wcip_invoices', $invoices);

                // Redirect to invoice list
                wp_redirect(home_url('wp-admin/admin.php?page=wc-invoice-payment'));
            } else {
                // Show error message

                echo '<div class="lkn_wcip_notice_negative">' . __('Error on invoice deletion', 'wc-invoice-payment') . '</div>';
            }
        }
    }
}
