(() => {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement, Fragment } = window.wp.element;
    const { __ } = window.wp.i18n;
    const { decodeEntities } = window.wp.htmlEntities || {};

    const settings = window.wc.wcSettings.getSetting('lkn_wcip_partial_gateway_data', {});

    const label = decodeEntities(settings.title) || __('Pagamento Parcial', 'wc-invoice-payment');

    const Content = () => {
        return createElement('div', {
            className: 'wc-block-components-payment-method-content',
        }, []);
    };

    const Label = (props) => {
        const { PaymentMethodLabel } = props.components || {};

        if (PaymentMethodLabel) {
            return createElement(PaymentMethodLabel, { text: label });
        }

        return createElement('span', {
            className: 'wc-block-components-payment-method-label'
        }, label);
    };

    const partialGatewayMethod = {
        name: 'lkn_wcip_partial_gateway',
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Content),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports || ['products'],
        },
    };

    if (registerPaymentMethod) {
        registerPaymentMethod(partialGatewayMethod);
    }
})();
