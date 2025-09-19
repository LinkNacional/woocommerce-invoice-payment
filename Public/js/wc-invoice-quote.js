(function() {
  // Function to remove price elements
  function removePrice() {
    if(wcInvoiceHidePrice.showPrice == 'no' && wcInvoiceHidePrice.quoteMode == 'yes'){
        if(!wcInvoiceHidePrice.quoteStatus || wcInvoiceHidePrice.quoteStatus == 'quote-request'){
          document.querySelectorAll(`
            .wc-block-components-formatted-money-amount,
            .wc-block-cart-items__header-total,
            .wp-block-woocommerce-cart-order-summary-totals-block,
            .wc-block-components-totals-item__label,
            .woocommerce-Price-amount.amount
          `).forEach(el => {
            if(el.innerHTML !== wcInvoiceHidePrice.reviewText){
              el.innerHTML = wcInvoiceHidePrice.reviewText;
              el.style.setProperty('display', 'block', 'important');
            }
          });
        }
    }

    if(wcInvoiceHidePrice.quoteMode == 'yes'){
        document.querySelectorAll(`
            .wc-block-components-button.wp-element-button.wp-block-woocommerce-mini-cart-checkout-button-block.wc-block-mini-cart__footer-checkout.contained,
            .wc-block-components-button.wp-element-button.wc-block-cart__submit-button.contained
        `).forEach((el) => {
            // Check if button has already been replaced to avoid duplicates
            if (!el.hasAttribute('data-replaced')) {
                // Create new button
                const newA = document.createElement('a');
                newA.href = el.href;
                newA.className = 'wc-block-components-button wp-element-button wc-block-cart__submit-button contained';
                newA.innerHTML = '<div class="wc-block-components-button__text">' + wcInvoiceHidePrice.requestQuoteText + '</div>';
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


        document.querySelectorAll(`
          .wc-block-checkout__payment-method.wp-block-woocommerce-checkout-payment-block.wc-block-components-checkout-step,
          .wc_payment_method.payment_method_lkn_invoice_quote_gateway
          `)
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

        summaryTitleElement = document.querySelector('.wc-block-components-checkout-order-summary__title-text')
        orderNotesElement = document.querySelector('#order-notes')
        finishButton = document.querySelector('.wc-block-components-checkout-place-order-button__text')
        descriptionsElements = document.querySelectorAll('.wc-block-components-checkout-step__description')

        if(summaryTitleElement && orderNotesElement){
          //Modify descriptions
          descriptionsElements[0].innerHTML = wcInvoiceHidePrice.emailDescription || 'We will use this email to send information and updates about your quote.'
          descriptionsElements[1].innerHTML = wcInvoiceHidePrice.addressDescription || 'Enter the address where you want your quote to be delivered.'
          summaryTitleElement.innerHTML = wcInvoiceHidePrice.quoteSummaryText
          finishButton.innerHTML = wcInvoiceHidePrice.requestQuoteText
          orderNotesElement.remove()
        }

        addCartElement = document.querySelector('.wp-block-button__link.wp-element-button.add_to_cart_button.ajax_add_to_cart');
        if(addCartElement && addCartElement.innerHTML !== wcInvoiceHidePrice.requestQuoteText) {
          addCartElement.innerHTML = wcInvoiceHidePrice.requestQuoteText;
        }

        cuponElement = document.querySelector(`
          .wp-block-woocommerce-checkout-order-summary-coupon-form-block.wc-block-components-totals-wrapper,
          .wp-block-woocommerce-cart-order-summary-coupon-form-block.wc-block-components-totals-wrapper
          `);
        if(cuponElement && wcInvoiceHidePrice.showCupon != 'yes') {
          cuponElement.remove();
        }
        if(document.querySelector('.quotesAccount')){
          tableQuotesThElement = document.querySelector('.quotesAccount')?.parentElement?.parentElement?.querySelector('.nobr');
          if(tableQuotesThElement && tableQuotesThElement.innerHTML !== wcInvoiceHidePrice.quotesText) {
            tableQuotesThElement.innerHTML = wcInvoiceHidePrice.quotesText;
          }
        }
      }
    }

  // Execute immediately, in case there are already prices on the page
  removePrice();

  // Define the target to be observed: the entire body to capture insertions anywhere
  const observer = new MutationObserver((mutationsList) => {
    for (const mutation of mutationsList) {
      if (mutation.type === 'childList' || mutation.type === 'subtree') {
        removePrice();
      }
    }
  });

  // Start the observer
  observer.observe(document, {
    childList: true,
    subtree: true
  });

})();
