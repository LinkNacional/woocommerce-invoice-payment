/**
 * Script para atualizar o título da página de confirmação de orçamento
 * baseado no status do orçamento
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const status = wcInvoiceQuoteConfirmation.quoteStatus;
        const orderId = wcInvoiceQuoteConfirmation.orderId;

        const statusMessages = {
            'quote-draft': {
                title: 'Orçamento em Rascunho',
                message: 'Seu orçamento está sendo preparado.'
            },
            'quote-pending': {
                title: 'Orçamento Recebido',
                message: 'Seu orçamento foi recebido e está sendo analisado.'
            },
            'quote-awaiting': {
                title: 'Orçamento Aguardando Aprovação',
                message: 'Seu orçamento está pronto e aguarda sua aprovação.'
            },
            'quote-approved': {
                title: 'Orçamento Aprovado',
                message: 'Seu orçamento foi aprovado com sucesso.'
            },
            'quote-cancelled': {
                title: 'Orçamento Cancelado',
                message: 'Seu orçamento foi cancelado.'
            },
            'quote-expired': {
                title: 'Orçamento Expirado',
                message: 'Seu orçamento expirou.'
            }
        };

        // Função para atualizar o título e mensagem
        function updateQuoteConfirmationStatus() {

            // Adicionar classes CSS ao body para estilização
            document.body.classList.add('wc-quote-confirmation-custom', 'wc-quote-status-' + status.replace('quote-', ''));

            // Procurar pelo bloco de status do WooCommerce
            const statusBlock = document.querySelector('.wp-block-woocommerce-order-confirmation-status, .wc-block-order-confirmation-status');
            
            if (statusBlock) {
                const titleElement = statusBlock.querySelector('h1');
                const messageElement = statusBlock.querySelector('p');
                
                if (titleElement) {
                    titleElement.textContent = statusMessages[status].title;
                }
                
                if (messageElement) {
                    messageElement.textContent = statusMessages[status].message;
                }
                
                return true; // Indica que atualizou com sucesso
            }
            
            // Fallback: procurar por outros elementos de título comuns
            const fallbackSelectors = [
                '.entry-title',
                '.page-title', 
                'h1.woocommerce-order-received-title',
                '.woocommerce-thankyou-order-received h1',
                '.woocommerce-order h1',
                'h1', // Último recurso - primeiro h1 da página
            ];
            
            
            for (const selector of fallbackSelectors) {
                const element = document.querySelector(selector);
                
                if (element) {
                    // Verificar se o texto atual parece ser relacionado a orçamento/pedido
                    const currentText = element.textContent.toLowerCase();
                    if (currentText.includes('orçamento') || 
                        currentText.includes('pedido') || 
                        currentText.includes('recebido') ||
                        currentText.includes('order') ||
                        currentText.includes('received')) {
                        
                        element.textContent = statusMessages[status].title;
                        return true;
                    }
                }
            }
            
            return false;
        }

        // Executar a atualização quando a página carregar
        const success = updateQuoteConfirmationStatus();
        
        if (!success) {
            // Usar MutationObserver para capturar mudanças dinâmicas no DOM
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        // Verificar se novos elementos foram adicionados
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                if (node.matches && 
                                    (node.matches('.wp-block-woocommerce-order-confirmation-status') || 
                                     node.matches('.wc-block-order-confirmation-status'))) {
                                    if (updateQuoteConfirmationStatus()) {
                                        observer.disconnect(); // Para de observar se conseguiu atualizar
                                    }
                                }
                            }
                        });
                    }
                });
            });

            // Observar mudanças no body
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });

})(jQuery);
