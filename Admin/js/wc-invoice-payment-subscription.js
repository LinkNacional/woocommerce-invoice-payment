jQuery(function ($) {
    const toggleVariationFields = (variation) => {
        const mode = variation.find('.lkn_wcip_variation_payment_mode').val()
        const isSubscription = mode === 'subscription'

        variation.find('.lkn_wcip_variation_subscription_field')
            .toggleClass('lkn_wcip_variation_subscription_hidden', !isSubscription)
    }

    const toggleAllVariations = () => {
        $('.woocommerce_variation').each(function () {
            toggleVariationFields($(this))
        })
    }

    const subscriptionCheckbox = document.querySelector('#_lkn-wcip-subscription-product')
    const subscriptionTab = document.querySelector('.subscriptionTab_options.subscriptionTab_tab')

    const showTab = () => {
        if (!subscriptionCheckbox || !subscriptionTab) {
            return
        }

        subscriptionTab.style.display = subscriptionCheckbox.checked ? '' : 'none'
    }

    if (subscriptionCheckbox) {
        subscriptionCheckbox.addEventListener('change', showTab)
    }

    showTab()
    toggleAllVariations()

    $(document).on('change', '.lkn_wcip_variation_payment_mode', function () {
        toggleVariationFields($(this).closest('.woocommerce_variation'))
    })

    $('#variable_product_options').on('woocommerce_variations_added', toggleAllVariations)
    $('#woocommerce-product-data').on('woocommerce_variations_loaded woocommerce_variations_saved', toggleAllVariations)
})
