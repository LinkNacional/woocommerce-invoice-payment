(function ($) {
    $(window).on('load', function () {
        let orderId
        if(typeof wcSettings != 'undefined'){
            orderId = wcSettings?.checkoutData?.order_id
        }else{
            orderId = 'newOrder';
        }
        const intervalId = setInterval(() => {
            const checkoutForm = document.querySelector('.wc-block-components-form.wc-block-checkout__form')
            const urlParams = new URLSearchParams(window.location.search);
            const payForOrder = urlParams.get('pay_for_order');
            cartFlowDiv = false
            if(!checkoutForm && payForOrder != 'true'){
                cartFlowDiv = document.querySelector('#payment');
            }
            if (checkoutForm || cartFlowDiv) {
                symbol = lknWcipDonationVariables.symbol
                clearInterval(intervalId);
                
                if(checkoutForm){
                    anonymousDonatePaymentHTML = `
                    <div class="wcPaymentInvoiceContainer">
                        <div class="wcPaymentInvoiceInner">
                            <div class="wcPaymentInvoiceCheckboxWrapper">
                                <div class="wc-block-components-checkbox wc-block-checkout__use-address-for-billing">
                                    <label for="wcPaymentInvoiceContainerCheckboxAnonymousDonate" class="wcPaymentInvoiceCheckboxLabel">
                                        <input id="wcPaymentInvoiceContainerCheckboxAnonymousDonate" class="wc-block-components-checkbox__input" type="checkbox" aria-invalid="false">
                                        <svg class="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 20">
                                            <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path>
                                        </svg>
                                        <span class="wc-block-components-checkbox__label">
                                            Doação anônima.
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>`;

                }else{
                    anonymousDonatePaymentHTML = `
                    <div class="wcPaymentInvoiceContainer">
                        <div class="wcPaymentInvoiceInner">
                            <div class="woocommerce-shipping-fields">
                                <label for="wcPaymentInvoiceContainerCheckboxAnonymousDonate" class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox wcPaymentInvoiceCheckboxLabelCartFlow">
                                    <input id="wcPaymentInvoiceContainerCheckboxAnonymousDonate"
                                        class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" type="checkbox"
                                        value="1"> 
                                        <span class="wcPaymentInvoiceCheckboxTitleCartFlow">
                                            Doação anônima.
                                        </span>
                                </label>
                            </div>
                        </div>
                    </div>`;
                }

                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = anonymousDonatePaymentHTML;
                const anonymousDonatePaymentElement = tempDiv.firstElementChild;

                if(checkoutForm){
                    const fourthElement = checkoutForm.children[4]
                    const methodsElement = document.querySelector('#shipping-fields');

                    if(methodsElement){
                        checkoutForm.insertBefore(anonymousDonatePaymentElement, methodsElement)
                    }else{
                        checkoutForm.insertBefore(anonymousDonatePaymentElement, fourthElement)
                    }
                }else{

                    const parentElement = document.querySelector('#customer_details');

                    if (parentElement && parentElement.parentNode) {
                        parentElement.parentNode.insertBefore(anonymousDonatePaymentElement, parentElement);
                    }
                }

                const checkboxAnonymousDonate = $('#wcPaymentInvoiceContainerCheckboxAnonymousDonate');
                const wcShippingFields  = $('#shipping-fields');
                const wcBillingFields  = $('#billing-fields');
                const wcBillingFieldsShortcode  = $('#customer_details');
                
                // Função para alternar a visibilidade com base no estado do checkbox
                function toggleFields() {
                    if (checkboxAnonymousDonate.is(':checked')) {
                        wcShippingFields.hide();
                        wcBillingFields.hide();
                        wcBillingFieldsShortcode.hide();
                    } else {
                        wcShippingFields.show();
                        wcBillingFields.show();
                        wcBillingFieldsShortcode.show();
                    }
                }

                // Verifica no carregamento inicial
                toggleFields();

                // Escuta mudanças no checkbox
                checkboxAnonymousDonate.on('change', toggleFields);
            }
        }, 500);
    });
})(jQuery);