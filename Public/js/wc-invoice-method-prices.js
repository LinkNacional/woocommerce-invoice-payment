/**
 * Torna os blocos de método de pagamento clicáveis para trocar o gateway padrão.
 * Mantém a ordem natural da lista, apenas marca o selecionado como is-default.
 */
(function() {
  document.addEventListener('click', function(e) {
    var block = e.target.closest('.wc-invoice-payment-method-price');
    if (!block) return;

    // Não faz nada se já for o default
    if (block.classList.contains('is-default')) return;

    var gatewayId = block.getAttribute('data-gateway');
    if (!gatewayId) return;

    var gatewayTitle = block.getAttribute('data-gateway-title') || gatewayId;
    var confirmed = confirm(wcInvoiceMethodPrices.confirmText + ' "' + gatewayTitle + '"?');
    if (!confirmed) return;

    // Atualiza o session via AJAX e recarrega a página
    var formData = new FormData();
    formData.append('action', 'lkn_wcip_set_default_gateway');
    formData.append('gateway', gatewayId);

    fetch(wcInvoiceMethodPrices.ajaxUrl, {
      method: 'POST',
      body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        window.location.reload();
      }
    })
    .catch(function() {
      window.location.reload();
    });
  });
})();
