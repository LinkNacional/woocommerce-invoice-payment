/**
 * JavaScript para produtos de doação - Frontend
 * 
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Função para remover preço de elementos de doação
    function removeDonationPrice() {
        const donationAmount = document.querySelector('#donation_amount');
        if (!donationAmount) {

            // Percorre todos os elementos com o atributo data-wp-context
            const elements = document.querySelectorAll('[data-wp-context]');
            let element = null;

            elements.forEach(el => {
                let context = el.getAttribute('data-wp-context');

                // 🔧 converte &quot; para aspas e tenta fazer parse do JSON
                context = context.replace(/&quot;/g, '"');

                // 🔄 decodifica unicode automaticamente
                const json = JSON.parse(context);

                // 🔍 compara com o valor real vindo do backend (sem se importar com codificação)
                if (json.addToCartText && json.addToCartText.trim() === phpAttributes.makeDonation.trim()) {
                    element = el;
                }
                if (element) {
                    const li = element.closest('li');
                    if (li) {
                        const priceElement = li.querySelector('.woocommerce-Price-amount');
                        if (priceElement) {
                            priceElement.remove();
                        }
                        
                        const link = li.querySelector('a[href]');
                        if (link) {
                            element.onclick = function (e) {
                                e.preventDefault();
                                link.click();
                            };
                        }
                    }
                    const spanButton = element.querySelector('button span');
                    if (spanButton) {
                        spanButton.textContent = phpAttributes.makeDonation;
                    }
                }
            });
        }
    }



    // Executa imediatamente
    removeDonationPrice();

    // Observer para monitorar mudanças no DOM
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                // Verifica se algum nó adicionado contém o texto de doação
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.textContent && node.textContent.includes(phpAttributes.makeDonation)) {
                            removeDonationPrice();
                        }
                    }
                });
            }
        });
    });

    // Inicia o observer
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    /**
     * Manipula cliques nos botões de valores pré-definidos
     */
    $('.donation-preset-btn').on('click', function(e) {
        e.preventDefault();
        
        var amount = $(this).data('amount');
        
        // Define o valor no campo customizado
        $('#donation_amount').val(amount);
        
        // Remove seleção de outros botões e seleciona o atual
        $('.donation-preset-btn').removeClass('selected');
        $(this).addClass('selected');
    });
    
    /**
     * Remove seleção dos botões quando usuário digita valor customizado
     */
    $('#donation_amount').on('input', function() {
        $('.donation-preset-btn').removeClass('selected');
        // Remove campo hidden se existir
        $('input[name="donation_amount"][type="hidden"]').remove();
    });
});
