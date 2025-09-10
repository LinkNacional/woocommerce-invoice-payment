(() => {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement, Fragment } = window.wp.element;
    const { __ } = window.wp.i18n;
    const { decodeEntities } = window.wp.htmlEntities || {};

    // Get settings from the server
    const settings = window.wc.wcSettings.getSetting('lkn_invoice_quote_gateway_data', {});
    
    // Payment method configuration
    const label = decodeEntities(settings.title) || __('Pagamento por Orçamento', 'wc-invoice-payment');
    const description = decodeEntities(settings.description) || __('Solicite um orçamento personalizado para seus produtos.', 'wc-invoice-payment');

    /**
     * Content component for the payment method
     */
    const Content = () => {
        return createElement('div', {
            className: 'wc-block-components-payment-method-content',
        }, []);
    };

    /**
     * Label component for the payment method
     */
    const Label = (props) => {
        const { PaymentMethodLabel } = props.components || {};
        
        if (PaymentMethodLabel) {
            return createElement(PaymentMethodLabel, { text: label });
        }
        
        // Fallback if PaymentMethodLabel is not available
        return createElement('span', {
            className: 'wc-block-components-payment-method-label'
        }, label);
    };

    /**
     * Payment method object
     */
    const invoiceQuotePaymentMethod = {
        name: 'lkn_invoice_quote_gateway',
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Content),
        canMakePayment: () => {
            // Check if quote mode is enabled
            const quoteMode = window.wcInvoiceHidePrice?.quoteMode || 'no';
            return quoteMode === 'yes';
        },
        ariaLabel: label,
        supports: {
            features: settings.supports || ['products'],
        },
    };

    // Register the payment method
    if (registerPaymentMethod) {
        registerPaymentMethod(invoiceQuotePaymentMethod);
    } else {
        console.error('WooCommerce Blocks registerPaymentMethod not found');
    }
})();
