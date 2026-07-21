/**
 * Split de Pagamento — Checkout Clássico (shortcode)
 *
 * Mesmo step HTML do Blocks, eventos delegados via jQuery,
 * mas adaptado para o fluxo clássico (sem Store API / wp.data).
 */
(function ($) {
    'use strict';

    if (!$) return;

    var CONFIG = window.lknWcipSplitBlocksConfig || {};
    var AJAX_URL = CONFIG.ajaxUrl || '/wp-admin/admin-ajax.php';
    var NONCE = CONFIG.nonce || '';
    var MIN_AMOUNT = parseFloat(CONFIG.minPartialAmount) || 0;

    function formatCurrency(val) {
        var num = parseFloat(val);
        if (isNaN(num)) return '';
        return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function parseCurrency(str) {
        if (!str) return 0;
        return parseFloat(String(str).replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    }

    function ajaxPost(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', NONCE);
        if (data) {
            Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        }
        return fetch(AJAX_URL, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }

    var _cartData = null; // cache do último getPartialSplitState

    function refreshCartData() {
        return ajaxPost('lkn_wcip_get_partial_split_state').then(function (res) {
            if (res && res.success && res.data) {
                _cartData = res.data;
            }
        });
    }

    function getCartTotal() {
        // Le do DOM se _cartData ainda não carregou
        if (!_cartData) {
            return parseCurrency(getCard().find('.lkn-wcip-base-max-val').text());
        }
        return parseFloat(_cartData.cart_total) || parseCurrency(getCard().find('.lkn-wcip-base-max-val').text());
    }

    function getBaseMax() {
        if (_cartData) return parseFloat(_cartData.base_max) || 0;
        return 0;
    }

    function updateBaseMaxLabel() {
        refreshCartData().then(function () {
            var base = getBaseMax();
            if (base > 0) {
                getCard().find('.lkn-wcip-base-max-val').text(formatCurrency(base));
            }
        });
    }

    var calculated = false;
    var splitData = null;

    // —— Getters ——
    function getCard() { return $('.lkn-wcip-split-blocks-container'); }
    function getCheckbox() { return $('#lkn-wcip-split-checkbox'); }
    function getFields() { return $('.lkn-wcip-split-fields'); }
    function getInput() { return $('#lkn-wcip-split-amount'); }
    function getBtn() { return $('#lkn-wcip-split-btn'); }
    function getResult() { return $('#lkn-wcip-split-result'); }
    function getStep() { return $('.lkn-wcip-partial-split-step'); }

    function clearState() {
        calculated = false;
        splitData = null;
        _cartData = null;
        getCheckbox().prop('checked', false);
        getInput().val('').prop('disabled', false);
        getBtn().text('Split pagamento').css({ opacity: '0.5', pointerEvents: 'none' });
        getResult().hide().empty();
        getFields().hide();
        getCard().find('.lkn-wcip-base-max-msg').hide();
        getCard().find('.lkn-wcip-base-min-msg').hide();
    }

    function handleReset() {
        getBtn().prop('disabled', true);
        ajaxPost('lkn_wcip_clear_partial_split').then(function (res) {
            if (res && res.success) {
                clearState();
                if (IS_PAY_REMAINING) {
                    getFields().show();
                    getCard().find('.lkn-wcip-base-max-msg').show();
                    getCard().find('.lkn-wcip-base-min-msg').show();
                    getInput().val('').prop('disabled', false);
                    getBtn().text('Split pagamento').css({ opacity: '0.5', pointerEvents: 'none' });
                }
                invalidateCart();
            }
        }).finally(function () { getBtn().prop('disabled', false); });
    }

    function handleCalculate() {
        var val = parseCurrency(getInput().val());
        if (!val || val <= 0) { alert('Digite um valor válido para o pagamento parcial.'); return; }
        // Classic: usa o valor máximo do HTML (já renderizado pelo PHP)
        // em vez de getCartTotal() que pode não ter carregado ainda.
        var maxVal = parseCurrency(getCard().find('.lkn-wcip-base-max-val').text());
        if (maxVal > 0 && val >= maxVal) { alert('O valor parcial deve ser menor que o total (R$ ' + maxVal.toFixed(2).replace('.', ',') + ').'); return; }
        if (MIN_AMOUNT > 0 && val < MIN_AMOUNT) { alert('Valor abaixo do mínimo permitido.'); return; }
        if (MIN_AMOUNT > 0) {
            var rem = getBaseMax() - val;
            if (rem > 0 && rem < MIN_AMOUNT) {
                alert('O valor restante (R$ ' + rem.toFixed(2).replace('.', ',') + ') não pode ser menor que o mínimo (R$ ' + MIN_AMOUNT.toFixed(2).replace('.', ',') + '). Ajuste o valor informado.');
                return;
            }
        }

        getBtn().prop('disabled', true);

        ajaxPost('lkn_wcip_set_partial_split', { partialAmount: val }).then(function (res) {
            if (!res || !res.success) {
                alert(res && res.data && res.data.message ? res.data.message : 'Erro.');
                return;
            }

            calculated = true;
            splitData = res.data;
            _cartData = res.data;

            getInput().val(formatCurrency(val)).prop('disabled', true);
            getBtn().text('Cancelar split').css({ opacity: '', pointerEvents: '' });
            renderResult();
            invalidateCart();
        }).finally(function () { getBtn().prop('disabled', false); });
    }

    function handleInitiatePartial() {
        getBtn().prop('disabled', true).text('Finalizando...').css({ opacity: '0.5', cursor: 'not-allowed', background: '#999' });

        ajaxPost('lkn_wcip_initiate_partial').then(function (res) {
            if (res && res.success && res.data && res.data.redirect_url) {
                window.location.href = res.data.redirect_url;
            } else {
                alert(res && res.data && res.data.message ? res.data.message : 'Erro ao iniciar pagamento parcial.');
                getBtn().prop('disabled', false).text('Iniciar pagamento parcial').css({ opacity: '', cursor: '', background: '' });
            }
        }).catch(function () {
            alert('Erro ao processar. Tente novamente.');
            getBtn().prop('disabled', false).text('Iniciar pagamento parcial').css({ opacity: '', cursor: '', background: '' });
        });
    }

    function renderResult() {
        var d = splitData;
        if (!d) return;

        var partialAmount = parseFloat(d.partial_amount) || 0;
        var baseMax = parseFloat(d.base_max) || 0;
        var gatewayFees = parseFloat(d.gateway_fees) || 0;
        var cartTotal = parseFloat(d.cart_total) || 0;
        var remaining = parseFloat(d.remaining) || (baseMax - partialAmount);
        if (remaining < 0) remaining = 0;
        var realPaidNow = Math.abs(gatewayFees) > 0.01 ? partialAmount + gatewayFees : partialAmount;
        var hasFees = Math.abs(gatewayFees) > 0.01;

        var html = '<div style="margin-top:12px;padding:14px;background:#fff;border:1px solid #e0e0e0;border-radius:4px">';

        html += '<div style="font-size:13px;color:#555;margin-bottom:4px"><span>Subtotal + Frete</span><span style="float:right;font-weight:500">' + formatCurrency(baseMax) + '</span></div>';
        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
        html += '<div style="font-size:13px;color:#555;margin-bottom:4px"><span>Valor informado</span><span style="float:right;font-weight:500">' + formatCurrency(partialAmount) + '</span></div>';
        html += '<div style="font-size:13px;color:#555;margin-bottom:6px"><span>Taxas/Descontos adicionais:</span><span style="float:right;font-weight:500;color:' + (hasFees ? '#00a32a' : '#999') + '">' + (gatewayFees > 0.01 ? '+' : '') + formatCurrency(gatewayFees) + '</span></div>';

        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
        html += '<div style="font-size:14px;font-weight:600;color:#333;margin-bottom:' + (hasFees ? '4px' : '12px') + '"><span>Você pagará agora:</span><span style="float:right">' + formatCurrency(realPaidNow) + '</span></div>';

        if (hasFees) {
            html += '<p style="font-size:12px;color:#999;margin:0 0 12px;line-height:1.4">O valor informado de ' + formatCurrency(partialAmount) + ' foi ajustado para ' + formatCurrency(realPaidNow) + ' devido a taxas ou descontos aplicados ao pedido.</p>';
        }

        html += '<div style="padding-top:10px;border-top:2px solid #e0e0e0"><p style="margin:0;font-size:14px;color:#d63638">Restante para depois: <strong>' + formatCurrency(remaining) + '</strong></p></div>';
        html += '</div>';

        getResult().html(html).show();
    }

    function invalidateCart() {
        // Classic checkout: trigger WooCommerce update_checkout
        $(document.body).trigger('update_checkout');
    }

    // ==========================================================
    // Event delegation
    // ==========================================================

    $(document).on('click', '.lkn-wcip-resume-btn', function () {
        var btn = $(this);
        btn.prop('disabled', true).text('Processando...');
        fetch(CONFIG.restUrl || btn.data('rest-url'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': CONFIG.restNonce || btn.data('nonce'),
            },
            body: JSON.stringify({
                orderId: parseInt(btn.data('order-id')),
                partialAmount: parseFloat(btn.data('amount')),
                userId: CONFIG.userId || 0
            })
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res && res.payment_url) window.location.href = res.payment_url;
        }).catch(function () {
            alert('Erro ao processar. Tente novamente.');
            btn.prop('disabled', false).text('Continuar');
        });
    });

    $(document).on('change', '#lkn-wcip-split-checkbox', function () {
        if (this.checked) {
            if (!IS_PAY_REMAINING) {
                getFields().show();
                getInput().hide();
                getCard().find('.lkn-wcip-base-max-msg').show();
                getCard().find('.lkn-wcip-base-min-msg').show();
                getBtn().css({ opacity: '1', pointerEvents: 'auto' }).show().parent().css({ justifyContent: 'center' });
                ajaxPost('lkn_wcip_toggle_partial_mode', { active: '1' }).then(invalidateCart);

                var retries = 0;
                var retryTimer = setInterval(function () {
                    if (retries >= 2 || !getCheckbox().is(':checked')) {
                        clearInterval(retryTimer);
                        return;
                    }
                    retries++;
                    ajaxPost('lkn_wcip_toggle_partial_mode', { active: '1' }).then(invalidateCart);
                }, 5000);
            } else {
                getFields().slideDown(200);
                getCard().find('.lkn-wcip-base-max-msg').slideDown(200);
                getCard().find('.lkn-wcip-base-min-msg').slideDown(200);
                var hasValue = parseCurrency(getInput().val()) > 0;
                getBtn().css({ opacity: hasValue ? '' : '0.5', pointerEvents: hasValue ? '' : 'none' });
            }
        } else {
            if (!IS_PAY_REMAINING) {
                getFields().hide();
                getInput().show();
                getCard().find('.lkn-wcip-base-max-msg').hide();
                getCard().find('.lkn-wcip-base-min-msg').hide();
                ajaxPost('lkn_wcip_toggle_partial_mode', { active: '0' }).then(invalidateCart);
            } else {
                getFields().slideUp(200);
                getCard().find('.lkn-wcip-base-max-msg').slideUp(200);
                getCard().find('.lkn-wcip-base-min-msg').slideUp(200);
                if (calculated) handleReset();
            }
        }
    });

    $(document).on('click', '#lkn-wcip-split-btn', function () {
        if (IS_PAY_REMAINING) {
            if (calculated) handleReset();
            else handleCalculate();
        } else {
            handleInitiatePartial();
        }
    });

    $(document).on('input', '#lkn-wcip-split-amount', function () {
        var raw = $(this).val();
        $(this).data('raw', raw.replace(/[^\d,]/g, '').replace(',', '.'));
        var btn = getBtn();
        if (btn.length) {
            var hasValue = parseCurrency(raw) > 0;
            btn.css({ opacity: hasValue ? '' : '0.5', pointerEvents: hasValue ? '' : 'none' });
        }
    });

    $(document).on('blur', '#lkn-wcip-split-amount', function () {
        var val = parseCurrency($(this).val());
        if (val > 0) $(this).val(formatCurrency(val));
    });

    // ==========================================================
    // Place Order intercept (classic: #place_order button)
    // ==========================================================
    (function () {
        var placeOrderBound = false;

        function handlePlaceOrderClick(e) {
            if (IS_PAY_REMAINING) {
                var $inp = getInput();
                var $err = $('.lkn-wcip-split-error');
                if ($inp.length && $inp.is(':visible')) {
                    var val = parseCurrency($inp.val());
                    if (!val || val <= 0) {
                        e.stopImmediatePropagation();
                        e.preventDefault();
                        $err.slideDown(200);
                        $inp[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                    if (!calculated) {
                        e.stopImmediatePropagation();
                        e.preventDefault();
                        $err.slideDown(200);
                        $inp[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                    $err.slideUp(200);
                }
                return;
            }
        }

        function bindPlaceOrder() {
            if (placeOrderBound) return;
            // Classic: #place_order button
            var btn = document.getElementById('place_order');
            if (!btn) return;
            placeOrderBound = true;
            btn.addEventListener('click', handlePlaceOrderClick, true);
        }

        setInterval(function () {
            var btn = document.getElementById('place_order');
            if (btn && !placeOrderBound) bindPlaceOrder();
            if (!btn) placeOrderBound = false;
        }, 500);

        new MutationObserver(function () {
            var btn = document.getElementById('place_order');
            if (btn && !placeOrderBound) bindPlaceOrder();
        }).observe(document.body, { childList: true, subtree: true });
    })();

    // ==========================================================
    // Checkout update — refresh dados após AJAX do WC
    // ==========================================================
    $(document.body).on('updated_checkout', function () {
        if (IS_PAY_REMAINING && calculated && splitData) {
            ajaxPost('lkn_wcip_get_partial_split_state').then(function (res) {
                if (res && res.success && res.data) {
                    splitData = res.data;
                    _cartData = res.data;
                    renderPayRemainingResult();
                }
            });
        }
        updateBaseMaxLabel();
    });

    // ==========================================================
    // Modo pay_remaining: inicial
    // ==========================================================
    var IS_PAY_REMAINING = !!CONFIG.isPayRemaining;

    if (IS_PAY_REMAINING) {
        $(function () {
            refreshCartData().then(function () {
                ajaxPost('lkn_wcip_get_partial_split_state').then(function (res) {
                    if (res && res.success && res.data && res.data.active) {
                        calculated = true;
                        splitData = res.data;
                        _cartData = res.data;
                        renderPayRemainingResult();
                    }
                });
            });
        });
    }

    function renderPayRemainingResult() {
        var d = splitData;
        if (!d) return;

        var partialAmount = parseFloat(d.partial_amount) || 0;
        var baseMax = parseFloat(d.base_max) || 0;
        var gatewayFees = parseFloat(d.gateway_fees) || 0;
        var cartTotal = parseFloat(d.cart_total) || 0;
        var remaining = parseFloat(d.remaining) || 0;
        var isSecondPartial = (CONFIG.parentConfirmed || 0) > 0;
        var hasFees = Math.abs(gatewayFees) > 0.01;
        var realPaidNow = hasFees ? partialAmount + gatewayFees : partialAmount;

        var html = '<div style="margin-top:12px;padding:14px;background:#fff;border:1px solid #e0e0e0;border-radius:4px">';

        html += '<div style="font-size:13px;color:#555;margin-bottom:4px"><span>Subtotal + Frete</span><span style="float:right;font-weight:500">' + formatCurrency(baseMax) + '</span></div>';
        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
        html += '<div style="font-size:13px;color:#555;margin-bottom:4px"><span>' + (isSecondPartial ? 'Valor restante' : 'Valor informado') + '</span><span style="float:right;font-weight:500">' + formatCurrency(partialAmount) + '</span></div>';
        html += '<div style="font-size:13px;color:#555;margin-bottom:6px"><span>Taxas/Descontos adicionais:</span><span style="float:right;font-weight:500;color:' + (hasFees ? '#00a32a' : '#999') + '">' + (gatewayFees > 0.01 ? '+' : '') + formatCurrency(gatewayFees) + '</span></div>';

        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
        html += '<div style="font-size:14px;font-weight:600;color:#333;margin-bottom:' + (hasFees ? '4px' : '12px') + '"><span>Você pagará agora:</span><span style="float:right">' + formatCurrency(realPaidNow) + '</span></div>';

        if (hasFees) {
            html += '<p style="font-size:12px;color:#999;margin:0 0 12px;line-height:1.4">O valor informado de ' + formatCurrency(partialAmount) + ' foi ajustado para ' + formatCurrency(realPaidNow) + ' devido a taxas ou descontos aplicados ao pedido.</p>';
        }

        if (isSecondPartial) {
            html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
            html += '<div style="padding-top:10px;border-top:2px solid #e0e0e0"><p style="margin:0;font-size:14px;color:#008a20">Pago anteriormente: <strong>' + formatCurrency(CONFIG.parentConfirmed || 0) + '</strong></p></div>';
        } else {
            html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
            html += '<div style="padding-top:10px;border-top:2px solid #e0e0e0"><p style="margin:0;font-size:14px;color:#d63638">Saldo a pagar depois: <strong>' + formatCurrency(remaining) + '</strong></p></div>';
        }

        html += '</div>';

        getResult().html(html).show();
    }

    // ==========================================================
    // Botão "Cancelar" na lista de retomada
    // ==========================================================
    $(document).on('click', '.lkn-wcip-cancel-pending-btn', function () {
        var btn = $(this);
        var orderId = btn.data('order-id');
        var restUrl = btn.data('rest-url');
        var nonce = btn.data('nonce');

        if (!confirm('Tem certeza que deseja cancelar o pagamento parcial pendente? Você poderá iniciar um novo split depois.')) return;

        btn.prop('disabled', true).text('Cancelando...');

        $.ajax({
            url: restUrl,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-WP-Nonce': nonce || '' },
            data: JSON.stringify({ orderId: orderId }),
            success: function () {
                location.reload();
            },
            error: function () {
                alert('Erro ao cancelar. Tente novamente.');
                btn.prop('disabled', false).text('Cancelar');
            }
        });
    });

})(window.jQuery);
