(function (global) {
  'use strict';

  var MIN_CROP = 56;
  var HANDLE_HIT = 40;
  var CORNER_VIS = 22;

  function createMediaSessionId() {
    return String(Date.now()) + '-' + Math.random().toString(36).slice(2, 10);
  }

  function isMediaHistoryState(state, sessionId) {
    return (
      !!state &&
      state.pnvSupportMedia === true &&
      !!sessionId &&
      state.session === sessionId
    );
  }

  function ensureMediaRoot() {
    var root = document.getElementById('pnvSupportMediaRoot');
    if (root) {
      return root;
    }
    root = document.createElement('div');
    root.id = 'pnvSupportMediaRoot';
    root.innerHTML =
      '<div class="pnv-media-overlay pnv-media-gallery" id="pnvMediaGallery" hidden>' +
      '<header class="pnv-media-top">' +
      '<button type="button" class="pnv-media-action pnv-media-back" id="pnvMediaGalleryBack" aria-label="بازگشت">' +
      '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>' +
      '<span class="pnv-media-title" id="pnvMediaGalleryTitle">گالری</span>' +
      '<span class="pnv-media-top-spacer" aria-hidden="true"></span>' +
      '</header>' +
      '<div class="pnv-media-gallery-scroll" id="pnvMediaGalleryGrid"></div>' +
      '<p class="pnv-media-gallery-hint" id="pnvMediaGalleryHint">برای انتخاب تصویر، از گالری دستگاه یک عکس برگزینید.</p>' +
      '</div>' +
      '<div class="pnv-media-overlay pnv-media-editor" id="pnvMediaEditor" hidden>' +
      '<div class="pnv-media-crop-stage" id="pnvMediaCropStage">' +
      '<canvas id="pnvMediaCropCanvas" aria-hidden="true"></canvas>' +
      '</div>' +
      '<footer class="pnv-media-bottom pnv-media-bottom--edit">' +
      '<button type="button" class="pnv-media-action" id="pnvMediaEditCancel" aria-label="لغو">' +
      '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>' +
      '<button type="button" class="pnv-media-action" id="pnvMediaEditRotate" aria-label="چرخش">' +
      '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2">' +
      '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></button>' +
      '<button type="button" class="pnv-media-action" id="pnvMediaEditApply" aria-label="تأیید">' +
      '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6L9 17l-5-5"/></svg></button>' +
      '</footer></div>' +
      '<div class="pnv-media-overlay pnv-media-preview" id="pnvMediaPreview" hidden>' +
      '<header class="pnv-media-top pnv-media-top--preview">' +
      '<span class="pnv-media-title" id="pnvMediaPreviewTitle">پشتیبانی</span>' +
      '</header>' +
      '<div class="pnv-media-preview-stage">' +
      '<img id="pnvMediaPreviewImg" alt="">' +
      '</div>' +
      '<div class="pnv-media-preview-bottom">' +
      '<div class="pnv-media-caption-wrap">' +
      '<textarea class="pnv-media-caption" id="pnvMediaCaption" dir="rtl" rows="1" maxlength="2000" placeholder="افزودن توضیح..." enterkeyhint="send"></textarea>' +
      '</div>' +
      '<p class="pnv-media-error" id="pnvMediaError" hidden role="alert"></p>' +
      '<footer class="pnv-media-bottom pnv-media-bottom--preview">' +
      '<button type="button" class="pnv-media-action" id="pnvMediaPreviewBack" aria-label="بازگشت">' +
      '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>' +
      '<button type="button" class="pnv-media-action" id="pnvMediaPreviewEdit" aria-label="ویرایش">' +
      '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">' +
      '<path d="M6 2v4H2"/><path d="M18 22v-4h4"/><path d="M6 6h12v12H6z"/></svg></button>' +
      '<button type="button" class="pnv-media-action pnv-media-action--send" id="pnvMediaSend" aria-label="ارسال">' +
      '<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg></button>' +
      '</footer></div></div>';
    document.body.appendChild(root);
    return root;
  }

  function bindTelegramMediaComposer(cfg) {
    cfg = cfg || {};
    var form = cfg.form;
    if (!form) {
      return;
    }
    var imageInputId = cfg.imageInputId;
    var input = imageInputId ? document.getElementById(imageInputId) : null;
    if (!input) {
      return;
    }

    var ajaxPost = cfg.ajaxPost;
    var focusComposer = cfg.focusComposer || function () {};
    var previewTitle = cfg.previewTitle || 'پشتیبانی';
    var mainTextarea = cfg.textarea || form.querySelector('textarea');

    ensureMediaRoot();

    var galleryEl = document.getElementById('pnvMediaGallery');
    var galleryGrid = document.getElementById('pnvMediaGalleryGrid');
    var galleryHint = document.getElementById('pnvMediaGalleryHint');
    var galleryBack = document.getElementById('pnvMediaGalleryBack');
    var editorEl = document.getElementById('pnvMediaEditor');
    var cropCanvas = document.getElementById('pnvMediaCropCanvas');
    var editCancel = document.getElementById('pnvMediaEditCancel');
    var editRotate = document.getElementById('pnvMediaEditRotate');
    var editApply = document.getElementById('pnvMediaEditApply');
    var previewEl = document.getElementById('pnvMediaPreview');
    var previewImg = document.getElementById('pnvMediaPreviewImg');
    var previewTitleEl = document.getElementById('pnvMediaPreviewTitle');
    var captionEl = document.getElementById('pnvMediaCaption');
    var errorEl = document.getElementById('pnvMediaError');
    var previewBack = document.getElementById('pnvMediaPreviewBack');
    var previewEdit = document.getElementById('pnvMediaPreviewEdit');
    var sendBtn = document.getElementById('pnvMediaSend');

    if (!cropCanvas || !galleryEl || !editorEl || !previewEl) {
      return;
    }

    var ctx = cropCanvas.getContext('2d');
    var view = null;
    var mediaOpen = false;
    var mediaSessionId = null;
    var sessionHistoryDepth = 0;
    var finishingSession = false;
    var submitting = false;

    var originalBlob = null;
    var originalObjectUrl = '';
    var rotationDegrees = 0;
    var cropRect = { x: 0, y: 0, w: 0, h: 0 };
    var cropDisplayScale = 1;
    var editSourceImage = null;
    var drag = null;
    var resizeMode = null;

    var finalBlob = null;
    var finalPreviewUrl = '';
    var pendingName = 'image.jpg';

    var galleryItems = [];
    var galleryPickerOpenedAt = 0;

    var galleryPicker = form.querySelector('input[data-pnv-gallery-picker]');
    if (!galleryPicker) {
      galleryPicker = document.createElement('input');
      galleryPicker.type = 'file';
      galleryPicker.accept = 'image/jpeg,image/png,image/webp,image/*,.jpg,.jpeg,.png,.webp';
      galleryPicker.multiple = true;
      galleryPicker.className = 'msgFileInput';
      galleryPicker.setAttribute('data-pnv-gallery-picker', '1');
      galleryPicker.hidden = true;
      form.appendChild(galleryPicker);
    }

    function revokeUrl(urlRef) {
      if (urlRef && urlRef.url) {
        try {
          URL.revokeObjectURL(urlRef.url);
        } catch (e) {}
        urlRef.url = '';
      }
    }

    function setBodyOpen(open) {
      document.body.classList.toggle('pnv-media-open', !!open);
      document.body.classList.toggle('supportMediaOpen', !!open);
    }

    function pushMediaHistory(step) {
      if (!mediaSessionId || finishingSession) {
        return;
      }
      sessionHistoryDepth += 1;
      history.pushState(
        {
          pnvSupportMedia: true,
          session: mediaSessionId,
          step: step
        },
        '',
        window.location.href
      );
    }

    function beginMediaSession() {
      mediaSessionId = createMediaSessionId();
      sessionHistoryDepth = 0;
      mediaOpen = true;
    }

    function renderMediaStep(step) {
      if (step === 'gallery') {
        showView('gallery');
        return;
      }
      if (step === 'edit') {
        showView('edit');
        drawEditCanvas();
        return;
      }
      if (step === 'preview') {
        showView('preview');
      }
    }

    function hideError() {
      if (errorEl) {
        errorEl.hidden = true;
        errorEl.textContent = '';
      }
    }

    function showError(msg) {
      if (!errorEl) {
        return;
      }
      errorEl.textContent = msg || 'ارسال ناموفق بود';
      errorEl.hidden = false;
    }

    function revokeFinalUrl() {
      if (finalPreviewUrl) {
        try {
          URL.revokeObjectURL(finalPreviewUrl);
        } catch (e) {}
        finalPreviewUrl = '';
      }
    }

    function revokeOriginalUrl() {
      if (originalObjectUrl) {
        try {
          URL.revokeObjectURL(originalObjectUrl);
        } catch (e) {}
        originalObjectUrl = '';
      }
    }

    function clearGalleryItems() {
      galleryItems.forEach(function (item) {
        if (item.url) {
          try {
            URL.revokeObjectURL(item.url);
          } catch (e) {}
        }
      });
      galleryItems = [];
      if (galleryGrid) {
        galleryGrid.innerHTML = '';
      }
    }

    function resetMediaData() {
      hideError();
      clearGalleryItems();
      revokeOriginalUrl();
      revokeFinalUrl();
      originalBlob = null;
      finalBlob = null;
      rotationDegrees = 0;
      editSourceImage = null;
      drag = null;
      resizeMode = null;
      cropRect = { x: 0, y: 0, w: 0, h: 0 };
      if (captionEl) {
        captionEl.value = '';
      }
      if (previewImg) {
        previewImg.removeAttribute('src');
      }
      try {
        input.value = '';
      } catch (e) {}
      try {
        galleryPicker.value = '';
      } catch (e2) {}
    }

    function closeMediaUi() {
      view = null;
      galleryEl.hidden = true;
      editorEl.hidden = true;
      previewEl.hidden = true;
      setBodyOpen(false);
    }

    function closeMediaWithoutHistoryMutation() {
      resetMediaData();
      closeMediaUi();
      mediaOpen = false;
      mediaSessionId = null;
      sessionHistoryDepth = 0;
      finishingSession = false;
    }

    function finishMediaSession() {
      var depth = sessionHistoryDepth;
      resetMediaData();
      closeMediaUi();
      mediaOpen = false;
      var endedSession = mediaSessionId;
      mediaSessionId = null;
      sessionHistoryDepth = 0;
      if (depth > 0) {
        finishingSession = true;
        history.go(-depth);
      }
      if (typeof global.__pnvMediaHistoryDebug === 'function') {
        global.__pnvMediaHistoryDebug('finish', { depth: depth, session: endedSession });
      }
    }

    function resetAll() {
      finishMediaSession();
    }

    function showView(next) {
      view = next;
      mediaOpen = !!next;
      galleryEl.hidden = next !== 'gallery';
      editorEl.hidden = next !== 'edit';
      previewEl.hidden = next !== 'preview';
      setBodyOpen(!!next);
      if (previewTitleEl) {
        previewTitleEl.textContent = previewTitle;
      }
    }

    function validImageFile(file) {
      if (!file) {
        return false;
      }
      if (/^image\/(jpeg|jpg|png|webp)$/i.test(file.type)) {
        return true;
      }
      return /\.(jpe?g|png|webp)$/i.test(file.name || '');
    }

    function assignInputFile(file) {
      try {
        var dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        return !!(input.files && input.files.length);
      } catch (err) {
        return false;
      }
    }

    function loadImageFromBlob(blob) {
      return new Promise(function (resolve, reject) {
        var url = URL.createObjectURL(blob);
        var img = new Image();
        img.onload = function () {
          resolve({ img: img, url: url });
        };
        img.onerror = function () {
          URL.revokeObjectURL(url);
          reject(new Error('load failed'));
        };
        img.src = url;
      });
    }

    function buildRotatedCanvas(img, degrees) {
      var w = img.naturalWidth || img.width;
      var h = img.naturalHeight || img.height;
      var swap = degrees % 180 !== 0;
      var canvas = document.createElement('canvas');
      canvas.width = swap ? h : w;
      canvas.height = swap ? w : h;
      var c = canvas.getContext('2d');
      c.translate(canvas.width / 2, canvas.height / 2);
      c.rotate((degrees * Math.PI) / 180);
      c.drawImage(img, -w / 2, -h / 2);
      return canvas;
    }

    function defaultCropRect(cw, ch) {
      var pad = Math.max(8, Math.round(Math.min(cw, ch) * 0.03));
      return {
        x: pad,
        y: pad,
        w: Math.max(MIN_CROP, cw - pad * 2),
        h: Math.max(MIN_CROP, ch - pad * 2)
      };
    }

    function clampCrop() {
      cropRect.w = Math.max(MIN_CROP, Math.min(cropRect.w, cropCanvas.width));
      cropRect.h = Math.max(MIN_CROP, Math.min(cropRect.h, cropCanvas.height));
      cropRect.x = Math.max(0, Math.min(cropRect.x, cropCanvas.width - cropRect.w));
      cropRect.y = Math.max(0, Math.min(cropRect.y, cropCanvas.height - cropRect.h));
    }

    function drawCropCorner(x, y, dirX, dirY) {
      var len = CORNER_VIS;
      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.lineTo(x + dirX * len, y);
      ctx.moveTo(x, y);
      ctx.lineTo(x, y + dirY * len);
      ctx.stroke();
    }

    function drawCropHandles() {
      var x = cropRect.x + 0.5;
      var y = cropRect.y + 0.5;
      var w = cropRect.w - 1;
      var h = cropRect.h - 1;
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 2.5;
      ctx.lineCap = 'square';
      drawCropCorner(x, y, 1, 1);
      drawCropCorner(x + w, y, -1, 1);
      drawCropCorner(x, y + h, 1, -1);
      drawCropCorner(x + w, y + h, -1, -1);
    }

    function drawEditCanvas() {
      if (!editSourceImage) {
        return;
      }
      var img = editSourceImage;
      var maxW = window.innerWidth;
      var maxH = window.innerHeight - 72 - (parseInt(getComputedStyle(document.documentElement).getPropertyValue('--pnv-media-bottom-h'), 10) || 72);
      var scale = Math.min(1, maxW / img.width, maxH / img.height);
      cropDisplayScale = scale;
      cropCanvas.width = Math.max(1, Math.round(img.width * scale));
      cropCanvas.height = Math.max(1, Math.round(img.height * scale));
      if (cropRect.w <= 0 || cropRect.h <= 0) {
        cropRect = defaultCropRect(cropCanvas.width, cropCanvas.height);
      } else {
        cropRect.x = Math.round(cropRect.x);
        cropRect.y = Math.round(cropRect.y);
        cropRect.w = Math.round(cropRect.w);
        cropRect.h = Math.round(cropRect.h);
        clampCrop();
      }
      ctx.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
      ctx.drawImage(img, 0, 0, cropCanvas.width, cropCanvas.height);
      ctx.fillStyle = 'rgba(0,0,0,.62)';
      ctx.fillRect(0, 0, cropCanvas.width, cropCanvas.height);
      ctx.clearRect(cropRect.x, cropRect.y, cropRect.w, cropRect.h);
      ctx.drawImage(
        img,
        cropRect.x / scale,
        cropRect.y / scale,
        cropRect.w / scale,
        cropRect.h / scale,
        cropRect.x,
        cropRect.y,
        cropRect.w,
        cropRect.h
      );
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 1.5;
      ctx.strokeRect(cropRect.x + 0.5, cropRect.y + 0.5, cropRect.w - 1, cropRect.h - 1);
      drawCropHandles();
    }

    function refreshEditSource() {
      if (!originalBlob || !originalObjectUrl) {
        return Promise.resolve();
      }
      return loadImageFromBlob(originalBlob).then(function (loaded) {
        var rotated = buildRotatedCanvas(loaded.img, rotationDegrees);
        URL.revokeObjectURL(loaded.url);
        editSourceImage = rotated;
        cropRect = { x: 0, y: 0, w: 0, h: 0 };
        drawEditCanvas();
      });
    }

    function pointerPos(e) {
      var rect = cropCanvas.getBoundingClientRect();
      var pt = e.touches ? e.touches[0] : e;
      return { x: pt.clientX - rect.left, y: pt.clientY - rect.top };
    }

    function pointInHitBox(px, py, cx, cy) {
      var half = HANDLE_HIT / 2;
      return px >= cx - half && px <= cx + half && py >= cy - half && py <= cy + half;
    }

    function hitTestHandle(p) {
      var cr = cropRect;
      var cx = cr.x + cr.w / 2;
      var cy = cr.y + cr.h / 2;
      var targets = [
        { id: 'tl', x: cr.x, y: cr.y },
        { id: 'tr', x: cr.x + cr.w, y: cr.y },
        { id: 'bl', x: cr.x, y: cr.y + cr.h },
        { id: 'br', x: cr.x + cr.w, y: cr.y + cr.h },
        { id: 't', x: cx, y: cr.y },
        { id: 'b', x: cx, y: cr.y + cr.h },
        { id: 'l', x: cr.x, y: cy },
        { id: 'r', x: cr.x + cr.w, y: cy }
      ];
      for (var i = 0; i < targets.length; i++) {
        var t = targets[i];
        if (pointInHitBox(p.x, p.y, t.x, t.y)) {
          return t.id;
        }
      }
      if (
        p.x >= cr.x &&
        p.x <= cr.x + cr.w &&
        p.y >= cr.y &&
        p.y <= cr.y + cr.h
      ) {
        return 'move';
      }
      return null;
    }

    function onCropDown(e) {
      e.preventDefault();
      var p = pointerPos(e);
      resizeMode = hitTestHandle(p);
      drag = { x: p.x, y: p.y, cx: cropRect.x, cy: cropRect.y, cw: cropRect.w, ch: cropRect.h };
    }

    function onCropMove(e) {
      if (!drag) {
        return;
      }
      e.preventDefault();
      var p = pointerPos(e);
      var dx = p.x - drag.x;
      var dy = p.y - drag.y;
      if (resizeMode === 'move') {
        cropRect.x = Math.max(0, Math.min(cropCanvas.width - cropRect.w, drag.cx + dx));
        cropRect.y = Math.max(0, Math.min(cropCanvas.height - cropRect.h, drag.cy + dy));
      } else if (resizeMode === 'br') {
        cropRect.w = Math.max(MIN_CROP, drag.cw + dx);
        cropRect.h = Math.max(MIN_CROP, drag.ch + dy);
      } else if (resizeMode === 'bl') {
        cropRect.w = Math.max(MIN_CROP, drag.cw - dx);
        cropRect.h = Math.max(MIN_CROP, drag.ch + dy);
        cropRect.x = drag.cx + (drag.cw - cropRect.w);
      } else if (resizeMode === 'tr') {
        cropRect.w = Math.max(MIN_CROP, drag.cw + dx);
        cropRect.h = Math.max(MIN_CROP, drag.ch - dy);
        cropRect.y = drag.cy + (drag.ch - cropRect.h);
      } else if (resizeMode === 'tl') {
        cropRect.w = Math.max(MIN_CROP, drag.cw - dx);
        cropRect.h = Math.max(MIN_CROP, drag.ch - dy);
        cropRect.x = drag.cx + (drag.cw - cropRect.w);
        cropRect.y = drag.cy + (drag.ch - cropRect.h);
      } else if (resizeMode === 'r') {
        cropRect.w = Math.max(MIN_CROP, drag.cw + dx);
      } else if (resizeMode === 'l') {
        cropRect.w = Math.max(MIN_CROP, drag.cw - dx);
        cropRect.x = drag.cx + (drag.cw - cropRect.w);
      } else if (resizeMode === 'b') {
        cropRect.h = Math.max(MIN_CROP, drag.ch + dy);
      } else if (resizeMode === 't') {
        cropRect.h = Math.max(MIN_CROP, drag.ch - dy);
        cropRect.y = drag.cy + (drag.ch - cropRect.h);
      }
      clampCrop();
      drawEditCanvas();
    }

    function onCropUp() {
      drag = null;
      resizeMode = null;
    }

    cropCanvas.addEventListener('mousedown', onCropDown);
    cropCanvas.addEventListener('mousemove', onCropMove);
    window.addEventListener('mouseup', onCropUp);
    cropCanvas.addEventListener('touchstart', onCropDown, { passive: false });
    cropCanvas.addEventListener('touchmove', onCropMove, { passive: false });
    cropCanvas.addEventListener('touchend', onCropUp);

    function renderGalleryGrid() {
      if (!galleryGrid) {
        return;
      }
      galleryGrid.innerHTML = '';
      if (galleryHint) {
        galleryHint.hidden = galleryItems.length > 0;
      }
      galleryItems.forEach(function (item, index) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pnv-media-gallery-cell';
        btn.setAttribute('data-index', String(index));
        var thumb = document.createElement('span');
        thumb.className = 'pnv-media-gallery-thumb';
        var thumbImg = document.createElement('img');
        thumbImg.src = item.url;
        thumbImg.alt = '';
        thumb.appendChild(thumbImg);
        btn.appendChild(thumb);
        var check = document.createElement('span');
        check.className = 'pnv-media-gallery-check';
        check.setAttribute('aria-hidden', 'true');
        btn.appendChild(check);
        btn.addEventListener('click', function () {
          openEditWithFile(item.file);
        });
        galleryGrid.appendChild(btn);
      });
    }

    function collectValidImageFiles(fileList) {
      var files = fileList ? Array.prototype.slice.call(fileList) : [];
      var picked = [];
      files.forEach(function (file) {
        if (validImageFile(file)) {
          picked.push(file);
        }
      });
      return picked;
    }

    function showGalleryGridForFiles(files) {
      clearGalleryItems();
      files.forEach(function (file) {
        galleryItems.push({ file: file, url: URL.createObjectURL(file) });
      });
      showView('gallery');
      pushMediaHistory('gallery');
      renderGalleryGrid();
    }

    function handlePickerSelection(fileList) {
      var picked = collectValidImageFiles(fileList);
      if (!picked.length) {
        onGalleryPickerDismissedWithoutSelection();
        return;
      }
      if (picked.length === 1) {
        openEditWithFile(picked[0]);
        return;
      }
      showGalleryGridForFiles(picked);
    }

    function openEditWithFile(file) {
      if (!file || !validImageFile(file)) {
        alert('فقط JPG، PNG یا WebP مجاز است');
        return;
      }
      hideError();
      revokeOriginalUrl();
      revokeFinalUrl();
      originalBlob = file;
      pendingName = file.name || 'image.jpg';
      if (!/\.(jpe?g|png|webp)$/i.test(pendingName)) {
        pendingName = 'image.jpg';
      }
      originalObjectUrl = URL.createObjectURL(file);
      rotationDegrees = 0;
      finalBlob = null;
      showView('edit');
      pushMediaHistory('edit');
      refreshEditSource();
    }

    function openGalleryPickerOnly() {
      try {
        galleryPicker.value = '';
      } catch (e) {}
      galleryPickerOpenedAt = Date.now();
      setTimeout(function () {
        if (!mediaSessionId || view === 'edit' || view === 'preview') {
          return;
        }
        try {
          if (typeof galleryPicker.showPicker === 'function') {
            galleryPicker.showPicker();
          } else {
            galleryPicker.click();
          }
        } catch (e2) {
          galleryPicker.click();
        }
      }, 0);
    }

    function openGallery(fromAttach) {
      hideError();
      beginMediaSession();
      clearGalleryItems();
      openGalleryPickerOnly();
    }

    function applyEditToFinalBlob() {
      return new Promise(function (resolve, reject) {
        if (!editSourceImage) {
          reject(new Error('no source'));
          return;
        }
        var scale = cropDisplayScale || 1;
        var out = document.createElement('canvas');
        var sw = Math.max(1, Math.round(cropRect.w / scale));
        var sh = Math.max(1, Math.round(cropRect.h / scale));
        out.width = sw;
        out.height = sh;
        out.getContext('2d').drawImage(
          editSourceImage,
          cropRect.x / scale,
          cropRect.y / scale,
          cropRect.w / scale,
          cropRect.h / scale,
          0,
          0,
          sw,
          sh
        );
        var mime = 'image/jpeg';
        if (originalBlob && originalBlob.type === 'image/png') {
          mime = 'image/png';
        } else if (originalBlob && originalBlob.type === 'image/webp') {
          mime = 'image/webp';
        }
        var quality = mime === 'image/jpeg' ? 0.92 : undefined;
        out.toBlob(
          function (blob) {
            if (!blob) {
              reject(new Error('crop failed'));
              return;
            }
            resolve(blob);
          },
          mime,
          quality
        );
      });
    }

    function goPreview() {
      applyEditToFinalBlob()
        .then(function (blob) {
          finalBlob = blob;
          revokeFinalUrl();
          finalPreviewUrl = URL.createObjectURL(blob);
          previewImg.src = finalPreviewUrl;
          if (captionEl && mainTextarea && !captionEl.value) {
            captionEl.value = mainTextarea.value || '';
          }
          showView('preview');
          pushMediaHistory('preview');
        })
        .catch(function () {
          alert('پردازش تصویر ناموفق بود');
        });
    }

    window.addEventListener('popstate', function (e) {
      if (finishingSession) {
        finishingSession = false;
        sessionHistoryDepth = 0;
        if (typeof global.__pnvMediaHistoryDebug === 'function') {
          global.__pnvMediaHistoryDebug('popstate-finish', { state: e.state });
        }
        return;
      }

      var state = e.state;
      if (isMediaHistoryState(state, mediaSessionId)) {
        sessionHistoryDepth = Math.max(0, sessionHistoryDepth - 1);
        mediaOpen = true;
        renderMediaStep(state.step);
        if (typeof global.__pnvMediaHistoryDebug === 'function') {
          global.__pnvMediaHistoryDebug('popstate-step', {
            step: state.step,
            depth: sessionHistoryDepth
          });
        }
        return;
      }

      if (mediaOpen) {
        closeMediaWithoutHistoryMutation();
        if (typeof global.__pnvMediaHistoryDebug === 'function') {
          global.__pnvMediaHistoryDebug('popstate-chat', { state: state });
        }
      }
    });

    galleryBack.addEventListener('click', function () {
      history.back();
    });

    editCancel.addEventListener('click', function () {
      finishMediaSession();
    });

    editRotate.addEventListener('click', function () {
      rotationDegrees = (rotationDegrees + 90) % 360;
      refreshEditSource();
    });

    editApply.addEventListener('click', function () {
      goPreview();
    });

    previewBack.addEventListener('click', function () {
      history.back();
    });

    previewEdit.addEventListener('click', function () {
      history.back();
    });

    function submitMedia() {
      if (submitting || !finalBlob || !ajaxPost) {
        return;
      }
      submitting = true;
      hideError();
      sendBtn.disabled = true;
      var caption = (captionEl ? captionEl.value : '').trim();
      var file = new File([finalBlob], pendingName, {
        type: finalBlob.type || 'image/jpeg'
      });
      assignInputFile(file);
      var fd = new FormData(form);
      fd.set('image', file, pendingName);
      fd.set('message', caption);
      var action = form.getAttribute('action') || window.location.href;
      ajaxPost(action, fd)
        .then(function () {
          finishMediaSession();
          if (mainTextarea) {
            mainTextarea.value = '';
            mainTextarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
          form.dispatchEvent(new CustomEvent('support-composer-sent', { bubbles: true }));
          window.dispatchEvent(new CustomEvent('support:poll-now'));
          focusComposer(mainTextarea);
        })
        .catch(function (err) {
          showError((err && err.message) || 'ارسال تصویر ناموفق بود');
          focusComposer(captionEl);
        })
        .finally(function () {
          submitting = false;
          sendBtn.disabled = false;
        });
    }

    sendBtn.addEventListener('click', submitMedia);
    if (captionEl) {
      captionEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          submitMedia();
        }
      });
      captionEl.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 96) + 'px';
      });
    }

    function onGalleryPickerDismissedWithoutSelection() {
      if (galleryItems.length > 0) {
        return;
      }
      if (view === 'edit' || view === 'preview') {
        return;
      }
      if (galleryPickerOpenedAt && Date.now() - galleryPickerOpenedAt < 450) {
        return;
      }
      finishMediaSession();
    }

    galleryPicker.addEventListener('change', function () {
      var files = galleryPicker.files;
      if (!files || !files.length) {
        onGalleryPickerDismissedWithoutSelection();
        return;
      }
      galleryPickerOpenedAt = 0;
      handlePickerSelection(files);
    });

    galleryPicker.addEventListener('cancel', function () {
      onGalleryPickerDismissedWithoutSelection();
    });

    form.addEventListener('submit', function (e) {
      if (view) {
        e.preventDefault();
      }
    });

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) {
        return;
      }
      beginMediaSession();
      clearGalleryItems();
      openEditWithFile(file);
    });

    return {
      openGallery: openGallery,
      isOpen: function () {
        return !!view;
      },
      reset: finishMediaSession,
      _test: {
        getView: function () {
          return view;
        },
        getSessionHistoryDepth: function () {
          return sessionHistoryDepth;
        },
        getMediaSessionId: function () {
          return mediaSessionId;
        }
      }
    };
  }

  global.PNVSupportMedia = {
    bindTelegramMediaComposer: bindTelegramMediaComposer,
    ensureMediaRoot: ensureMediaRoot
  };
})(window);
