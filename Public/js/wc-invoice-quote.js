/**
 * JavaScript para modo orçamento - substitui textos do WooCommerce.
 * @package WcInvoicePayment
 */
(function() {

  var applyScheduled = false;

  function scheduleApply() {
    if (applyScheduled) return;
    applyScheduled = true;
    requestAnimationFrame(function() {
      applyScheduled = false;
      applyQuoteTexts();
      hidePrices();
    });
  }

  function applyQuoteTexts() {
    if (wcInvoiceHidePrice.quoteMode !== 'yes') return;

    // Botões "Ir para finalização" → "Request quote"
    document.querySelectorAll('.wc-block-mini-cart__footer-checkout, .wc-block-cart__submit-button').forEach(function(el) {
      if (el.hasAttribute('data-quote-replaced')) return;
      var a = document.createElement('a');
      a.href = el.href;
      a.className = el.className;
      a.innerHTML = '<div class="wc-block-components-button__text">' + wcInvoiceHidePrice.requestQuoteText + '</div>';
      a.setAttribute('data-quote-replaced', 'true');
      el.parentNode.replaceChild(a, el);
    });

    // Títulos "Total no carrinho" / "Total in cart"
    document.querySelectorAll('.wc-block-cart__totals-title, .cart_totals h2').forEach(function(el) {
      if (el.hasAttribute('data-quote-title')) return;
      el.textContent = wcInvoiceHidePrice.totalInQuote;
      el.setAttribute('data-quote-title', 'true');
    });

    // "Continuar para Finalização" (shortcode)
    document.querySelectorAll('.checkout-button.button.alt.wc-forward').forEach(function(a) {
      if (a.hasAttribute('data-quote-proceed')) return;
      a.textContent = wcInvoiceHidePrice.requestQuoteText;
      a.setAttribute('data-quote-proceed', 'true');
    });

    // "Atualizar carrinho" (shortcode)
    document.querySelectorAll('button[name="update_cart"]').forEach(function(btn) {
      if (btn.hasAttribute('data-quote-update')) return;
      btn.value = wcInvoiceHidePrice.updateQuote;
      btn.textContent = wcInvoiceHidePrice.updateQuote;
      btn.setAttribute('data-quote-update', 'true');
    });

    // "O frete será calculado..." / "Shipping will be calculated..."
    document.querySelectorAll('.wc-block-components-totals-footer-item-shipping').forEach(function(el) {
      if (el.hasAttribute('data-quote-shipping')) return;
      el.textContent = wcInvoiceHidePrice.shippingCalcAtQuote;
      el.setAttribute('data-quote-shipping', 'true');
    });

    // Notícias: "Carrinho atualizado." / "adicionado ao carrinho" → texto traduzido
    document.querySelectorAll('.wc-block-components-notice-banner__content').forEach(function(el) {
      var p = wcInvoiceHidePrice;
      el.innerHTML = el.innerHTML
        .replace(/Carrinho atualizado\./g, p.quoteUpdated)
        .replace(/Cart updated\./g, p.quoteUpdated)
        .replace(/(?:foi|foram) adicionad[oa]s? ao seu carrinho\./g, p.addedToQuoteText)
        .replace(/has been added to your cart\./g, p.addedToQuoteText);
    });

    // Link "Ver carrinho" dentro de notícias → "View quote" traduzido
    document.querySelectorAll('.wc-block-components-notice-banner__content a.wc-forward').forEach(function(link) {
      if (link.textContent.trim() === 'Ver carrinho' || link.textContent.trim() === 'View cart') {
        link.textContent = wcInvoiceHidePrice.viewQuote;
      }
    });
  }

  function hidePrices() {
    if (wcInvoiceHidePrice.showPrice !== 'no' || wcInvoiceHidePrice.quoteMode !== 'yes') return;
    if (wcInvoiceHidePrice.quoteStatus && wcInvoiceHidePrice.quoteStatus !== 'wc-quote-request') return;

    document.querySelectorAll('.wc-block-components-formatted-money-amount, .wc-block-cart-items__header-total, .wp-block-woocommerce-cart-order-summary-totals-block, .wc-block-components-totals-item__label, .woocommerce-Price-amount.amount').forEach(function(el) {
      if (el.innerHTML === wcInvoiceHidePrice.reviewText) return;
      el.innerHTML = wcInvoiceHidePrice.reviewText;
      el.style.setProperty('display', 'block', 'important');
    });
  }

  // Executa imediatamente
  applyQuoteTexts();
  hidePrices();

  // Observer com requestAnimationFrame - evita loop em atualizações AJAX
  var observer = new MutationObserver(function() {
    scheduleApply();
  });

  observer.observe(document, { childList: true, subtree: true });

})();
