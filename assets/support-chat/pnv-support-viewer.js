(function (global) {
  'use strict';

  function ensureViewerRoot() {
    var root = document.getElementById('pnvSupportImageViewer');
    if (root) {
      return root;
    }

    root = document.createElement('div');
    root.id = 'pnvSupportImageViewer';
    root.className = 'pnv-image-viewer';
    root.hidden = true;
    root.innerHTML =
      '<div class="pnv-image-viewer-backdrop" data-viewer-close="backdrop"></div>' +
      '<header class="pnv-image-viewer-top">' +
      '<button type="button" class="pnv-image-viewer-close" data-viewer-close="btn" aria-label="بازگشت">' +
      '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>' +
      '</button></header>' +
      '<div class="pnv-image-viewer-stage" id="pnvImageViewerStage">' +
      '<div class="pnv-image-viewer-spinner" id="pnvImageViewerSpinner" aria-hidden="true"></div>' +
      '<p class="pnv-image-viewer-error" id="pnvImageViewerError" hidden>نمایش تصویر ممکن نشد</p>' +
      '<img class="pnv-image-viewer-image" id="pnvImageViewerImg" alt="">' +
      '</div>';
    document.body.appendChild(root);
    return root;
  }

  function bindImageViewer(cfg) {
    cfg = cfg || {};
    var preferredChatEl = cfg.chatEl || null;

    ensureViewerRoot();

    var rootEl = document.getElementById('pnvSupportImageViewer');
    var imgEl = document.getElementById('pnvImageViewerImg');
    var spinnerEl = document.getElementById('pnvImageViewerSpinner');
    var errorEl = document.getElementById('pnvImageViewerError');
    var stageEl = document.getElementById('pnvImageViewerStage');

    var viewerOpen = false;
    var viewerHistoryActive = false;
    var savedScrollTop = 0;
    var savedScrollEl = null;

    function resolveChatScrollEl() {
      if (preferredChatEl && preferredChatEl.scrollHeight !== undefined) {
        return preferredChatEl;
      }
      return (
        document.getElementById('supportMessages') ||
        document.getElementById('userChat') ||
        document.querySelector('.msgBody') ||
        document.querySelector('.msgMessages')
      );
    }

    function showLoading() {
      if (spinnerEl) {
        spinnerEl.hidden = false;
      }
      if (errorEl) {
        errorEl.hidden = true;
      }
      if (imgEl) {
        imgEl.hidden = true;
        imgEl.removeAttribute('src');
      }
    }

    function showError() {
      if (spinnerEl) {
        spinnerEl.hidden = true;
      }
      if (errorEl) {
        errorEl.hidden = false;
      }
      if (imgEl) {
        imgEl.hidden = true;
      }
    }

    function showImage() {
      if (spinnerEl) {
        spinnerEl.hidden = true;
      }
      if (errorEl) {
        errorEl.hidden = true;
      }
      if (imgEl) {
        imgEl.hidden = false;
      }
    }

    function restoreScroll() {
      if (savedScrollEl) {
        savedScrollEl.scrollTop = savedScrollTop;
        requestAnimationFrame(function () {
          if (savedScrollEl) {
            savedScrollEl.scrollTop = savedScrollTop;
          }
        });
      }
    }

    function closeViewerUiWithoutHistoryMutation() {
      if (!viewerOpen) {
        return;
      }
      viewerOpen = false;
      viewerHistoryActive = false;
      rootEl.hidden = true;
      document.body.classList.remove('pnv-image-viewer-open');
      if (imgEl) {
        imgEl.onload = null;
        imgEl.onerror = null;
        imgEl.removeAttribute('src');
      }
      showLoading();
      restoreScroll();
    }

    function requestCloseViewer() {
      if (viewerHistoryActive) {
        history.back();
        return;
      }
      closeViewerUiWithoutHistoryMutation();
    }

    function openImageViewer(src) {
      var href = (src || '').trim();
      if (!href) {
        return;
      }

      savedScrollEl = resolveChatScrollEl();
      savedScrollTop = savedScrollEl ? savedScrollEl.scrollTop : 0;

      viewerOpen = true;
      rootEl.hidden = false;
      document.body.classList.add('pnv-image-viewer-open');
      showLoading();

      if (imgEl) {
        imgEl.onload = function () {
          showImage();
        };
        imgEl.onerror = function () {
          showError();
        };
        imgEl.src = href;
      }

      viewerHistoryActive = true;
      history.pushState({ pnvSupportImageViewer: true }, '', window.location.href);
    }

    window.addEventListener('popstate', function (e) {
      if (!viewerOpen) {
        return;
      }
      if (e.state && e.state.pnvSupportImageViewer) {
        return;
      }
      closeViewerUiWithoutHistoryMutation();
    });

    document.addEventListener(
      'click',
      function (e) {
        var link = e.target.closest('.msgImageLink');
        if (!link) {
          return;
        }
        e.preventDefault();
        e.stopPropagation();
        var img = link.querySelector('img');
        var imgSrc = img ? img.getAttribute('src') : '';
        openImageViewer(link.getAttribute('href') || imgSrc);
      },
      true
    );

    rootEl.addEventListener('click', function (e) {
      if (e.target.closest('.pnv-image-viewer-image')) {
        return;
      }

      var onStage =
        stageEl &&
        (e.target === stageEl || (stageEl.contains(e.target) && !e.target.closest('.pnv-image-viewer-image')));

      if (onStage || e.target.closest('[data-viewer-close]')) {
        e.preventDefault();
        requestCloseViewer();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (!viewerOpen) {
        return;
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        requestCloseViewer();
      }
    });

    return {
      open: openImageViewer,
      close: requestCloseViewer,
      isOpen: function () {
        return viewerOpen;
      }
    };
  }

  global.PNVSupportImageViewer = {
    bind: bindImageViewer
  };
})(window);
