document.addEventListener('DOMContentLoaded', function () {
  let defaultPaymethod = document.getElementById('lkn_wcip_default_paymethod');

  if (defaultPaymethod) {
    defaultPaymethod = defaultPaymethod.value;

    const listPaymethods = document.getElementsByClassName('wc_payment_method');

    if (wcInvoicePaymentMethods.isPartialOrder === 'yes') {
      const enabledMethods = wcInvoicePaymentMethods.enabledMethods;

      // Oculta todos os métodos de pagamento que não estão em enabledMethods
      for (let i = listPaymethods.length - 1; i >= 0; i--) {
        const methodInput = listPaymethods[i].querySelector('input');
        const methodId = methodInput ? methodInput.value : null;

        if (!enabledMethods[methodId] || enabledMethods[methodId] !== 'yes') {
          listPaymethods[i].remove();
        }
      }

      return; // Evita execução do restante do script se for pagamento parcial
    }

    // Caso padrão (não é pagamento parcial)
    if (defaultPaymethod === 'multiplePayment') return;

    const inputPaymethod = document.getElementById('payment_method_' + defaultPaymethod);

    let otherPaymethods = [];

    for (let i = 0; i < listPaymethods.length; i++) {
      const methodInput = listPaymethods[i].querySelector('input');
      if (methodInput) {
        otherPaymethods.push(methodInput.value);
      }
    }

    // Remove métodos diferentes do default
    for (let i = 0; i < otherPaymethods.length; i++) {
      if (otherPaymethods[i] !== defaultPaymethod) {
        const el = document.querySelector('.wc_payment_method.payment_method_' + otherPaymethods[i]);
        if (el) el.remove();
      }
    }

    // Ativa o método padrão
    if (inputPaymethod) {
      inputPaymethod.click();
    }
  }
});
