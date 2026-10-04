(function (global) {
  'use strict';

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function bindAdminSearch(cfg) {
    var supportSearch = cfg.searchEl;
    var supportUserResults = cfg.userResultsEl;
    var usersApiUrl = cfg.usersApiUrl;
    var listUrl = cfg.listUrl;
    var userSearchTimer = null;
    var userSearchRequest = 0;

    if (!supportSearch) {
      return;
    }

    function hideUserSearchResults() {
      if (!supportUserResults) {
        return;
      }
      supportUserResults.classList.remove('is-open');
      supportUserResults.innerHTML = '';
    }

    function openUserChat(username) {
      if (!username) {
        return;
      }
      var sep = listUrl.indexOf('?') >= 0 ? '&' : '?';
      window.location.href = listUrl + sep + 'user=' + encodeURIComponent(username);
    }

    function renderUserSearchResults(users) {
      if (!supportUserResults) {
        return;
      }
      supportUserResults.innerHTML = '';
      if (!users || !users.length) {
        supportUserResults.innerHTML =
          '<div class="msgUserSearchEmpty">کاربری یافت نشد</div>';
        supportUserResults.classList.add('is-open');
        return;
      }
      users.forEach(function (user) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'msgUserSearchItem';
        btn.innerHTML =
          '<span class="msgUserSearchName">' +
          escapeHtml(user.username || '') +
          '</span><span class="msgUserSearchMobile">' +
          escapeHtml(user.mobile || 'بدون موبایل') +
          '</span>';
        btn.addEventListener('click', function () {
          openUserChat(user.username || '');
        });
        supportUserResults.appendChild(btn);
      });
      supportUserResults.classList.add('is-open');
    }

    function searchUsersByQuery(query) {
      if (!supportUserResults || !usersApiUrl) {
        return;
      }
      var requestId = ++userSearchRequest;
      fetch(usersApiUrl + '?q=' + encodeURIComponent(query), {
        credentials: 'same-origin'
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (requestId !== userSearchRequest) {
            return;
          }
          renderUserSearchResults(data.users || []);
        })
        .catch(function () {
          if (requestId !== userSearchRequest) {
            return;
          }
          hideUserSearchResults();
        });
    }

    supportSearch.addEventListener('input', function () {
      var q = this.value.trim();
      var qLower = q.toLowerCase();
      document.querySelectorAll('.msgConv[data-username]').forEach(function (item) {
        var name = (item.dataset.username || '').toLowerCase();
        item.style.display = name.indexOf(qLower) >= 0 ? 'flex' : 'none';
      });
      clearTimeout(userSearchTimer);
      if (q.length < 2) {
        hideUserSearchResults();
        return;
      }
      userSearchTimer = setTimeout(function () {
        searchUsersByQuery(q);
      }, 250);
    });

    document.addEventListener('click', function (e) {
      if (
        supportUserResults &&
        !supportUserResults.contains(e.target) &&
        e.target !== supportSearch
      ) {
        hideUserSearchResults();
      }
    });
  }

  function wireSupportUi(cfg) {
    var ui = global.SupportUI;
    if (!ui) {
      return;
    }

    var form = cfg.formEl;
    var textarea = cfg.textareaEl;
    var messagesEl = cfg.messagesEl;

    if (textarea && form) {
      ui.bindTextareaGrow(textarea);
      ui.bindEnterToSend(textarea, form, true);
      ui.bindFormGuard(form, textarea, cfg.imageInputId);
    }

    if (form && global.PNVSupportVoice) {
      global.PNVSupportVoice.bindComposer({
        formEl: form,
        textareaEl: textarea,
        micBtnEl: cfg.micBtnEl,
        sendBtnEl: cfg.sendBtnEl,
        audioInputId: cfg.audioInputId
      });
      if (messagesEl && typeof global.PNVSupportVoice.bindPlayers === 'function') {
        global.PNVSupportVoice.bindPlayers(messagesEl);
      }
    }

    if (form) {
      ui.bindImageAttach(form, cfg.imageInputId, cfg.attachBtnId);
      ui.bindMessageActions({
        chatEl: messagesEl,
        form: form,
        role: cfg.mode === 'admin' ? 'admin' : 'user'
      });
      ui.bindComposerAjaxSend({
        form: form,
        textarea: textarea,
        imageInputId: cfg.imageInputId,
        audioInputId: cfg.audioInputId
      });
      ui.bindComposerSessionGuard(form, textarea);
    }

    if (cfg.mode === 'admin' && cfg.currentUser) {
      if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
      }
      ui.bindMobileChatLayout({
        containerEl: cfg.containerEl,
        messagesEl: messagesEl,
        textareaEl: textarea,
        chatTopEl: cfg.chatTopEl
      });
      ui.initPolling({
        chatEl: messagesEl,
        pollUrl: cfg.pollUrl,
        getParams: function (since) {
          return (
            '?user=' +
            encodeURIComponent(cfg.currentUser) +
            '&since=' +
            (since || 0)
          );
        },
        classMap: cfg.classMap || { admin: 'admin', user: 'usermsg' },
        actionMeta: cfg.actionMeta || { isAdmin: true, ownSender: 'admin' },
        interval: cfg.pollInterval || 5000
      });
    }

    if (cfg.mode === 'user') {
      ui.initPolling({
        chatEl: messagesEl,
        pollUrl: cfg.pollUrl,
        getParams: function (since) {
          return '?since=' + (since || 0);
        },
        classMap: cfg.classMap || { admin: 'admin', user: 'usermsg' },
        actionMeta: cfg.actionMeta || { isAdmin: false, ownSender: 'user' },
        interval: cfg.pollInterval || 5000
      });
    }

    if (messagesEl) {
      ui.scrollToBottomOnOpen(messagesEl);
    }
  }

  function init(cfg) {
    cfg = cfg || {};
    var shell = global.PNVSupportShell;
    if (shell && typeof shell.mount === 'function') {
      shell.mount(cfg);
    }

    wireSupportUi(cfg);

    if (cfg.mode === 'admin') {
      bindAdminSearch(cfg);
      var backBtn = cfg.backBtnEl;
      if (backBtn && cfg.listUrl) {
        backBtn.addEventListener('click', function () {
          window.location.href = cfg.listUrl;
        });
      }
    }

    if (typeof cfg.onReady === 'function') {
      cfg.onReady();
    }
  }

  global.PNVSupportChat = {
    init: init
  };
})(window);
