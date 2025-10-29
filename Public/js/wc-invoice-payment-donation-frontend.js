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
     * Função para controlar estado do botão baseado no valor do input
     */
    function toggleButtonVisibility() {
        var donationAmount = $('#donation_amount').val();
        var button = $('.custom-amount-field button');
        
        if (!donationAmount || donationAmount.trim() === '') {
            button.prop('disabled', true);
        } else {
            button.prop('disabled', false);
        }
    }
    
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
        
        // Controla visibilidade do botão
        toggleButtonVisibility();
    });
    
    /**
     * Remove seleção dos botões quando usuário digita valor customizado
     */
    $('#donation_amount').on('input change keyup blur', function() {
        $('.donation-preset-btn').removeClass('selected');
        // Remove campo hidden se existir
        $('input[name="donation_amount"][type="hidden"]').remove();
        
        // Controla visibilidade do botão
        toggleButtonVisibility();
    });
    
    // Executa a verificação inicial quando a página carrega
    toggleButtonVisibility();
    
    /**
     * Funcionalidade da barra de progresso da doação
     */
    function initDonationProgressBar() {
        const progressBar = document.querySelector('.donation-progress-fill');
        if (progressBar) {
            // Animação inicial da barra de progresso
            const targetWidth = progressBar.style.width;
            progressBar.style.width = '0%';
            setTimeout(() => {
                progressBar.style.width = targetWidth;
            }, 300);
        }
    }
    
    // Inicializa a barra de progresso
    initDonationProgressBar();
    
    /**
     * Funcionalidade do contador regressivo
     */
    function initDonationCountdown() {
        const countdownTimer = document.querySelector('.donation-countdown-timer');
        if (!countdownTimer) return;
        
        const deadline = countdownTimer.getAttribute('data-deadline');
        if (!deadline) return;
        
        // Verifica se já inclui horário (formato datetime-local com 'T')
        // Se não, adiciona horário de fim do dia (23:59:59) para compatibilidade
        let deadlineDate;
        if (deadline.includes('T')) {
            // Formato datetime-local (ex: 2025-12-31T23:59)
            deadlineDate = new Date(deadline);
        } else {
            // Formato apenas data (ex: 2025-12-31) - adiciona fim do dia
            deadlineDate = new Date(deadline + ' 23:59:59');
        }
        
        function updateCountdown() {
            const now = new Date().getTime();
            const timeLeft = deadlineDate.getTime() - now;
            
            if (timeLeft <= 0) {
                // Prazo expirado - recarregar página para mostrar mensagem de expirado
                location.reload();
                return;
            }
            
            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            // Atualizar os elementos do DOM
            const daysElement = document.getElementById('countdown-days');
            const hoursElement = document.getElementById('countdown-hours');
            const minutesElement = document.getElementById('countdown-minutes');
            const secondsElement = document.getElementById('countdown-seconds');
            
            if (daysElement) daysElement.textContent = days;
            if (hoursElement) hoursElement.textContent = hours;
            if (minutesElement) minutesElement.textContent = minutes;
            if (secondsElement) secondsElement.textContent = seconds;
        }
        
        // Atualizar a cada segundo
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
    
    // Inicializa o contador regressivo
    initDonationCountdown();
});
