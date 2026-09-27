/**
 * Sadhana Utils — shared utilities for Sadhana feature
 */

/**
 * Get current user ID from session/localStorage
 * Returns null if not authenticated
 */
export async function getCurrentUserId() {
    // Try cached user_id first
    const cached = localStorage.getItem('user_id');
    if (cached) return cached;

    // Try userInfo localStorage
    try {
        const info = JSON.parse(localStorage.getItem('userInfo') || '{}');
        if (info.id || info.user_id) {
            const userId = info.id || info.user_id;
            localStorage.setItem('user_id', userId);
            return userId;
        }
    } catch (e) {
        // ignore
    }

    // Try to fetch from auth endpoint
    try {
        const res = await fetch('/api/check_auth.php');
        if (res.ok) {
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                // Not JSON, try to find JSON in response
                const jsonIdx = text.indexOf('{');
                if (jsonIdx >= 0) {
                    data = JSON.parse(text.substring(jsonIdx));
                }
            }
            if (data) {
                const userId = data.user_id || data.id || data.uid;
                if (userId) {
                    localStorage.setItem('user_id', userId);
                    return userId;
                }
            }
        }
    } catch (e) {
        console.warn('Could not fetch user ID from auth endpoint:', e);
    }

    return null;
}

/**
 * API fetch with retry mechanism
 * @param {string} url - Request URL
 * @param {Object} options - Fetch options
 * @param {number} retries - Number of retries (default: 2)
 * @returns {Promise<any>} Parsed JSON response
 */
export async function apiFetch(url, options = {}, retries = 2) {
    // Inject auth token if available
    const token = localStorage.getItem('auth_token');
    const userId = localStorage.getItem('user_id');
    
    // Add auth_token to URL as query parameter
    if (token) {
        const separator = url.includes('?') ? '&' : '?';
        url = url + separator + 'auth_token=' + encodeURIComponent(token);
    }
    
    // Add user_id to URL as query parameter (fallback for X-User-Id header)
    if (userId) {
        const separator = url.includes('?') ? '&' : '?';
        url = url + separator + 'user_id=' + encodeURIComponent(userId);
    }

    for (let i = 0; i <= retries; i++) {
        try {
            const response = await fetch(url, options);
            
            // Handle non-JSON responses
            const contentType = response.headers.get('content-type');
            let data;
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                const jsonIdx = text.indexOf('{');
                data = jsonIdx >= 0 ? JSON.parse(text.substring(jsonIdx)) : { success: response.ok };
            }

            if (!response.ok) {
                const errorText = data.message || data.error || data.debug ? JSON.stringify(data) : 'Unknown error';
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            return data;
        } catch (error) {
            if (i === retries) throw error;
            // Exponential backoff: 500ms, 1000ms, 2000ms
            await new Promise(r => setTimeout(r, 500 * Math.pow(2, i)));
        }
    }
}

/**
 * Save data to localStorage with error handling
 * @param {string} key - Storage key
 * @param {any} data - Data to save
 */
export function saveToLocalStorage(key, data) {
    try {
        localStorage.setItem(key, JSON.stringify(data));
        return true;
    } catch (e) {
        console.error('localStorage save error:', e);
        return false;
    }
}

/**
 * Load data from localStorage
 * @param {string} key - Storage key
 * @param {any} defaultValue - Default value if key not found
 * @returns {any} Parsed data or default
 */
export function loadFromLocalStorage(key, defaultValue = null) {
    try {
        const data = localStorage.getItem(key);
        return data ? JSON.parse(data) : defaultValue;
    } catch (e) {
        console.error('localStorage load error:', e);
        return defaultValue;
    }
}

/**
 * Show toast notification
 * @param {string} message - Toast message
 * @param {string} type - Toast type: 'success', 'error', 'info'
 * @param {number} duration - Display duration in ms
 */
export function showToast(message, type = 'info', duration = 2000) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.background = type === 'error' ? '#FF3B30' : type === 'success' ? '#34C759' : '#333333';
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML
 */
export function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Validate numeric input
 * @param {string|number} value - Input value
 * @param {number} min - Minimum allowed value
 * @param {number} max - Maximum allowed value
 * @returns {number} Validated number
 */
export function validateNumber(value, min = 0, max = 9999) {
    const num = Number(value);
    if (isNaN(num)) return min;
    return Math.max(min, Math.min(max, num));
}

/**
 * Format sleep duration
 * @param {number} totalMinutes - Duration in minutes
 * @returns {string} Formatted string (e.g., "5ч 30м")
 */
export function formatSleepDuration(totalMinutes) {
    if (totalMinutes <= 0) return '—';
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}ч ${minutes}м`;
}

/**
 * Calculate sleep duration from bedtime and waketime
 * Handles midnight crossover correctly
 * @param {string} bedTime - Bedtime in HH:MM format
 * @param {string} wakeTime - Wake time in HH:MM format
 * @returns {number} Duration in minutes
 */
export function calculateSleepDuration(bedTime, wakeTime) {
    const [bedH, bedM] = bedTime.split(':').map(Number);
    const [wakeH, wakeM] = wakeTime.split(':').map(Number);
    
    let bedMinutes = bedH * 60 + bedM;
    let wakeMinutes = wakeH * 60 + wakeM;
    
    // If wake time is before or equal to bed time, assume next day
    if (wakeMinutes <= bedMinutes) {
        wakeMinutes += 24 * 60;
    }
    
    return wakeMinutes - bedMinutes;
}

/**
 * Get formatted date string in Russian
 * @returns {string} Formatted date (e.g., "27 сентября 2026, Суббота")
 */
export function getFormattedDate() {
    const now = new Date();
    const months = [
        'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
    ];
    const days = [
        'Воскресенье', 'Понедельник', 'Вторник', 'Среда',
        'Четверг', 'Пятница', 'Суббота',
    ];
    return `${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}, ${days[now.getDay()]}`;
}

/**
 * Get today's date in YYYY-MM-DD format
 * @returns {string} Today's date
 */
export function getTodayDate() {
    return new Date().toISOString().split('T')[0];
}
