(function ($) {
    if (!$('form#order_review').length) return;

    function refreshTable() {
        $.ajax({
            url: lknWcipPartialOrderRefresh.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lkn_wcip_refresh_order_review',
                orderId: lknWcipPartialOrderRefresh.partialOrderId
            },
            success: function (response) {
                if (response.success && response.data.html) {
                    $('form#order_review .shop_table').replaceWith(response.data.html);
                }
            }
        });
    }

    $(document.body).on('update_checkout', refreshTable);
})(jQuery);
