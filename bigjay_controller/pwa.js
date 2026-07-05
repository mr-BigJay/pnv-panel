(function(){
  const cfg = window.PNV_ADMIN_PWA || {};
  const pollUrl = cfg.pollUrl || '';
  const supportUrl = cfg.supportUrl || '/bigjay_controller/?page=support';
  const swUrl = cfg.swUrl || '/bigjay_controller/sw.js';
  const swScope = cfg.swScope || '/bigjay_controller/';
  const vapidUrl = cfg.vapidUrl || '';
  const subscribeUrl = cfg.subscribeUrl || '';
  const menuLink = document.getElementById('adminSupportMenu');
  const storageKey = 'pnvAdminLastNotifyTs';

  let lastNotifyTs = parseInt(localStorage.getItem(storageKey) || '0', 10);
  let swRegistration = null;

  function urlBase64ToUint8Array(base64String){
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);

    for(let i = 0; i < raw.length; i++){
      output[i] = raw.charCodeAt(i);
    }

    return output;
  }

  function setUnreadDot(hasUnread){
    if(!menuLink){
      return;
    }

    let dot = menuLink.querySelector('.notifDot');

    if(hasUnread){
      if(!dot){
        dot = document.createElement('span');
        dot.className = 'notifDot';
        menuLink.insertBefore(dot, menuLink.firstChild);
      }
      return;
    }

    if(dot){
      dot.remove();
    }
  }

  function buildSupportUrl(username){
    if(!username){
      return supportUrl;
    }

    const join = supportUrl.indexOf('?') === -1 ? '?' : '&';
    return supportUrl + join + 'user=' + encodeURIComponent(username);
  }

  function showNotification(latest){
    if(!latest || !latest.timestamp){
      return;
    }

    if(latest.timestamp <= lastNotifyTs){
      return;
    }

    lastNotifyTs = latest.timestamp;
    localStorage.setItem(storageKey, String(lastNotifyTs));

    const title = 'پیام جدید از ' + (latest.user || 'کاربر');
    const body = latest.text || 'پیام جدید در پشتیبانی';
    const url = buildSupportUrl(latest.user);
    const tag = 'support-' + (latest.user || 'user');

    if(swRegistration && swRegistration.active){
      swRegistration.active.postMessage({
        type: 'notify',
        title: title,
        body: body,
        url: url,
        tag: tag
      });
      return;
    }

    if('Notification' in window && Notification.permission === 'granted'){
      const note = new Notification(title, {
        body: body,
        icon: '/bigjay_controller/icons/icon-192.png',
        tag: tag
      });

      note.onclick = function(){
        window.focus();
        window.location.href = url;
      };
    }
  }

  function checkUnread(){
    if(!pollUrl){
      return;
    }

    fetch(pollUrl, {credentials: 'same-origin'})
      .then(function(response){
        return response.json();
      })
      .then(function(data){
        setUnreadDot(!!data.has_unread);

        if(data.has_unread && data.latest_unread){
          const onSupportPage = window.location.search.indexOf('page=support') !== -1;
          const activeUser = new URLSearchParams(window.location.search).get('user');

          if(onSupportPage && activeUser && data.latest_unread.user === activeUser){
            return;
          }

          if(Notification.permission === 'granted'){
            showNotification(data.latest_unread);
          }
        }
      })
      .catch(function(){});
  }

  async function subscribePush(){
    if(!subscribeUrl || !vapidUrl || !swRegistration || !('PushManager' in window)){
      return;
    }

    if(Notification.permission !== 'granted'){
      return;
    }

    try{
      const keyResponse = await fetch(vapidUrl, {credentials: 'same-origin'});
      const keyData = await keyResponse.json();

      if(!keyData.publicKey){
        return;
      }

      let subscription = await swRegistration.pushManager.getSubscription();

      if(!subscription){
        subscription = await swRegistration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(keyData.publicKey)
        });
      }

      await fetch(subscribeUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(subscription)
      });
    }
    catch(e){}
  }

  async function requestNotifications(){
    if(!('Notification' in window)){
      return;
    }

    if(Notification.permission === 'granted'){
      await subscribePush();
      return;
    }

    if(Notification.permission !== 'default'){
      return;
    }

    const granted = await Notification.requestPermission();

    if(granted === 'granted'){
      await subscribePush();
    }
  }

  async function init(){
    if('serviceWorker' in navigator){
      try{
        swRegistration = await navigator.serviceWorker.register(swUrl, {scope: swScope});
      }
      catch(e){}
    }

    checkUnread();
    setInterval(checkUnread, 10000);

    if(cfg.promptNotifications !== false){
      setTimeout(requestNotifications, 1200);
    }
  }

  window.PNV_ADMIN_PWA = window.PNV_ADMIN_PWA || {};
  window.PNV_ADMIN_PWA.requestNotifications = requestNotifications;
  window.PNV_ADMIN_PWA.checkUnread = checkUnread;

  init();
})();
