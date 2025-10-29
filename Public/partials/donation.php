<?php
/**
 * Template para botão de adicionar ao carrinho de produtos de doação
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WC_Invoice_Payment/Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

global $product;

if (!$product->is_purchasable()) {
    return;
}

echo wp_kses_post(wc_get_stock_html($product));

// Instanciar classe de doação
$donation_class = new LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceDonation();

// Verificar se a doação está dentro do prazo
$within_deadline = $donation_class->is_donation_within_deadline($product->get_id());

// Verificar se a meta de doação foi atingida
$progress = $donation_class->get_donation_progress($product->get_id());
$goal_reached = $progress['goal_reached'];

// Exibir barra de progresso da doação se habilitada e se for doação variável
$donation_type = $product->get_meta('_donation_type', true);
if ($donation_type === 'variable') {
    // Exibir contador regressivo se habilitado
    echo wp_kses_post($donation_class->render_donation_countdown($product->get_id()));
    echo wp_kses_post($donation_class->render_donation_progress_bar($product->get_id()));
}

if ($product->is_in_stock() && $within_deadline && !$goal_reached) : ?>

    <?php do_action('woocommerce_before_add_to_cart_form'); ?>

    <form class="cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype='multipart/form-data'>
        
        <?php do_action('woocommerce_before_add_to_cart_button'); ?>
        
        <?php
            $donation_type = $product->get_meta('_donation_type', true);
            
            if ($donation_type === 'variable') {
                $button_values = $product->get_meta('_donation_button_values', true);
                $hide_custom_amount = $product->get_meta('_donation_hide_custom_amount', true);
        ?>
                <div class="donation-fields">
                    <?php if ($button_values) {
                        $values = array_map('trim', explode(',', $button_values));
                    ?>
                    <div
                    <?php if ($hide_custom_amount != 'yes') { ?>
                        class="preset-buttons donation-margin-left"
                    <?php }else{
                        ?>
                        class="preset-buttons"
                    <?php } ?>
                    >
                        <?php foreach ($values as $value) {
                            if (is_numeric($value) && $value > 0) { 
                                $formatted_price = number_format($value, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
                            ?>
                        <button type="button" class="button alt wp-element-button donation-preset-btn" data-amount="<?php echo esc_attr($value); ?>"><?php echo esc_html($formatted_price); ?></button>
                        <?php }
                        } ?>
                    </div>
                    <?php } ?>
                    <div class="custom-amount-field">
                        <div class="custom-amount-field"
                            <?php if ($hide_custom_amount == 'yes') { ?>
                            style="
                                display: none !important;
                            "
                            <?php } ?>    
                        >
                            <label for="donation_amount"><?php echo esc_html(get_woocommerce_currency_symbol()); ?></label>
                            <div class="quantity">
                                <input type="number" id="donation_amount" class="input-text qty text" name="donation_amount" step="0.01" min="0.01" placeholder="0.00" inputmode="numeric">
                            </div>
                        </div>
                        <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt wp-element-button"><?php echo esc_html($product->single_add_to_cart_text()); ?></button>
                    </div>
                </div>
                <?php
            }else{
                ?>
                <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt wp-element-button"><?php echo esc_html($product->single_add_to_cart_text()); ?></button>
                <?php
            }
        ?>

    </form>

    <?php do_action('woocommerce_after_add_to_cart_form'); ?>

<?php elseif ($goal_reached && $donation_type === 'variable') : ?>
    <div class="donation-goal-reached-message">
        <p><?php esc_html_e('This donation goal has been reached! Thank you to everyone who contributed.', 'wc-invoice-payment'); ?></p>
    </div>
<?php elseif (!$within_deadline && $donation_type === 'variable') : ?>
    <div class="donation-deadline-expired-message">
        <?php 
        $deadline_message = get_post_meta($product->get_id(), '_donation_deadline_message', true);
        if (!$deadline_message) {
            $deadline_message = __('The donation period has ended. Thank you for your interest!', 'wc-invoice-payment');
        }
        ?>
        <p><?php echo esc_html($deadline_message); ?></p>
    </div>
<?php endif; ?>
