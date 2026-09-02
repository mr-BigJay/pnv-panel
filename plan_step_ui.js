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

    function planCountsByCategory(plansData){
        const counts = { unlimited: 0, limited: 0 };
        (plansData || []).forEach(function(p){
            const cat = planCategoryOf(p);
            if(cat === 'unlimited'){ counts.unlimited++; }
            else if(cat === 'limited'){ counts.limited++; }
        });
        return counts;
    }

    function categoryLabel(cat){
        return cat === 'limited' ? 'محدود زمانی' : 'نامحدود زمانی';
    }

    function initCategoryPlanPicker(options){
        options = options || {};
        const plansData = options.plansData || [];
        const planGrid = options.planGrid;
        const planEmpty = options.planEmpty;
        const planBlockEl = options.planBlockEl;
        const planListTitle = options.planListTitle;
        const planSelect = options.planSelect;
        const mode = options.mode === 'renew' ? 'renew' : 'buy';
        const getSubTimeCategory = typeof options.getSubTimeCategory === 'function' ? options.getSubTimeCategory : null;
        const isCategoryLocked = typeof options.isCategoryLocked === 'function' ? options.isCategoryLocked : function(){ return false; };
        const onCategoryLockedClick = typeof options.onCategoryLockedClick === 'function' ? options.onCategoryLockedClick : null;
        const onSelectionChange = typeof options.onSelectionChange === 'function' ? options.onSelectionChange : null;
        const fmtPrice = typeof options.fmtPrice === 'function' ? options.fmtPrice : function(v){ return String(v); };
        const discountedPrice = typeof options.discountedPrice === 'function' ? options.discountedPrice : function(v){ return v; };
        const getCouponState = typeof options.getCouponState === 'function' ? options.getCouponState : function(){ return { applied: false }; };
        const escapeHtml = typeof options.escapeHtml === 'function' ? options.escapeHtml : function(s){ return String(s || ''); };
        const getEmptyCategoryMessage = typeof options.getEmptyCategoryMessage === 'function' ? options.getEmptyCategoryMessage : null;

        let selectedCategory = '';
        let selectedPlan = null;

        function getSelectedCategory(){ return selectedCategory; }
        function getSelectedPlan(){ return selectedPlan; }

        function selectCategory(cat, silent){
            selectedCategory = cat || '';
            document.querySelectorAll('.catCard').forEach(function(el){
                el.classList.toggle('is-active', el.getAttribute('data-cat') === selectedCategory);
            });
            if(!silent && onSelectionChange){
                onSelectionChange(selectedPlan, selectedCategory);
            }
        }

        function clearPlanSelection(){
            selectedPlan = null;
            if(planSelect){ planSelect.value = ''; }
        }

        function notifySelection(){
            if(onSelectionChange){
                onSelectionChange(selectedPlan, selectedCategory);
            }
        }

        function initCategories(){
            const counts = planCountsByCategory(plansData);
            const total = (counts.unlimited || 0) + (counts.limited || 0);

            document.querySelectorAll('.catCard').forEach(function(card){
                const cat = card.getAttribute('data-cat');
                const hasPlans = (counts[cat] || 0) > 0;
                card.hidden = !hasPlans;
                card.classList.toggle('is-empty', !hasPlans);
                if(!hasPlans){
                    card.classList.remove('is-active');
                    if(selectedCategory === cat){
                        selectedCategory = '';
                        clearPlanSelection();
                    }
                }
            });

            if(total === 0){
                selectCategory('', true);
                clearPlanSelection();
                if(planBlockEl){ planBlockEl.classList.add('is-visible'); }
                if(planEmpty){
                    planEmpty.textContent = 'هنوز پلنی در پنل تعریف نشده است.';
                    planEmpty.classList.add('is-visible');
                }
                notifySelection();
                return;
            }

            const available = ['unlimited', 'limited'].filter(function(cat){
                return (counts[cat] || 0) > 0 && !isCategoryLocked(cat);
            });

            if(available.length === 1 && (!selectedCategory || isCategoryLocked(selectedCategory) || !(counts[selectedCategory] || 0))){
                selectCategory(available[0], true);
            } else if(selectedCategory && (!(counts[selectedCategory] || 0) || isCategoryLocked(selectedCategory))){
                selectCategory(available[0] || '', true);
            }
        }

        function renderPlans(){
            if(!planGrid || !planEmpty){ return; }

            initCategories();

            planGrid.innerHTML = '';
            planEmpty.classList.remove('is-visible');

            if(!selectedCategory){
                if(planBlockEl){ planBlockEl.classList.remove('is-visible'); }
                notifySelection();
                return;
            }

            const categoryLocked = isCategoryLocked(selectedCategory);
            const isLimited = selectedCategory === 'limited';
            const list = (plansData || []).filter(function(p){ return planCategoryOf(p) === selectedCategory; });

            if(planBlockEl){ planBlockEl.classList.add('is-visible'); }
            if(planListTitle){
                planListTitle.textContent = isLimited ? 'حجم و مدت را انتخاب کنید' : 'حجم را انتخاب کنید';
            }

            if(list.length === 0){
                const counts = planCountsByCategory(plansData);
                const other = selectedCategory === 'limited' ? 'unlimited' : 'limited';
                if((counts[other] || 0) > 0 && !isCategoryLocked(other)){
                    selectCategory(other, true);
                    renderPlans();
                    return;
                }
                if(getEmptyCategoryMessage && categoryLocked){
                    planEmpty.textContent = getEmptyCategoryMessage(selectedCategory);
                } else if((counts[other] || 0) > 0){
                    planEmpty.textContent = 'در این دسته پلنی تعریف نشده. دسته «' + categoryLabel(other) + '» را انتخاب کنید.';
                } else {
                    planEmpty.textContent = 'در این دسته پلنی تعریف نشده است.';
                }
                planEmpty.classList.add('is-visible');
                clearPlanSelection();
                notifySelection();
                return;
            }

            if(selectedPlan && !list.some(function(p){ return p.value === selectedPlan.value; })){
                clearPlanSelection();
            }

            const couponState = getCouponState();

            list.forEach(function(plan){
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'planChip'
                    + (isLimited ? ' planChip--limited' : '')
                    + (selectedPlan && selectedPlan.value === plan.value ? ' is-active' : '')
                    + (categoryLocked ? ' is-locked' : '');

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

                btn.innerHTML = '<span class="planCheck">✓</span><span class="planName"></span>' + priceHtml + (isLimited ? '<span class="planDays"></span>' : '');
                btn.querySelector('.planName').textContent = plan.name;
                if(isLimited){
                    const d = btn.querySelector('.planDays');
                    if(d){ d.textContent = 'مدت: ' + (plan.days_label || '—'); }
                }

                btn.addEventListener('click', function(){
                    if(categoryLocked){
                        if(onCategoryLockedClick){ onCategoryLockedClick(selectedCategory); }
                        return;
                    }
                    selectedPlan = plan;
                    if(planSelect){ planSelect.value = plan.value; planSelect.dispatchEvent(new Event('change')); }
                    renderPlans();
                });

                planGrid.appendChild(btn);
            });

            notifySelection();
        }

        function syncRenewCategory(subTimeCategory){
            if(mode !== 'renew'){ return; }
            document.querySelectorAll('.catCard').forEach(function(card){
                const cat = card.getAttribute('data-cat');
                const locked = isCategoryLocked(cat);
                card.classList.toggle('is-locked', locked);
                if(locked && card.classList.contains('is-active')){
                    card.classList.remove('is-active');
                    if(selectedCategory === cat){
                        selectedCategory = '';
                        clearPlanSelection();
                    }
                }
            });
            if(subTimeCategory === 'unlimited' && !selectedCategory){
                selectCategory('unlimited', true);
            }
            if(subTimeCategory === 'limited' && !selectedCategory){
                selectCategory('limited', true);
            }
            if(selectedCategory){
                const activeCard = document.querySelector('.catCard[data-cat="' + selectedCategory + '"]');
                if(activeCard && activeCard.classList.contains('is-locked')){
                    selectedCategory = '';
                    clearPlanSelection();
                }
            }
            renderPlans();
        }

        document.querySelectorAll('.catCard').forEach(function(card){
            card.addEventListener('click', function(){
                if(card.hidden || card.classList.contains('is-empty')){ return; }
                const cat = card.getAttribute('data-cat');
                if(isCategoryLocked(cat)){
                    if(onCategoryLockedClick){ onCategoryLockedClick(cat); }
                    return;
                }
                selectCategory(cat, true);
                clearPlanSelection();
                if(planSelect){ planSelect.dispatchEvent(new Event('change')); }
                renderPlans();
            });
        });

        renderPlans();

        return {
            renderPlans: renderPlans,
            syncRenewCategory: syncRenewCategory,
            getSelectedCategory: getSelectedCategory,
            getSelectedPlan: getSelectedPlan,
            selectCategory: selectCategory,
            clearPlanSelection: clearPlanSelection,
            planCategoryOf: planCategoryOf
        };
    }

    global.PnvPlanUi = {
        planCategoryOf: planCategoryOf,
        planCountsByCategory: planCountsByCategory,
        initCategoryPlanPicker: initCategoryPlanPicker
    };
})(window);
