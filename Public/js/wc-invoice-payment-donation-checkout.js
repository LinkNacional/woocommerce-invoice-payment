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

                // Intercepta as requisições AJAX/REST API do checkout
                interceptCheckoutRequests();
                
                // Adiciona campo hidden para checkout tradicional
                addHiddenFieldToForm();
            }
        }, 500);
    });

    // Função para interceptar requisições do checkout
    function interceptCheckoutRequests() {
        // Intercepta XMLHttpRequest (AJAX tradicional)
        const originalXHROpen = XMLHttpRequest.prototype.open;
        const originalXHRSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function(method, url, ...args) {
            this._method = method;
            this._url = url;
            return originalXHROpen.apply(this, [method, url, ...args]);
        };

        XMLHttpRequest.prototype.send = function(data) {
            if (this._method === 'POST' && this._url && this._url.includes('/wp-json/wc/store/v1/checkout')) {
                data = modifyCheckoutData(data);
            }
            return originalXHRSend.apply(this, [data]);
        };

        // Intercepta fetch API
        const originalFetch = window.fetch;
        window.fetch = function(url, options = {}) {
            if (options.method === 'POST' && url && url.includes('/wp-json/wc/store/v1/checkout')) {
                options.body = modifyCheckoutData(options.body);
            }
            return originalFetch.apply(this, [url, options]);
        };

        // Intercepta jQuery AJAX
        if (typeof jQuery !== 'undefined') {
            const originalAjax = jQuery.ajax;
            jQuery.ajax = function(options) {
                if (options.type === 'POST' && options.url && options.url.includes('/wp-json/wc/store/v1/checkout')) {
                    options.data = modifyCheckoutData(options.data);
                }
                return originalAjax.apply(this, [options]);
            };
        }
    }

    // Função para modificar os dados do checkout
    function modifyCheckoutData(data) {
        try {
            const checkboxAnonymousDonate = document.getElementById('wcPaymentInvoiceContainerCheckboxAnonymousDonate');
            const isAnonymousDonation = checkboxAnonymousDonate && checkboxAnonymousDonate.checked;

            let parsedData;
            let isFormData = false;
            let isString = false;

            // Verifica o tipo de dados
            if (data instanceof FormData) {
                isFormData = true;
                parsedData = {};
                for (let [key, value] of data.entries()) {
                    parsedData[key] = value;
                }
            } else if (typeof data === 'string') {
                isString = true;
                try {
                    parsedData = JSON.parse(data);
                } catch (e) {
                    // Se não for JSON, tenta como URL encoded
                    parsedData = new URLSearchParams(data);
                    const obj = {};
                    for (let [key, value] of parsedData.entries()) {
                        obj[key] = value;
                    }
                    parsedData = obj;
                }
            } else if (typeof data === 'object') {
                parsedData = { ...data };
            } else {
                return data;
            }

            // Adiciona a informação da doação anônima
            parsedData.anonymous_donation = isAnonymousDonation ? '1' : '0';

            // Converte de volta para o formato original
            if (isFormData) {
                const newFormData = new FormData();
                for (let key in parsedData) {
                    newFormData.append(key, parsedData[key]);
                }
                return newFormData;
            } else if (isString) {
                return JSON.stringify(parsedData);
            } else {
                return parsedData;
            }
        } catch (error) {
            console.error('Erro ao modificar dados do checkout:', error);
            return data;
        }
    }

    // Função para adicionar campo hidden ao formulário de checkout tradicional
    function addHiddenFieldToForm() {
        const checkoutForm = document.querySelector('form.checkout, form.woocommerce-checkout');
        
        if (checkoutForm && !document.getElementById('anonymous_donation_hidden')) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.id = 'anonymous_donation_hidden';
            hiddenField.name = 'anonymous_donation';
            hiddenField.value = '0';
            checkoutForm.appendChild(hiddenField);
            
            // Atualiza o valor do campo hidden quando a checkbox muda
            const checkboxAnonymousDonate = document.getElementById('wcPaymentInvoiceContainerCheckboxAnonymousDonate');
            if (checkboxAnonymousDonate) {
                checkboxAnonymousDonate.addEventListener('change', function() {
                    hiddenField.value = this.checked ? '1' : '0';
                });
            }
        }
    }

    // Função adicional para interceptar eventos de checkout de blocos
    function interceptBlocksCheckout() {
        // Para checkout em blocos, monitora mudanças nos dados
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    const checkoutForm = document.querySelector('.wc-block-components-form.wc-block-checkout__form');
                    if (checkoutForm && !checkoutForm.hasAttribute('data-anonymous-listener')) {
                        checkoutForm.setAttribute('data-anonymous-listener', 'true');
                        
                        // Adiciona listener para quando o formulário é submetido
                        checkoutForm.addEventListener('submit', function(e) {
                            const checkbox = document.getElementById('wcPaymentInvoiceContainerCheckboxAnonymousDonate');
                            if (checkbox && checkbox.checked) {
                                // Adiciona dados aos componentes do WooCommerce Blocks
                                if (window.wc && window.wc.wcBlocksData) {
                                    window.wc.wcBlocksData.anonymous_donation = '1';
                                }
                            }
                        });
                    }
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Inicializa interceptação para checkout em blocos
    interceptBlocksCheckout();
})(jQuery);