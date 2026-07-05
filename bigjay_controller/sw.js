const CACHE = 'pnv-admin-v1';
const ASSETS = [
  '/bigjay_controller/manifest.webmanifest',
  '/bigjay_controller/icons/icon-192.png',
  '/bigjay_controller/icons/icon-512.png',
  '/bigjay_controller/pwa.js'
];

self.addEventListener('install', function(event){
  event.waitUntil(
    caches.open(CACHE).then(function(cache){
      return cache.addAll(ASSETS);
    }).then(function(){
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function(event){
  event.waitUntil(self.clients.claim());
});

self.addEventListener('message', function(event){
  const data = event.data || {};

  if(data.type !== 'notify'){
    return;
  }

  const title = data.title || 'پیام جدید';
  const options = {
    body: data.body || '',
    icon: '/bigjay_controller/icons/icon-192.png',
    badge: '/bigjay_controller/icons/icon-192.png',
    tag: data.tag || 'support-message',
    renotify: true,
    data: {
      url: data.url || '/bigjay_controller/?page=support'
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('push', function(event){
  let payload = {
    title: 'پیام جدید از کاربر',
    body: 'یک پیام پشتیبانی دریافت شد',
    url: '/bigjay_controller/?page=support'
  };

  if(event.data){
    try{
      payload = Object.assign(payload, event.data.json());
    }
    catch(e){}
  }

  event.waitUntil(
    self.registration.showNotification(payload.title, {
      body: payload.body,
      icon: '/bigjay_controller/icons/icon-192.png',
      badge: '/bigjay_controller/icons/icon-192.png',
      tag: payload.tag || 'support-push',
      renotify: true,
      data: {url: payload.url}
    })
  );
});

self.addEventListener('notificationclick', function(event){
  event.notification.close();

  const targetUrl = (event.notification.data && event.notification.data.url)
    ? event.notification.data.url
    : '/bigjay_controller/?page=support';

  event.waitUntil(
    clients.matchAll({type: 'window', includeUncontrolled: true}).then(function(list){
      for(let i = 0; i < list.length; i++){
        const client = list[i];

        if(client.url.indexOf('/bigjay_controller') !== -1 && 'focus' in client){
          client.navigate(targetUrl);
          return client.focus();
        }
      }

      if(clients.openWindow){
        return clients.openWindow(targetUrl);
      }
    })
  );
});
