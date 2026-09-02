(function(global){
    'use strict';

    function planCategoryOf(plan){
        if(!plan || typeof plan !== 'object'){ return ''; }
        if(plan.category === 'unlimited' || plan.category === 'limited'){ return plan.category; }
        const days = String(plan.days || '').trim();
        if(!days || days === 'نامحدود' || days === 'نامحدود زمانی' || days.toLowerCase() === 'unlimited'){ return 'unlimited'; }
        const normalized = days.replace(/[۰-۹]/g, function(ch){ return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(ch)); })
            .replace(/[٠-٩]/g, function(ch){ return String('٠١٢٣٤٥٦٧٨٩'.indexOf(ch)); });
        if(/^\d+$/.test(normalized)){ return parseInt(normalized, 10) > 0 ? 'limited' : 'unlimited'; }
        return 'limited';
    }

    function sortPlans(list){
        return list.slice().sort(function(a, b){
            const ca = planCategoryOf(a);
            const cb = planCategoryOf(b);
            if(ca !== cb){ return ca === 'unlimited' ? -1 : 1; }
            return (parseInt(a.price, 10) || 0) - (parseInt(b.price, 10) || 0);
        });
    }

    function filterRenewPlans(plans, subTimeCategory){
        const list = Array.isArray(plans) ? plans : [];
        if(subTimeCategory === 'unlimited'){
            return list.filter(function(p){ return planCategoryOf(p) === 'unlimited'; });
        }
        if(subTimeCategory === 'limited'){
            return list.filter(function(p){ return planCategoryOf(p) === 'limited'; });
        }
        return list;
    }

    function isRenewPlanLocked(plan, subTimeCategory){
        if(!subTimeCategory || subTimeCategory === 'unknown'){ return false; }
        return planCategoryOf(plan) !== subTimeCategory;
    }

    function initPlanPicker(options){
        options = options || {};
        const plansData = options.plansData || [];
        const planGrid = options.planGrid;
        const planEmpty = options.planEmpty;
        const planBlockEl = options.planBlockEl;
        const planListTitle = options.planListTitle;
        const planSelect = options.planSelect;
        const mode = options.mode === 'renew' ? 'renew' : 'buy';
        const getSubTimeCategory = typeof options.getSubTimeCategory === 'function' ? options.getSubTimeCategory : null;
        const onSelectionChange = typeof options.onSelectionChange === 'function' ? options.onSelectionChange : null;
        const onLockedClick = typeof options.onLockedClick === 'function' ? options.onLockedClick : null;
        const fmtPrice = typeof options.fmtPrice === 'function' ? options.fmtPrice : function(v){ return String(v); };
        const discountedPrice = typeof options.discountedPrice === 'function' ? options.discountedPrice : function(v){ return v; };
        const getCouponState = typeof options.getCouponState === 'function' ? options.getCouponState : function(){ return { applied: false }; };
        const escapeHtml = typeof options.escapeHtml === 'function' ? options.escapeHtml : function(s){ return String(s || ''); };

        let selectedPlan = null;

        function getSelectedPlan(){ return selectedPlan; }
        function getSelectedCategory(){ return selectedPlan ? planCategoryOf(selectedPlan) : ''; }

        function clearSelection(){
            selectedPlan = null;
            if(planSelect){ planSelect.value = ''; }
            if(onSelectionChange){ onSelectionChange(null, ''); }
        }

        function selectPlan(plan){
            selectedPlan = plan || null;
            if(planSelect){ planSelect.value = plan ? plan.value : ''; }
            if(onSelectionChange){ onSelectionChange(selectedPlan, getSelectedCategory()); }
        }

        function emptyMessage(subCat){
            if(!plansData.length){
                return 'هنوز پلنی در پنل تعریف نشده است. از پنل ادمین → پلن‌ها، حداقل یک پلن اضافه کنید.';
            }
            if(mode === 'renew'){
                if(subCat === 'unlimited'){
                    return 'پلن نامحدود زمانی برای تمدید این اشتراک تعریف نشده است.';
                }
                if(subCat === 'limited'){
                    return 'پلن محدود زمانی برای تمدید این اشتراک تعریف نشده است.';
                }
            }
            return 'پلنی برای نمایش وجود ندارد.';
        }

        function renderPlans(){
            if(!planGrid || !planEmpty){ return; }

            planGrid.innerHTML = '';
            planEmpty.classList.remove('is-visible');

            const subCat = getSubTimeCategory ? getSubTimeCategory() : '';
            let list = sortPlans(plansData);
            if(mode === 'renew'){
                list = sortPlans(filterRenewPlans(plansData, subCat));
            }

            if(planBlockEl){ planBlockEl.classList.add('is-visible'); }
            if(planListTitle){
                planListTitle.textContent = mode === 'renew' ? 'پلن تمدید را انتخاب کنید' : 'پلن را انتخاب کنید';
            }

            if(!list.length){
                planEmpty.textContent = emptyMessage(subCat);
                planEmpty.classList.add('is-visible');
                clearSelection();
                return;
            }

            if(selectedPlan && !list.some(function(p){ return p.value === selectedPlan.value; })){
                clearSelection();
            }

            const couponState = getCouponState();

            list.forEach(function(plan){
                const cat = planCategoryOf(plan);
                const isLimited = cat === 'limited';
                const locked = mode === 'renew' && isRenewPlanLocked(plan, subCat);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'planChip planChip--typed'
                    + (isLimited ? ' planChip--limited' : ' planChip--unlimited')
                    + (selectedPlan && selectedPlan.value === plan.value ? ' is-active' : '')
                    + (locked ? ' is-locked' : '');

                const disc = couponState.applied ? discountedPrice(plan.price) : 0;
                let priceHtml;
                if(couponState.applied && disc < plan.price){
                    const badgeText = couponState.type === 'fixed'
                        ? 'تخفیف ویژه'
                        : ('٪' + couponState.percent + ' تخفیف');
                    priceHtml = '<span class="planPriceWrap">' +
                        '<span class="planPrice planPrice--orig">' + escapeHtml(plan.price_text) + '</span>' +
                        '<span class="planPrice planPrice--disc">' + escapeHtml(fmtPrice(disc)) + '</span>' +
                        '</span>' +
                        '<span class="planDiscBadge">' + badgeText + '</span>';
                } else {
                    priceHtml = '<span class="planPrice">' + escapeHtml(plan.price_text) + '</span>';
                }

                btn.innerHTML = '<span class="planCheck">✓</span><span class="planName"></span>' + priceHtml + '<span class="planDays"></span>';
                btn.querySelector('.planName').textContent = plan.name;
                const daysEl = btn.querySelector('.planDays');
                if(daysEl){
                    daysEl.textContent = plan.days_label || (isLimited ? '—' : 'نامحدود زمانی');
                }

                btn.addEventListener('click', function(){
                    if(locked){
                        if(onLockedClick){ onLockedClick(cat); }
                        return;
                    }
                    selectPlan(plan);
                    if(planSelect){ planSelect.dispatchEvent(new Event('change')); }
                    renderPlans();
                });

                planGrid.appendChild(btn);
            });

            if(onSelectionChange){ onSelectionChange(selectedPlan, getSelectedCategory()); }
        }

        renderPlans();

        return {
            renderPlans: renderPlans,
            getSelectedPlan: getSelectedPlan,
            getSelectedCategory: getSelectedCategory,
            clearSelection: clearSelection,
            selectPlan: selectPlan,
            planCategoryOf: planCategoryOf
        };
    }

    global.PnvPlanUi = {
        planCategoryOf: planCategoryOf,
        filterRenewPlans: filterRenewPlans,
        initPlanPicker: initPlanPicker
    };
})(window);
