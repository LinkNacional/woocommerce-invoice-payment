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
            
            // Esconde todos os campos primeiro
            $fixedFields.hide();
            $variableFields.hide();
            
            // Mostra campos baseado no tipo
            if (donationType === 'fixed') {
                $fixedFields.show();
            } else if (donationType === 'variable') {
                $variableFields.show();
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
        
        // Validação dos valores dos botões
        $('#_donation_button_values').on('blur', function() {
            var values = $(this).val();
            if (values) {
                // Remove espaços extras e vírgulas duplas
                var cleanedValues = values.replace(/\s*,\s*/g, ',').replace(/,+/g, ',').replace(/^,|,$/g, '');
                
                // Valida se os valores são números
                var valuesArray = cleanedValues.split(',');
                var validValues = [];
                
                valuesArray.forEach(function(value) {
                    var numValue = parseFloat(value.trim());
                    if (!isNaN(numValue) && numValue > 0) {
                        validValues.push(numValue);
                    }
                });
                
                // Atualiza o campo com valores limpos
                if (validValues.length > 0) {
                    $(this).val(validValues.join(', '));
                } else {
                    $(this).val('');
                }
            }
        });
    }

    // Inicializar quando o documento estiver pronto
    $(document).ready(function() {
        initDonationFields();
    });

})(jQuery);
