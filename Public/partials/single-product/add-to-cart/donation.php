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

echo wc_get_stock_html($product);

if ($product->is_in_stock()) : ?>

    <?php do_action('woocommerce_before_add_to_cart_form'); ?>

    <form class="cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype='multipart/form-data'>
        
        <?php do_action('woocommerce_before_add_to_cart_button'); ?>
        
        <?php
            $donation_type = $product->get_meta('_donation_type', true);
            
            if ($donation_type === 'variable') {
                $button_values = $product->get_meta('_donation_button_values', true);
        ?>
                <div class="donation-fields">
                    <?php if ($button_values) {
                        $values = array_map('trim', explode(',', $button_values));
                    ?>
                    <div class="preset-buttons">
                        <?php foreach ($values as $value) {
                            if (is_numeric($value) && $value > 0) { 
                                $formatted_price = number_format($value, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
                            ?>
                        <button type="button" class="button alt wp-element-button donation-preset-btn" data-amount="<?php echo esc_attr($value); ?>"><?php echo $formatted_price; ?></button>
                        <?php }
                        } ?>
                    </div>
                    <?php } ?>
                    <div class="custom-amount-field">
                        <label for="donation_amount"><?php echo get_woocommerce_currency_symbol(); ?></label>
                        <div class="quantity">
                            <input type="number" id="donation_amount" class="input-text qty text" name="donation_amount" step="0.01" min="0.01" placeholder="0.00" inputmode="numeric">
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

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.donation-preset-btn').on('click', function(e) {
            e.preventDefault();
            var amount = $(this).data('amount');
            $('#donation_amount').val(amount);
            $('.donation-preset-btn').removeClass('selected');
            $(this).addClass('selected');
        });
        
        $('#donation_amount').on('input', function() {
            $('.donation-preset-btn').removeClass('selected');
        });
    });
    </script>

    <style>
    <?php if ($donation_type === 'variable') { ?>
    .woocommerce-Price-amount{
        display: none;
    }
    <?php } ?>

    .donation-fields{
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .donation-preset-btn {
        padding: 8px 0px;
        width: 95px;
        height: 50px;
    }
    
    .preset-buttons{
        margin-left: 35px;
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
    }
    
    #donation_amount {
        width: 55px;
        margin: 0px;
        height: 24px;
        font-size: var(--wp--preset--font-size--medium);
        font-family: inherit;
    }

    [for="donation_amount"]{
        margin: 0px !important;
    }

    .custom-amount-field{
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Responsividade para celular */
    @media (max-width: 768px) {
        .preset-buttons{
            margin-left: 0;
            justify-content: center;
        }
        
        .donation-preset-btn {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .donation-fields{
            gap: 15px;
        }
        
        .custom-amount-field{
            flex-direction: column;
            align-items: stretch;
            gap: 0px;
        }
        
        .custom-amount-field > div:first-of-type {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        #donation_amount {
            width: 100px;
            height: 40px;
            text-align: center;
            font-size: 16px;
        }
        
        [for="donation_amount"]{
            text-align: left;
            font-size: 18px;
            margin: 0 !important;
        }
        
        .single_add_to_cart_button {
            width: 100%;
            height: 50px;
        }
    }
    </style>

<?php endif; ?>
