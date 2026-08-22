(function(global){
    var state = {
        preview: null,
        toggleEl: null
    };

    function escapeHtml(text){
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function resetResult(el){
        if(!el){
            return;
        }

        el.className = 'couponResult';
        el.textContent = '';
    }

    function clearPreview(resultEl, callbacks){
        state.preview = null;
        resetResult(resultEl);

        if(callbacks && typeof callbacks.onUpdate === 'function'){
            callbacks.onUpdate(null);
        }
    }

    function getPlanDiscount(plan){
        if(!state.preview || !state.preview.ok || !plan){
            return null;
        }

        if(state.toggleEl && !state.toggleEl.checked){
            return null;
        }

        var map = state.preview.plans || {};
        return map[plan.value] || null;
    }

    function planPriceHtml(plan){
        var info = getPlanDiscount(plan);

        if(info && info.allowed){
            return '<span class="planPriceOld">' + escapeHtml(plan.price_text) + '</span>' +
                '<span class="planPriceNew">' + escapeHtml(info.final_text) + '</span>';
        }

        return escapeHtml(plan.price_text);
    }

    function displayPriceText(plan){
        var info = getPlanDiscount(plan);

        if(info && info.allowed){
            return info.final_text;
        }

        return plan.price_text;
    }

    function hasActiveCode(input){
        if(state.toggleEl && !state.toggleEl.checked){
            return false;
        }

        return !!(input && String(input.value || '').trim() !== '' && state.preview && state.preview.ok);
    }

    function applyToPayBody(body, input){
        if(!body || !hasActiveCode(input)){
            return;
        }

        body.set('has_coupon', '1');
        body.set('coupon_code', String(input.value || '').trim());
    }

    function clearInvalidSelection(selectedPlanRef, planSelect){
        if(!selectedPlanRef || !selectedPlanRef.value || !planSelect){
            return false;
        }

        var info = getPlanDiscount(selectedPlanRef);

        if(info && info.allowed === false){
            planSelect.value = '';
            return true;
        }

        return false;
    }

    function validatePreview(code, resultEl, callbacks){
        callbacks = callbacks || {};
        code = String(code || '').trim();

        if(code === ''){
            clearPreview(resultEl, callbacks);
            return Promise.resolve(null);
        }

        if(resultEl){
            resultEl.className = 'couponResult is-pending';
            resultEl.textContent = 'در حال بررسی…';
        }

        return fetch(
            'coupon-api.php?preview=1&code=' + encodeURIComponent(code),
            {credentials: 'same-origin'}
        )
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(!data || !data.ok){
                state.preview = null;

                if(resultEl){
                    resultEl.className = 'couponResult is-error';
                    resultEl.textContent = (data && data.error) ? data.error : 'کد تخفیف معتبر نیست';
                }

                if(typeof callbacks.onError === 'function'){
                    callbacks.onError(data);
                }

                if(typeof callbacks.onUpdate === 'function'){
                    callbacks.onUpdate(null);
                }

                return null;
            }

            state.preview = data;

            if(resultEl){
                resultEl.className = 'couponResult is-ok';
                resultEl.textContent = 'کد «' + code + '» اعمال شد — ' +
                    (data.percent_label || (data.percent ? (data.percent + '٪') : 'تخفیف')) +
                    ' روی پلن‌های مجاز';
            }

            if(typeof callbacks.onSuccess === 'function'){
                callbacks.onSuccess(data);
            }

            if(typeof callbacks.onUpdate === 'function'){
                callbacks.onUpdate(data);
            }

            return data;
        })
        .catch(function(){
            state.preview = null;

            if(resultEl){
                resultEl.className = 'couponResult is-error';
                resultEl.textContent = 'خطا در بررسی کد';
            }

            if(typeof callbacks.onError === 'function'){
                callbacks.onError(null);
            }

            if(typeof callbacks.onUpdate === 'function'){
                callbacks.onUpdate(null);
            }

            return null;
        });
    }

    function bindStep1Ui(options){
        options = options || {};

        var toggleCheck = options.toggleCheck;
        var couponBox = options.couponBox;
        var input = options.input;
        var applyBtn = options.applyBtn;
        var resultEl = options.resultEl;
        var callbacks = {
            onUpdate: options.onUpdate,
            onSuccess: options.onSuccess,
            onError: options.onError
        };

        state.toggleEl = toggleCheck || null;

        function setApplyLoading(loading){
            if(!applyBtn){
                return;
            }

            applyBtn.disabled = !!loading;
            applyBtn.textContent = loading ? '…' : 'اعمال';
        }

        function doApply(){
            if(toggleCheck && !toggleCheck.checked){
                return;
            }

            setApplyLoading(true);

            validatePreview(input ? input.value : '', resultEl, callbacks)
                .finally(function(){
                    setApplyLoading(false);
                });
        }

        if(toggleCheck){
            toggleCheck.addEventListener('change', function(){
                if(this.checked){
                    if(couponBox){
                        couponBox.classList.add('is-open');
                    }

                    if(input){
                        input.focus();
                    }
                }
                else{
                    if(couponBox){
                        couponBox.classList.remove('is-open');
                    }

                    if(input){
                        input.value = '';
                    }

                    clearPreview(resultEl, callbacks);
                }
            });
        }

        if(applyBtn){
            applyBtn.addEventListener('click', function(){
                doApply();
            });
        }

        if(input){
            input.addEventListener('keydown', function(e){
                if(e.key === 'Enter'){
                    e.preventDefault();
                    doApply();
                }
            });
        }
    }

    global.PlanCoupon = {
        get preview(){ return state.preview; },
        resetResult: resetResult,
        getPlanDiscount: getPlanDiscount,
        planPriceHtml: planPriceHtml,
        displayPriceText: displayPriceText,
        hasActiveCode: hasActiveCode,
        applyToPayBody: applyToPayBody,
        clearInvalidSelection: clearInvalidSelection,
        validatePreview: validatePreview,
        bindStep1Ui: bindStep1Ui
    };
})(window);
