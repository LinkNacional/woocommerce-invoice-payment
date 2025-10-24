<div class="show_if_donation dokan_donation_settings">
	<p>
		<label for="_donation_type"><?php esc_html_e(__('Donation type', 'wc-invoice-payment')); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_attr_e(__('Select the donation type.', 'wc-invoice-payment')); ?>"></i></label>
		<select class="dokan-form-control" id="_donation_type" name="_donation_type">
			<option value="fixed" <?php selected($_donation_type, 'fixed'); ?>><?php esc_html_e(__('Fixed Amount (Donate a fixed value item)', 'wc-invoice-payment')); ?></option>
			<option value="variable" <?php selected($_donation_type, 'variable'); ?>><?php esc_html_e(__('Variable Amount (Receive monetary donations)', 'wc-invoice-payment')); ?></option>
			<option value="free" <?php selected($_donation_type, 'free'); ?>><?php esc_html_e(__('Free (Donate an item)', 'wc-invoice-payment')); ?></option>
		</select>
	</p>
	<p class="show_if_donation_fixed ">
		<label for="_regular_donation_price"><?php echo sprintf(esc_html__('Donation amount (%s)', 'wc-invoice-payment'), get_woocommerce_currency_symbol()); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_attr_e(__('Set the fixed donation amount.', 'wc-invoice-payment')); ?>"></i></label>
		<input type="text" class="short wc_input_price dokan-form-control" name="_regular_donation_price" id="_regular_donation_price" value="<?php echo esc_attr($_regular_price); ?>" placeholder="0">
	</p>
	<p class="show_if_donation_variable">
		<label for="_donation_button_values"><?php esc_html_e(__('Preset button values', 'wc-invoice-payment')); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_attr_e(__('Enter the preset button values separated by comma. E.g.: 10, 20, 25', 'wc-invoice-payment')); ?>"></i></label>
		<textarea class="short dokan-form-control" name="_donation_button_values" id="_donation_button_values" cols="20"><?php echo esc_textarea($_donation_button_values); ?></textarea>
	</p>
	<p class="show_if_donation_variable" id="_donation_hide_custom_amount_field">
		<label>
			<input type="checkbox" class="_donation_hide_custom_amount" name="_donation_hide_custom_amount" id="_donation_hide_custom_amount" value="yes" <?php checked($_donation_hide_custom_amount, 'yes'); ?>> <?php esc_html_e(__('Hide custom amount field', 'wc-invoice-payment')); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_attr_e(__('Check this option to hide the custom amount field and show only the preset buttons.', 'wc-invoice-payment')); ?>"></i>
		</label>
	</p>
	<p class="show_if_donation_free">
		<label for="_donation_free_text"><?php esc_html_e(__('Text', 'wc-invoice-payment')); ?> <i class="fas fa-question-circle tips" aria-hidden="true" data-title="<?php esc_attr_e(__('Text to be displayed for free donation.', 'wc-invoice-payment')); ?>"></i></label>
		<input type="text" class="short dokan-form-control" name="_donation_free_text" id="_donation_free_text" value="<?php echo esc_attr($_donation_free_text); ?>" placeholder="<?php esc_attr_e(__('Free', 'wc-invoice-payment')); ?>">
	</p>
</div>