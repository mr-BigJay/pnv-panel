(function(global){

    function scrollToBottom(el, force){

        if(!el){
            return;
        }

        const distance = el.scrollHeight - el.scrollTop - el.clientHeight;

        if(force || distance < 140){
            el.scrollTop = el.scrollHeight;
        }

    }

    function escapeHtml(text){

        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    }

    function isMobileComposer(){

        const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
        const narrowScreen = window.matchMedia('(max-width: 768px)').matches;
        const touchMac = navigator.maxTouchPoints > 1;

        return narrowScreen && (coarsePointer || touchMac);

    }

    function isEnterKey(e){

        return e.key === 'Enter'
            || e.code === 'Enter'
            || e.keyCode === 13;

    }

    function submitComposerForm(form){

        if(!form){
            return;
        }

        const submitBtn = form.querySelector(
            'button[type="submit"], input[type="submit"]'
        );

        if(submitBtn){
            submitBtn.click();
            return;
        }

        try{

            if(typeof form.requestSubmit === 'function'){
                form.requestSubmit();
                return;
            }

        }
        catch(err){}

        form.submit();

    }

    function bindTextareaGrow(textarea){

        if(!textarea){
            return;
        }

        const maxHeight = 160;

        textarea.style.overflowY = 'hidden';

        textarea.addEventListener('input', function(){

            this.style.height = '44px';

            const scrollHeight = this.scrollHeight;
            const nextHeight = Math.min(scrollHeight, maxHeight);

            this.style.height = nextHeight + 'px';
            this.style.overflowY = scrollHeight > maxHeight ? 'auto' : 'hidden';

        });

    }

    function bindEnterToSend(textarea, form, allowEmptyImage){

        if(!textarea || !form){
            return;
        }

        if(isMobileComposer()){
            textarea.setAttribute('enterkeyhint', 'enter');
        }

        textarea.addEventListener('keydown', function(e){

            if(!isEnterKey(e)){
                return;
            }

            if(isMobileComposer()){
                return;
            }

            if(e.shiftKey){
                return;
            }

            e.preventDefault();

            const text = (textarea.value || '').trim();
            const imageInput = allowEmptyImage
                ? form.querySelector('input[type="file"]')
                : null;
            const hasImage = imageInput && imageInput.files && imageInput.files.length > 0;

            if(text === '' && !hasImage){
                return;
            }

            setTimeout(function(){
                submitComposerForm(form);
            }, 0);

        });

    }

    function bindFormGuard(form, textarea, imageId){

        if(!form){
            return;
        }

        form.addEventListener('submit', function(e){

            const text = (textarea?.value || '').trim();
            const image = imageId ? document.getElementById(imageId) : null;

            if(text === '' && (!image || !image.files.length)){
                e.preventDefault();
                alert('متن یا تصویر وارد کنید');
            }

        });

    }

    function buildBubbleNode(msg, classMap){

        const sender = msg.sender || 'user';
        let cls = classMap[sender] || classMap.user || 'user';

        const wrap = document.createElement('div');
        wrap.className = 'msgBubble ' + cls + ' msg ' + cls;
        wrap.dataset.msgId = msg.id || '';
        wrap.dataset.timestamp = msg.timestamp || 0;

        let html = escapeHtml(msg.text || '').replace(/\n/g, '<br>');

        if(msg.edited){
            html += '<br><small>(ویرایش شد)</small>';
        }

        if(msg.image){
            html += '<br><img src="' + escapeHtml(msg.image) + '" alt="">';
        }

        html += '<div class="msgMeta">' +
            escapeHtml(msg.date || '') + ' · ' + escapeHtml(msg.time || '') +
            '</div>';

        wrap.innerHTML = html;
        return wrap;

    }

    function initPolling(options){

        const chatEl = options.chatEl;
        const pollUrl = options.pollUrl;
        const getParams = options.getParams || function(){ return ''; };
        const classMap = options.classMap || {admin:'admin',user:'user'};
        const interval = options.interval || 5000;
        let lastPollTimestamp = options.since || 0;

        if(chatEl){

            chatEl.querySelectorAll('[data-timestamp]').forEach(function(node){

                const ts = parseInt(node.dataset.timestamp || '0', 10);

                if(ts > lastPollTimestamp){
                    lastPollTimestamp = ts;
                }

            });

        }

        async function poll(){

            if(!chatEl || !pollUrl){
                return;
            }

            const url = pollUrl + getParams(lastPollTimestamp);

            try{

                const response = await fetch(url, {credentials: 'same-origin'});

                if(!response.ok){
                    return;
                }

                const payload = await response.json();
                let added = false;

                (payload.messages || []).forEach(function(msg){

                    if(chatEl.querySelector('[data-msg-id="' + msg.id + '"]')){
                        return;
                    }

                    const empty = chatEl.querySelector('.msgEmpty');

                    if(empty){
                        empty.remove();
                    }

                    const node = buildBubbleNode(msg, classMap);
                    chatEl.appendChild(node);
                    lastPollTimestamp = Math.max(lastPollTimestamp, msg.timestamp || 0);
                    added = true;

                });

                if(added){
                    scrollToBottom(chatEl, false);
                }

            }
            catch(e){}

        }

        setInterval(poll, interval);
        scrollToBottom(chatEl, true);

    }

    global.SupportUI = {
        scrollToBottom: scrollToBottom,
        bindTextareaGrow: bindTextareaGrow,
        bindEnterToSend: bindEnterToSend,
        bindFormGuard: bindFormGuard,
        initPolling: initPolling,
        submitComposerForm: submitComposerForm
    };

})(window);
