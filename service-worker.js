/**
 * Service Worker для Web Push уведомлений
 * Обрабатывает push-события и показывает нативные уведомления браузера
 */

const SW_VERSION = 'v1.0.0';
const CACHE_NAME = `namahatta-sw-${SW_VERSION}`;

// === INSTALL ===
self.addEventListener('install', (event) => {
    // Активируем SW сразу, не дожидаясь закрытия вкладок
    self.skipWaiting();
});

// === ACTIVATE ===
self.addEventListener('activate', (event) => {
    // Забираем контроль над всеми клиентами сразу
    event.waitUntil(clients.claim());
    
    // Очищаем старые кэши
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((cache) => cache.startsWith('namahatta-sw-') && cache !== CACHE_NAME)
                    .map((cache) => caches.delete(cache))
            );
        })
    );
});

// === PUSH EVENT ===
self.addEventListener('push', (event) => {
    let data = {};
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            // Если не JSON, читаем как текст
            data = {
                title: 'Нама-Хатта',
                body: event.data.text(),
                action: 'default'
            };
        }
    }
    
    const title = data.title || 'Нама-Хатта';
    const body = data.body || '';
    
    // Получаем group_id для группировки уведомлений
    const groupId = data.group_id || null;
    
    // Формируем URL для deep linking
    let url = '/index.html';
    if (data.target_page) {
        try {
            const params = data.target_params ? JSON.parse(decodeURIComponent(data.target_params)) : {};
            url = '/' + data.target_page + '?' + new URLSearchParams(params).toString();
        } catch (e) {
            url = '/' + data.target_page;
        }
    }
    
    const options = {
        body: body,
        icon: '/assets/groups/group_avatar_default.png',
        badge: '/assets/groups/group_avatar_default.png',
        data: {
            url: url,
            action: data.action || 'default',
            groupId: groupId,
            senderId: data.sender_id || null,
            senderName: data.sender_name || null,
            timestamp: Date.now()
        },
        // Группируем уведомления по группе (одно уведомление на группу)
        tag: groupId ? `group_${groupId}` : 'default',
        renotify: true,
        requireAction: true,
        silent: false
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// === NOTIFICATION CLICK ===
self.addEventListener('notificationclick', (event) => {
    // Закрываем уведомление
    event.notification.close();
    
    const url = event.notification.data?.url || '/index.html';
    
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            // Проверяем, есть ли уже открытая вкладка с этим URL
            for (const client of clients) {
                // Сравниваем базовый URL (без параметров)
                const clientPath = new URL(client.url).pathname;
                const targetPath = new URL(url, self.location.origin).pathname;
                
                if (clientPath === targetPath && 'focus' in client) {
                    return client.focus();
                }
            }
            // Если нет открытой вкладки - открываем новую
            return self.clients.openWindow(url);
        })
    );
});

// === NOTIFICATION CLOSE (пользователь закрыл без клика) ===
self.addEventListener('notificationclose', (event) => {
    // Можно логировать для аналитики
    const notificationData = event.notification.data;
    if (notificationData && notificationData.action) {
        // Отправляем метрику о закрытии уведомления (опционально)
        console.log('Notification closed:', notificationData.action);
    }
});

// === MESSAGE (для отладки) ===
self.addEventListener('message', (event) => {
    const data = event.data || {};
    
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (data.type === 'SHOW_TEST_NOTIFICATION') {
        self.registration.showNotification('Нама-Хатта', {
            body: 'Веб-уведомления работают! ✓',
            icon: '/assets/groups/group_avatar_default.png',
            badge: '/assets/groups/group_avatar_default.png',
            tag: 'test',
            data: { url: '/index.html', action: 'test' }
        });
    }
});
