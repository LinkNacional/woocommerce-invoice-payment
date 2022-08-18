document.addEventListener('DOMContentLoaded', function () {
    // Select default paymethod
    let defaultPaymethod = document.getElementById('lkn_wcip_default_paymethod');

    if (defaultPaymethod) {
        defaultPaymethod = defaultPaymethod.value;

        let inputPaymethod = document.getElementById('payment_method_' + defaultPaymethod);

        if (inputPaymethod) {
            console.log('default paymethod recognized: ' + inputPaymethod.value);
            inputPaymethod.click();
        }
    }
});
