/**
 * JavaScript para gerenciar as configurações de doação no admin
 */
(function($) {
    'use strict';

    /**
     * Gerencia a exibição dos campos baseado no tipo de doação
     */
    function initDonationFields() {
        const $selectType = $('#product-type');
        const $donationType = $('#_donation_type');
        const $donationRegularInput = $('#_regular_donation_price');
        const $regularPrice = $('#_regular_price');
        const $inventoryTab = $('.inventory_options.inventory_tab');
        const $shippingTab = $('.shipping_options.shipping_tab');
        const $manageStock = $('#_manage_stock');
        const $donationTab = $('.donation_options');
        const $donationPanel = $('#donation_product_data');
        const $stock = $('#_stock');
        const $fixedFields = $('.show_if_donation_fixed');
        const $variableFields = $('.show_if_donation_variable');
        const $freeFields = $('.show_if_donation_free');
        const $goalSection = $('.donation-goal-section');
        const $enableGoal = $('#_donation_enable_goal');
        const $goalFields = $('.show_if_donation_goal');
        const $enableDeadline = $('#_donation_enable_deadline');
        const $deadlineFields = $('.show_if_donation_deadline');

        if (!$selectType.length) {
            return;
        }

        // Se o tipo donation não estiver habilitado, não aplica nenhuma regra do plugin.
        if (!$selectType.find('option[value="donation"]').length) {
            return;
        }

        function getState() {
            const productType = $selectType.val();
            const donationType = $donationType.val();

            return {
                isDonationProduct: productType === 'donation',
                donationType: donationType,
                isVariableDonation: donationType === 'variable'
            };
        }

        function toggleDonationFields(donationType) {
            // Esconde todos os campos primeiro
            $fixedFields.hide();
            $variableFields.hide();
            $freeFields.hide();
            $goalSection.hide();

            // Mostra campos baseado no tipo
            if (donationType === 'fixed') {
                $fixedFields.show();
            } else if (donationType === 'variable') {
                $variableFields.show();
                $goalSection.show();
            } else if (donationType === 'free') {
                $freeFields.show();
            }
        }

        function toggleGoalFields(isVisible) {
            if (isVisible && $enableGoal.is(':checked')) {
                $goalFields.show();
            } else {
                $goalFields.hide();
            }
        }

        function toggleDeadlineFields(isVisible) {
            if (isVisible && $enableDeadline.is(':checked')) {
                $deadlineFields.show();
            } else {
                $deadlineFields.hide();
            }
        }

        function applyDonationUiState(options) {
            const state = getState();
            const opts = options || {};

            $donationTab.toggle(state.isDonationProduct);

            if (!state.isDonationProduct) {
                $donationPanel.hide();
                $inventoryTab.show();
                $shippingTab.show();
                toggleGoalFields(false);
                toggleDeadlineFields(false);
                return;
            }

            toggleDonationFields(state.donationType);
            toggleGoalFields(state.isVariableDonation);
            toggleDeadlineFields(state.isVariableDonation);

            if (state.isVariableDonation) {
                $inventoryTab.hide();
                $shippingTab.hide();
                $manageStock.prop('checked', false).trigger('change');
            } else {
                $inventoryTab.show();
                $shippingTab.show();
                $manageStock.prop('checked', true).trigger('change');

                if (opts.fromDonationTypeChange) {
                    const currentStock = parseInt($stock.val(), 10);
                    if (isNaN(currentStock) || currentStock <= 0) {
                        $stock.val(1).trigger('change');
                    }
                }
            }
        }

        // Executa quando o tipo de produto muda
        if ($donationRegularInput.length || $regularPrice.length) {
            $selectType.on('change', function() {
                if ($(this).val() === 'donation' && $regularPrice.val() === '') {
                    $regularPrice.val($donationRegularInput.val());
                }
            });
        }

        $selectType.on('change', function() {
            applyDonationUiState();
        });

        $donationType.on('change', function() {
            applyDonationUiState({ fromDonationTypeChange: true });
        });

        $enableGoal.on('change', function() {
            applyDonationUiState();
        });

        $enableDeadline.on('change', function() {
            applyDonationUiState();
        });

        $(document.body).on('woocommerce-product-type-change', function() {
            applyDonationUiState();
        });

        // Quando a aba de doação é clicada, reaplica o estado dos campos.
        $(document).on('click', '.donation_tab a', function() {
            applyDonationUiState();
        });

        // Função para mostrar/ocultar checkbox baseado nos valores
        function toggleCustomAmountCheckbox() {
            var values = $('#_donation_button_values').val();
            var checkboxField = $('#_donation_hide_custom_amount_field');
            
            if (values && values.trim()) {
                // Valida se há valores válidos
                var cleanedValues = values.replace(/\s*,\s*/g, ',').replace(/,+/g, ',').replace(/^,|,$/g, '');
                var valuesArray = cleanedValues.split(',');
                var hasValidValues = false;
                
                valuesArray.forEach(function(value) {
                    var numValue = parseFloat(value.trim());
                    if (!isNaN(numValue) && numValue > 0) {
                        hasValidValues = true;
                    }
                });
                
                if (hasValidValues) {
                    checkboxField.show();
                } else {
                    checkboxField.hide();
                    $('#_donation_hide_custom_amount').prop('checked', false);
                }
            } else {
                checkboxField.hide();
                $('#_donation_hide_custom_amount').prop('checked', false);
            }
        }
        
        // Validação dos valores dos botões
        $('#_donation_button_values').on('blur input', function() {
            // Atualiza visibilidade da checkbox
            toggleCustomAmountCheckbox();
        });
        
        // Executa ao carregar
        toggleCustomAmountCheckbox();
        
        // Sincroniza valor do campo de doação para o campo original do WooCommerce
        $('#_regular_donation_price').on('input', function() {
            var donationType = $('#_donation_type').val();
            if (donationType === 'fixed') {
                var value = $(this).val();
                // Atualiza o campo original do WooCommerce
                $('#_regular_price').val(value);
            }
        });

        applyDonationUiState();
    }

    // Inicializar quando o documento estiver pronto
    $(document).ready(function() {
        initDonationFields();
    });

})(jQuery);


