document.addEventListener('DOMContentLoaded', function () {
    // Função para mostrar/ocultar campos baseado no tipo de doação
    function toggleDonationFields() {
        var donationType = document.getElementById('_donation_type')?.value;
        var fixedFields = document.querySelectorAll('.show_if_donation_fixed');
        var variableFields = document.querySelectorAll('.show_if_donation_variable');
        var freeFields = document.querySelectorAll('.show_if_donation_free');

        // Esconde todos os campos primeiro
        fixedFields.forEach(function(el){ el.style.display = 'none'; });
        variableFields.forEach(function(el){ el.style.display = 'none'; });
        freeFields.forEach(function(el){ el.style.display = 'none'; });

        // Mostra campos baseado no tipo
        if (donationType === 'fixed') {
            fixedFields.forEach(function(el){ el.style.display = ''; });
        } else if (donationType === 'variable') {
            variableFields.forEach(function(el){ el.style.display = ''; });
        } else if (donationType === 'free') {
            freeFields.forEach(function(el){ el.style.display = ''; });
        }
    }

    // Executa ao carregar
    toggleDonationFields();

    // Event listener para mudança do select
    var donationTypeSelect = document.getElementById('_donation_type');
    if (donationTypeSelect) {
        donationTypeSelect.addEventListener('change', toggleDonationFields);
    }

    document.querySelectorAll('.show_if_simple').forEach((item, i) => {
        if(i == 0 || i == 2 || i == 3){
            item.classList.add('show_if_donation')
        }
    })

    // Lógica para esconder/mostrar abas e manipular estoque no Dokan
    const $donationType = jQuery('#_donation_type');
    const $inventoryTab = jQuery('.dokan-product-inventory-options, .dokan-product-inventory');
    const $shippingTab = jQuery('.dokan-product-shipping-tax');
    const $manageStock = jQuery('#_manage_stock');
    const $donationTab = jQuery('.donation_options, .dokan-product-donation-options');
    const $stock = jQuery('[name=_stock]');

    function handleDonationTypeChangeDokan(isOnChange = false) {
        const value = $donationType.val();

        if (value === 'variable') {
            $inventoryTab.hide();
            $shippingTab.hide();
            $manageStock.prop('checked', false).trigger('change');
            $donationTab.find('a').trigger('click');
        } else {
            $inventoryTab.show();
            $shippingTab.show();
            $manageStock.prop('checked', true).trigger('change');
            if (isOnChange) {
                const currentStock = parseInt($stock.val(), 10);
                if (isNaN(currentStock) || currentStock <= 0) {
                    $stock.val(1).trigger('change');
                }
            }
        }
    }

    // Executa no carregar da tela
    handleDonationTypeChangeDokan(false);

    // Executa no change
    $donationType.on('change', function () {
        handleDonationTypeChangeDokan(true);
    });
})