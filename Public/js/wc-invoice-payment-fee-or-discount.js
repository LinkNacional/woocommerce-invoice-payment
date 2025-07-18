(function($) {
    const updatePaymentLabels = () => {
        const methods = wcInvoicePaymentFeeOrDiscountVariables.methods || {};

        Object.keys(methods).forEach(methodId => {
            const method = methods[methodId];
            const labelHtml = method.label;

            // Seleciona o label com base no atributo for
            const label = document.querySelector(`[for="radio-control-wc-payment-method-options-${methodId}"]`);
            if (!label) return;

            const spanLabel = label.querySelector('.wc-block-components-radio-control__label');
            if (!spanLabel) return;

            // Evita adicionar duplicado
            if (spanLabel.dataset.modified === 'true') return;

            // Cria estrutura do novo conteúdo com flexbox
            const wrapper = document.createElement('span');
            wrapper.style.display = 'flex';
            wrapper.style.justifyContent = 'space-between';
            wrapper.style.width = '100%';

            const methodName = spanLabel.querySelector('.wc-block-components-payment-method-label');
            if (!methodName) return;

            const textSpan = document.createElement('span');
            textSpan.innerHTML = labelHtml;

            wrapper.appendChild(methodName);
            wrapper.appendChild(textSpan);

            spanLabel.innerHTML = '';
            spanLabel.appendChild(wrapper);
            spanLabel.dataset.modified = 'true';
        });
    };

    // Aguarda a renderização dos métodos do checkout
    const observer = new MutationObserver(() => {
        updatePaymentLabels();
    });

    observer.observe(document, {
        childList: true,
        subtree: true
    });

    // Também força uma verificação inicial (caso já esteja renderizado)
    document.addEventListener('DOMContentLoaded', updatePaymentLabels);

})(jQuery);
