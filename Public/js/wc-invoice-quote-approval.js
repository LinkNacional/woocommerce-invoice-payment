/**
 * JavaScript para página de aprovação de orçamento
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Handler para botão de aprovar orçamento
        $('.approve-quote').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var quoteId = button.data('quote-id');
            
            // Confirmação
            if (!confirm(wcInvoiceQuoteApproval.texts.confirmApprove)) {
                return;
            }
            
            // Mostrar loading
            button.prop('disabled', true);
            button.text(wcInvoiceQuoteApproval.texts.processing);
            $('.quote-action-buttons').addClass('quote-loading');
            
            // Fazer requisição AJAX
            $.ajax({
                url: wcInvoiceQuoteApproval.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lkn_wcip_approve_quote_frontend',
                    nonce: wcInvoiceQuoteApproval.nonce,
                    quote_id: quoteId
                },
                success: function(response) {
                    if (response.success) {
                        // Sucesso - redirecionar para a página de pagamento
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert(response.data.message || 'Orçamento aprovado com sucesso!');
                            window.location.reload();
                        }
                    } else {
                        alert('Erro: ' + (response.data || 'Erro desconhecido'));
                        resetButtons();
                    }
                },
                error: function() {
                    alert('Erro de conexão. Tente novamente.');
                    resetButtons();
                }
            });
        });
        
        // Handler para botão de cancelar orçamento
        $('.cancel-quote').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var quoteId = button.data('quote-id');
            
            // Confirmação
            if (!confirm(wcInvoiceQuoteApproval.texts.confirmCancel)) {
                return;
            }
            
            // Mostrar loading
            button.prop('disabled', true);
            button.text(wcInvoiceQuoteApproval.texts.processing);
            $('.quote-action-buttons').addClass('quote-loading');
            
            // Fazer requisição AJAX
            $.ajax({
                url: wcInvoiceQuoteApproval.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lkn_wcip_cancel_quote_frontend',
                    nonce: wcInvoiceQuoteApproval.nonce,
                    quote_id: quoteId
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || 'Orçamento cancelado com sucesso!');
                        // Redirecionar para página de pedidos ou recarregar
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert('Erro: ' + (response.data || 'Erro desconhecido'));
                        resetButtons();
                    }
                },
                error: function() {
                    alert('Erro de conexão. Tente novamente.');
                    resetButtons();
                }
            });
        });
        
        function resetButtons() {
            $('.approve-quote').prop('disabled', false).text(wcInvoiceQuoteApproval.texts.approveText || 'Approve');
            $('.cancel-quote').prop('disabled', false).text(wcInvoiceQuoteApproval.texts.cancelText || 'Cancel');
            $('.quote-action-buttons').removeClass('quote-loading');
        }
    });

})(jQuery);
