document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.dokan-attribute-variation-options, .dokan-rma-options, .dokan-linked-product-options').forEach(item => {
        item.classList.add('hide_if_donation');
        console.log(item)
    })

    const linkEl = document.querySelector('a[href*="_dokan_edit_product_nonce"]');
    const currentUrl = new URL(window.location.href);
    const donationType = currentUrl.searchParams.get('donation_type');

    if (donationType && linkEl) {
        // URL do link
        const linkUrl = new URL(linkEl.href);

        // adiciona o parâmetro
        linkUrl.searchParams.set('donation_type', donationType);

        // atualiza o href do link
        linkEl.href = linkUrl.toString();
        linkEl.click()
    }

    if (donationType) {
        const $productTypeSelect = jQuery('#product_type');
        const $donationTypeSelect = jQuery('#_donation_type');

        // Atualiza o tipo de produto para "doação"
        if ($productTypeSelect.length) {3
            $productTypeSelect.val('donation').trigger('change');
        }

        // Atualiza o tipo de doação
        if ($donationTypeSelect.length) {
            $donationTypeSelect.val(donationType).trigger('change');
        }
    }


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
    
    // === CONTROLE DE VISIBILIDADE DOS CAMPOS DE META NO DOKAN ===
    initDokanGoalFields();
    
    function initDokanGoalFields() {
        const $enableGoal = jQuery('#_donation_enable_goal');
        const $goalFields = jQuery('.show_if_donation_goal');
        
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
    }
    
    // === CONTROLE DE VISIBILIDADE DOS CAMPOS DE DATA LIMITE NO DOKAN ===
    initDokanDeadlineFields();
    
    function initDokanDeadlineFields() {
        const $enableDeadline = jQuery('#_donation_enable_deadline');
        const $deadlineFields = jQuery('.show_if_donation_deadline');
        
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
    }
})