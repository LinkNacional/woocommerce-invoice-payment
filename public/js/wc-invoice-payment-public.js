document.addEventListener('DOMContentLoaded', function () {
  // Select default paymethod
  let defaultPaymethod = document.getElementById('lkn_wcip_default_paymethod')

  if (defaultPaymethod) {
    defaultPaymethod = defaultPaymethod.value

    const inputPaymethod = document.getElementById('payment_method_' + defaultPaymethod)

    if (inputPaymethod) {
      inputPaymethod.click()
    }
  }
})
