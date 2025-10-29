/**
 * JavaScript para gerenciar as configurações de doação no admin
 */
(function($) {
    'use strict';

    /**
     * Gerencia a exibição dos campos baseado no tipo de doação
     */
    function initDonationFields() {
        const donationRegularInput = $('#_regular_donation_price');
        const regularPrice = $('#_regular_price');
        const productType = $('#product-type');

        if (donationRegularInput.length || regularPrice.length || productType.length) {
            // Executa quando o tipo de produto muda
            productType.on('change', function() {
                if ($(this).val() === 'donation' && regularPrice.val() === '') {
                    regularPrice.val(donationRegularInput.val());
                }
            });
        }

        
        document.querySelectorAll('.show_if_simple').forEach(item => {
            //Se o item não tiver a classe options_group
            if (!item.classList.contains('options_group')) {
                item.classList.add('show_if_donation');
            }
        })
        
        const $selectType = $('#product-type');

        if ($selectType.length) {
            // Se o tipo atual for "donation", troca para "simple" e volta depois de 1s
            if ($selectType.val() === 'donation') {
                $selectType.trigger('change');
            }
        }

        const $donationType = $('#_donation_type');
        const $inventoryTab = $('.inventory_options.inventory_tab');
        const $shippingTab = $('.shipping_options.shipping_tab');
        const $manageStock = $('#_manage_stock');
        const $donationTab = $('.donation_options');
        const $stock = $('#_stock');

        function handleDonationTypeChange(isOnChange = false) {
            const value = $donationType.val();

            if (value === 'variable' && $selectType.val() === 'donation') {
                // Esconde abas e desmarca estoque
                $inventoryTab.hide();
                $shippingTab.hide();
                $manageStock.prop('checked', false).trigger('change');
                //click em donationTab
                $donationTab.find('a').trigger('click');
            } else {
                // Mostra abas e marca estoque
                $inventoryTab.show();
                $shippingTab.show();
                $manageStock.prop('checked', true).trigger('change');

                // Só altera o valor do estoque no onchange
                if (isOnChange) {
                    const currentStock = parseInt($stock.val(), 10);
                    if (isNaN(currentStock) || currentStock <= 0) {
                        $stock.val(1).trigger('change');
                    }
                }
            }
        }

        // Executa no carregar da tela
        handleDonationTypeChange(false);

        // Executa no change
        $donationType.on('change', function () {
            handleDonationTypeChange(true);
        });


        // Função para mostrar/ocultar campos baseado no tipo de doação
        function toggleDonationFields() {
            var donationType = $('#_donation_type').val();
            var $fixedFields = $('.show_if_donation_fixed');
            var $variableFields = $('.show_if_donation_variable');
            var $freeFields = $('.show_if_donation_free');
            var $goalSection = $('.donation-goal-section');
            
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
        
        // === CONTROLE DE VISIBILIDADE DOS CAMPOS DE META ===
        initDonationGoalFields();
    }
    
    /**
     * Controla a visibilidade dos campos de meta de doação
     */
    function initDonationGoalFields() {
        const $enableGoal = $('#_donation_enable_goal');
        const $goalFields = $('.show_if_donation_goal');
        
        // Função para mostrar/ocultar campos de meta
        function toggleGoalFields() {
            if ($enableGoal.is(':checked')) {
                $goalFields.show();
            } else {
                $goalFields.hide();
            }
        }
        
        // Executa ao carregar
        toggleGoalFields();
        
        // Event listener para mudança no checkbox
        $enableGoal.on('change', toggleGoalFields);
        
        // Também executa quando a aba de doação for clicada
        $(document).on('click', '.donation_tab a', function() {
            setTimeout(toggleGoalFields, 100);
        });
        
        // === CONTROLES PARA CAMPOS DE DATA LIMITE ===
        const $enableDeadline = $('#_donation_enable_deadline');
        const $deadlineFields = $('.show_if_donation_deadline');
        
        // Função para mostrar/ocultar campos de data limite
        function toggleDeadlineFields() {
            if ($enableDeadline.is(':checked')) {
                $deadlineFields.show();
            } else {
                $deadlineFields.hide();
            }
        }
        
        // Executa ao carregar
        toggleDeadlineFields();
        
        // Event listener para mudança no checkbox
        $enableDeadline.on('change', toggleDeadlineFields);
        
        // Também executa quando a aba de doação for clicada
        $(document).on('click', '.donation_tab a', function() {
            setTimeout(function() {
                toggleGoalFields();
                toggleDeadlineFields();
            }, 100);
        });
    }

    // Inicializar quando o documento estiver pronto
    $(document).ready(function() {
        initDonationFields();
    });

})(jQuery);


