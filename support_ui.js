(function(global){

    function scrollToBottom(el, force){
        if(!el){ return; }
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
            if(text === '' && !hasImage && !hasPreview){ return; }
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
            if(text === '' && !hasFile && !hasPreview){
                e.preventDefault();
                alert('متن یا تصویر وارد کنید');
            }
        });
    }

    function ensureOverlay(){
        let root = document.getElementById('supportUiOverlay');
        if(root){ return root; }
        root = document.createElement('div');
        root.id = 'supportUiOverlay';
        root.innerHTML =
            '<div class="supportSheet" id="supportActionSheet" hidden></div>' +
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
            '<div class="supportCropShell">' +
            '<div class="supportCropStage"><canvas id="supportCropCanvas"></canvas></div>' +
            '<div class="supportCropBar">' +
            '<button type="button" class="supportCropIconBtn" id="supportCropCancel" aria-label="رد">' +
            '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M18 6L6 18M6 6l12 12"/></svg>' +
            '</button>' +
            '<button type="button" class="supportCropIconBtn supportCropIconBtn--rotate" id="supportCropRotate" aria-label="چرخش">' +
            '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M4 7h7V4"/><path d="M20 17h-7v3"/><path d="M11 4a8 8 0 0 1 8 8"/><path d="M13 20a8 8 0 0 1-8-8"/><rect x="8" y="8" width="8" height="8" rx="1"/></svg>' +
            '</button>' +
            '<button type="button" class="supportCropIconBtn supportCropIconBtn--ok" id="supportCropOk" aria-label="تایید">' +
            '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M20 6L9 17l-5-5"/></svg>' +
            '</button>' +
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
        let displayScale = 1;
        let pendingBlob = null;
        let pendingName = 'support-image.jpg';
        let pendingUrl = '';
        let submitting = false;
        const mainTextarea = form.querySelector('textarea');
        const HANDLE = 16;
        const MIN_CROP = 40;

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

        function stageSize(){
            const stage = canvas.parentElement;
            const w = Math.max(200, (stage ? stage.clientWidth : window.innerWidth) - 8);
            const h = Math.max(200, (stage ? stage.clientHeight : window.innerHeight * 0.65) - 8);
            return {w: w, h: h};
        }

        function fitCanvas(){
            if(!sourceImg){ return; }
            const box = stageSize();
            displayScale = Math.min(box.w / sourceImg.width, box.h / sourceImg.height, 1);
            canvas.width = Math.max(1, Math.round(sourceImg.width * displayScale));
            canvas.height = Math.max(1, Math.round(sourceImg.height * displayScale));
        }

        function clampCrop(){
            crop.w = Math.max(MIN_CROP, Math.min(crop.w, canvas.width));
            crop.h = Math.max(MIN_CROP, Math.min(crop.h, canvas.height));
            crop.x = Math.max(0, Math.min(crop.x, canvas.width - crop.w));
            crop.y = Math.max(0, Math.min(crop.y, canvas.height - crop.h));
        }

        function initCropRect(){
            fitCanvas();
            const w = Math.round(canvas.width * 0.82);
            const h = Math.round(canvas.height * 0.62);
            crop = {
                x: Math.round((canvas.width - w) / 2),
                y: Math.round((canvas.height - h) / 2),
                w: w,
                h: h
            };
            clampCrop();
        }

        function drawHandle(x, y){
            ctx.fillStyle = '#22c55e';
            ctx.strokeStyle = '#052e16';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.rect(x - 6, y - 6, 12, 12);
            ctx.fill();
            ctx.stroke();
        }

        function draw(){
            if(!sourceImg){ return; }
            fitCanvas();
            clampCrop();
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.drawImage(sourceImg, 0, 0, canvas.width, canvas.height);
            ctx.fillStyle = 'rgba(0,0,0,.5)';
            ctx.fillRect(0,0,canvas.width,canvas.height);
            ctx.clearRect(crop.x, crop.y, crop.w, crop.h);
            ctx.drawImage(
                sourceImg,
                crop.x / displayScale, crop.y / displayScale, crop.w / displayScale, crop.h / displayScale,
                crop.x, crop.y, crop.w, crop.h
            );
            ctx.strokeStyle = '#22c55e';
            ctx.lineWidth = 2;
            ctx.strokeRect(crop.x + 1, crop.y + 1, crop.w - 2, crop.h - 2);

            // corner + edge handles
            drawHandle(crop.x, crop.y);
            drawHandle(crop.x + crop.w, crop.y);
            drawHandle(crop.x, crop.y + crop.h);
            drawHandle(crop.x + crop.w, crop.y + crop.h);
            drawHandle(crop.x + crop.w / 2, crop.y);
            drawHandle(crop.x + crop.w / 2, crop.y + crop.h);
            drawHandle(crop.x, crop.y + crop.h / 2);
            drawHandle(crop.x + crop.w, crop.y + crop.h / 2);
        }

        function hitTest(p){
            const near = function(a, b){ return Math.abs(a - b) <= HANDLE; };
            const inside =
                p.x >= crop.x - HANDLE && p.x <= crop.x + crop.w + HANDLE &&
                p.y >= crop.y - HANDLE && p.y <= crop.y + crop.h + HANDLE;
            if(!inside){ return null; }

            const onL = near(p.x, crop.x);
            const onR = near(p.x, crop.x + crop.w);
            const onT = near(p.y, crop.y);
            const onB = near(p.y, crop.y + crop.h);

            if(onT && onL){ return 'nw'; }
            if(onT && onR){ return 'ne'; }
            if(onB && onL){ return 'sw'; }
            if(onB && onR){ return 'se'; }
            if(onT){ return 'n'; }
            if(onB){ return 's'; }
            if(onL){ return 'w'; }
            if(onR){ return 'e'; }
            if(
                p.x >= crop.x && p.x <= crop.x + crop.w &&
                p.y >= crop.y && p.y <= crop.y + crop.h
            ){
                return 'move';
            }
            return null;
        }

        function openCropEditor(){
            if(!pendingBlob){ return; }
            const reader = new FileReader();
            reader.onload = function(){
                const img = new Image();
                img.onload = function(){
                    sourceImg = img;
                    cropModal.hidden = false;
                    requestAnimationFrame(function(){
                        initCropRect();
                        draw();
                    });
                };
                img.onerror = function(){
                    alert('این تصویر قابل برش نیست.');
                };
                img.src = reader.result;
            };
            reader.readAsDataURL(pendingBlob);
        }

        function rotateSource90(){
            if(!sourceImg){ return; }
            const srcW = sourceImg.width;
            const srcH = sourceImg.height;
            const c = document.createElement('canvas');
            c.width = srcH;
            c.height = srcW;
            const x = c.getContext('2d');
            x.translate(srcH, 0);
            x.rotate(Math.PI / 2);
            x.drawImage(sourceImg, 0, 0);
            const img = new Image();
            img.onload = function(){
                sourceImg = img;
                initCropRect();
                draw();
            };
            img.src = c.toDataURL('image/jpeg', 0.92);
        }

        function pointerPos(e){
            const rect = canvas.getBoundingClientRect();
            const pt = e.touches ? e.touches[0] : e;
            const scaleX = canvas.width / Math.max(1, rect.width);
            const scaleY = canvas.height / Math.max(1, rect.height);
            return {
                x: (pt.clientX - rect.left) * scaleX,
                y: (pt.clientY - rect.top) * scaleY
            };
        }

        function onDown(e){
            e.preventDefault();
            const p = pointerPos(e);
            const mode = hitTest(p);
            if(!mode){ return; }
            drag = {
                mode: mode,
                x: p.x,
                y: p.y,
                cx: crop.x,
                cy: crop.y,
                cw: crop.w,
                ch: crop.h
            };
        }

        function onMove(e){
            if(!drag){ return; }
            e.preventDefault();
            const p = pointerPos(e);
            const dx = p.x - drag.x;
            const dy = p.y - drag.y;
            let x = drag.cx;
            let y = drag.cy;
            let w = drag.cw;
            let h = drag.ch;
            const mode = drag.mode;

            if(mode === 'move'){
                x = drag.cx + dx;
                y = drag.cy + dy;
            }else{
                if(mode.indexOf('e') >= 0){ w = drag.cw + dx; }
                if(mode.indexOf('s') >= 0){ h = drag.ch + dy; }
                if(mode.indexOf('w') >= 0){
                    x = drag.cx + dx;
                    w = drag.cw - dx;
                }
                if(mode.indexOf('n') >= 0){
                    y = drag.cy + dy;
                    h = drag.ch - dy;
                }
            }

            if(w < MIN_CROP){
                if(mode.indexOf('w') >= 0){ x = drag.cx + drag.cw - MIN_CROP; }
                w = MIN_CROP;
            }
            if(h < MIN_CROP){
                if(mode.indexOf('n') >= 0){ y = drag.cy + drag.ch - MIN_CROP; }
                h = MIN_CROP;
            }

            crop = {x: x, y: y, w: w, h: h};
            clampCrop();
            draw();
        }

        function onUp(){ drag = null; }

        canvas.addEventListener('mousedown', onDown);
        canvas.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
        canvas.addEventListener('touchstart', onDown, {passive:false});
        canvas.addEventListener('touchmove', onMove, {passive:false});
        canvas.addEventListener('touchend', onUp);
        window.addEventListener('resize', function(){
            if(cropModal.hidden || !sourceImg){ return; }
            draw();
        });

        document.getElementById('supportCropCancel').onclick = function(){
            cropModal.hidden = true;
            sourceImg = null;
            drag = null;
        };

        document.getElementById('supportCropRotate').onclick = function(){
            rotateSource90();
        };

        document.getElementById('supportCropOk').onclick = function(){
            if(!sourceImg){ return; }
            fitCanvas();
            clampCrop();
            const outW = Math.max(32, Math.round(crop.w / displayScale));
            const outH = Math.max(32, Math.round(crop.h / displayScale));
            const out = document.createElement('canvas');
            out.width = outW;
            out.height = outH;
            out.getContext('2d').drawImage(
                sourceImg,
                crop.x / displayScale, crop.y / displayScale, crop.w / displayScale, crop.h / displayScale,
                0, 0, outW, outH
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
                drag = null;
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

    function bindMessageActions(options){
        const chatEl = options.chatEl;
        const form = options.form;
        const role = options.role || 'user';
        if(!chatEl || !form){ return; }

        ensureOverlay();
        const sheet = document.getElementById('supportActionSheet');
        let replyInput = form.querySelector('input[name="reply_to"]');
        if(!replyInput){
            replyInput = document.createElement('input');
            replyInput.type = 'hidden';
            replyInput.name = 'reply_to';
            form.appendChild(replyInput);
        }

        let replyChip = form.querySelector('.supportReplyChip');
        if(!replyChip){
            replyChip = document.createElement('div');
            replyChip.className = 'supportReplyChip';
            replyChip.hidden = true;
            form.insertBefore(replyChip, form.firstChild);
        }

        function closeSheet(){
            sheet.hidden = true;
            sheet.innerHTML = '';
        }

        function setReply(msgId, preview){
            replyInput.value = msgId || '';
            if(!msgId){
                replyChip.hidden = true;
                replyChip.innerHTML = '';
                return;
            }
            replyChip.hidden = false;
            replyChip.innerHTML =
                '<div><small>در پاسخ به</small><div>'+escapeHtml(preview || '')+'</div></div>' +
                '<button type="button" class="supportReplyClear">×</button>';
            replyChip.querySelector('.supportReplyClear').onclick = function(){
                setReply('', '');
            };
        }

        function openSheet(bubble){
            const canEdit = bubble.dataset.canEdit === '1';
            const canDelete = bubble.dataset.canDelete === '1';
            const canReply = bubble.dataset.canReply === '1';
            const isOwn = bubble.dataset.own === '1';
            const msgId = bubble.dataset.msgId || '';
            const text = bubble.dataset.text || '';
            const actions = [];

            if(canReply){ actions.push({key:'reply', label:'پاسخ'}); }
            if(canEdit){ actions.push({key:'edit', label:'ویرایش'}); }
            if(canDelete){ actions.push({key:'delete', label:'حذف', danger:true}); }

            if(!actions.length){
                if(isOwn && role !== 'admin'){
                    actions.push({
                        key:'info',
                        label:'امکان حذف و ویرایش پیام های قدیمی وجود ندارد',
                        disabled:true
                    });
                }else{
                    return;
                }
            }

            try{ if(navigator.vibrate){ navigator.vibrate(18); } }catch(err){}

            sheet.innerHTML =
                '<div class="supportSheetCard">' +
                actions.map(function(a){
                    const cls = [
                        a.danger ? 'danger' : '',
                        a.disabled ? 'is-disabled' : ''
                    ].filter(Boolean).join(' ');
                    return '<button type="button" data-act="'+a.key+'" class="'+cls+'"'+(a.disabled?' disabled':'')+'>'+a.label+'</button>';
                }).join('') +
                '<button type="button" data-act="cancel" class="ghost">انصراف</button>' +
                '</div>';
            sheet.hidden = false;

            sheet.querySelectorAll('button[data-act]').forEach(function(btn){
                btn.onclick = function(){
                    const act = btn.getAttribute('data-act');
                    closeSheet();
                    if(act === 'cancel' || act === 'info'){ return; }
                    if(act === 'reply'){
                        setReply(msgId, text || 'پیام');
                        const ta = form.querySelector('textarea');
                        if(ta){ ta.focus(); }
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
                        const next = window.prompt('متن جدید پیام:', text);
                        if(next === null){ return; }
                        const f = document.createElement('form');
                        f.method = 'POST';
                        f.innerHTML =
                            '<input type="hidden" name="csrf" value="'+escapeHtml(getCsrf(form))+'">' +
                            '<input type="hidden" name="edit_id" value="'+escapeHtml(msgId)+'">' +
                            '<input type="hidden" name="user" value="'+escapeHtml((form.querySelector('input[name="user"]')||{}).value || '')+'">' +
                            '<input type="hidden" name="edit_text" value="">';
                        f.querySelector('input[name="edit_text"]').value = next;
                        document.body.appendChild(f);
                        f.submit();
                    }
                };
            });
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

        chatEl.addEventListener('contextmenu', function(e){
            const bubble = e.target.closest('.msgBubble');
            if(!bubble || !chatEl.contains(bubble)){ return; }
            if(e.target.closest('button,textarea,input')){ return; }
            e.preventDefault();
            openSheet(bubble);
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
                if(target){ openSheet(target); }
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
        sheet.addEventListener('click', function(e){
            if(e.target === sheet){ closeSheet(); }
        });
    }

    function buildBubbleNode(msg, classMap, actionMeta){
        const sender = msg.sender || 'user';
        let cls = classMap[sender] || classMap.user || 'user';
        const wrap = document.createElement('div');
        wrap.className = 'msgBubble ' + cls + ' msg ' + cls;
        wrap.dataset.msgId = msg.id || '';
        wrap.dataset.timestamp = msg.timestamp || 0;
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
        html += '<div class="msgMeta">'+
            escapeHtml(msg.time || '') + ' - ' + escapeHtml(msg.date || '') +
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
                if(added){ scrollToBottom(chatEl, false); }
            }catch(e){}
        }

        setInterval(poll, interval);
        scrollToBottom(chatEl, true);
    }

    global.SupportUI = {
        scrollToBottom: scrollToBottom,
        bindTextareaGrow: bindTextareaGrow,
        bindEnterToSend: bindEnterToSend,
        bindFormGuard: bindFormGuard,
        bindImageAttach: bindImageAttach,
        bindMessageActions: bindMessageActions,
        initPolling: initPolling,
        submitComposerForm: submitComposerForm
    };

})(window);
