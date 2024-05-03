document.addEventListener('DOMContentLoaded', function () {
  let defaultPaymethod = document.getElementById('lkn_wcip_default_paymethod')

  if (defaultPaymethod) {
    defaultPaymethod = defaultPaymethod.value

    if(defaultPaymethod == "multiplePayment") return ''

    const inputPaymethod = document.getElementById('payment_method_' + defaultPaymethod)
    const listPaymethods = document.getElementsByClassName('wc_payment_method')

    let uniquePaymethod

    let otherPaymethods = []

    for (let i = 0; i < listPaymethods.length; i++) {
      const temp = (listPaymethods[i].getElementsByTagName('input'))[0].value
      otherPaymethods.push(temp)
    }

    let difPaymethod

    for (let i = 0; i < otherPaymethods.length; i++) {
      if (defaultPaymethod === (otherPaymethods[i])) {
        otherPaymethods = otherPaymethods.filter(Paymethods => Paymethods !== (String(defaultPaymethod)))
        difPaymethod = false
        break
      } else if (defaultPaymethod !== (otherPaymethods[i])) {
        difPaymethod = true
      }
    }

    if (difPaymethod) {
      uniquePaymethod = (listPaymethods[0].getElementsByTagName('input'))[0].value
      otherPaymethods = otherPaymethods.filter(Paymethods => Paymethods !== (String(uniquePaymethod)))
    }

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
