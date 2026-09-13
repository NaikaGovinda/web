/**
 * Web Push Suppression - Управление видимостью страниц
 * Использует Page Visibility API для отправки состояния на сервер
 * Чтобы не показывать уведомления, когда пользователь уже смотрит страницу
 */

(function() {
    'use strict';
    
    let currentPage = null;
    let currentGroupId = null;
    let isVisible = true;
    let lastSentState = null;
    let sendTimeout = null;
    
    /**
     * Установить текущую страницу и контекст
     */
    function setPage(page, groupId) {
        currentPage = page;
        currentGroupId = groupId;
        
        // Отправляем состояние сразу с debounce
        debouncedSendVisibility();
    }
    
    /**
     * Отправить состояние видимости на сервер (с debounce 2 сек)
     */
    function debouncedSendVisibility() {
        if (sendTimeout) {
            clearTimeout(sendTimeout);
        }
        
        sendTimeout = setTimeout(function() {
            sendVisibilityState();
        }, 2000);
    }
    
    /**
     * Отправить состояние видимости на сервер
     */
    function sendVisibilityState() {
        if (!currentPage) return;
        
        // Проверяем, изменилось ли состояние
        const newState = `${currentPage}:${currentGroupId}:${isVisible}`;
        if (newState === lastSentState) return;
        
        lastSentState = newState;
        
        // Отправляем только если страница видна (экономим трафик)
        if (isVisible) {
            fetch('/api/update_web_push_visibility.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    page: currentPage,
                    group_id: currentGroupId || null,
                    visible: isVisible
                }),
                // keepalive позволяет отправить запрос даже при закрытии страницы
                keepalive: true
            }).catch(function(e) {
                // Игнорируем ошибки - не критично
            });
        }
    }
    
    /**
     * Обработчик изменения видимости страницы
     */
    function onVisibilityChange() {
        isVisible = !document.hidden;
        
        if (isVisible) {
            sendVisibilityState();
        }
    }
    
    // === Инициализация ===
    
    // Слушаем изменение видимости
    if (document.addEventListener) {
        document.addEventListener('visibilitychange', onVisibilityChange);
    }
    
    // Делаем API доступным для страниц
    window.WEB_PUSH_VISIBILITY = {
        setPage: setPage,
        
        /**
         * Установить текущую группу (для group.html)
         */
        setGroup: function(groupId) {
            setPage('group.html', groupId);
        },
        
        /**
         * Установить профиль (для profile.html)
         */
        setProfile: function() {
            setPage('profile.html', null);
        },
        
        /**
         * Установить чаты (для chats.html)
         */
        setChats: function() {
            setPage('chats.html', null);
        },
        
        /**
         * Установить главную (для index.html, namahatta.html)
         */
        setHome: function() {
            setPage('index.html', null);
        }
    };
    
    // Автоматически определяем страницу при загрузке
    document.addEventListener('DOMContentLoaded', function() {
        const path = window.location.pathname;
        const filename = path.substring(path.lastIndexOf('/') + 1);
        const urlParams = new URLSearchParams(window.location.search);
        
        if (filename === 'group.html') {
            const groupId = urlParams.get('id');
            if (groupId) {
                window.WEB_PUSH_VISIBILITY.setGroup(groupId);
            }
        } else if (filename === 'profile.html') {
            window.WEB_PUSH_VISIBILITY.setProfile();
        } else if (filename === 'chats.html') {
            window.WEB_PUSH_VISIBILITY.setChats();
        } else {
            window.WEB_PUSH_VISIBILITY.setHome();
        }
    });
    
})();
