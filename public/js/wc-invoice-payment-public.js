document.addEventListener('DOMContentLoaded', function () {
  // Select default paymethod
  let defaultPaymethod = document.getElementById('lkn_wcip_default_paymethod')

  if (defaultPaymethod) {
    defaultPaymethod = defaultPaymethod.value

    const inputPaymethod = document.getElementById('payment_method_' + defaultPaymethod)

    const listPaymethods = document.getElementsByTagName('ul')[10].querySelectorAll('li')

    let otherPaymethods = []

    for (let i = 0; i < listPaymethods.length; i++) {
      const temp = (listPaymethods[i].getElementsByTagName('input'))[0].value
      otherPaymethods.push(temp)
    }

    otherPaymethods = otherPaymethods.filter(Paymethods => Paymethods !== (String(defaultPaymethod)))

    for (let i = 0; i < otherPaymethods.length; i++) {
      otherPaymethods[i] = document.getElementsByClassName('wc_payment_method payment_method_' + otherPaymethods[i])
    }

    for (let i = 0; i < otherPaymethods.length; i++) {
      otherPaymethods[i][0].remove()
    }

    if (inputPaymethod) {
      inputPaymethod.click()
    }
  }
})
