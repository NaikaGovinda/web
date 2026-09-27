// android.js — интеграция с Android-приложением через WebView
// Теперь работает через сессию + токен (пароли не передаются!)

/**
 * Получает auth_token из localStorage
 * @returns {string|null}
 */
function getAuthToken() {
    return localStorage.getItem('auth_token');
}

/**
 * Список внешних доменов, к которым НЕ добавляем токен
 * (VK ID, Google, карты, CDN — они не должны получать наш токен)
 */
const EXTERNAL_DOMAINS = [
    'id.vk.ru',
    'id.vk.com',
    'vk.com',
    'googleapis.com',
    'nominatim.openstreetmap.org',
    'photon.komoot.io',
    'unpkg.com',
    'tile.openstreetmap.org',
    'leafletjs.com',
    'api-maps.yandex.ru',
    'yastatic.net',
    'yandex.ru'
];

/**
 * Добавляет auth_token ко всем fetch запросам
 */
(function injectAuthToken() {
    const originalFetch = window.fetch;

    window.fetch = function(...args) {
        let url = args[0];
        let options = args[1] || {};

        // Исключаем запросы к внешним доменам
        const urlString = typeof url === 'string' ? url : '';
        for (let i = 0; i < EXTERNAL_DOMAINS.length; i++) {
            if (urlString.includes(EXTERNAL_DOMAINS[i])) {
                return originalFetch.apply(window, args);
            }
        }

        const token = getAuthToken();

        if (token) {
            const isFormData = options.body instanceof FormData;

            if (isFormData) {
                // Для FormData добавляем токен в URL (query parameter)
                if (typeof url === 'string' && !url.includes('?')) {
                    url = url + '?auth_token=' + encodeURIComponent(token);
                    args[0] = url;
                } else if (typeof url === 'string') {
                    url = url + '&auth_token=' + encodeURIComponent(token);
                    args[0] = url;
                }
            } else {
                // Для обычных запросов добавляем токен в заголовки
                if (options.headers instanceof Headers) {
                    if (!options.headers.has('X-Auth-Token')) {
                        options.headers.set('X-Auth-Token', token);
                    }
                } else if (options.headers && typeof options.headers === 'object') {
                    if (!options.headers['X-Auth-Token']) {
                        options.headers['X-Auth-Token'] = token;
                    }
                } else {
                    options.headers = { 'X-Auth-Token': token };
                }
                args[1] = options;
            }
        }

        return originalFetch.apply(window, args);
    };
})();

/**
 * Получает внутренний user_id для отправки в Android-приложение
 */
async function getUserIdForAndroid() {
    try {
        const res = await fetch('/api/get_user_id.php', {
            method: 'GET',
            credentials: 'include'
        });

        if (!res.ok) return null;

        const data = await res.json();

        if (data.success && typeof data.user_id === 'number') {
            return String(data.user_id);
        }
        return null;
    } catch (e) {
        return null;
    }
}

/**
 * Отправляет user_id в Android-приложение (с таймаутом 2 секунды)
 */
async function sendUserIdToAndroid() {
    var waited = 0;
    while ((!window.Android || typeof window.Android.setUser !== 'function') && waited < 2000) {
        await new Promise(function(resolve) { setTimeout(resolve, 100); });
        waited += 100;
    }

    if (!window.Android || typeof window.Android.setUser !== 'function') {
        return;
    }

    const userId = await getUserIdForAndroid();

    if (userId) {
        try {
            window.Android.setUser(userId);
        } catch (e) {}
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sendUserIdToAndroid);
} else {
    sendUserIdToAndroid();
}

if (typeof window.Android === 'object' && window.Android !== null) {
    sendUserIdToAndroid().catch(console.error);
}

ensureAuthToken().catch(console.error);

/**
 * Вызывается Android-приложением после выбора фото
 */
function receiveImageFromAndroid(imageUri) {
    if (typeof openCropperModal === 'function') {
        openCropperModal(imageUri, '/api/update_profile.php');
    } else {
        console.error("Критическая ошибка: Функция openCropperModal не найдена.");
    }
}

/**
 * Информирует Android о текущем чате
 */
function updateActiveChatContextInAndroid(groupId) {
    if (typeof window.Android === 'object' && window.Android !== null && typeof window.Android.setActiveChat === 'function') {
        try {
            window.Android.setActiveChat(groupId ? String(groupId) : "");
        } catch (e) {}
    }
}

function auditCurrentPageContext() {
    const urlParams = new URLSearchParams(window.location.search);
    const groupId = urlParams.get('id');

    if (window.location.pathname.includes('group.html') && (urlParams.has('open_chat') || urlParams.has('open_private_chat'))) {
        updateActiveChatContextInAndroid(groupId);
    } else {
        updateActiveChatContextInAndroid(null);
    }
}

document.addEventListener('DOMContentLoaded', auditCurrentPageContext);

/**
 * Автоматически получает и сохраняет auth_token после входа
 */
async function ensureAuthToken() {
    if (getAuthToken()) return;

    try {
        const res = await fetch('/api/check_auth.php', {
            method: 'GET',
            credentials: 'include'
        });

        if (!res.ok) return;

        const data = await res.json();

        if (data.is_authenticated && data.auth_token) {
            localStorage.setItem('auth_token', data.auth_token);
            localStorage.setItem('user_id', String(data.user_id));

            if (typeof sendUserIdToAndroid === 'function') {
                sendUserIdToAndroid();
            }
        }
    } catch (e) {
        console.warn('[AUTH] Не удалось получить auth_token:', e);
    }
}