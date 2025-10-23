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
})