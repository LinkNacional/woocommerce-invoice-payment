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
                symbol = lknWcipPartialVariables.symbol
                
                
                if(checkoutForm){
                    totalElement = $('.wc-block-components-totals-footer-item-tax-value');
                    
                }else{
                    totalElement = $('.woocommerce-Price-amount.amount').last();
                }
                const totalText = totalElement.text();
                const numericText = totalText.replace(/[^\d,]/g, '').replace(',', '.');
                const cartTotal = parseFloat(numericText);
                if(cartTotal > parseFloat(lknWcipPartialVariables.minPartialAmount)){
                    if(checkoutForm){
                        partialPaymentHTML = `
                        <div class="wcPaymentInvoiceContainer">
                            <h1 class="wc-block-components-title wc-block-components-checkout-step__title">Pagamento Parcial</h1>
                            <div class="wcPaymentInvoiceInner">
                                <div class="wcPaymentInvoiceCheckboxWrapper">
                                    <div class="wc-block-components-checkbox wc-block-checkout__use-address-for-billing">
                                        <label for="wcPaymentInvoiceContainerCheckboxPartial" class="wcPaymentInvoiceCheckboxLabel">
                                            <input id="wcPaymentInvoiceContainerCheckboxPartial" class="wc-block-components-checkbox__input" type="checkbox" aria-invalid="false">
                                            <svg class="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 20">
                                                <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path>
                                            </svg>
                                            <span class="wc-block-components-checkbox__label">
                                                Utilize essa opção para dividir o pagamento do pedido em diversos métodos.
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div class="wcPaymentInvoiceFields">
                                    <div class="wc-block-components-text-input wcPaymentInvoiceInputWrapper">
                                        <input id="wcPaymentInvoicePartialAmountFormatted" type="text" placeholder="${symbol} 0,00">
                                        <input id="wcPaymentInvoicePartialAmount" type="number" max="1" step="0.01" min="0.01" style="display: none;">
                                    </div>
                                    <span class="wcPaymentInvoiceCheckboxTitleCartFlowSmall">
                                        ${lknWcipPartialVariables.partialPaymentDescription || 'Enter the amount you want to pay now, the rest can be paid later with other payment methods.'}
                                    </span>
                                    <button class="wc-block-components-button wp-element-button wc-block-components-checkout-place-order-button contained wcPaymentInvoiceButton" type="button">
                                        <span class="wc-block-components-button__text">
                                            <div aria-hidden="false" class="wc-block-components-checkout-place-order-button__text">
                                                ${lknWcipPartialVariables.payPartialText || 'Pay Partial'}
                                            </div>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>`;

                    }else{
                        partialPaymentHTML = `
                        <div class="wcPaymentInvoiceContainer">
                            <h1 class="wcf-shipping-methods-title wcPaymentInvoiceTitleCartFlow">${lknWcipPartialVariables.partialPaymentTitle || 'Partial Payment'}</h1>
                            <div class="wcPaymentInvoiceInner">
                                <div class="woocommerce-shipping-fields">
                                <label for="wcPaymentInvoiceContainerCheckboxPartial" class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox wcPaymentInvoiceCheckboxLabelCartFlow">
                                    <input id="wcPaymentInvoiceContainerCheckboxPartial"
                                        class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" type="checkbox"
                                        value="1"> 
                                            <span class="wcPaymentInvoiceCheckboxTitleCartFlow">
                                                Utilize essa opção para dividir o pagamento do pedido em diversos métodos.
                                            </span>
                                </label>
                                </div>
                                <div class="wcPaymentInvoiceFields">
                                    <div class="wc-block-components-text-input wcPaymentInvoiceInputWrapper">
                                        <input type="text" class="input-text " id="wcPaymentInvoicePartialAmountFormatted" placeholder="${symbol} 0,00" aria-required="true">
                                        <input id="wcPaymentInvoicePartialAmount" type="number" max="1" step="0.01" min="0.01" style="display: none;">
                                    </div>
                                                                        <div class="woocommerce-info partial-payment-description">
                                        ${lknWcipPartialVariables.partialPaymentDescription || 'Enter the amount you want to pay now, the rest can be paid later with other payment methods.'}
                                    </div>
                                    <button class="wc-block-components-button wp-element-button wc-block-components-checkout-place-order-button contained wcPaymentInvoiceButton" type="button">
                                        <span class="wc-block-components-button__text">
                                            <div aria-hidden="false" class="wc-block-components-checkout-place-order-button__text">
                                                ${lknWcipPartialVariables.payPartialText || 'Pay Partial'}
                                            </div>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>`;
                    }
    
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = partialPaymentHTML;
                    const partialPaymentElement = tempDiv.firstElementChild;

                    if(checkoutForm){
                        const fifthElement = checkoutForm.children[6]
                        const methodsElement = document.querySelector('#payment-method');
                        if(methodsElement){
                            checkoutForm.insertBefore(partialPaymentElement, methodsElement)
                        }else{
                            checkoutForm.insertBefore(partialPaymentElement, fifthElement)
                        }
                    }else{

                        const parentElement = cartFlowDiv.parentElement
                        const textElement = parentElement.querySelector('.wcf-payment-option-heading')
                        if(textElement){
                            divPartialPaymentElement = partialPaymentElement.querySelector('.woocommerce-shipping-fields');
                            divPartialPaymentElement.style.paddingTop = '13px';
                            divPartialPaymentElement.style.display = 'flex';
                            parentElement.insertBefore(partialPaymentElement, textElement)
                        }else{
                            parentElement.insertBefore(partialPaymentElement, cartFlowDiv)
                        }
                    }
    
                    const checkboxPartial = $('#wcPaymentInvoiceContainerCheckboxPartial');
                    const wcPaymentInvoiceFields = $('.wcPaymentInvoiceFields');
                    const wcPaymentInvoiceButton = $('.wcPaymentInvoiceButton');
                    const wcPaymentInvoiceInner = $('.wcPaymentInvoiceInner');
                    const wcPaymentMethods = $('#payment-method');
                    const wcPaymentNotes = $('#order-notes');
                    const wcSubmitButton = $('.wc-block-components-button.wp-element-button.wc-block-components-checkout-place-order-button.contained').last();
                    
                    // Função para alternar a visibilidade com base no estado do checkbox
                    function toggleFields() {
                        if (checkboxPartial.is(':checked')) {
                            wcPaymentInvoiceFields.show();
                            wcPaymentInvoiceButton.show();
                            wcPaymentMethods.hide();
                            wcPaymentNotes.hide();
                            if(!cartFlowDiv){
                                wcSubmitButton.hide();
                            }else{
                                parentElement = cartFlowDiv.parentElement
                                textElement = parentElement.querySelector('.wcf-payment-option-heading')
                                if(textElement){
                                    textElement.style.display = 'none';
                                }
                                cartFlowDiv.style.display = 'none';
                            }
                            wcPaymentInvoiceInner.addClass('active');
                        } else {
                            wcPaymentInvoiceFields.hide();
                            wcPaymentInvoiceButton.hide();
                            wcPaymentMethods.show();
                            wcPaymentNotes.show();
                            if(!cartFlowDiv){
                                wcSubmitButton.show();
                            }else{
                                parentElement = cartFlowDiv.parentElement
                                textElement = parentElement.querySelector('.wcf-payment-option-heading')
                                if(textElement){
                                    textElement.style.display = '';
                                }
                                cartFlowDiv.style.display = '';
                            }
                            wcPaymentInvoiceInner.removeClass('active');
                        }
                    }
    
                    // Verifica no carregamento inicial
                    toggleFields();
    
                    // Escuta mudanças no checkbox
                    checkboxPartial.on('change', toggleFields);
    
                    const formattedInput = $('#wcPaymentInvoicePartialAmountFormatted');
                    const hiddenInput = $('#wcPaymentInvoicePartialAmount');
    
                    // Formata o valor como moeda BRL
                    function formatToCurrency(value) {
                        const number = parseFloat(value.replace(/[^\d,]/g, '').replace(',', '.'));
                        currency = 'BRL';
                        if(typeof(wc) != 'undefined'){
                            currency = wc?.wcSettings?.CURRENCY?.code ?? 'BRL'
                        };
                        if (isNaN(number)) return '';
                        return new Intl.NumberFormat('pt-BR', {
                            style: 'currency',
                            currency: currency
                        }).format(number);
                    }
    
                    // Evento ao digitar no campo formatado
                    formattedInput.on('input', function () {
                        const raw = $(this).val();
                        const cleaned = raw.replace(/[^\d,]/g, '').replace(',', '.');
                        const numeric = parseFloat(cleaned);
    
                        // Atualiza o campo hidden com o valor numérico real
                        if (!isNaN(numeric)) {
                            hiddenInput.val(numeric.toFixed(2));
                        } else {
                            hiddenInput.val('');
                        }
    
                        formattedInput.val(raw);
                    });
    
                    // Ao focar, remove a formatação para facilitar edição
                    formattedInput.on('focus', function () {
                        const val = hiddenInput.val();
                        if (val) {
                            $(this).val(val.replace('.', ','));
                        }
                    });
    
                    // Ao perder foco, reformatar o valor
                    formattedInput.on('blur', function () {
                        const val = $(this).val();
                        const formatted = formatToCurrency(val);
                        formattedInput.val(formatted);
                    });

                    $('.wcPaymentInvoiceButton').on('click', function (e) {
                        e.preventDefault();
                    
                        const partialValue = parseFloat($('#wcPaymentInvoicePartialAmount').val());
                        // Verifica se o valor está válido
                        if (partialValue == 0 || isNaN(partialValue)) {
                            alert('Digite um valor válido para pagamento parcial.');
                            return;
                        }

                        if ( partialValue >= cartTotal ) {
                            alert('Valor solicitado para pagamento parcial não pode ser maior ou igual ao total do pedido.');
                            return;
                        }
                    
                        // Cria o payload
                        const data = {
                            partialAmount: partialValue,
                            orderId: orderId,
                            cart: lknWcipPartialVariables.cart,
                            userId: lknWcipPartialVariables.userId,
                        };
                    
                        // Envia a requisição POST para a REST API
                        this.disabled = true;

                        fetch(`${wpApiSettings.root}invoice_payments/create_partial_payment`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': lknWcipPartialVariables.nonce || wpApiSettings.nonce,
                            },
                            body: JSON.stringify(data)
                        })                        
                        .then(async response => {
                            const result = await response.json();
                        
                            if (!response.ok) {
                                // Lida com erro retornado pela API
                                this.disabled = false;
                                throw new Error(result.message);
                            }
                        
                            // Sucesso: redireciona
                            window.location.href = result.payment_url;
                        })
                        .catch(error => {
                            alert(error.message || 'Erro na requisição');
                            this.disabled = false;
                        });
                    });

                    $('.wcPaymentInvoiceTotalButton').on('click', function (e) {
                        e.preventDefault();
                    
                        const partialValue = parseFloat($('#wcPaymentInvoicePartialAmount').val());
                    
                        // Verifica se o valor está válido
                        if (partialValue == 0 || isNaN(partialValue)) {
                            alert('Digite um valor válido para pagamento parcial.');
                            return;
                        }
                    
                        // Cria o payload
                        const data = {
                            partialAmount: partialValue,
                            orderId: orderId,
                            cart: lknWcipPartialVariables.cart,
                            userId: lknWcipPartialVariables.userId,
                        };
                    
                        // Envia a requisição POST para a REST API
                        fetch(`${wpApiSettings.root}invoice_payments/create_partial_payment`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': lknWcipPartialVariables.nonce || wpApiSettings.nonce,
                            },
                            body: JSON.stringify(data)
                        })                        
                        .then(async response => {
                            const result = await response.json();
                        
                            if (!response.ok) {
                                // Lida com erro retornado pela API
                                throw new Error(result.message);
                            }
                        
                            // Sucesso: redireciona
                            window.location.href = result.payment_url;
                        })
                        .catch(error => {
                            alert(error.message || 'Erro na requisição');
                        });
                    });
                    clearInterval(intervalId);
                }

            }
        }, 500);
    });
})(jQuery);