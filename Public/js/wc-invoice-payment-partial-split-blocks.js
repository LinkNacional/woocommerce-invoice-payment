/**
 * Split de Pagamento — Checkout Blocks (vanilla JS)
 *
 * O step HTML completo é injetado pelo PHP via render_block filter.
 * Eventos delegados no document + fetch interceptor pra detectar mudanças.
 *
 * @since 2.12.1
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

    function getCartTotal() {
        try {
            if (window.wp && window.wp.data && window.wp.data.select) {
                var totals = window.wp.data.select('wc/store/cart').getCartTotals();
                if (totals && totals.total_price) {
                    var price = parseFloat(totals.total_price);
                    var minorUnit = totals.currency_minor_unit || 2;
                    return price / Math.pow(10, minorUnit);
                }
            }
        } catch (e) {}
        return 0;
    }

    /**
     * Base máxima (sem juros/taxas): subtotal + shipping - discount.
     * Lê direto da Store API — sem necessidade de AJAX.
     */
    function getBaseMax() {
        try {
            if (window.wp && window.wp.data && window.wp.data.select) {
                var totals = window.wp.data.select('wc/store/cart').getCartTotals();
                if (totals) {
                    var minorUnit = totals.currency_minor_unit || 2;
                    var items = parseFloat(totals.total_items) || 0;
                    var shipping = parseFloat(totals.total_shipping) || 0;
                    var discount = parseFloat(totals.total_discount) || 0;
                    return (items + shipping - discount) / Math.pow(10, minorUnit);
                }
            }
        } catch (e) {}
        return 0;
    }

    function updateBaseMaxLabel() {
        var base = getBaseMax();
        if (base > 0) {
            getCard().find('.lkn-wcip-base-max-val').text(formatCurrency(base));
        }
    }

    var calculated = false;
    var splitData = null;
    var _restoring = false; // trava durante restoreState pra evitar loop

    // —— Getters (re-query DOM toda vez — WC Blocks re-renderiza) ——
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
        getCheckbox().prop('checked', false);
        getInput().val('').prop('disabled', false);
        getBtn().text('Split pagamento').css({ opacity: '0.5', pointerEvents: 'none' });
        getResult().hide().empty();
        getFields().hide();
        getCard().find('.lkn-wcip-base-max-msg').hide();
    }

    function handleReset() {
        getBtn().prop('disabled', true);
        ajaxPost('lkn_wcip_clear_partial_split').then(function (res) {
            if (res && res.success) {
                clearState();
                if (res.data && res.data.cart_total) {
                    getStep().find('.lkn-wcip-base-max-val').text(formatCurrency(parseFloat(res.data.cart_total)));
                }
                invalidateCart();
            }
        }).finally(function () { getBtn().prop('disabled', false); });
    }

    function handleCalculate() {
        var val = parseCurrency(getInput().val());
        if (!val || val <= 0) { alert('Digite um valor válido para o pagamento parcial.'); return; }
        if (val >= getCartTotal()) { alert('O valor parcial deve ser menor que o total.'); return; }
        if (MIN_AMOUNT > 0 && val < MIN_AMOUNT) { alert('Valor abaixo do mínimo permitido.'); return; }

        getBtn().prop('disabled', true);

        ajaxPost('lkn_wcip_set_partial_split', { partialAmount: val }).then(function (res) {
            if (!res || !res.success) {
                alert(res && res.data && res.data.message ? res.data.message : 'Erro.');
                return;
            }

            calculated = true;
            splitData = res.data;

            getInput().val(formatCurrency(val)).prop('disabled', true);
            getBtn().text('Cancelar split').css({ opacity: '', pointerEvents: '' });
            renderResult();
            invalidateCart();
        }).finally(function () { getBtn().prop('disabled', false); });
    }

    function handleInitiatePartial() {
        getBtn().prop('disabled', true).text('Criando pedido...');

        ajaxPost('lkn_wcip_initiate_partial', {}).then(function (res) {
            if (!res || !res.success || !res.data.redirect_url) {
                alert(res && res.data && res.data.message ? res.data.message : 'Erro ao iniciar pagamento parcial.');
                getBtn().prop('disabled', false).text('Iniciar pagamento parcial');
                return;
            }

            window.location.href = res.data.redirect_url;
        }).catch(function () {
            alert('Erro de rede. Tente novamente.');
            getBtn().prop('disabled', false).text('Iniciar pagamento parcial');
        });
    }

    function renderResult() {
        var d = splitData;
        if (!d) return;

        var partialAmount = parseFloat(d.partial_amount) || 0;
        var baseMax = parseFloat(d.base_max) || 0;
        var gatewayFees = parseFloat(d.gateway_fees) || 0;
        // cartTotal do wp.data (sempre atualizado) c/ fallback pro PHP
        var cartTotal = getCartTotal() || parseFloat(d.cart_total) || 0;
        // remaining recalculado com dados frescos
        var remaining = baseMax + gatewayFees - cartTotal;
        if (remaining < 0) remaining = 0;
        var totalComTaxas = baseMax + gatewayFees;
        var realPaidNow = Math.abs(gatewayFees) > 0.01 ? cartTotal : partialAmount;
        var hasFees = Math.abs(gatewayFees) > 0.01;

        console.log('[PartialSplit] renderResult — cartTotal=' + cartTotal + ' remaining=' + remaining + ' fees=' + gatewayFees);

        var html = '<div style="margin-top:12px;padding:14px;background:#fff;border:1px solid #e0e0e0;border-radius:4px">';

        html += '<div style="font-size:13px;color:#999;margin-bottom:10px"><span>Total original</span><span style="float:right">' + formatCurrency(totalComTaxas) + '</span></div>';
        html += '<div style="font-size:13px;color:#555;margin-bottom:4px"><span>Subtotal + Frete</span><span style="float:right;font-weight:500">' + formatCurrency(baseMax) + '</span></div>';
        html += '<div style="font-size:13px;color:#555;margin-bottom:6px"><span>Taxas/juros adicionais:</span><span style="float:right;font-weight:500;color:' + (hasFees ? '#00a32a' : '#999') + '">' + (gatewayFees > 0.01 ? '+' : '') + formatCurrency(gatewayFees) + '</span></div>';

        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
        html += '<div style="font-size:13px;color:#d63638;margin-bottom:4px"><span>Pagamento parcial</span><span style="float:right;font-weight:500">' + formatCurrency(-remaining) + '</span></div>';
        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
        html += '<div style="font-size:14px;font-weight:600;color:#333;margin-bottom:' + (hasFees ? '4px' : '12px') + '"><span>Você pagará agora:</span><span style="float:right">' + formatCurrency(realPaidNow) + '</span></div>';

        if (hasFees) {
            html += '<p style="font-size:12px;color:#999;margin:0 0 12px;line-height:1.4">O valor informado de ' + formatCurrency(partialAmount) + ' foi ajustado para ' + formatCurrency(realPaidNow) + ' devido a taxas, juros ou descontos aplicados ao pedido.</p>';
        }

        html += '<div style="padding-top:10px;border-top:2px solid #e0e0e0"><p style="margin:0;font-size:14px;color:#d63638">Restante para depois: <strong>' + formatCurrency(remaining) + '</strong></p></div>';
        html += '</div>';

        getResult().html(html).show();
    }

    function invalidateCart() {
        if (window.wp && window.wp.data && window.wp.data.dispatch) {
            try { window.wp.data.dispatch('wc/store/cart').invalidateResolutionForStore(); } catch (e) {}
        }
        // Acorda gateways (Rede, Cielo) — gera POST /batch
        setTimeout(function () {
            try {
                if (window.wc && window.wc.blocksCheckout && window.wc.blocksCheckout.extensionCartUpdate) {
                    window.wc.blocksCheckout.extensionCartUpdate({
                        namespace: 'woo_invoice_payment',
                        data: { _ts: Date.now() }
                    });
                }
            } catch (e) {}
        }, 300);
    }

    // ==========================================================
    // Event delegation
    // ==========================================================

    // Botão "Continuar" na lista de retomada
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
        console.log('[PartialSplit] checkbox CHANGE — checked=' + this.checked);
        if (this.checked) {
            if (!IS_PAY_REMAINING) {
                // Checkout normal: esconde gateways, recalcula, mostra só o botão centralizado
                getFields().show();
                getInput().hide();
                getBtn().css({ opacity: '1', pointerEvents: '' }).show().parent().css({ justifyContent: 'center' });
                $('#payment-method').hide();
                ajaxPost('lkn_wcip_toggle_partial_mode', { active: '1' }).then(invalidateCart);
            } else {
                // pay_remaining: mostra input + botão
                getFields().slideDown(200);
                getCard().find('.lkn-wcip-base-max-msg').slideDown(200);
                var hasValue = parseCurrency(getInput().val()) > 0;
                getBtn().css({ opacity: hasValue ? '' : '0.5', pointerEvents: hasValue ? '' : 'none' });
            }
        } else {
            if (!IS_PAY_REMAINING) {
                // Checkout normal: mostra gateways, esconde tudo
                getFields().hide();
                getInput().show(); // restaura pra próxima vez
                $('#payment-method').show();
                ajaxPost('lkn_wcip_toggle_partial_mode', { active: '0' }).then(invalidateCart);
            } else {
                getFields().slideUp(200);
                getCard().find('.lkn-wcip-base-max-msg').slideUp(200);
                if (calculated) handleReset();
            }
        }
    });

    $(document).on('click', '#lkn-wcip-split-btn', function () {
        console.log('[PartialSplit] btn CLICK — calculated=' + calculated + ' IS_PAY_REMAINING=' + IS_PAY_REMAINING);
        if (IS_PAY_REMAINING) {
            // Fluxo pay_remaining (1/2 ou 2/2): calcula split e mostra resultado
            if (calculated) handleReset();
            else handleCalculate();
        } else {
            // Checkout normal (1ª vez): cria pedido sem pagamento e redireciona
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
    // Bloqueio do botão "Place Order" via MutationObserver.
    // Inspirado no woo-better: attach direto ao elemento (não delegado)
    // pra interceptar ANTES do handler nativo do WooCommerce Blocks.
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
                    // Tudo ok, esconde erro
                    $err.slideUp(200);
                }
                return;
            }
            if (getCheckbox().is(':checked') && !calculated) {
                e.stopImmediatePropagation();
                e.preventDefault();
                var $inp = getInput();
                var $err = $('.lkn-wcip-split-error');
                $err.slideDown(200);
                if ($inp.length) $inp[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        }

        function bindPlaceOrder() {
            if (placeOrderBound) return;
            var btn = document.querySelector('.wc-block-components-checkout-place-order-button');
            if (!btn) return;
            placeOrderBound = true;
            btn.addEventListener('click', handlePlaceOrderClick);
            console.log('[PartialSplit] Place Order button bound via addEventListener');
        }

        // Tenta várias vezes (WC Blocks re-renderiza o botão)
        setInterval(function () {
            var btn = document.querySelector('.wc-block-components-checkout-place-order-button');
            if (btn && !placeOrderBound) {
                bindPlaceOrder();
            }
            if (!btn) {
                placeOrderBound = false;
            }
        }, 500);

        // Também via MutationObserver pra ser mais rápido
        new MutationObserver(function () {
            var btn = document.querySelector('.wc-block-components-checkout-place-order-button');
            if (btn && !placeOrderBound) {
                bindPlaceOrder();
            }
        }).observe(document.body, { childList: true, subtree: true });
    })();

    console.log('[PartialSplit] delegated events bound on document');

    // ==========================================================
    // Fetch interception — detecta QUALQUER mudança no carrinho
    // /batch    = POST (frete, cupom, extensionCartUpdate)
    // /cart     = GET refetch (invalidateResolutionForStore, Rede/Cielo)
    // /checkout = POST (troca de gateway, recalcula totais)
    // ==========================================================
    (function () {
        var originalFetch = window.fetch;

        window.fetch = function () {
            var url = arguments[0];
            var urlStr = '';
            if (typeof url === 'string') {
                urlStr = url;
            } else if (url instanceof Request) {
                urlStr = url.url || '';
            } else if (url && typeof url.url === 'string') {
                urlStr = url.url;
            }

            var isBatch = urlStr.indexOf('/wc/store/v1/batch') !== -1;
            var isCart = !isBatch && urlStr.indexOf('/wc/store/v1/cart') !== -1 && urlStr.indexOf('select-shipping-rate') === -1;
            var isCheckout = !isBatch && !isCart && urlStr.indexOf('/wc/store/v1/checkout') !== -1;

            // Debug: log toda requisição pra Store API
            if (isBatch || isCart || isCheckout) {
                console.log('[PartialSplit] fetch detected: ' + (isCheckout ? 'CHECKOUT' : (isBatch ? 'BATCH' : 'CART')) + ' url=' + urlStr);
            }

            var promise = originalFetch.apply(this, arguments);

            if (isBatch || isCart || isCheckout) {
                // Atualiza o "Valor maximo" sempre que o carrinho mudar
                setTimeout(updateBaseMaxLabel, 400);
            }

            if ((isBatch || isCart || isCheckout) && calculated && splitData && !_restoring) {
                var kind = isCheckout ? 'checkout' : (isBatch ? 'batch' : 'cart');
                console.log('[PartialSplit] fetch detected (' + kind + ') — will refresh after settle');
                // Espera a resposta da fetch atual ANTES de restaurar o estado.
                // CHECKOUT: gateway mudou → totais recalculados → AJAX vai refletir.
                promise.then(function () {
                    setTimeout(function () {
                        if (!calculated || !splitData || _restoring) return;
                        restoreState();
                    }, isCart ? 800 : 400);
                });
            }

            return promise;
        };
    })();

    function restoreState(retryCount) {
        var retries = retryCount || 0;
        if (!splitData || !splitData.partial_amount) return;

        var partialAmount = parseFloat(splitData.partial_amount);
        _restoring = true;

        ajaxPost('lkn_wcip_get_partial_split_state').then(function (res) {
            if (!res || !res.success || !res.data || !res.data.active) {
                // Gateway pode ter mudado pra um não suportado, ou race condition.
                // Tenta de novo uma vez depois de 600ms.
                if (retries < 2) {
                    console.log('[PartialSplit] restoreState: inactive (retry ' + (retries+1) + '/2)');
                    setTimeout(function () {
                        _restoring = false;
                        if (!calculated || !splitData) return;
                        restoreState(retries + 1);
                    }, 600);
                    return;
                }
                console.log('[PartialSplit] restoreState: split inactive after retries, keeping UI');
                restoreUI(partialAmount);
                _restoring = false;
                return;
            }

            var cartTotal = getCartTotal();
            splitData = {
                partial_amount: res.data.partial_amount,
                cart_total: cartTotal > 0 ? cartTotal : res.data.cart_total,
                base_max: res.data.base_max,
                gateway_fees: res.data.gateway_fees,
                remaining: res.data.remaining
            };

            restoreUI(partialAmount);
            renderResult();
            console.log('[PartialSplit] state restored with fresh data');
            setTimeout(function () { _restoring = false; }, 800);
        }).catch(function () {
            _restoring = false;
        });
    }

    // Reconstroi UI do split (checkbox + campos + valores) — usado por restoreState
    function restoreUI(partialAmount) {
        var $cb = getCheckbox();
        var $f = getFields();
        var $c = getCard();
        var $inp = getInput();
        var $b = getBtn();

        if ($cb.length && !$cb.is(':checked')) $cb.prop('checked', true);
        $f.show();
        $c.find('.lkn-wcip-base-max-msg').show();

        if (splitData && splitData.base_max) {
            $c.find('.lkn-wcip-base-max-val').text(formatCurrency(parseFloat(splitData.base_max)));
        }

        $inp.val(formatCurrency(partialAmount)).prop('disabled', true);
        $b.text('Cancelar split').css({ opacity: '', pointerEvents: '' });
    }

    // ==========================================================
    // Modo "pagar restante" (pay_remaining na URL)
    // ==========================================================
    var IS_PAY_REMAINING = !!CONFIG.isPayRemaining;

    function renderPayRemainingResult() {
        var d = splitData;
        if (!d) return;

        var partialAmount = parseFloat(d.partial_amount) || 0;
        var baseMax = parseFloat(d.base_max) || 0;
        var gatewayFees = parseFloat(d.gateway_fees) || 0;
        var cartTotal = getCartTotal() || parseFloat(d.cart_total) || 0;
        var remaining = parseFloat(d.remaining) || 0;
        var hasFees = Math.abs(gatewayFees) > 0.01;
        var realPaidNow = hasFees ? cartTotal : partialAmount;

        var html = '<div style="margin-top:12px;padding:14px;background:#fff;border:1px solid #e0e0e0;border-radius:4px">';

        html += '<div style="font-size:13px;color:#555;margin-bottom:4px"><span>Valor restante</span><span style="float:right;font-weight:500">' + formatCurrency(partialAmount) + '</span></div>';
        html += '<div style="font-size:13px;color:#555;margin-bottom:6px"><span>Taxas/juros adicionais:</span><span style="float:right;font-weight:500;color:' + (hasFees ? '#00a32a' : '#999') + '">' + (gatewayFees > 0.01 ? '+' : '') + formatCurrency(gatewayFees) + '</span></div>';

        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
        html += '<div style="font-size:14px;font-weight:600;color:#333;margin-bottom:' + (hasFees ? '4px' : '12px') + '"><span>Você pagará agora:</span><span style="float:right">' + formatCurrency(realPaidNow) + '</span></div>';

        if (hasFees) {
            html += '<p style="font-size:12px;color:#999;margin:0 0 12px;line-height:1.4">O valor informado de ' + formatCurrency(partialAmount) + ' foi ajustado para ' + formatCurrency(realPaidNow) + ' devido a taxas, juros ou descontos aplicados ao pedido.</p>';
        }

        html += '<hr style="border:none;border-top:1px dashed #ccc;margin:6px 0">';
        html += '<div style="padding-top:10px;border-top:2px solid #e0e0e0"><p style="margin:0;font-size:14px;color:#008a20">Restante pago anteriormente: <strong>' + formatCurrency(remaining) + '</strong></p></div>';

        html += '</div>';

        getResult().html(html).show();
    }

    function refreshPayRemainingSummary() {
        if (_restoring) return;

        ajaxPost('lkn_wcip_get_partial_split_state').then(function (res) {
            if (!res || !res.success || !res.data || !res.data.active) return;

            var cartTotal = getCartTotal();
            splitData = {
                partial_amount: res.data.partial_amount,
                cart_total: cartTotal > 0 ? cartTotal : res.data.cart_total,
                base_max: res.data.base_max,
                gateway_fees: res.data.gateway_fees,
                remaining: res.data.remaining
            };

            renderPayRemainingResult();
        });
    }

    if (IS_PAY_REMAINING) {
        // Inicial logo ao carregar
        $(function () {
            setTimeout(refreshPayRemainingSummary, 300);
        });

        // Refresca a cada mudança de carrinho
        (function () {
            var _origFetch = window.fetch;
            window.fetch = function () {
                var urlStr = '';
                var arg = arguments[0];
                if (typeof arg === 'string') { urlStr = arg; }
                else if (arg instanceof Request) { urlStr = arg.url || ''; }
                else if (arg && typeof arg.url === 'string') { urlStr = arg.url; }

                var promise = _origFetch.apply(this, arguments);

                var isRelevant = urlStr.indexOf('/wc/store/v1/batch') !== -1
                              || (urlStr.indexOf('/wc/store/v1/cart') !== -1 && urlStr.indexOf('select-shipping-rate') === -1)
                              || urlStr.indexOf('/wc/store/v1/checkout') !== -1;

                if (isRelevant) {
                    setTimeout(refreshPayRemainingSummary, 600);
                }

                return promise;
            };
        })();
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
