(function() {
  // Função para remover elementos de preço
  function removerPrecos() {
    if(wcInvoiceHidePrice.showPrice == 'no'){
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
            // Verifica se o botão já foi substituído para evitar duplicatas
            if (!el.hasAttribute('data-replaced')) {
                // Cria o novo botão
                const newA = document.createElement('a');
                newA.href = el.href;
                newA.className = 'wc-block-components-button wp-element-button wc-block-cart__submit-button contained';
                newA.innerHTML = '<div class="wc-block-components-button__text">Solicitar orçamento</div>';
                newA.setAttribute('data-replaced', 'true');
                
                el.parentNode.replaceChild(newA, el);
            }
        });
        document.querySelectorAll(`
            .wp-block-woocommerce-checkout-order-summary-totals-block,
            .wc-block-checkout__shipping-option.wp-block-woocommerce-checkout-shipping-methods-block.wc-block-components-checkout-step,
            .wc-block-components-totals-item.wc-block-components-totals-footer-item
        `).forEach((el) => {
          el.remove();
        });


        document.querySelectorAll(`.wc-block-checkout__payment-method.wp-block-woocommerce-checkout-payment-block.wc-block-components-checkout-step`)
        .forEach((el) => {
            el.querySelectorAll('.wc-block-components-radio-control-accordion-option').forEach((option) => {
                if(option.firstChild.getAttribute('for') !== 'radio-control-wc-payment-method-options-lkn_invoice_quote_gateway') {
                  option.remove();
                }else{
                  option.firstChild.firstChild.click()
                }
            });
            el.style.display = 'none';
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
