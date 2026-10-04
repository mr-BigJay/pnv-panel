(function (global) {
  'use strict';

  var STORAGE_KEY = 'pnv-chat-theme';

  function chatModeFromRoot(root) {
    if (!root) {
      return 'admin';
    }
    if (root.dataset.pnvMode === 'user' || root.classList.contains('pnv-chat--user')) {
      return 'user';
    }
    return 'admin';
  }

  function applyBubbleLanes(container, root) {
    if (!container) {
      return;
    }
    var mode = chatModeFromRoot(root);
    container.querySelectorAll('.msgBubble').forEach(function (bubble) {
      bubble.classList.remove('pnv-out', 'pnv-in');
      var sender = bubble.dataset.sender || '';
      var outgoing =
        mode === 'admin' ? sender === 'admin' : sender === 'user';
      bubble.classList.add(outgoing ? 'pnv-out' : 'pnv-in');
    });
  }

  function layoutBubbleMeta(container) {
    if (!container) {
      return;
    }
    container.querySelectorAll('.msgBubble').forEach(function (bubble) {
      var meta = bubble.querySelector(':scope > .msgMeta');
      var edited = bubble.querySelector(':scope > .msgEdited');
      if (!meta && !edited) {
        return;
      }
      var footer = bubble.querySelector(':scope > .pnv-bubble-footer');
      if (!footer) {
        footer = document.createElement('div');
        footer.className = 'pnv-bubble-footer';
        bubble.appendChild(footer);
      }
      footer.textContent = '';
      if (edited) {
        footer.appendChild(edited);
      }
      if (meta) {
        footer.appendChild(meta);
      }
    });
  }

  function applyBubbleGrouping(container, root) {
    if (!container) {
      return;
    }
    var bubbles = container.querySelectorAll('.msgBubble');
    var prevSender = null;
    bubbles.forEach(function (bubble, index) {
      bubble.classList.remove(
        'pnv-bubble--first',
        'pnv-bubble--mid',
        'pnv-bubble--last',
        'pnv-bubble--single',
        'pnv-bubble--has-tail'
      );
      var sender = bubble.dataset.sender || '';
      var next = bubbles[index + 1];
      var prevSame = prevSender === sender && sender !== '';
      var nextSame = next && (next.dataset.sender || '') === sender;
      var pos = 'single';
      if (!prevSame && !nextSame) {
        pos = 'single';
      } else if (!prevSame && nextSame) {
        pos = 'first';
      } else if (prevSame && nextSame) {
        pos = 'mid';
      } else {
        pos = 'last';
      }
      bubble.classList.add('pnv-bubble--' + pos);
      if (pos === 'single' || pos === 'last') {
        bubble.classList.add('pnv-bubble--has-tail');
      }
      prevSender = sender;
    });
  }

  function applyDateDividers(container) {
    if (!container) {
      return;
    }
    container.querySelectorAll('.pnv-date-divider').forEach(function (node) {
      node.remove();
    });
    var bubbles = container.querySelectorAll('.msgBubble');
    var lastDay = null;
    bubbles.forEach(function (bubble) {
      var day = (bubble.dataset.jalaliDate || '').trim();
      if (!day || day === '-' || day === lastDay) {
        return;
      }
      lastDay = day;
      var divider = document.createElement('div');
      divider.className = 'pnv-date-divider';
      divider.setAttribute('data-day', day);
      divider.textContent = day;
      container.insertBefore(divider, bubble);
    });
  }

  function decorateMessages(container, root) {
    applyBubbleLanes(container, root);
    layoutBubbleMeta(container);
    applyBubbleGrouping(container, root);
    applyDateDividers(container);
    if (
      global.PNVSupportVoice &&
      typeof global.PNVSupportVoice.bindPlayers === 'function'
    ) {
      global.PNVSupportVoice.bindPlayers(container);
    }
  }

  function observeMessages(container, root) {
    if (!container || typeof MutationObserver !== 'function') {
      return function () {};
    }
    var scheduled = false;
    var observer = new MutationObserver(function () {
      if (scheduled) {
        return;
      }
      scheduled = true;
      requestAnimationFrame(function () {
        scheduled = false;
        decorateMessages(container, root);
      });
    });
    observer.observe(container, { childList: true, subtree: false });
    return function () {
      observer.disconnect();
    };
  }

  function readTheme() {
    try {
      var stored = localStorage.getItem(STORAGE_KEY);
      if (stored === 'light' || stored === 'dark') {
        return stored;
      }
    } catch (e) {}
    return 'dark';
  }

  function applyTheme(root, theme) {
    if (!root) {
      return;
    }
    root.setAttribute('data-pnv-theme', theme === 'light' ? 'light' : 'dark');
  }

  function mountThemeToggle(root) {
    if (!root) {
      return;
    }
    var existing = root.querySelector('.pnv-chat-theme-toggle');
    if (!existing) {
      return;
    }
    var syncLabel = function () {
      existing.textContent =
        root.getAttribute('data-pnv-theme') === 'light' ? 'حالت تیره' : 'حالت روشن';
    };
    syncLabel();
    existing.addEventListener('click', function () {
      var next = root.getAttribute('data-pnv-theme') === 'light' ? 'dark' : 'light';
      applyTheme(root, next);
      try {
        localStorage.setItem(STORAGE_KEY, next);
      } catch (e) {}
      syncLabel();
    });
  }

  function mountMobilePanel(root) {
    if (!root || !root.classList.contains('supportPage')) {
      return;
    }
    root.classList.add('pnv-chat--panels');
  }

  function mount(cfg) {
    cfg = cfg || {};
    var root =
      cfg.rootEl ||
      document.getElementById('supportPage') ||
      document.getElementById('userSupportPage') ||
      document.querySelector('.pnv-chat') ||
      document.querySelector('.msgApp--user');
    if (!root) {
      return { decorateMessages: decorateMessages };
    }

    root.classList.add('pnv-chat');
    if (cfg.mode === 'user') {
      root.classList.add('pnv-chat--user');
      root.dataset.pnvMode = 'user';
    } else {
      root.dataset.pnvMode = 'admin';
    }

    applyTheme(root, readTheme());
    mountThemeToggle(root);
    mountMobilePanel(root);

    var messagesEl = cfg.messagesEl;
    if (messagesEl) {
      decorateMessages(messagesEl, root);
      observeMessages(messagesEl, root);
    }

    return {
      root: root,
      decorateMessages: function () {
        decorateMessages(messagesEl, root);
      }
    };
  }

  global.PNVSupportShell = {
    mount: mount,
    decorateMessages: decorateMessages
  };
})(window);
