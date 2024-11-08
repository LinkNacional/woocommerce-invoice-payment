showTab()
document.querySelector('#_lkn-wcip-subscription-product').onchange = () => { showTab() }

function showTab() {
    const subscriptionCheckbox = document.querySelector('#_lkn-wcip-subscription-product')
    const subscriptionTab = document.querySelector('.subscriptionTab_options.subscriptionTab_tab')

    if (subscriptionCheckbox.checked) {
        subscriptionTab.style.display = ''
    } else {
        subscriptionTab.style.display = 'none'
    }
}
