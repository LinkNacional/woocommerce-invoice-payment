/**
 * JavaScript para gerenciar as configurações de doação no admin
 */
(function($) {
    'use strict';

    /**
     * Gerencia a exibição dos campos baseado no tipo de doação
     */
    function initDonationFields() {
        // Função para mostrar/ocultar campos baseado no tipo de doação
        function toggleDonationFields() {
            var donationType = $('#_donation_type').val();
            var $fixedFields = $('.show_if_donation_fixed');
            var $variableFields = $('.show_if_donation_variable');
            var $freeFields = $('.show_if_donation_free');
            
            // Esconde todos os campos primeiro
            $fixedFields.hide();
            $variableFields.hide();
            $freeFields.hide();
            
            // Mostra campos baseado no tipo
            if (donationType === 'fixed') {
                $fixedFields.show();
            } else if (donationType === 'variable') {
                $variableFields.show();
            } else if (donationType === 'free') {
                $freeFields.show();
            }
        }
        
        // Função para verificar se é produto de doação
        function isDonationProduct() {
            return $('#product-type').val() === 'donation';
        }
        
        // Função para controlar visibilidade da aba de doação
        function toggleDonationTab() {
            var $donationTab = $('.donation_tab');
            var $donationPanel = $('#donation_product_data');
            
            if (isDonationProduct()) {
                $donationTab.show();
                if ($donationTab.hasClass('active')) {
                    toggleDonationFields();
                }
            } else {
                $donationTab.hide();
                $donationPanel.hide();
            }
        }
        
        // Executar ao carregar a página
        if ($('#_donation_type').length) {
            toggleDonationTab();
            toggleDonationFields();
        }
        
        // Event listeners
        $('#_donation_type').on('change', toggleDonationFields);
        $('#product-type').on('change', function() {
            setTimeout(toggleDonationTab, 100);
        });
        
        // Quando a aba de doação é clicada
        $(document).on('click', '.donation_tab a', function() {
            setTimeout(toggleDonationFields, 100);
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
    }

    // Inicializar quando o documento estiver pronto
    $(document).ready(function() {
        initDonationFields();
    });

})(jQuery);
