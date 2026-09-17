<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>Админка — Нама-Хатта</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="/android.js"></script>
</head>
<body>
<div id="app">
    <header class="app-header">
        <button class="back-btn" onclick="window.location.href='/index.html'">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </button>
        <h1 class="org-name">Админка</h1>
    </header>

    <main class="content" id="adminContent">
        <div class="loading">Загрузка...</div>
    </main>

    <nav class="bottom-bar">
        <a href="/index.html" class="bar-btn">
            <svg class="bar-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>Главная</span>
        </a>
        <a href="/namahatta.html" class="bar-btn">
            <svg class="bar-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <span>Группы</span>
        </a>
        <a href="/profile.html" class="bar-btn">
            <svg class="bar-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            <span>Профиль</span>
        </a>
    </nav>
</div>

<!-- Модальное окно группы -->
<div id="groupModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="width: 90%; max-width: 400px; max-height: 85vh; background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); margin: auto; display: flex; flex-direction: column;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e0e0e2; flex-shrink: 0;">
            <h3 id="modalTitle" style="margin: 0; font-size: 18px; text-align: center;">Создать группу</h3>
        </div>
        <div style="overflow-y: auto; padding: 20px; flex: 1;">
            <input type="hidden" id="groupId">
            <label for="groupName" style="display:block; margin-bottom:5px; font-weight:600;">Название *</label>
            <input type="text" id="groupName" class="input-field" placeholder="Например: Харе Кришна Москва" required>
            <label for="groupDesc" style="display:block; margin-bottom:5px; font-weight:600;">Описание *</label>
            <textarea id="groupDesc" class="textarea-field" placeholder="Киртаны, лекции, воскресные обеды" required></textarea>
            <label for="groupType" style="display:block; margin-bottom:5px; font-weight:600;">Тип *</label>
            <select id="groupType" class="select-field" required>
                <option value="namahatta">Нама-Хатта</option>
                <option value="bhakti_vriksha">Бхакти-Врикша</option>
            </select>
            <label style="display:block; margin-bottom:5px; font-weight:600;">
                <input type="checkbox" id="groupOnline"> Онлайн-группа
            </label>
            <div id="cityField">
                <label for="groupCity" style="display:block; margin-bottom:5px; font-weight:600;">Город *</label>
                <input type="text" id="groupCity" class="input-field" placeholder="Москва" required>
                <label for="groupAddress" style="display:block; margin-bottom:5px; font-weight:600;">Адрес (улица, дом)</label>
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px;">
                    <input type="text" id="groupAddress" class="input-field" placeholder="ул. Тверская, 1" style="flex: 1; margin-top: 0;">
                    <button type="button" id="btnOpenMap" class="btn btn-ghost" style="padding: 8px 12px; white-space: nowrap;" title="Выбрать на карте">🗺️</button>
                </div>
                <input type="hidden" id="groupLat" name="lat">
                <input type="hidden" id="groupLng" name="lng">
            </div>
            <label for="groupLeaderId" style="display:block; margin-bottom:5px; font-weight:600;">Лидер группы *</label>
            <select id="groupLeaderId" multiple size="3" class="multi-select">
                <option value="">— Выберите лидеров —</option>
            </select>
        </div>
        <div style="padding: 16px 20px; border-top: 1px solid #e0e0e2; flex-shrink: 0;">
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-ghost" id="btnCancelGroupModal" style="flex: 1; margin: 0;">Отмена</button>
                <button class="btn" id="btnSaveGroupModal" style="flex: 1; margin: 0; background: #4a90e2; color: #fff;">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно карты -->
<div id="mapModal" class="modal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: #fff; padding: 0; border-radius: 16px; width: 95%; max-width: 800px; height: 85vh; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.15); display: flex; flex-direction: column;">
        <div style="padding: 12px 16px; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px;">Выберите точку на карте</h3>
            <button id="closeMapModal" class="btn btn-ghost" style="padding: 4px 8px;">✕</button>
        </div>
        <div style="padding: 10px 16px; background: #fff; border-bottom: 1px solid #dee2e6;">
            <div style="margin-bottom: 8px;">
                <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">📍 Адрес (улица, город)</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="inputAddress" placeholder="Красноярск, ул. Мира, 15" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    <button id="btnSearchAddress" class="btn" style="padding: 8px 16px; font-size: 13px; white-space: nowrap;">🔍 Найти</button>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">Широта (Lat)</label>
                    <input type="number" step="any" id="inputLat" placeholder="56.0153" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">Долгота (Lng)</label>
                    <input type="number" step="any" id="inputLng" placeholder="92.8932" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div style="align-self: flex-end;">
                    <button id="btnGoToCoords" class="btn btn-ghost" style="padding: 8px 16px; font-size: 13px;">📌 Перейти</button>
                </div>
            </div>
        </div>
        <div id="map" style="flex: 1; width: 100%;"></div>
        <div style="padding: 12px 16px; border-top: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa;">
            <div style="font-size: 13px; color: #666;">
                <span id="mapCoords">📍 Координаты: </span>
                <span id="mapAddress" style="color: #333;">Кликните на карту</span>
            </div>
            <div style="display: flex; gap: 8px;">
                <button id="btnUseMyLocation" class="btn btn-ghost" style="padding: 6px 12px; font-size: 13px;">📍 Моё местоположение</button>
                <button id="btnSelectMapPoint" class="btn" style="padding: 6px 16px; font-size: 13px;">✓ Выбрать</button>
            </div>
        </div>
    </div>
</div>

<!-- Кастомное окно подтверждения удаления ГРУППЫ -->
<div id="customGroupDeleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div class="card" style="width: 85%; max-width: 320px; text-align: center; padding: 20px; border-radius: 16px; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.3); margin: auto;">
        <h3 style="margin-top: 0; font-size: 18px;">Удалить группу?</h3>
        <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Вы действительно хотите удалить эту группу? Все связанные участники и события будут стерты навсегда.</p>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-ghost" id="btnCancelGroupDelete" style="flex: 1; margin: 0;">Отмена</button>
            <button class="btn btn-danger" id="btnConfirmGroupDelete" style="flex: 1; margin: 0; background: #ff3b30; color: #fff; border: none;">Удалить</button>
        </div>
    </div>
</div>

<script>
    // Защита от XSS
    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    let pendingGroupIdToDelete = null;

    async function loadAdmin() {
        try {
            const authToken = localStorage.getItem('auth_token') || '';

            const authRes = await fetch('/api/check_auth.php');
            const authData = await authRes.json();

            if (!authData.is_authenticated) {
                window.location.href = '/login.html';
                return;
            }

            const res = await fetch('/api/get_admin_data.php');
            const data = await res.json();

            if (!data.success) {
                document.getElementById('adminContent').innerHTML = `<p class="error-state">${escapeHtml(data.error || 'Доступ запрещён')}</p>`;
                return;
            }

            let html = `
          <div class="card">
            <div class="js-accordion-header" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer; user-select:none;" onclick="toggleAccordion(this)">
              <div style="display:flex; align-items:center; gap: 10px;">
                <span style="font-size: 18px;">👥</span>
                <h3 style="margin: 0; font-size: 17px;">Группы (${data.groups.length})</h3>
              </div>
              <span class="js-accordion-arrow" style="transition:transform 0.2s ease; font-size:12px; color:var(--color-muted);">▶</span>
            </div>
            <div class="js-accordion-content" style="max-height: 0px; overflow: hidden; transition: max-height 0.3s ease;">
              <div style="padding-top: 12px; max-height: 400px; overflow-y: auto;">
                <button class="btn" id="btnCreateGroup" style="margin-bottom: 12px;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                  Создать группу
                </button>
                <div id="groupsList"></div>
              </div>
            </div>
          </div>

          <div class="card">
            <h3>📬 Все заявки (${data.applications?.length || 0})</h3>
            <div id="applicationsList"></div>
          </div>

          <div class="card" style="margin-top: 20px; border-top: 2px solid var(--color-primary, #4a90e2);">
            <h3>👑 Управление ролями пользователей</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Здесь вы можете изменить глобальный статус любого преданного в системе.</p>
            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 15px;">
              <label style="font-weight: 600; font-size: 13px;">1. Выберите пользователя:</label>
              <select id="assignLeaderUserId" class="select-field" style="width: 100%; height: 40px; margin: 0; padding: 5px 10px; border-radius: 8px; border: 1px solid #ddd;">
                <option value="">-- Загрузка пользователей... --</option>
              </select>
              <label style="font-weight: 600; font-size: 13px;">2. Назначить глобальную роль:</label>
              <select id="assignUserRoleValue" class="select-field" style="width: 100%; height: 40px; margin: 0; padding: 5px 10px; border-radius: 8px; border: 1px solid #ddd;">
                <option value="user">Обычный пользователь (Участник)</option>
                <option value="leader">Лидер (Управление группами и событиями)</option>
                <option value="admin">Главный Администратор (Доступ в админку)</option>
                <option value="observer">Наблюдатель (только просмотр, без права писать)</option>
              </select>
            </div>
            <button type="button" class="btn btn-block" onclick="executeChangeUserRole()" style="background: #4a90e2; color: #fff; font-weight: 600;">
              Сохранить новую роль пользователя 💾
            </button>
          </div>
        `;

            document.getElementById('adminContent').innerHTML = html;
            loadUsersForRoleManagement();
            document.getElementById('btnCreateGroup').addEventListener('click', createGroup);

            const groupsEl = document.getElementById('groupsList');
            if (data.groups && data.groups.length > 0) {
                data.groups.forEach(g => {
                    const leaders = g.leaders?.map(l => `${l.first_name} ${l.last_name || ''}`.trim()).join(', ') || '—';
                    const cityInfo = g.city ? `📍 ${escapeHtml(g.city)}` : '';
                    const metaInfo = [cityInfo, `👥 ${g.member_count || 0}`, `Лидеры: ${escapeHtml(leaders)}`].filter(Boolean).join(' | ');
                    const onlineBadge = g.is_online ? '<span style="font-size: 12px; color: var(--color-muted);">(онлайн)</span>' : '';
                    groupsEl.innerHTML += `
            <div class="application-item" style="display:flex; justify-content:space-between; align-items:flex-start; padding: 12px 0; border-bottom: 0.5px solid var(--color-border);">
              <div class="application-item-content" style="flex: 1;">
                <strong style="font-size: 15px; font-weight: 600;">${escapeHtml(g.name)} ${onlineBadge}</strong>
                <small style="display:block; color:var(--color-muted); font-size: 13px; margin-top: 4px;">${escapeHtml(metaInfo)}</small>
              </div>
              <div style="display:flex; gap: 8px; align-items:center; margin-left: 12px; flex-shrink: 0;">
                <button class="btn btn-ghost" onclick="editGroup(${g.id})" style="padding: 6px 12px; border-radius: 8px; border: 1px solid #ccc; background: #f5f5f7; color: #666; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;">Редактировать</button>
                <button class="btn" onclick="deleteGroup(${g.id})" style="padding: 6px 12px; border-radius: 8px; border: 1px solid #e0a0a0; background: #fde8e8; color: #c62828; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;">Удалить</button>
              </div>
            </div>
          `;
                });
            } else {
                groupsEl.innerHTML = '<p style="color: var(--color-muted); text-align: center; padding: 20px 0;">Нет созданных групп</p>';
            }

            const appsEl = document.getElementById('applicationsList');
            if (data.applications && data.applications.length) {
                data.applications.forEach(a => {
                    let status = a.status === 'approved' ? '✅' : a.status === 'rejected' ? '❌' : '⏳';
                    appsEl.innerHTML += `
              <div class="application-item">
                <strong>${escapeHtml(a.group_name)}</strong> → ${escapeHtml(a.applicant_name)}<br>
                <small>${status} ${escapeHtml(a.message)}</small>
              </div>
            `;
                });
            } else {
                appsEl.innerHTML = '<p>Нет заявок для отображения</p>';
            }

        } catch (e) {
            console.error(e);
            document.getElementById('adminContent').innerHTML = '<p class="error-state">Ошибка загрузки</p>';
        }
    }

    async function loadUsersForRoleManagement() {
        const userSelect = document.getElementById('assignLeaderUserId');
        if (!userSelect) return;

        try {
            const response = await fetch('/api/admin_get_all_users.php', {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.success) {
                let userHtml = '<option value="">-- Выберите пользователя --</option>';
                data.users.forEach(u => {
                    let roleText = ' [Пользователь]';
                    if (parseInt(u.is_admin) === 1) {
                        roleText = ' [АДМИН]';
                    } else if (u.role === 'leader') {
                        roleText = ' [ЛИДЕР]';
                    } else if (u.role === 'observer') {
                        roleText = ' [НАБЛЮДАТЕЛЬ]';
                    }
                    const fullName = `${u.first_name || ''} ${u.last_name || ''}`.trim();
                    userHtml += `<option value="${u.id}">ID ${u.id} | ${escapeHtml(fullName)} (${escapeHtml(u.email)})${roleText}</option>`;
                });
                userSelect.innerHTML = userHtml;
            } else {
                userSelect.innerHTML = '<option value="">Ошибка загрузки списка</option>';
            }
        } catch (err) {
            console.error(err);
            userSelect.innerHTML = '<option value="">Ошибка сети</option>';
        }
    }

    async function executeChangeUserRole() {
        const userId = document.getElementById('assignLeaderUserId').value;
        const roleValue = document.getElementById('assignUserRoleValue').value;

        if (!userId || !roleValue) {
            showToast('⚠️ Выберите пользователя из списка');
            return;
        }

        try {
            const response = await fetch('/api/admin_assign_leader.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ user_id: userId, role: roleValue })
            });

            const result = await response.json();
            if (result.success) {
                showToast('✅ Глобальная роль пользователя успешно изменена!');
                loadAdmin();
            } else {
                showToast('❌ Ошибка: ' + (result.error || 'не удалось изменить роль'));
            }
        } catch (err) {
            console.error(err);
            showToast('❌ Сетевой сбой при отправке данных');
        }
    }

    async function createGroup() {
        document.getElementById('modalTitle').textContent = 'Создать группу';
        document.getElementById('groupId').value = '';
        document.getElementById('groupName').value = '';
        document.getElementById('groupDesc').value = '';
        document.getElementById('groupType').value = 'namahatta';
        document.getElementById('groupOnline').checked = false;
        document.getElementById('groupCity').value = '';
        document.getElementById('groupAddress').value = '';
        document.getElementById('groupLat').value = '';
        document.getElementById('groupLng').value = '';
        toggleCityField();

        const select = document.getElementById('groupLeaderId');
        try {
            const res = await fetch('/api/get_leaders.php');
            const data = await res.json();
            select.innerHTML = '<option value="">— Выберите лидеров —</option>';
            if (data.success && data.leaders) {
                data.leaders.forEach(l => {
                    const name = `${l.first_name || ''} ${l.last_name || ''}`.trim();
                    const option = document.createElement('option');
                    option.value = l.id;
                    option.textContent = name;
                    select.appendChild(option);
                });
            }
        } catch (err) {
            console.error('Ошибка загрузки лидеров:', err);
            select.innerHTML = '<option value="">Ошибка загрузки</option>';
        }

        document.getElementById('groupModal').style.display = 'flex';
    }

    async function editGroup(id) {
        try {
            const res = await fetch(`/api/get_group_for_edit.php?id=${id}`);
            const data = await res.json();

            if (!data.success) {
                showToast('❌ Ошибка: ' + (data.error || 'нет доступа'));
                return;
            }

            const g = data.group;
            document.getElementById('modalTitle').textContent = 'Редактировать группу';
            document.getElementById('groupId').value = g.id;
            document.getElementById('groupName').value = g.name;
            document.getElementById('groupDesc').value = g.description;
            document.getElementById('groupType').value = g.type;
            document.getElementById('groupOnline').checked = parseInt(g.is_online) === 1;
            document.getElementById('groupCity').value = g.city || '';
            document.getElementById('groupAddress').value = g.address || '';
            document.getElementById('groupLat').value = g.lat !== null && g.lat !== undefined ? g.lat : '';
            document.getElementById('groupLng').value = g.lng !== null && g.lng !== undefined ? g.lng : '';

            const leadersRes = await fetch('/api/get_leaders.php');
            const leadersData = await leadersRes.json();
            const select = document.getElementById('groupLeaderId');
            select.innerHTML = '<option value="">— Выберите лидеров —</option>';

            if (leadersData.success && leadersData.leaders) {
                leadersData.leaders.forEach(l => {
                    const name = `${l.first_name || ''} ${l.last_name || ''}`.trim();
                    const option = document.createElement('option');
                    option.value = l.id;
                    option.textContent = name;
                    if (g.leaders && g.leaders.map(Number).includes(parseInt(l.id))) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            }

            document.getElementById('groupModal').style.display = 'flex';
        } catch (err) {
            console.error(err);
            showToast('❌ Не удалось загрузить данные группы');
        }
    }

    function toggleCityField() {}

    let isSaving = false;

    async function saveGroup() {
        const button = document.getElementById('btnSaveGroupModal');
        if (!button || isSaving) return;

        isSaving = true;
        const originalText = button.textContent;

        try {
            const city = document.getElementById('groupCity')?.value.trim() || '';
            const address = document.getElementById('groupAddress')?.value.trim() || '';
            let latVal = null;
            let lngVal = null;
            const existingLat = document.getElementById('groupLat')?.value;
            const existingLng = document.getElementById('groupLng')?.value;

            if (existingLat && existingLng) {
                latVal = parseFloat(existingLat);
                lngVal = parseFloat(existingLng);
            } else {
                button.textContent = 'Поиск координат...';
                button.disabled = true;

                try {
                    const cleanCity = city.toLowerCase() === 'онлайн' ? '' : city;
                    const cleanAddress = address.toLowerCase() === 'онлайн' ? '' : address;
                    let searchQuery = '';
                    if (cleanAddress && cleanCity && cleanAddress.toLowerCase().includes(cleanCity.toLowerCase())) {
                        searchQuery = cleanAddress;
                    } else if (cleanCity && cleanAddress) {
                        searchQuery = `${cleanCity}, ${cleanAddress}`;
                    } else {
                        searchQuery = cleanAddress || cleanCity;
                    }
                    searchQuery = searchQuery.replace(/\s+/g, ' ').trim();

                    if (searchQuery) {
                        try {
                            var urlArray = [
                                "https://", "nominatim.", "openstreetmap.org", "/search?q=",
                                encodeURIComponent(searchQuery), "&format=json&limit=1&accept-language=ru"
                            ];
                            const controller = new AbortController();
                            const timeout = setTimeout(() => controller.abort(), 8000);
                            const geoRes = await fetch(urlArray.join(""), { signal: controller.signal });
                            clearTimeout(timeout);
                            const geoData = await geoRes.json();
                            if (geoData && Array.isArray(geoData) && geoData.length > 0 && geoData[0]) {
                                latVal = parseFloat(geoData[0].lat);
                                lngVal = parseFloat(geoData[0].lon);
                            }
                        } catch (nominatimErr) {
                            console.warn('Nominatim failed, trying Photon:', nominatimErr);
                            try {
                                const photonUrl = `https://photon.komoot.io/api/?q=${encodeURIComponent(searchQuery)}&limit=1`;
                                const controller2 = new AbortController();
                                const timeout2 = setTimeout(() => controller2.abort(), 5000);
                                const geoRes2 = await fetch(photonUrl, { signal: controller2.signal });
                                clearTimeout(timeout2);
                                const photonData = await geoRes2.json();
                                if (photonData && photonData.features && photonData.features.length > 0) {
                                    const f = photonData.features[0];
                                    latVal = parseFloat(f.geometry.coordinates[1]);
                                    lngVal = parseFloat(f.geometry.coordinates[0]);
                                }
                            } catch (photonErr) {
                                console.error('Photon also failed:', photonErr);
                            }
                        }
                    }
                } catch (geoErr) {
                    console.error('Ошибка геокодирования:', geoErr);
                }
            }

            button.textContent = 'Сохранение...';
            const id = document.getElementById('groupId').value;
            const isEdit = !!id;

            const data = {
                name: document.getElementById('groupName').value.trim(),
                description: document.getElementById('groupDesc').value.trim(),
                type: document.getElementById('groupType').value,
                is_online: document.getElementById('groupOnline').checked ? 1 : 0,
                city: city,
                address: address,
                leader_ids: Array.from(document.getElementById('groupLeaderId').selectedOptions)
                    .map(opt => parseInt(opt.value))
                    .filter(v => !isNaN(v)),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
                id: id ? parseInt(id) : null,
                lat: latVal,
                lng: lngVal
            };

            if (!data.name || !data.description || data.leader_ids.length === 0) {
                showToast('⚠️ Заполните обязательные поля и выберите лидера');
                isSaving = false;
                button.textContent = originalText;
                button.disabled = false;
                return;
            }

            const response = await fetch('/api/save_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showToast(isEdit ? '✅ Группа успешно обновлена!' : '🎉 Группа успешно создана!');
                document.getElementById('groupModal').style.display = 'none';
                loadAdmin();
            } else {
                showToast('❌ Ошибка бэкенда: ' + (result.error || 'неизвестный сбой'));
            }
        } catch (err) {
            console.error("Глобальный сбой функции saveGroup:", err);
            showToast('❌ Не удалось сохранить данные группы.');
        } finally {
            isSaving = false;
            if (button) {
                button.textContent = originalText;
                button.disabled = false;
            }
        }
    }

    function cancelGroup() {
        document.getElementById('groupModal').style.display = 'none';
    }

    function toggleAccordion(header) {
        const content = header.nextElementSibling;
        const inner = content.querySelector('[style*="max-height: 400px"]');
        const arrow = header.querySelector('.js-accordion-arrow');
        if (content && inner && arrow) {
            const isExpanded = content.style.maxHeight === '0px';
            if (isExpanded) {
                content.style.maxHeight = (inner.scrollHeight + 50) + 'px';
            } else {
                content.style.maxHeight = '0px';
            }
            arrow.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(90deg)';
        }
    }

    // ===== КАРТА =====
    let mapPickerInstance = null;
    let mapPickerMarker = null;
    let selectedLat = null;
    let selectedLng = null;

    async function loadLeafletAsync() {
        return new Promise((resolve, reject) => {
            if (typeof L !== 'undefined') { resolve(); return; }
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            css.onload = () => {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.onload = () => resolve();
                script.onerror = reject;
                document.head.appendChild(script);
            };
            css.onerror = reject;
            document.head.appendChild(css);
        });
    }

    async function openMapPicker() {
        try {
            await loadLeafletAsync();
        } catch (e) {
            showToast('❌ Ошибка загрузки карты');
            return;
        }

        document.getElementById('mapModal').style.display = 'flex';
        document.getElementById('mapAddress').textContent = 'Кликните на карту';

        if (mapPickerInstance) {
            setTimeout(() => mapPickerInstance.invalidateSize(), 100);
            return;
        }

        try {
            const mapContainer = document.getElementById('map');
            mapContainer.innerHTML = '';
            let center = [55.751244, 37.618423];
            let zoom = 12;

            if (navigator.geolocation) {
                try {
                    const pos = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000, enableHighAccuracy: true });
                    });
                    center = [pos.coords.latitude, pos.coords.longitude];
                } catch (e) {
                    console.log('Геолокация недоступна');
                }
            }

            mapPickerInstance = L.map('map', { zoomControl: true, attributionControl: false }).setView(center, zoom);
            document.getElementById('inputLat').value = center[0].toFixed(6);
            document.getElementById('inputLng').value = center[1].toFixed(6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mapPickerInstance);

            mapPickerInstance.on('click', async function (e) {
                const { lat, lng } = e.latlng;
                selectedLat = lat;
                selectedLng = lng;

                if (mapPickerMarker) {
                    mapPickerMarker.setLatLng([lat, lng]);
                } else {
                    mapPickerMarker = L.marker([lat, lng]).addTo(mapPickerInstance);
                }

                document.getElementById('mapCoords').textContent = `📍 ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                document.getElementById('inputLat').value = lat.toFixed(6);
                document.getElementById('inputLng').value = lng.toFixed(6);

                try {
                    const urlArray = [`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=ru`];
                    const controller = new AbortController();
                    const timeout = setTimeout(() => controller.abort(), 8000);
                    const geoRes = await fetch(urlArray.join(""), { signal: controller.signal });
                    clearTimeout(timeout);
                    const data = await geoRes.json();
                    if (data && data.display_name) {
                        document.getElementById('mapAddress').textContent = data.display_name.substring(0, 80);
                    }
                } catch (err) {
                    console.error('Ошибка геокодирования:', err);
                }
            });

            document.getElementById('btnUseMyLocation').onclick = async function () {
                if (!navigator.geolocation) {
                    showToast('❌ Геолокация не поддерживается');
                    return;
                }
                try {
                    const pos = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 8000, enableHighAccuracy: true });
                    });
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    document.getElementById('inputLat').value = lat.toFixed(6);
                    document.getElementById('inputLng').value = lng.toFixed(6);
                    mapPickerInstance.setView([lat, lng], 16);
                    mapPickerInstance.fire('click', { latlng: L.latLng(lat, lng) });
                } catch (err) {
                    showToast('❌ Не удалось определить местоположение');
                }
            };

            document.getElementById('btnSelectMapPoint').onclick = async function () {
                if (selectedLat !== null && selectedLng !== null) {
                    document.getElementById('groupLat').value = selectedLat;
                    document.getElementById('groupLng').value = selectedLng;

                    try {
                        const urlArray = [`https://nominatim.openstreetmap.org/reverse?lat=${selectedLat}&lon=${selectedLng}&format=json&accept-language=ru`];
                        const controller = new AbortController();
                        const timeout = setTimeout(() => controller.abort(), 8000);
                        const geoRes = await fetch(urlArray.join(""), { signal: controller.signal });
                        clearTimeout(timeout);
                        const geoData = await geoRes.json();
                        if (geoData && geoData.display_name) {
                            const addr = geoData.address || {};
                            const street = addr.road || '';
                            const house = addr.house_number || '';
                            if (street) {
                                document.getElementById('groupAddress').value = `${street}${house ? ', ' + house : ''}`;
                            } else {
                                document.getElementById('groupAddress').value = geoData.display_name.substring(0, 100);
                            }
                        }
                    } catch (err) {
                        console.error('Ошибка получения адреса:', err);
                    }

                    closeMapPicker();
                    showToast('✅ Точка выбрана');
                } else {
                    showToast('⚠️ Сначала выберите точку на карте');
                }
            };

            document.getElementById('btnGoToCoords').onclick = function () {
                const lat = parseFloat(document.getElementById('inputLat').value);
                const lng = parseFloat(document.getElementById('inputLng').value);

                if (isNaN(lat) || isNaN(lng)) {
                    showToast('⚠️ Введите корректные координаты');
                    return;
                }

                mapPickerInstance.setView([lat, lng], 16);
                selectedLat = lat;
                selectedLng = lng;

                if (mapPickerMarker) {
                    mapPickerMarker.setLatLng([lat, lng]);
                } else {
                    mapPickerMarker = L.marker([lat, lng]).addTo(mapPickerInstance);
                }

                document.getElementById('mapCoords').textContent = `📍 ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            };

            document.getElementById('btnSearchAddress').onclick = async function () {
                const address = document.getElementById('inputAddress').value.trim();
                if (!address) {
                    showToast('⚠️ Введите адрес');
                    return;
                }

                try {
                    const urlArray = [
                        "https://", "nominatim.", "openstreetmap.org", "/search?q=",
                        encodeURIComponent(address), "&format=json&limit=1&accept-language=ru"
                    ];
                    const controller = new AbortController();
                    const timeout = setTimeout(() => controller.abort(), 8000);
                    const geoRes = await fetch(urlArray.join(""), { signal: controller.signal });
                    clearTimeout(timeout);
                    const geoData = await geoRes.json();

                    if (geoData && geoData.length > 0 && geoData[0]) {
                        const lat = parseFloat(geoData[0].lat);
                        const lng = parseFloat(geoData[0].lon);

                        mapPickerInstance.setView([lat, lng], 16);
                        selectedLat = lat;
                        selectedLng = lng;

                        if (mapPickerMarker) {
                            mapPickerMarker.setLatLng([lat, lng]);
                        } else {
                            mapPickerMarker = L.marker([lat, lng]).addTo(mapPickerInstance);
                        }

                        document.getElementById('mapCoords').textContent = `📍 ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                        document.getElementById('inputLat').value = lat.toFixed(6);
                        document.getElementById('inputLng').value = lng.toFixed(6);
                        document.getElementById('mapAddress').textContent = geoData[0].display_name?.substring(0, 80) || address;
                        showToast('✅ Адрес найден');
                    } else {
                        showToast('⚠️ Адрес не найден');
                    }
                } catch (err) {
                    console.error('Ошибка поиска адреса:', err);
                    showToast('❌ Ошибка при поиске');
                }
            };

            setTimeout(() => mapPickerInstance.invalidateSize(), 100);
        } catch (err) {
            console.error('Ошибка инициализации карты:', err);
            showToast('❌ Ошибка загрузки карты');
        }
    }

    function closeMapPicker() {
        document.getElementById('mapModal').style.display = 'none';
    }

    async function deleteGroup(id) {
        pendingGroupIdToDelete = id;
        const modal = document.getElementById('customGroupDeleteModal');
        if (modal) modal.style.display = 'flex';
    }

    document.getElementById('btnCancelGroupDelete')?.addEventListener('click', () => {
        const modal = document.getElementById('customGroupDeleteModal');
        if (modal) modal.style.display = 'none';
        pendingGroupIdToDelete = null;
    });

    document.getElementById('btnConfirmGroupDelete')?.addEventListener('click', async () => {
        if (!pendingGroupIdToDelete) return;

        const idToSend = pendingGroupIdToDelete;
        const modal = document.getElementById('customGroupDeleteModal');
        if (modal) modal.style.display = 'none';

        try {
            const response = await fetch('/api/delete_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ group_id: idToSend })
            });
            const result = await response.json();
            if (result.success) {
                showToast('🗑️ Группа успешно удалена');
                loadAdmin();
            } else {
                showToast('❌ Ошибка: ' + result.error);
            }
        } catch (err) {
            showToast('❌ Не удалось удалить группу');
        } finally {
            pendingGroupIdToDelete = null;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('btnSaveGroupModal')?.addEventListener('click', saveGroup);
        document.getElementById('btnCancelGroupModal')?.addEventListener('click', cancelGroup);
        document.getElementById('btnOpenMap')?.addEventListener('click', openMapPicker);
        document.getElementById('closeMapModal')?.addEventListener('click', closeMapPicker);

        document.getElementById('groupModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                cancelGroup();
            }
        });

        loadAdmin();
    });
</script>

<script>
    const IS_DEBUG = true;
    const APP_VERSION = '1.0.0';

    (function() {
        const version = IS_DEBUG ? Date.now() : APP_VERSION;
        const mainStyle = document.querySelector('link[href^="style.css"]');
        if (mainStyle) {
            mainStyle.href = 'style.css?v=' + version;
        }
        const themeScript = document.createElement('script');
        themeScript.src = 'theme.js?v=' + version;
        document.body.appendChild(themeScript);
    })();
</script>
</body>
</html>