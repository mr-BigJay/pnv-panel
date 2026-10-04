(function(global){

    function scrollToBottom(el, force){
        if(!el){ return; }
        const distance = el.scrollHeight - el.scrollTop - el.clientHeight;
        if(force || distance < 140){
            el.scrollTop = el.scrollHeight;
        }
    }

    function scrollToBottomOnOpen(el){
        if(!el){ return; }

        function doScroll(){
            el.scrollTop = el.scrollHeight;
        }

        doScroll();

        requestAnimationFrame(function(){
            doScroll();
            requestAnimationFrame(doScroll);
        });

        [0, 50, 120, 250, 500, 900].forEach(function(ms){
            setTimeout(doScroll, ms);
        });

        el.querySelectorAll('img').forEach(function(img){
            if(img.complete){
                return;
            }
            img.addEventListener('load', doScroll, {once: true});
            img.addEventListener('error', doScroll, {once: true});
        });

        if(typeof ResizeObserver === 'function'){
            let ticks = 0;
            const observer = new ResizeObserver(function(){
                doScroll();
                ticks += 1;
                if(ticks >= 4){
                    observer.disconnect();
                }
            });
            observer.observe(el);
            setTimeout(function(){ observer.disconnect(); }, 1200);
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
        return e.key === 'Enter' || e.code === 'Enter' || e.keyCode === 13;
    }

    function submitComposerForm(form){
        if(!form){ return; }
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if(submitBtn){
            submitBtn.click();
            return;
        }
        try{
            if(typeof form.requestSubmit === 'function'){
                form.requestSubmit();
                return;
            }
        }catch(err){}
        form.submit();
    }

    function bindTextareaGrow(textarea){
        if(!textarea){ return; }
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
        if(!textarea || !form){ return; }
        if(isMobileComposer()){
            textarea.setAttribute('enterkeyhint', 'enter');
        }
        textarea.addEventListener('keydown', function(e){
            if(!isEnterKey(e) || isMobileComposer() || e.shiftKey){ return; }
            e.preventDefault();
            const text = (textarea.value || '').trim();
            const imageInput = allowEmptyImage ? form.querySelector('input[type="file"]') : null;
            const hasImage = imageInput && imageInput.files && imageInput.files.length > 0;
            const hasPreview = !!form.querySelector('.supportAttachPreview:not([hidden])');
            const hasVoicePreview = form.dataset.pnvVoiceMode === 'preview';
            if(text === '' && !hasImage && !hasPreview && !hasVoicePreview){ return; }
            setTimeout(function(){ submitComposerForm(form); }, 0);
        });
    }

    function bindFormGuard(form, textarea, imageId){
        if(!form){ return; }
        form.addEventListener('submit', function(e){
            const text = (textarea?.value || '').trim();
            const image = imageId ? document.getElementById(imageId) : null;
            const hasFile = image && image.files && image.files.length > 0;
            const hasPreview = !!form.querySelector('.supportAttachPreview:not([hidden])');
            const hasVoicePreview = form.dataset.pnvVoiceMode === 'preview';
            if(text === '' && !hasFile && !hasPreview && !hasVoicePreview){
                e.preventDefault();
                alert('متن، تصویر یا پیام صوتی وارد کنید');
            }
        });
    }

    function ensureOverlay(){
        let root = document.getElementById('supportUiOverlay');
        if(root){ return root; }
        root = document.createElement('div');
        root.id = 'supportUiOverlay';
        root.innerHTML =
            '<div class="supportMsgMenuLayer" id="supportActionSheet" hidden>' +
            '<div class="supportMsgMenuBackdrop" data-close-menu></div>' +
            '<div class="supportContextMenu" id="supportContextMenu" role="menu"></div>' +
            '</div>' +
            '<div class="supportCopyToast" id="supportCopyToast" hidden aria-live="polite"></div>' +
            '<div class="supportMediaComposer" id="supportMediaComposer" hidden>' +
            '<div class="supportMediaStage"><img id="supportMediaImg" alt=""></div>' +
            '<div class="supportMediaFooter">' +
            '<input type="text" class="supportMediaCaption" id="supportMediaCaption" placeholder="نوشتن کپشن..." maxlength="2000" enterkeyhint="send">' +
            '<div class="supportMediaActions">' +
            '<button type="button" class="supportMediaBtn" id="supportMediaBack" aria-label="بازگشت">' +
            '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>' +
            '</button>' +
            '<button type="button" class="supportMediaBtn" id="supportMediaCrop" aria-label="برش">' +
            '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M6 2v4H2"/><path d="M18 22v-4h4"/><path d="M6 6h12v12H6z"/><path d="M2 6h4"/><path d="M18 18h4"/></svg>' +
            '</button>' +
            '<button type="button" class="supportMediaBtn supportMediaBtn--send" id="supportMediaSend" aria-label="ارسال">' +
            '<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>' +
            '</button>' +
            '</div></div></div>' +
            '<div class="supportCrop" id="supportCropModal" hidden>' +
            '<div class="supportCropCard">' +
            '<div class="supportCropTitle">برش تصویر</div>' +
            '<div class="supportCropStage"><canvas id="supportCropCanvas"></canvas></div>' +
            '<div class="supportCropActions">' +
            '<button type="button" class="supportCropBtn ghost" id="supportCropCancel">انصراف</button>' +
            '<button type="button" class="supportCropBtn" id="supportCropOk">تایید برش</button>' +
            '</div></div></div>';
        document.body.appendChild(root);
        return root;
    }

    function bindImageAttach(form, imageId, attachBtnId){
        if(!form){ return; }
        const input = document.getElementById(imageId);
        if(!input){ return; }

        ensureOverlay();
        const mediaComposer = document.getElementById('supportMediaComposer');
        const mediaImg = document.getElementById('supportMediaImg');
        const mediaCaption = document.getElementById('supportMediaCaption');
        const mediaBack = document.getElementById('supportMediaBack');
        const mediaCropBtn = document.getElementById('supportMediaCrop');
        const mediaSend = document.getElementById('supportMediaSend');
        const cropModal = document.getElementById('supportCropModal');
        const canvas = document.getElementById('supportCropCanvas');
        if(!mediaComposer || !mediaImg || !cropModal || !canvas){ return; }

        const ctx = canvas.getContext('2d');
        let sourceImg = null;
        let crop = {x:0,y:0,w:0,h:0};
        let drag = null;
        let pendingBlob = null;
        let pendingName = 'support-image.jpg';
        let pendingUrl = '';
        let submitting = false;
        const mainTextarea = form.querySelector('textarea');

        function revokeUrl(){
            if(pendingUrl){
                try{ URL.revokeObjectURL(pendingUrl); }catch(e){}
                pendingUrl = '';
            }
        }

        function closeMediaComposer(){
            mediaComposer.hidden = true;
            document.body.classList.remove('supportMediaOpen');
            revokeUrl();
            pendingBlob = null;
            pendingName = 'support-image.jpg';
            sourceImg = null;
            if(mediaCaption){ mediaCaption.value = ''; }
            mediaImg.removeAttribute('src');
            try{ input.value = ''; }catch(e){}
        }

        function openMediaComposer(file){
            revokeUrl();
            pendingBlob = file;
            pendingName = file.name || 'support-image.jpg';
            pendingUrl = URL.createObjectURL(file);
            mediaImg.src = pendingUrl;
            if(mediaCaption){
                mediaCaption.value = (mainTextarea && mainTextarea.value) ? mainTextarea.value : '';
            }
            mediaComposer.hidden = false;
            document.body.classList.add('supportMediaOpen');
        }

        function assignInputFile(file){
            try{
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                return !!(input.files && input.files.length);
            }catch(err){
                return false;
            }
        }

        function draw(){
            if(!sourceImg){ return; }
            const maxW = Math.min(360, window.innerWidth - 32);
            const scale = Math.min(1, maxW / sourceImg.width);
            canvas.width = Math.round(sourceImg.width * scale);
            canvas.height = Math.round(sourceImg.height * scale);
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.drawImage(sourceImg, 0, 0, canvas.width, canvas.height);
            ctx.fillStyle = 'rgba(0,0,0,.45)';
            ctx.fillRect(0,0,canvas.width,canvas.height);
            ctx.clearRect(crop.x, crop.y, crop.w, crop.h);
            ctx.drawImage(
                sourceImg,
                crop.x / scale, crop.y / scale, crop.w / scale, crop.h / scale,
                crop.x, crop.y, crop.w, crop.h
            );
            ctx.strokeStyle = '#22c55e';
            ctx.lineWidth = 2;
            ctx.strokeRect(crop.x, crop.y, crop.w, crop.h);
        }

        function openCropEditor(){
            if(!pendingBlob){ return; }
            const reader = new FileReader();
            reader.onload = function(){
                const img = new Image();
                img.onload = function(){
                    sourceImg = img;
                    const side = Math.min(img.width, img.height);
                    const maxW = Math.min(360, window.innerWidth - 32);
                    const scale = Math.min(1, maxW / img.width);
                    const cw = Math.round(img.width * scale);
                    const ch = Math.round(img.height * scale);
                    const sw = Math.round(side * scale);
                    crop = {
                        x: Math.round((cw - sw) / 2),
                        y: Math.round((ch - sw) / 2),
                        w: sw,
                        h: sw
                    };
                    cropModal.hidden = false;
                    draw();
                };
                img.onerror = function(){
                    alert('این تصویر قابل برش نیست.');
                };
                img.src = reader.result;
            };
            reader.readAsDataURL(pendingBlob);
        }

        function pointerPos(e){
            const rect = canvas.getBoundingClientRect();
            const pt = e.touches ? e.touches[0] : e;
            return {x: pt.clientX - rect.left, y: pt.clientY - rect.top};
        }

        function onDown(e){
            e.preventDefault();
            const p = pointerPos(e);
            drag = {x: p.x, y: p.y, cx: crop.x, cy: crop.y};
        }
        function onMove(e){
            if(!drag){ return; }
            e.preventDefault();
            const p = pointerPos(e);
            crop.x = Math.max(0, Math.min(canvas.width - crop.w, drag.cx + (p.x - drag.x)));
            crop.y = Math.max(0, Math.min(canvas.height - crop.h, drag.cy + (p.y - drag.y)));
            draw();
        }
        function onUp(){ drag = null; }

        canvas.addEventListener('mousedown', onDown);
        canvas.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
        canvas.addEventListener('touchstart', onDown, {passive:false});
        canvas.addEventListener('touchmove', onMove, {passive:false});
        canvas.addEventListener('touchend', onUp);

        document.getElementById('supportCropCancel').onclick = function(){
            cropModal.hidden = true;
            sourceImg = null;
        };

        document.getElementById('supportCropOk').onclick = function(){
            if(!sourceImg){ return; }
            const maxW = Math.min(360, window.innerWidth - 32);
            const scale = Math.min(1, maxW / sourceImg.width);
            const out = document.createElement('canvas');
            const size = Math.max(32, Math.round(crop.w / scale));
            out.width = size;
            out.height = size;
            out.getContext('2d').drawImage(
                sourceImg,
                crop.x / scale, crop.y / scale, crop.w / scale, crop.h / scale,
                0, 0, size, size
            );
            out.toBlob(function(blob){
                if(!blob){
                    alert('برش تصویر ناموفق بود');
                    return;
                }
                const file = new File([blob], 'support-crop.jpg', {type: 'image/jpeg'});
                pendingBlob = file;
                pendingName = 'support-crop.jpg';
                assignInputFile(file);
                revokeUrl();
                pendingUrl = URL.createObjectURL(file);
                mediaImg.src = pendingUrl;
                cropModal.hidden = true;
                sourceImg = null;
            }, 'image/jpeg', 0.88);
        };

        function submitMedia(){
            if(submitting || !pendingBlob){ return; }
            submitting = true;
            mediaSend.disabled = true;

            const caption = (mediaCaption ? mediaCaption.value : '').trim();
            if(mainTextarea){
                mainTextarea.value = caption;
            }

            const file = (pendingBlob instanceof File)
                ? pendingBlob
                : new File([pendingBlob], pendingName, {type: pendingBlob.type || 'image/jpeg'});
            assignInputFile(file);

            const fd = new FormData(form);
            fd.set('image', file, pendingName);
            fd.set('message', caption);

            const action = form.getAttribute('action') || window.location.href;
            fetch(action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                redirect: 'follow'
            }).then(function(res){
                if(res.redirected && res.url){
                    window.location.href = res.url;
                    return;
                }
                window.location.reload();
            }).catch(function(){
                submitting = false;
                mediaSend.disabled = false;
                alert('ارسال تصویر ناموفق بود');
            });
        }

        mediaBack.addEventListener('click', function(){
            if(!cropModal.hidden){
                cropModal.hidden = true;
                sourceImg = null;
                return;
            }
            closeMediaComposer();
        });

        mediaCropBtn.addEventListener('click', function(){
            openCropEditor();
        });

        mediaSend.addEventListener('click', function(){
            submitMedia();
        });

        if(mediaCaption){
            mediaCaption.addEventListener('keydown', function(e){
                if(e.key === 'Enter'){
                    e.preventDefault();
                    submitMedia();
                }
            });
        }

        const attachBtn = (attachBtnId && document.getElementById(attachBtnId))
            || form.querySelector('.msgIconBtn--attach');
        if(attachBtn){
            attachBtn.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                input.click();
            });
        }

        input.addEventListener('change', function(){
            const file = input.files && input.files[0];
            if(!file){ return; }
            if(!/^image\/(jpeg|jpg|png|webp)$/i.test(file.type) && !/\.(jpe?g|png|webp)$/i.test(file.name || '')){
                alert('فقط JPG، PNG یا WebP مجاز است');
                try{ input.value = ''; }catch(err){}
                return;
            }
            openMediaComposer(file);
        });

        // Normal text-only submit still works; block if media composer open
        form.addEventListener('submit', function(e){
            if(!mediaComposer.hidden || (cropModal && !cropModal.hidden)){
                e.preventDefault();
            }
        });
    }

    function getCsrf(form){
        const el = form ? form.querySelector('input[name="csrf"]') : null;
        return el ? el.value : '';
    }

    const MSG_MENU_ICONS = {
        reply: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 17l-5-5 5-5"/><path d="M4 12h12a4 4 0 0 1 4 4v1"/></svg>',
        edit: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
        copy: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        delete: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>'
    };

    function bindMessageActions(options){
        const chatEl = options.chatEl;
        const form = options.form;
        const role = options.role || 'user';
        if(!chatEl || !form){ return; }

        ensureOverlay();
        const layer = document.getElementById('supportActionSheet');
        const menuEl = document.getElementById('supportContextMenu');
        const copyToast = document.getElementById('supportCopyToast');
        let activeBubble = null;
        let copyToastTimer = null;

        let replyInput = form.querySelector('input[name="reply_to"]');
        if(!replyInput){
            replyInput = document.createElement('input');
            replyInput.type = 'hidden';
            replyInput.name = 'reply_to';
            form.appendChild(replyInput);
        }

        let editIdInput = form.querySelector('input[name="edit_id"]');
        if(!editIdInput){
            editIdInput = document.createElement('input');
            editIdInput.type = 'hidden';
            editIdInput.name = 'edit_id';
            form.appendChild(editIdInput);
        }

        let replyChip = form.querySelector('.supportReplyChip');
        if(!replyChip){
            replyChip = document.createElement('div');
            replyChip.className = 'supportReplyChip';
            replyChip.hidden = true;
            form.insertBefore(replyChip, form.firstChild);
        }

        let editChip = form.querySelector('.supportEditChip');
        if(!editChip){
            editChip = document.createElement('div');
            editChip.className = 'supportEditChip';
            editChip.hidden = true;
            form.insertBefore(editChip, replyChip);
        }

        const composerTextarea = form.querySelector('textarea');

        function closeMenu(){
            if(layer){ layer.hidden = true; }
            if(menuEl){ menuEl.innerHTML = ''; }
            if(activeBubble){
                activeBubble.classList.remove('is-menu-open');
                activeBubble = null;
            }
        }

        function showCopyToast(){
            if(!copyToast){ return; }
            copyToast.textContent = 'کپی شد';
            copyToast.hidden = false;
            if(copyToastTimer){ clearTimeout(copyToastTimer); }
            copyToastTimer = setTimeout(function(){
                copyToast.hidden = true;
            }, 1600);
        }

        function copyMessageText(text){
            const value = String(text || '');
            if(!value.trim()){ return; }
            function done(ok){
                if(ok){ showCopyToast(); }
            }
            if(navigator.clipboard && navigator.clipboard.writeText){
                navigator.clipboard.writeText(value).then(function(){ done(true); }).catch(fallback);
                return;
            }
            fallback();
            function fallback(){
                try{
                    const ta = document.createElement('textarea');
                    ta.value = value;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    const ok = document.execCommand('copy');
                    document.body.removeChild(ta);
                    done(ok);
                }catch(err){
                    done(false);
                }
            }
        }

        function clearEditMode(){
            editIdInput.value = '';
            editChip.hidden = true;
            editChip.innerHTML = '';
        }

        function setEditMode(msgId, text){
            clearEditMode();
            setReply('', '');
            if(!msgId){ return; }
            editIdInput.value = msgId;
            editChip.hidden = false;
            editChip.innerHTML =
                '<div><small>ویرایش پیام</small><div>'+escapeHtml(text || '')+'</div></div>' +
                '<button type="button" class="supportEditClear" aria-label="لغو ویرایش">×</button>';
            editChip.querySelector('.supportEditClear').onclick = function(){
                clearEditMode();
                if(composerTextarea){ composerTextarea.value = ''; }
            };
            if(composerTextarea){
                composerTextarea.value = text || '';
                composerTextarea.focus();
                composerTextarea.dispatchEvent(new Event('input', {bubbles: true}));
            }
        }

        function setReply(msgId, preview){
            clearEditMode();
            replyInput.value = msgId || '';
            if(!msgId){
                replyChip.hidden = true;
                replyChip.innerHTML = '';
                return;
            }
            replyChip.hidden = false;
            replyChip.innerHTML =
                '<div><small>در پاسخ به</small><div>'+escapeHtml(preview || '')+'</div></div>' +
                '<button type="button" class="supportReplyClear" aria-label="لغو پاسخ">×</button>';
            replyChip.querySelector('.supportReplyClear').onclick = function(){
                setReply('', '');
            };
        }

        form.addEventListener('submit', function(){
            if(!editIdInput.value){ return; }
            let editTextInput = form.querySelector('input[name="edit_text"]');
            if(!editTextInput){
                editTextInput = document.createElement('input');
                editTextInput.type = 'hidden';
                editTextInput.name = 'edit_text';
                form.appendChild(editTextInput);
            }
            editTextInput.value = composerTextarea ? composerTextarea.value.trim() : '';
            if(composerTextarea && composerTextarea.getAttribute('name')){
                composerTextarea.dataset.pnvComposerName = composerTextarea.getAttribute('name');
                composerTextarea.removeAttribute('name');
            }
        }, true);

        function positionMenu(bubble){
            if(!menuEl || !layer){ return; }
            const bubbleRect = bubble.getBoundingClientRect();
            const menuW = Math.max(menuEl.offsetWidth, menuEl.scrollWidth, 184);
            const menuH = Math.max(menuEl.offsetHeight, menuEl.scrollHeight, 36);
            const pad = 8;
            const gap = 6;
            let top = bubbleRect.top - menuH - gap;
            if(top < pad){
                top = bubbleRect.bottom + gap;
            }
            top = Math.max(pad, Math.min(top, window.innerHeight - menuH - pad));
            const isOwn = bubble.dataset.own === '1';
            let left = isOwn
                ? bubbleRect.right - menuW
                : bubbleRect.left;
            left = Math.max(pad, Math.min(left, window.innerWidth - menuW - pad));
            menuEl.style.top = top + 'px';
            menuEl.style.left = left + 'px';
        }

        function openMenu(bubble){
            const canEdit = bubble.dataset.canEdit === '1';
            const canDelete = bubble.dataset.canDelete === '1';
            const canReply = bubble.dataset.canReply === '1';
            const msgId = bubble.dataset.msgId || '';
            const text = bubble.dataset.text || '';
            const hasText = !!String(text).trim();
            const state = {canEdit: canEdit, canDelete: canDelete, canReply: canReply, hasText: hasText};
            const defs = [
                {key:'reply', label:'پاسخ', icon:'reply', show:function(s){ return s.canReply; }},
                {key:'edit', label:'ویرایش', icon:'edit', show:function(s){ return s.canEdit; }},
                {key:'copy', label:'کپی', icon:'copy', show:function(s){ return s.hasText; }},
                {key:'delete', label:'حذف', icon:'delete', danger:true, show:function(s){ return s.canDelete; }}
            ];
            const actions = defs.filter(function(d){ return d.show(state); });
            if(!actions.length){ return; }

            try{ if(navigator.vibrate){ navigator.vibrate(18); } }catch(err){}

            closeMenu();
            activeBubble = bubble;
            bubble.classList.add('is-menu-open');

            menuEl.innerHTML = actions.map(function(a){
                const cls = a.danger ? 'supportContextMenuItem is-danger' : 'supportContextMenuItem';
                const icon = MSG_MENU_ICONS[a.icon] || '';
                return '<button type="button" role="menuitem" data-act="'+a.key+'" class="'+cls+'">' +
                    '<span class="supportContextMenuIcon">'+icon+'</span>' +
                    '<span class="supportContextMenuLabel">'+a.label+'</span></button>';
            }).join('');

            layer.hidden = false;
            menuEl.style.top = '-9999px';
            menuEl.style.left = '-9999px';
            requestAnimationFrame(function(){
                positionMenu(bubble);
                requestAnimationFrame(function(){
                    positionMenu(bubble);
                });
            });

            menuEl.querySelectorAll('button[data-act]').forEach(function(btn){
                btn.onclick = function(e){
                    e.stopPropagation();
                    const act = btn.getAttribute('data-act');
                    closeMenu();
                    if(act === 'reply'){
                        setReply(msgId, text || 'پیام');
                        if(composerTextarea){ composerTextarea.focus(); }
                        return;
                    }
                    if(act === 'copy'){
                        copyMessageText(text);
                        return;
                    }
                    if(act === 'delete'){
                        if(!confirm('پیام حذف شود؟')){ return; }
                        const f = document.createElement('form');
                        f.method = 'POST';
                        f.innerHTML =
                            '<input type="hidden" name="csrf" value="'+escapeHtml(getCsrf(form))+'">' +
                            '<input type="hidden" name="delete_message" value="1">' +
                            '<input type="hidden" name="delete_id" value="'+escapeHtml(msgId)+'">' +
                            '<input type="hidden" name="user" value="'+escapeHtml((form.querySelector('input[name="user"]')||{}).value || '')+'">';
                        document.body.appendChild(f);
                        f.submit();
                        return;
                    }
                    if(act === 'edit'){
                        setEditMode(msgId, text);
                    }
                };
            });
        }

        function ensureMenuTrigger(bubble){
            if(!bubble || bubble.querySelector('.msgBubbleMenuTrigger')){ return; }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'msgBubbleMenuTrigger';
            btn.setAttribute('aria-label', 'عملیات پیام');
            btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>';
            bubble.appendChild(btn);
        }

        function decorateBubbles(root){
            (root || chatEl).querySelectorAll('.msgBubble').forEach(ensureMenuTrigger);
        }
        decorateBubbles(chatEl);
        if(typeof MutationObserver === 'function'){
            const observer = new MutationObserver(function(mutations){
                mutations.forEach(function(m){
                    m.addedNodes.forEach(function(node){
                        if(node.nodeType !== 1){ return; }
                        if(node.classList && node.classList.contains('msgBubble')){
                            ensureMenuTrigger(node);
                        }
                        else if(node.querySelectorAll){
                            decorateBubbles(node);
                        }
                    });
                });
            });
            observer.observe(chatEl, {childList: true, subtree: true});
        }

        let holdTimer = null;
        let holdStart = null;
        let holdBubble = null;

        function clearHold(){
            if(holdTimer){ clearTimeout(holdTimer); holdTimer = null; }
            if(holdBubble){ holdBubble.classList.remove('is-holding'); }
            holdStart = null;
            holdBubble = null;
        }

        chatEl.addEventListener('click', function(e){
            const trigger = e.target.closest('.msgBubbleMenuTrigger');
            if(!trigger || !chatEl.contains(trigger)){ return; }
            e.preventDefault();
            e.stopPropagation();
            const bubble = trigger.closest('.msgBubble');
            if(bubble){ openMenu(bubble); }
        });

        chatEl.addEventListener('contextmenu', function(e){
            const bubble = e.target.closest('.msgBubble');
            if(!bubble || !chatEl.contains(bubble)){ return; }
            if(e.target.closest('button:not(.msgBubbleMenuTrigger),textarea,input,a')){ return; }
            e.preventDefault();
            openMenu(bubble);
        });

        chatEl.addEventListener('touchstart', function(e){
            const bubble = e.target.closest('.msgBubble');
            if(!bubble || !chatEl.contains(bubble)){ return; }
            if(e.target.closest('button,textarea,input')){ return; }
            clearHold();
            const touch = e.touches && e.touches[0];
            holdStart = touch ? {x: touch.clientX, y: touch.clientY} : null;
            holdBubble = bubble;
            bubble.classList.add('is-holding');
            holdTimer = setTimeout(function(){
                const target = holdBubble;
                clearHold();
                if(target){ openMenu(target); }
            }, 420);
        }, {passive:true});

        chatEl.addEventListener('touchmove', function(e){
            if(!holdStart || !e.touches || !e.touches[0]){ return; }
            const dx = e.touches[0].clientX - holdStart.x;
            const dy = e.touches[0].clientY - holdStart.y;
            if((dx * dx + dy * dy) > 100){
                clearHold();
            }
        }, {passive:true});

        chatEl.addEventListener('touchend', clearHold);
        chatEl.addEventListener('touchcancel', clearHold);

        if(layer){
            layer.addEventListener('click', function(e){
                if(e.target.closest('[data-close-menu]') || e.target === layer){
                    closeMenu();
                }
            });
        }
        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape'){ closeMenu(); }
        });
        window.addEventListener('resize', closeMenu);
        window.addEventListener('scroll', closeMenu, true);
    }

    function buildMessageChecksHtml(msg, actionMeta){
        const meta = actionMeta || {};
        const sender = msg.sender || 'user';
        const ownSender = meta.ownSender || 'user';
        const isOwn = sender === ownSender;
        if(!isOwn){
            return '';
        }
        const isAdminView = !!meta.isAdmin;
        const seen = isAdminView ? !!msg.seen_by_user : !!msg.seen_by_admin;
        const cls = seen ? 'is-seen' : 'is-sent';
        const label = seen ? 'دیده شده' : 'ارسال شده';
        const marks = seen ? '✓✓' : '✓';
        return '<span class="msgChecks '+cls+'" aria-label="'+escapeHtml(label)+'">'+marks+'</span>';
    }

    function buildMessageMetaHtml(msg, actionMeta){
        return '<div class="msgMeta"><span class="msgTime">'+
            escapeHtml(msg.time || '')+
            '</span>'+buildMessageChecksHtml(msg, actionMeta)+'</div>';
    }

    function updateMessageStates(chatEl, messageStates, actionMeta){
        if(!chatEl || !messageStates || typeof messageStates !== 'object'){
            return;
        }
        const meta = actionMeta || {};
        const isAdminView = !!meta.isAdmin;
        const ownSender = meta.ownSender || 'user';

        Object.keys(messageStates).forEach(function(msgId){
            const st = messageStates[msgId];
            if(!st){
                return;
            }
            const bubble = chatEl.querySelector('[data-msg-id="'+msgId+'"]');
            if(!bubble || bubble.dataset.own !== '1'){
                return;
            }
            const sender = st.sender || bubble.dataset.sender || '';
            if(sender !== ownSender){
                return;
            }
            const seen = isAdminView ? !!st.seen_by_user : !!st.seen_by_admin;
            let checks = bubble.querySelector('.msgChecks');
            const msgMeta = bubble.querySelector('.msgMeta');
            if(!msgMeta){
                return;
            }
            if(!checks){
                checks = document.createElement('span');
                msgMeta.appendChild(checks);
            }
            checks.className = 'msgChecks '+(seen ? 'is-seen' : 'is-sent');
            checks.setAttribute('aria-label', seen ? 'دیده شده' : 'ارسال شده');
            checks.textContent = seen ? '✓✓' : '✓';
        });
    }

    function buildBubbleNode(msg, classMap, actionMeta){
        const sender = msg.sender || 'user';
        const roleCls = classMap[sender] || classMap.user || 'user';
        const typeCls = (sender === 'admin') ? 'is-admin admin' : 'is-user usermsg';
        const wrap = document.createElement('div');
        wrap.className = 'msgBubble msg ' + typeCls;
        wrap.dataset.msgId = msg.id || '';
        wrap.dataset.timestamp = msg.timestamp || 0;
        wrap.dataset.jalaliDate = msg.date || '';
        wrap.dataset.sender = sender;
        wrap.dataset.text = msg.text || '';

        const meta = actionMeta || {};
        const isAdminView = !!meta.isAdmin;
        const ownSender = meta.ownSender || 'user';
        const isOwn = sender === ownSender;
        let canEdit = false, canDelete = false, canReply = false;
        const age = Math.max(0, Math.floor(Date.now()/1000) - (msg.timestamp || 0));

        if(isAdminView){
            canEdit = true;
            canDelete = true;
            canReply = !isOwn;
        }else if(isOwn){
            canEdit = age <= 900;
            canDelete = age <= 300;
        }else{
            canReply = true;
        }

        wrap.dataset.own = isOwn ? '1' : '0';
        wrap.dataset.canEdit = canEdit ? '1' : '0';
        wrap.dataset.canDelete = canDelete ? '1' : '0';
        wrap.dataset.canReply = canReply ? '1' : '0';

        let html = '';
        if(msg.reply_to && msg.reply_to.text){
            html += '<div class="msgQuote"><strong>'+
                (msg.reply_to.sender === 'admin' ? 'پشتیبانی' : 'کاربر')+
                '</strong><span>'+escapeHtml(msg.reply_to.text)+'</span></div>';
        }
        if(msg.text){
            html += '<div class="msgText">'+escapeHtml(msg.text).replace(/\n/g, '<br>')+'</div>';
        }
        if(msg.edited){
            html += '<small class="msgEdited">(ویرایش شد)</small>';
        }
        if(msg.image){
            html += '<a class="msgImageLink" href="'+escapeHtml(msg.image)+'" target="_blank" rel="noopener"><img src="'+escapeHtml(msg.image)+'" alt=""></a>';
        }
        if(msg.audio){
            html += '<div class="pnv-voice-player" dir="ltr">'+
                '<button type="button" class="pnv-voice-play" aria-label="پخش پیام صوتی">'+
                '<span class="pnv-voice-play-icon" aria-hidden="true">▶</span></button>'+
                '<div class="pnv-voice-track" aria-hidden="true"><span class="pnv-voice-bars"></span></div>'+
                '<span class="pnv-voice-duration">0:00</span>'+
                '<audio class="pnv-voice-audio" preload="metadata" src="'+escapeHtml(msg.audio)+'"></audio></div>';
        }
        html += buildMessageMetaHtml(msg, actionMeta);
        wrap.innerHTML = html;
        const menuBtn = document.createElement('button');
        menuBtn.type = 'button';
        menuBtn.className = 'msgBubbleMenuTrigger';
        menuBtn.setAttribute('aria-label', 'عملیات پیام');
        menuBtn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>';
        wrap.appendChild(menuBtn);
        return wrap;
    }

    function bindMobileChatLayout(options){
        options = options || {};

        const vv = window.visualViewport;

        if(!vv){
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 768px)');

        const container = options.containerEl
            || document.querySelector('.content-support')
            || document.getElementById('supportPage');

        const messagesEl = options.messagesEl || document.getElementById('supportMessages');
        const textarea = options.textareaEl || document.getElementById('supportMessage');
        const chatTop = options.chatTopEl || document.getElementById('supportChatTop');

        if(!container){
            return;
        }

        let focusPending = false;
        let syncFrame = 0;

        function scrollMessagesToBottom(){
            scrollToBottom(messagesEl, true);
        }

        function isKeyboardOpen(){
            const layoutHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            const visibleHeight = vv.height || layoutHeight;
            const offsetTop = vv.offsetTop || 0;
            return focusPending
                || offsetTop > 0
                || (layoutHeight > 0 && visibleHeight < layoutHeight - 80);
        }

        function syncLayout(){
            if(syncFrame){
                cancelAnimationFrame(syncFrame);
            }

            syncFrame = requestAnimationFrame(function(){
                syncFrame = 0;

                if(!mobileQuery.matches){
                    resetLayout();
                    return;
                }

                const top = Math.max(0, vv.offsetTop || 0);
                const height = Math.max(0, vv.height || window.innerHeight);
                const keyboardOpen = isKeyboardOpen();

                container.style.position = 'fixed';
                container.style.left = '0';
                container.style.right = '0';
                container.style.width = '100%';
                container.style.top = top + 'px';
                container.style.height = height + 'px';
                container.style.maxHeight = height + 'px';
                container.style.bottom = 'auto';

                document.documentElement.classList.add('supportVvSync');
                document.body.classList.add('supportVvSync');

                document.body.classList.toggle('supportKeyboardOpen', keyboardOpen);

                if(chatTop){
                    chatTop.style.display = '';
                    chatTop.style.visibility = 'visible';
                }

                if(window.scrollY !== 0){
                    window.scrollTo(0, 0);
                }

                if(keyboardOpen){
                    scrollMessagesToBottom();
                }
            });
        }

        function resetLayout(){
            if(syncFrame){
                cancelAnimationFrame(syncFrame);
                syncFrame = 0;
            }

            container.style.position = '';
            container.style.left = '';
            container.style.right = '';
            container.style.width = '';
            container.style.top = '';
            container.style.height = '';
            container.style.maxHeight = '';
            container.style.bottom = '';

            document.documentElement.classList.remove('supportVvSync');
            document.body.classList.remove('supportVvSync');
            document.body.classList.remove('supportKeyboardOpen');
        }

        function onViewportChange(){
            syncLayout();
        }

        function onMobileChange(){
            if(mobileQuery.matches){
                syncLayout();
            }
            else{
                resetLayout();
            }
        }

        vv.addEventListener('resize', onViewportChange);
        vv.addEventListener('scroll', onViewportChange);
        window.addEventListener('resize', onViewportChange);
        window.addEventListener('orientationchange', function(){
            focusPending = false;
            setTimeout(onViewportChange, 120);
            setTimeout(onViewportChange, 320);
        });

        if(typeof mobileQuery.addEventListener === 'function'){
            mobileQuery.addEventListener('change', onMobileChange);
        }
        else if(typeof mobileQuery.addListener === 'function'){
            mobileQuery.addListener(onMobileChange);
        }

        if(textarea){
            textarea.addEventListener('touchstart', function(){
                focusPending = true;
                syncLayout();
            }, {passive: true});

            textarea.addEventListener('focus', function(){
                focusPending = true;
                window.scrollTo(0, 0);
                syncLayout();
                setTimeout(syncLayout, 50);
                setTimeout(syncLayout, 160);
                setTimeout(syncLayout, 320);
                setTimeout(scrollMessagesToBottom, 360);
            }, true);

            textarea.addEventListener('blur', function(){
                focusPending = false;
                setTimeout(function(){
                    if(document.activeElement !== textarea){
                        syncLayout();
                    }
                }, 120);
            });
        }

        if(mobileQuery.matches){
            syncLayout();
        }
    }

    function initPolling(options){
        const chatEl = options.chatEl;
        const pollUrl = options.pollUrl;
        const getParams = options.getParams || function(){ return ''; };
        const classMap = options.classMap || {admin:'admin',user:'user'};
        const interval = options.interval || 5000;
        const actionMeta = options.actionMeta || {};
        let lastPollTimestamp = options.since || 0;

        if(chatEl){
            chatEl.querySelectorAll('[data-timestamp]').forEach(function(node){
                const ts = parseInt(node.dataset.timestamp || '0', 10);
                if(ts > lastPollTimestamp){ lastPollTimestamp = ts; }
            });
        }

        async function poll(){
            if(!chatEl || !pollUrl){ return; }
            const url = pollUrl + getParams(lastPollTimestamp);
            try{
                const response = await fetch(url, {credentials: 'same-origin'});
                if(!response.ok){ return; }
                const payload = await response.json();
                let added = false;
                (payload.messages || []).forEach(function(msg){
                    if(chatEl.querySelector('[data-msg-id="' + msg.id + '"]')){ return; }
                    const empty = chatEl.querySelector('.msgEmpty');
                    if(empty){ empty.remove(); }
                    chatEl.appendChild(buildBubbleNode(msg, classMap, actionMeta));
                    lastPollTimestamp = Math.max(lastPollTimestamp, msg.timestamp || 0);
                    added = true;
                });
                updateMessageStates(chatEl, payload.message_states, actionMeta);
                if(added){
                    if(global.PNVSupportVoice && typeof global.PNVSupportVoice.bindPlayers === 'function'){
                        global.PNVSupportVoice.bindPlayers(chatEl);
                    }
                    if(global.PNVSupportShell && typeof global.PNVSupportShell.decorateMessages === 'function'){
                        const root = chatEl.closest('.pnv-chat') || chatEl;
                        global.PNVSupportShell.decorateMessages(chatEl, root);
                    }
                    scrollToBottom(chatEl, false);
                }
            }catch(e){}
        }

        setInterval(poll, interval);
        scrollToBottomOnOpen(chatEl);
    }

    global.SupportUI = {
        scrollToBottom: scrollToBottom,
        scrollToBottomOnOpen: scrollToBottomOnOpen,
        bindTextareaGrow: bindTextareaGrow,
        bindEnterToSend: bindEnterToSend,
        bindFormGuard: bindFormGuard,
        bindImageAttach: bindImageAttach,
        bindMessageActions: bindMessageActions,
        bindMobileChatLayout: bindMobileChatLayout,
        initPolling: initPolling,
        updateMessageStates: updateMessageStates,
        submitComposerForm: submitComposerForm
    };

})(window);
