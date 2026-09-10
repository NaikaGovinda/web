// theme.js — управление персональной темой пользователя



function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function applyTheme(theme) {
    const app = document.getElementById('app');
    if (!app) return;

    // Сброс фона
    app.style.background = '';
    app.style.backgroundColor = '';

    if (theme.background_type === 'color') {
        app.style.backgroundColor = theme.background_value || '#f5f0e6';
    } else if (theme.background_type === 'image') {
        // Путь к фонам: /assets/backgrounds/
        const imageUrl = `/assets/backgrounds/${escapeHtml(theme.background_value)}`;
        app.style.background = `url('${imageUrl}') center/cover fixed`;
    }
}

async function loadUserTheme() {
    try {
        const response = await fetch('/api/get_user_theme.php');
        const data = await response.json();
        if (data.success && data.theme) {
            applyTheme(data.theme);
        }
    } catch (err) {
        console.warn('Не удалось загрузить тему:', err);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadUserTheme);
} else {
    loadUserTheme();
}

// Глобальное уведомление вместо системного alert
function showToast(message) {
    const oldToast = document.getElementById('appToast');
    if (oldToast) oldToast.remove();

    const toast = document.createElement('div');
    toast.id = 'appToast';
    toast.className = 'toast-notification';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-show');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('toast-show');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

window.alert = function(message) {
    showToast(message);
};