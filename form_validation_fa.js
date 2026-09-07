(function(){
    'use strict';

    if(window.__pnvFormValidationFa){
        return;
    }

    window.__pnvFormValidationFa = true;

    function faNum(value){
        return String(value).replace(/\d/g, function(d){
            return '۰۱۲۳۴۵۶۷۸۹'[parseInt(d, 10)];
        });
    }

    function isFormField(el){
        return el instanceof HTMLInputElement
            || el instanceof HTMLSelectElement
            || el instanceof HTMLTextAreaElement;
    }

    function persianValidationMessage(field){
        var validity = field.validity;
        var type = (field.type || '').toLowerCase();
        var tag = (field.tagName || '').toLowerCase();

        if(validity.valueMissing){
            if(type === 'checkbox'){
                return 'لطفاً این گزینه را علامت بزنید.';
            }

            if(type === 'radio'){
                return 'لطفاً یک گزینه را انتخاب کنید.';
            }

            if(type === 'file'){
                return 'لطفاً یک فایل انتخاب کنید.';
            }

            if(tag === 'select'){
                return 'لطفاً یک مورد را انتخاب کنید.';
            }

            return 'لطفاً این فیلد را پر کنید.';
        }

        if(validity.typeMismatch){
            if(type === 'email'){
                return 'ایمیل وارد شده معتبر نیست.';
            }

            if(type === 'url'){
                return 'آدرس وارد شده معتبر نیست.';
            }

            return 'فرمت وارد شده درست نیست.';
        }

        if(validity.patternMismatch){
            var title = field.getAttribute('title');

            if(title){
                return title;
            }

            return 'فرمت وارد شده معتبر نیست.';
        }

        if(validity.tooShort){
            return 'حداقل ' + faNum(field.minLength) + ' کاراکتر وارد کنید.';
        }

        if(validity.tooLong){
            return 'حداکثر ' + faNum(field.maxLength) + ' کاراکتر مجاز است.';
        }

        if(validity.rangeUnderflow){
            return 'مقدار وارد شده کمتر از حد مجاز است.';
        }

        if(validity.rangeOverflow){
            return 'مقدار وارد شده بیشتر از حد مجاز است.';
        }

        if(validity.stepMismatch){
            return 'مقدار وارد شده معتبر نیست.';
        }

        if(validity.badInput){
            return 'مقدار وارد شده نامعتبر است.';
        }

        return 'مقدار وارد شده معتبر نیست.';
    }

    function clearCustomValidity(field){
        if(isFormField(field)){
            field.setCustomValidity('');
        }
    }

    document.addEventListener('invalid', function(e){
        var field = e.target;

        if(!isFormField(field)){
            return;
        }

        field.setCustomValidity(persianValidationMessage(field));
    }, true);

    document.addEventListener('input', function(e){
        clearCustomValidity(e.target);
    }, true);

    document.addEventListener('change', function(e){
        clearCustomValidity(e.target);
    }, true);
})();
