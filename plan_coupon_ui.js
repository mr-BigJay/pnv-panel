(function(global){
    var state = {
        preview: null,
        timer: null
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

    function getPlanDiscount(plan){
        if(!state.preview || !state.preview.ok || !plan){
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
            state.preview = null;
            resetResult(resultEl);

            if(typeof callbacks.onClear === 'function'){
                callbacks.onClear();
            }

            return Promise.resolve(null);
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
                resultEl.textContent = 'کد «' + code + '» فعال شد — ' +
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

    function bindInput(input, resultEl, callbacks){
        if(!input){
            return;
        }

        input.addEventListener('input', function(){
            clearTimeout(state.timer);
            state.timer = setTimeout(function(){
                validatePreview(input.value, resultEl, callbacks);
            }, 450);
        });
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
        bindInput: bindInput
    };
})(window);
