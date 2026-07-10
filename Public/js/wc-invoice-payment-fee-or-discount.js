(function($) {
    const updatePaymentLabels = () => {
        const methods = wcInvoicePaymentFeeOrDiscountVariables.methods || {};

        Object.keys(methods).forEach(methodId => {
            const method = methods[methodId];
            const labelHtml = method.label;

            if (method.type === 'fee' && wcInvoicePaymentFeeOrDiscountVariables.showFeeOption !== 'yes') {
                return;
            }
            if (method.type === 'discount' && wcInvoicePaymentFeeOrDiscountVariables.showDiscountOption !== 'yes') {
                return;
            }

            // Tenta seletor de bloco (Gutenberg) primeiro, depois clássico (shortcode)
            const blockLabel = document.querySelector(`[for="radio-control-wc-payment-method-options-${methodId}"]`);
            const classicLabel = document.querySelector(`[for="payment_method_${methodId}"]`);

            if (blockLabel) {
                const spanLabel = blockLabel.querySelector('.wc-block-components-radio-control__label');
                if (!spanLabel || spanLabel.dataset.modified === 'true') return;

                let methodNameEl = spanLabel.querySelector('.wc-block-components-payment-method-label');
                let methodName = methodNameEl ? methodNameEl.outerHTML : spanLabel.innerHTML.trim();

                const wrapper = document.createElement('div');
                wrapper.style.display = 'flex';
                wrapper.style.justifyContent = 'space-between';
                wrapper.style.alignItems = 'center';
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
            } else if (classicLabel) {
                if (classicLabel.dataset.modified === 'true') return;

                const li = classicLabel.closest('.wc_payment_method');
                if (!li) return;

                // Cria wrapper flex para input + label
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.alignItems = 'center';
                row.style.justifyContent = 'space-between';
                row.style.gap = '8px';
                row.style.width = '100%';
                row.className = 'wc-invoice-payment-row';

                // Move input e label pra dentro do wrapper
                const input = li.querySelector('input[name="payment_method"]');
                const leftSide = document.createElement('span');
                leftSide.style.display = 'inline-flex';
                leftSide.style.alignItems = 'center';
                leftSide.style.gap = '4px';

                if (input) {
                    leftSide.appendChild(input);
                }
                leftSide.appendChild(classicLabel);
                row.appendChild(leftSide);

                // Badge na direita
                const badgeSpan = document.createElement('span');
                badgeSpan.innerHTML = labelHtml;
                row.appendChild(badgeSpan);

                classicLabel.style.display = 'inline';
                li.insertBefore(row, li.firstChild);
                classicLabel.dataset.modified = 'true';
            }
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
