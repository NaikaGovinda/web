// android.js — интеграция с Android-приложением через WebView
// Теперь работает только через PHP-сессию (без Telegram, без кук)

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
      console.warn('HTTP ошибка при получении user_id:', res.status);
      return null;
    }

    const data = await res.json();

    if (data.success && typeof data.user_id === 'number') {
      return String(data.user_id);
    } else {
      console.warn('Некорректный ответ от /api/get_user_id.php:', data);
      return null;
    }
  } catch (e) {
    console.warn('Не удалось получить user_id для Android:', e);
    return null;
  }
}

/**
 * Отправляет user_id в Android-приложение (с автоповтором, если мост еще не готов)
 */
async function sendUserIdToAndroid() {
    if (!window.Android || typeof window.Android.setUser !== 'function') {
        // Убираем беззвучный режим, пусть сайт скажет, видит ли он Android!
        console.log('Мост Android еще не готов, запускаем ожидание...');
        setTimeout(sendUserIdToAndroid, 500);
        return;
    }

    const userId = await getUserIdForAndroid();
    // 🔥 ДОБАВЛЕНО ДЛЯ ТЕСТА: Показывает, какой ID сайт считал из сессии
    console.log('Мост зафиксирован! Сайт считал из сессии User ID: ' + userId);

    if (userId) {
        try {
            window.Android.setUser(userId);
            console.log('УСПЕХ: user_id ' + userId + ' отправлен в Android!');
        } catch (e) {
            console.log('Ошибка вызова setUser: ' + e.message);
        }
    } else {
        console.log('Внимание: Вы залогинены на сайте? Сессия вернула пустой User ID!');
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
    console.log("Мост Android -> JS сработал. Путь к фото:", imageUri);
    
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
            console.warn("Ошибка передачи контекста чата в Android:", e);
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


