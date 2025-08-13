(function() {
  // Função para remover elementos de preço
  function removerPrecos() {
    if(wcInvoiceHidePrice.quoteMode == 'yes'){
        document.querySelectorAll(`
          .wc-block-components-formatted-money-amount,
          .wc-block-cart-items__header-total,
          .wp-block-woocommerce-cart-order-summary-totals-block,
          .wc-block-components-totals-item__label,
          .woocommerce-Price-amount.amount
        `).forEach(el => el.remove());
    }

    if(wcInvoiceHidePrice.quoteMode == 'yes'){
        document.querySelectorAll(`
            .wc-block-components-button.wp-element-button.wp-block-woocommerce-mini-cart-checkout-button-block.wc-block-mini-cart__footer-checkout.contained,
            .wc-block-components-button.wp-element-button.wc-block-cart__submit-button.contained
        `).forEach((el) => {
            console.log(el)
             
            
            el.addEventListener('click', (e) => {
                e.preventDefault();
                console.log(el)
            });
        });
    }
  }

  // Executa imediatamente, caso já exista algum preço na página
  removerPrecos();

  // Define o alvo a ser observado: todo o body para capturar inserções em qualquer lugar
  const observer = new MutationObserver((mutationsList) => {
    for (const mutation of mutationsList) {
      if (mutation.type === 'childList' || mutation.type === 'subtree') {
        removerPrecos();
      }
    }
  });

  // Inicia o observador
  observer.observe(document, {
    childList: true,
    subtree: true
  });

})();
