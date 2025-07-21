(function($) {
    const updatePaymentLabels = () => {
        const methods = wcInvoicePaymentFeeOrDiscountVariables.methods || {};

        Object.keys(methods).forEach(methodId => {
            const method = methods[methodId];
            const labelHtml = method.label;

            if (method.type === 'fee' && wcInvoicePaymentFeeOrDiscountVariables.showFeeOption !== 'on') {
                return;
            }
            if (method.type === 'discount' && wcInvoicePaymentFeeOrDiscountVariables.showDiscountOption !== 'on') {
                return;
            }

            const label = document.querySelector(`[for="radio-control-wc-payment-method-options-${methodId}"]`);
            if (!label) return;

            const spanLabel = label.querySelector('.wc-block-components-radio-control__label');
            if (!spanLabel || spanLabel.dataset.modified === 'true') return;

            // Captura o nome do método, seja dentro de um span ou texto puro
            let methodNameEl = spanLabel.querySelector('.wc-block-components-payment-method-label');
            let methodName;

            if (methodNameEl) {
                methodName = methodNameEl.outerHTML;
            } else {
                methodName = spanLabel.innerHTML.trim();
            }

            // Monta o novo conteúdo com flexbox
            const wrapper = document.createElement('span');
            wrapper.style.display = 'flex';
            wrapper.style.justifyContent = 'space-between';
            wrapper.style.width = '100%';

            const nameSpan = document.createElement('span');
            nameSpan.innerHTML = methodName;

            const textSpan = document.createElement('span');
            textSpan.innerHTML = labelHtml;

            wrapper.appendChild(nameSpan);
            wrapper.appendChild(textSpan);

            spanLabel.innerHTML = '';
            spanLabel.appendChild(wrapper);
            spanLabel.dataset.modified = 'true';
        });
    };

    const observer = new MutationObserver(() => {
        updatePaymentLabels();
    });

    observer.observe(document, {
        childList: true,
        subtree: true
    });

    document.addEventListener('DOMContentLoaded', updatePaymentLabels);
})(jQuery);
