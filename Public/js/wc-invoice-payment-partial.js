(function ($) {
    $(window).on('load', function () {
        const intervalId = setInterval(() => {
            const shippingFieldset = $('#shipping-option');
            if (shippingFieldset.length) {
                clearInterval(intervalId);

                const totalElement = $('.wc-block-formatted-money-amount.wc-block-components-formatted-money-amount.wc-block-components-totals-footer-item-tax-value');
                const totalText = totalElement.text();
                const numericText = totalText.replace(/[^\d,.-]/g, '').replace(',', '.');
                const cartTotal = parseFloat(numericText);
                if(cartTotal > parseFloat(wcInvoicePaymentPartialVariables.minPartialAmount)){
                    const partialPaymentHTML = `
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
                                            Utilize essa opção para dividir o pagamento do pedido em diversos métodos. Digite o valor que deseja pagar agora, o restante pode ser pago depois com outros métodos de pagamento.
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="wcPaymentInvoiceFields">
                                <div class="wc-block-components-text-input wcPaymentInvoiceInputWrapper">
                                    <input id="wcPaymentInvoicePartialAmountFormatted" type="text" placeholder="R$ 0,00">
                                    <input id="wcPaymentInvoicePartialAmount" type="number" max="1" step="0.01" min="0.01" style="display: none;">
                                </div>
                                <button class="wc-block-components-button wp-element-button wc-block-components-checkout-place-order-button contained wcPaymentInvoiceButton" type="button">
                                    <span class="wc-block-components-button__text">
                                        <div aria-hidden="false" class="wc-block-components-checkout-place-order-button__text">
                                            Pagar parcial
                                        </div>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>`;
    
                    shippingFieldset.append(partialPaymentHTML);
    
                    const checkboxPartial = $('#wcPaymentInvoiceContainerCheckboxPartial');
                    const wcPaymentInvoiceFields = $('.wcPaymentInvoiceFields');
    
                    // Função para alternar a visibilidade com base no estado do checkbox
                    function toggleFields() {
                        if (checkboxPartial.is(':checked')) {
                            wcPaymentInvoiceFields.show();
                        } else {
                            wcPaymentInvoiceFields.hide();
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
                        const number = parseFloat(value.replace(/[^\d,\.]/g, '').replace(',', '.'));
                        if (isNaN(number) || number >= cartTotal) return '';
                        return new Intl.NumberFormat('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        }).format(number);
                    }
    
                    // Evento ao digitar no campo formatado
                    formattedInput.on('input', function () {
                        const raw = $(this).val();
                        const cleaned = raw.replace(/[^\d,\.]/g, '').replace(',', '.');
                        const numeric = parseFloat(cleaned);
    
                        // Atualiza o campo hidden com o valor numérico real
                        if (!isNaN(numeric) && numeric < cartTotal) {
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
                    
                        // Cria o payload
                        const data = {
                            partialAmount: partialValue,
                            orderId: wcSettings.checkoutData.order_id,
                            cart: wcInvoicePaymentPartialVariables.cart
                        };
                    
                        // Envia a requisição POST para a REST API
                        this.disabled = true;

                        fetch(`${wpApiSettings.root}invoice_payments/create_partial_payment`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(data)
                        })                        
                        .then(async response => {
                            const result = await response.json();
                        
                            if (!response.ok) {
                                // Lida com erro retornado pela API
                                this.disabled = false;
                                throw new Error(result.error || 'Erro desconhecido');
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
                            orderId: wcSettings.checkoutData.order_id,
                            cart: wcInvoicePaymentPartialVariables.cart
                        };
                    
                        // Envia a requisição POST para a REST API
                        fetch(`${wpApiSettings.root}invoice_payments/create_partial_payment`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(data)
                        })                        
                        .then(async response => {
                            const result = await response.json();
                        
                            if (!response.ok) {
                                // Lida com erro retornado pela API
                                throw new Error(result.error || 'Erro desconhecido');
                            }
                        
                            // Sucesso: redireciona
                            window.location.href = result.payment_url;
                        })
                        .catch(error => {
                            alert(error.message || 'Erro na requisição');
                        });
                    });
                }

            }
        }, 500);
    });
})(jQuery);