// /assets/custom-cropper.js
let imgX = 0, imgY = 0;       
let startX = 0, startY = 0;   
let isDragging = false;       
let currentScale = 1;         
let baseWidth = 0, baseHeight = 0; 
let circleRadius = 120;       
let currentUploadUrl = '/api/update_profile.php'; 

// 1. Инициализация событий (Мышь + Смартфоны)
function initCustomCropperEvents() {
    const containerEl = document.getElementById('cropZoneContainer');
    if (!containerEl) return;

    // Очищаем старые привязки для безопасности
    containerEl.replaceWith(containerEl.cloneNode(true));
    
    // Перезаписываем чистый контейнер после клонирования
    const newContainer = document.getElementById('cropZoneContainer');
    
    // События для ПК (Мышка)
    newContainer.addEventListener('mousedown', startDrag);
    newContainer.addEventListener('mousemove', doDrag);
    window.addEventListener('mouseup', stopDrag);

    // События для смартфонов (Палец) с отключением пассивного режима, чтобы разрешить preventDefault()
    newContainer.addEventListener('touchstart', startDrag, { passive: false });
    newContainer.addEventListener('touchmove', doDrag, { passive: false });
    window.addEventListener('touchend', stopDrag);

    const zoomRange = document.getElementById('cropZoomRange');
    if (zoomRange) {
        zoomRange.oninput = function() {
            currentScale = parseFloat(this.value);
            updateImageTransform();
        };
    }
}

// 2. Открытие окна обрезки
function openCropperModal(imageSrc, uploadApiUrl) {
    const modal = document.getElementById('cropperModal');
    const img = document.getElementById('myCropImage');
    const container = document.getElementById('cropZoneContainer');
    const mask = document.getElementById('cropCircleMask');
    const border = document.getElementById('cropCircleBorder');
    const zoomInput = document.getElementById('cropZoomRange');

    if (!modal || !img || !container) return;

    if (uploadApiUrl) {
        currentUploadUrl = uploadApiUrl;
    }

    modal.style.display = 'flex';
    img.src = imageSrc;
    if (zoomInput) zoomInput.value = 1;
    currentScale = 1;

    img.onload = function() {
        const contW = container.clientWidth;
        const contH = container.clientHeight;

        circleRadius = Math.min(contW, contH) * 0.35;
        
        border.style.width = (circleRadius * 2) + 'px';
        border.style.height = (circleRadius * 2) + 'px';
        border.style.left = (contW / 2 - circleRadius) + 'px';
        border.style.top = (contH / 2 - circleRadius) + 'px';

        // ГАРАНТИРОВАННЫЙ КРУГ БЕЗ ПЕРЕНОСОВ СТРОК
        mask.style.boxShadow = 'inset 0 0 0 9999px rgba(18, 18, 20, 0.85)';
        mask.style.clipPath = `polygon(0% 0%, 0% 100%, 100% 100%, 100% 0%, 0% 0%, calc(50% - ${circleRadius}px) calc(50% - ${circleRadius}px), calc(50% + ${circleRadius}px) calc(50% - ${circleRadius}px), calc(50% + ${circleRadius}px) calc(50% + ${circleRadius}px), calc(50% - ${circleRadius}px) calc(50% + ${circleRadius}px), calc(50% - ${circleRadius}px) calc(50% - ${circleRadius}px))`;
        
        border.style.pointerEvents = 'none';
        mask.style.borderRadius = '0';
        border.style.borderRadius = '50%';

        const ratio = img.naturalWidth / img.naturalHeight;
        if (ratio > 1) {
            baseHeight = circleRadius * 2.2;
            baseWidth = baseHeight * ratio;
        } else {
            baseWidth = circleRadius * 2.2;
            baseHeight = baseWidth / ratio;
        }

        img.style.width = baseWidth + 'px';
        img.style.height = baseHeight + 'px';

        imgX = contW / 2;
        imgY = contH / 2;
        updateImageTransform();
        initCustomCropperEvents(); 
    };
}

function updateImageTransform() {
    const img = document.getElementById('myCropImage');
    if (img) {
        img.style.transform = `translate(calc(-50% + ${imgX}px), calc(-50% + ${imgY}px)) scale(${currentScale})`;
    }
}

// 3. Движок перемещения кадра
// [ИСПРАВЛЕНО] Уточненный расчет координат для ПК без нарушения логики смартфонов
function startDrag(e) {
    isDragging = true;
    
    if (e.cancelable) e.preventDefault();
    
    // Для смартфонов оставляем старую логику, для ПК берем координаты из e.clientX напрямую
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    
    startX = clientX - imgX;
    startY = clientY - imgY;
}

function doDrag(e) {
    if (!isDragging) return;
    
    if (e.cancelable) e.preventDefault();
    
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    
    imgX = clientX - startX;
    imgY = clientY - startY;
    updateImageTransform();
}

function stopDrag() { 
    isDragging = false; 
}

function closeCropperModal() {
    const modal = document.getElementById('cropperModal');
    if (modal) modal.style.display = 'none';
    const fileInput = document.getElementById('avatarFile');
    if (fileInput) fileInput.value = '';
}
/*
function saveCustomCroppedAvatar() {
    const img = document.getElementById('myCropImage');
    const container = document.getElementById('cropZoneContainer');
    if (!img || !container) return;

    const canvas = document.createElement('canvas');
    canvas.width = 400;
    canvas.height = 400;
    const ctx = canvas.getContext('2d');

    const contW = container.clientWidth;
    const contH = container.clientHeight;
    const renderW = baseWidth * currentScale;
    const renderHeight = baseHeight * currentScale;

    const cropLeftOnRender = (contW / 2 - circleRadius) - (imgX - renderW / 2);
    const cropTopOnRender = (contH / 2 - circleRadius) - (imgY - renderHeight / 2);

    const scaleFactor = img.naturalWidth / renderW;
    const sX = cropLeftOnRender * scaleFactor;
    const sY = cropTopOnRender * scaleFactor;
    const sW = (circleRadius * 2) * scaleFactor;
    const sH = (circleRadius * 2) * scaleFactor;

    ctx.drawImage(img, sX, sY, sW, sH, 0, 0, 400, 400);

    canvas.toBlob(async (blob) => {
        if (!blob) return;
        closeCropperModal();
        showToast('⏳ Сохранение фото...');

        const formData = new FormData();
        formData.append('avatar', blob, 'avatar.jpg');

        const urlParams = new URLSearchParams(window.location.search);
        const groupId = urlParams.get('id');
        if (groupId && currentUploadUrl.includes('group')) {
            formData.append('group_id', groupId);
        }

        try {
            const response = await fetch(currentUploadUrl, { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ Фото успешно обновлено!');
                let previewEl = document.getElementById('avatarPreview') || document.getElementById('groupAvatarPreview');
                const t = new Date().getTime();
                
                if (previewEl) {
                    const newSrc = (result.avatar_url || result.group_avatar_url) + '?t=' + t;
                    if (previewEl.tagName === 'DIV') {
                        const newImg = document.createElement('img');
                        newImg.id = previewEl.id;
                        newImg.style.cssText = previewEl.style.cssText;
                        newImg.style.objectFit = 'cover';
                        newImg.src = newSrc;
                        previewEl.replaceWith(newImg);
                    } else {
                        previewEl.src = newSrc;
                    }
                }
            } else {
                showToast('❌ Ошибка: ' + (result.error || 'Не удалось сохранить'));
            }
        } catch (err) {
            console.error(err);
            showToast('❌ Ошибка соединения с сервером');
        }
    }, 'image/jpeg', 0.85);
}
*/

// [ИСПРАВЛЕНО НАВЕЧНО] Абсолютно точный расчет вырезки для ПК и смартфонов без черного фона
function saveCustomCroppedAvatar() {
    const img = document.getElementById('myCropImage');
    const container = document.getElementById('cropZoneContainer');
    if (!img || !container) return;

    const canvas = document.createElement('canvas');
    canvas.width = 400;
    canvas.height = 400;
    const ctx = canvas.getContext('2d');

    // Получаем точные текущие координаты и размеры картинки на экране, какими их видит браузер
    const imgRect = img.getBoundingClientRect();
    const contRect = container.getBoundingClientRect();

    // Вычисляем, где на экране находится центр нашего круглого окна обрезки
    const circleCenterX = contRect.left + contRect.width / 2;
    const circleCenterY = contRect.top + contRect.height / 2;

    // Находим координаты левого верхнего угла рамки обрезки на экране
    const cropLeftOnScreen = circleCenterX - circleRadius;
    const cropTopOnScreen = circleCenterY - circleRadius;

    // Вычисляем смещение рамки обрезки ОТНОСИТЕЛЬНО левого верхнего угла САМОЙ картинки
    const cropLeftRelativeToImg = cropLeftOnScreen - imgRect.left;
    const cropTopRelativeToImg = cropTopOnScreen - imgRect.top;

    // Переводим это смещение и размер круга в реальные пиксели оригинального файла изображения
    const scaleFactor = img.naturalWidth / imgRect.width;
    
    const sX = cropLeftRelativeToImg * scaleFactor;
    const sY = cropTopRelativeToImg * scaleFactor;
    const sW = (circleRadius * 2) * scaleFactor;
    const sH = (circleRadius * 2) * scaleFactor;

    // Рисуем на холсте строго то, что пользователь видит внутри синей рамки
    ctx.drawImage(img, sX, sY, sW, sH, 0, 0, 400, 400);

    // Превращаем в Blob и отправляем на сервер
    canvas.toBlob(async (blob) => {
        if (!blob) return;
        closeCropperModal();
        showToast('⏳ Сохранение фото...');

        const formData = new FormData();
        formData.append('avatar', blob, 'avatar.jpg');
		// ==========================================================
        // УНИВЕРСАЛЬНЫЙ СБОР ДАННЫХ ДЛЯ КРОППЕРА В КЛИЕНТСКОМ ФАЙЛЕ
        // ==========================================================
        const profFirstName = document.getElementById('profFirstName');
        const profLastName = document.getElementById('profLastName');
        const profCity = document.getElementById('profCity');
        const profPhone = document.getElementById('profPhone');

        // Если нашли инпут имени, значит мы на странице профиля — подмешиваем все поля
        if (profFirstName) {
            formData.append('first_name', profFirstName.value.trim());
            formData.append('last_name', profLastName ? profLastName.value.trim() : '');
            formData.append('city', profCity ? profCity.value.trim() : '');
            formData.append('phone', profPhone ? profPhone.value.trim() : '');
        }
        // ==========================================================

        const urlParams = new URLSearchParams(window.location.search);
        const groupId = urlParams.get('id');
        if (groupId && currentUploadUrl.includes('group')) {
            formData.append('group_id', groupId);
        }

        try {
            // [ИСПРАВЛЕНО] Универсальный относительный путь с принудительной передачей сессии для Android WebView и ПК
            let finalUrl = currentUploadUrl || '/api/update_profile.php';
            if (finalUrl.includes('namahata.ru')) {
                finalUrl = finalUrl.substring(finalUrl.indexOf('/api/'));
            }

            const response = await fetch(finalUrl + '?v=' + Date.now(), { 
                method: 'POST', 
                credentials: 'include', // Железно привязываем куки PHPSESSID
                body: formData 
            });
            
            // Читаем ответ как текст, чтобы вырезать любые HTML-предупреждения сервера
            const cropRawText = await response.text();
            let result;
            
            try {
                // Находим начало JSON (фигурную скобку {) и отрезаем любые Parse error или session_start
                const jsonIdx = cropRawText.indexOf('{');
                if (jsonIdx === -1) throw new Error("JSON не найден");
                result = JSON.parse(cropRawText.substring(jsonIdx));
            } catch (jsonErr) {
                console.error("Сырой ответ сервера при сохранении фото:", cropRawText);
                showToast('❌ Ошибка чтения ответа сервера. Проверьте логи PHP.');
                return;
            }

            if (result.success) {
                showToast('✅ Аватар успешно обновлен!');
                
                // Мгновенно обновляем превью картинки на экране профиля
                const previewEl = document.getElementById('avatarPreview');
                const t = new Date().getTime();
                if (previewEl) {
                    if (previewEl.tagName === 'DIV') {
                        const newImg = document.createElement('img');
                        newImg.id = 'avatarPreview';
                        newImg.style.cssText = previewEl.style.cssText;
                        newImg.style.objectFit = 'cover';
                        newImg.src = result.avatar_url + '?t=' + t;
                        previewEl.replaceWith(newImg);
                    } else {
                        previewEl.src = result.avatar_url + '?t=' + t;
                    }
                }
                
                // Если на странице есть функция перезагрузки профиля — вызываем её для обновления данных
                if (typeof loadProfile === 'function') {
                    loadProfile();
                }
            } else {
                showToast('❌ Ошибка: ' + (result.error || 'Не удалось сохранить'));
            }
        } catch (err) {
            console.error(err);
            showToast('❌ Ошибка соединения с сервером');
        }
    }, 'image/jpeg', 0.85);
}