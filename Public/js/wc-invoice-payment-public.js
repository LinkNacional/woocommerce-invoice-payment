document.addEventListener('DOMContentLoaded', function () {
  let defaultPaymethod = document.getElementById('lkn_wcip_default_paymethod');

  if (defaultPaymethod) {
    defaultPaymethod = defaultPaymethod.value;

    if (defaultPaymethod == 'multiplePayment' || !defaultPaymethod) return;

    const inputPaymethod = document.getElementById('payment_method_' + defaultPaymethod);

    let otherPaymethods = [];
    const listPaymethods = document.getElementsByClassName('wc_payment_method');

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
