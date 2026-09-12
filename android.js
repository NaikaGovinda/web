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
 * Добавляет auth_token ко всем fetch запросам (через прокси)
 * Это глобальная настройка — все fetch в коде будут использовать токен
 */
(function injectAuthToken() {
    const originalFetch = window.fetch;
    
    window.fetch = function(...args) {
        let [url, options] = args;
        const token = getAuthToken();
        
        if (token) {
            // Добавляем токен к заголовкам
            options = options || {};
            options.headers = options.headers || {};
            
            // Если ещё нет Authorization, добавляем наш токен
            if (!options.headers['X-Auth-Token']) {
                options.headers['X-Auth-Token'] = token;
            }
            
            // Обновляем args, чтобы fetch получил изменённые options
            args[1] = options;
        }
        
        return originalFetch.apply(window, args);
    };
})();

/**
 * Получает внутренний user_id для отправки в Android-приложение
 * Использует /api/get_user_id.php, который проверяет сессию.
 * @returns {Promise<string|null>}
 */
async function getUserIdForAndroid() {
  try {
    const res = await fetch('/api/get_user_id.php', {
      method: 'GET',
      credentials: 'include' // ← важно: передаёт куки сессии (PHPSESSID)
    });

    if (!res.ok) {
      return null;
    }

    const data = await res.json();

    if (data.success && typeof data.user_id === 'number') {
      return String(data.user_id);
    } else {
      return null;
    }
  } catch (e) {
    return null;
  }
}

/**
 * Отправляет user_id в Android-приложение (с автоповтором, если мост еще не готов)
 */
async function sendUserIdToAndroid() {
    if (!window.Android || typeof window.Android.setUser !== 'function') {
        setTimeout(sendUserIdToAndroid, 500);
        return;
    }

    const userId = await getUserIdForAndroid();

    if (userId) {
        try {
            window.Android.setUser(userId);
        } catch (e) {
            // silently fail
        }
    }
}

// 🔥 ИСПРАВЛЕНО: Надежный цикличный запуск при старте любой страницы
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sendUserIdToAndroid);
} else {
    sendUserIdToAndroid();
}

// Автоматический вызов при загрузке (если Android-мост доступен)
if (typeof window.Android === 'object' && window.Android !== null) {
  sendUserIdToAndroid().catch(console.error);
}

/**
 * 🔥 ДОБАВЛЕНО ДЛЯ ГАЛЕРЕИ:
 * Эту функцию автоматически вызывает Android-приложение (MainActivity.kt)
 * после того, как пользователь успешно выбрал фотографию в системной галерее.
 * 
 * @param {string} imageUri - Внутренний локальный путь к изображению в Android
 */
function receiveImageFromAndroid(imageUri) {
    // Проверяем, загружен ли наш кастомный кроппер на странице
    if (typeof openCropperModal === 'function') {
        
        // Вызываем функцию открытия кроппера из /assets/custom-cropper.js
        // В качестве API-урла передаем стандартный адрес обновления профиля
        openCropperModal(imageUri, '/api/update_profile.php');
        
    } else {
        console.error("Критическая ошибка: Функция openCropperModal не найдена.");
    }
}

/**
 * 🔥 ДОБАВЛЕНО ДЛЯ УВЕДОМЛЕНИЙ ЧАТА:
 * Информирует Android-приложение о том, в каком чате сейчас находится пользователь.
 * @param {string|null} groupId - ID текущей группы или null, если чат закрыт
 */
function updateActiveChatContextInAndroid(groupId) {
    if (typeof window.Android === 'object' && window.Android !== null && typeof window.Android.setActiveChat === 'function') {
        try {
            window.Android.setActiveChat(groupId ? String(groupId) : "");
        } catch (e) {
            // silently fail
        }
    }
}

/**
 * Перехватчик для глобального отслеживания изменения хэша или параметров URL
 */
function auditCurrentPageContext() {
    const urlParams = new URLSearchParams(window.location.search);
    const groupId = urlParams.get('id');
    
    // Если мы на странице group.html и открыт чат
    if (window.location.pathname.includes('group.html') && (urlParams.has('open_chat') || urlParams.has('open_private_chat'))) {
        updateActiveChatContextInAndroid(groupId);
    } else {
        // Если ушли из чата — сбрасываем контекст в Android
        updateActiveChatContextInAndroid(null);
    }
}

// Запускаем проверку при инициализации скрипта
document.addEventListener('DOMContentLoaded', auditCurrentPageContext);


