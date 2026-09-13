/**
 * Web Push Notifications - Frontend модуль
 * Управление подпиской, разрешением и регистрацией Service Worker
 */

const WEB_PUSH = (function() {
    'use strict';
    
    let vapidPublicKey = null;
    let isSupported = false;
    let isSubscribed = false;
    let currentSubscription = null;
    let serviceWorkerRegistration = null;
    
    // === Проверка поддержки Web Push ===
    function isPushSupported() {
        if (!('serviceWorker' in navigator)) return false;
        if (!('PushManager' in window)) return false;
        if (!('Notification' in window)) return false;
        isSupported = true;
        return true;
    }
    
    // === Получить статус разрешения ===
    function getPermissionStatus() {
        if (!('Notification' in window)) return 'unsupported';
        return Notification.permission;
    }
    
    // === Запрос разрешения ===
    async function requestPermission() {
        if (!isPushSupported()) return false;
        
        const permission = getPermissionStatus();
        
        if (permission === 'granted') return true;
        if (permission === 'denied') return false;
        
        try {
            const result = await Notification.requestPermission();
            return result === 'granted';
        } catch (e) {
            console.error('[WebPush] Permission error:', e);
            return false;
        }
    }
    
    // === Регистрация Service Worker ===
    async function registerServiceWorker() {
        if (serviceWorkerRegistration) return serviceWorkerRegistration;
        
        try {
            const registration = await navigator.serviceWorker.register('/service-worker.js', {
                scope: '/'
            });
            
            serviceWorkerRegistration = registration;
            
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // Новая версия SW доступна
                    }
                });
            });
            
            return registration;
        } catch (e) {
            console.error('[WebPush] Регистрация SW не удалась:', e);
            return null;
        }
    }
    
    // === Получить VAPID ключ с сервера ===
    async function getVapidKey() {
        if (vapidPublicKey) return vapidPublicKey;
        
        try {
            const response = await fetch('/api/get_vapid_key.php', {
                method: 'GET',
                credentials: 'include'
            });
            
            if (!response.ok) return null;
            
            const data = await response.json();
            vapidPublicKey = data.public_key;
            return vapidPublicKey;
        } catch (e) {
            console.error('[WebPush] Ошибка получения VAPID ключа:', e);
            return null;
        }
    }
    
    // === Конвертация Base64 в Uint8Array ===
    function urlB64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    
    // === Подписка ===
    async function subscribe(registration) {
        if (!registration) return false;
        
        const pushManager = registration.pushManager;
        if (!pushManager) return false;
        
        const existingSubscription = await pushManager.getSubscription();
        if (existingSubscription) {
            currentSubscription = existingSubscription;
            isSubscribed = true;
            return true;
        }
        
        const publicKey = await getVapidKey();
        if (!publicKey) return false;
        
        try {
            const subscription = await pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlB64ToUint8Array(publicKey)
            });
            
            const sent = await sendSubscriptionToServer(subscription);
            
            if (sent) {
                currentSubscription = subscription;
                isSubscribed = true;
                return true;
            } else {
                await subscription.unsubscribe();
                return false;
            }
        } catch (e) {
            console.error('[WebPush] Ошибка подписки:', e);
            return false;
        }
    }
    
    // === Отправка подписки на сервер ===
    async function sendSubscriptionToServer(subscription) {
        try {
            const p256dh = btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh'))));
            const auth = btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth'))));
            
            const response = await fetch('/api/save_web_push_subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    p256dh: p256dh,
                    auth: auth
                })
            });
            
            const result = await response.json();
            return result.success;
        } catch (e) {
            console.error('[WebPush] Ошибка отправки подписки:', e);
            return false;
        }
    }
    
    // === Отписка ===
    async function unsubscribe() {
        if (!currentSubscription) return false;
        
        try {
            await fetch('/api/remove_web_push_subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ endpoint: currentSubscription.endpoint })
            });
            
            await currentSubscription.unsubscribe();
            isSubscribed = false;
            currentSubscription = null;
            return true;
        } catch (e) {
            console.error('[WebPush] Ошибка отписки:', e);
            return false;
        }
    }
    
    // === Проверка статуса подписки ===
    async function checkSubscriptionStatus(registration) {
        if (!registration || !('pushManager' in registration)) return false;
        
        try {
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                isSubscribed = true;
                currentSubscription = subscription;
                return true;
            }
            return false;
        } catch (e) {
            console.error('[WebPush] Ошибка проверки подписки:', e);
            return false;
        }
    }
    
    // === Отправка тестового уведомления ===
    async function sendTestNotification() {
        if (!serviceWorkerRegistration) return false;
        
        try {
            await serviceWorkerRegistration.active.postMessage({ type: 'SHOW_TEST_NOTIFICATION' });
            return true;
        } catch (e) {
            console.error('[WebPush] Ошибка отправки теста:', e);
            return false;
        }
    }
    
    // === Инициализация ===
    async function init() {
        if (!isPushSupported()) return;
        
        try {
            const authRes = await fetch('/api/check_auth.php', { credentials: 'include' });
            const authData = await authRes.json();
            
            if (!authData.is_authenticated) return;
        } catch (e) {
            return;
        }
        
        const currentPermission = getPermissionStatus();
        if (currentPermission === 'denied') return;
        
        const registration = await registerServiceWorker();
        if (!registration) return;
        
        const hasExisting = await checkSubscriptionStatus(registration);
        
        if (!hasExisting) {
            const allowed = await requestPermission();
            if (allowed === true) {
                await subscribe(registration);
            }
        }
    }
    
    // === Публичный API ===
    return {
        init: init,
        isSubscribed: function() { return isSubscribed; },
        isSupported: function() { return isSupported; },
        unsubscribe: unsubscribe,
        sendTestNotification: sendTestNotification,
        getPermissionStatus: getPermissionStatus,
        requestPermission: requestPermission,
        registerServiceWorker: registerServiceWorker,
        subscribe: subscribe
    };
})();
