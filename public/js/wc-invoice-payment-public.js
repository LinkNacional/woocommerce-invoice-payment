document.addEventListener('DOMContentLoaded', function () {
  // Select default paymethod
  let defaultPaymethod = document.getElementById('lkn_wcip_default_paymethod')

  if (defaultPaymethod) {
    defaultPaymethod = defaultPaymethod.value

    const inputPaymethod = document.getElementById('payment_method_' + defaultPaymethod)

    let otherPaymethod

    if (defaultPaymethod === 'bacs') {
      otherPaymethod = ['cheque', 'cod']
    } else if (defaultPaymethod === 'cheque') {
      otherPaymethod = ['bacs', 'cod']
    } else if (defaultPaymethod === 'cod') {
      otherPaymethod = ['cheque', 'bacs']
    }

    for (let i = 0; i < otherPaymethod.length; i++) {
      otherPaymethod[i] = document.getElementsByClassName('wc_payment_method payment_method_' + otherPaymethod[i])
    }

    for (let i = 0; i < otherPaymethod.length; i++) {
      otherPaymethod[i][0].remove()
    }

    if (inputPaymethod) {
      inputPaymethod.click()
    }
  }
})
