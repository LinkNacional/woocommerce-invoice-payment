(function ($) {
    $(window).on('load', function () {
        const formattedInput = $('#wcPaymentInvoicePartialAmountFormatted');
        const hiddenInput = $('#wcPaymentInvoicePartialAmount');
        // Formata o valor como moeda BRL
        function formatToCurrency(value) {
            const number = parseFloat(value.replace(/[^\d,\.]/g, '').replace(',', '.'));
            if (isNaN(number)) return '';
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

            if (!confirm('Tem certeza que deseja pagar ' + formattedInput.val() + '?')) {
                return;
            }

            // Cria o payload
            const data = {
                partialAmount: partialValue,
                orderId: wcInvoicePaymentPartialTableVariables.orderId,
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

        $('.wcPaymentInvoiceTotalButton').on('click', function (e) {
            e.preventDefault();
        
            // Cria o payload
            const data = {
                partialAmount: wcInvoicePaymentPartialTableVariables.totalToPay,
                orderId: wcInvoicePaymentPartialTableVariables.orderId,
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

        $(document).on('click', '.wcPaymentInvoiceTableInputs .cancel', function (e) {
            if (!confirm('Tem certeza que deseja cancelar este pagamento parcial?')) {
                e.preventDefault();
            }
        });
    });
})(jQuery);