# Модель данных: Панель лидера (015-leader-panel)

## 1. Спецификация API Контрактов

### А. Обновление инфо (POST /api/update_group_info.php)
**Запрос (JSON):**
```json
{
  "groupId": 1,
  "name": "Новое название",
  "description": "Новое описание",
  "city": "Москва"
}
```

### Б. Загрузка фото группы (POST /api/upload_group_photos.php)
**Запрос (JSON):**
```json
{
  "groupId": 1,
  "photoBase64": "data:image/jpeg;base64,..."
}
```

### В. Удаление участника (POST /api/remove_member.php)
**Запрос (JSON):** `{"groupId": 1, "userId": 10}`

---

## 2. SQL Логика проверки прав (isLeader)

```sql
SELECT 1 FROM `groups` 
WHERE id = :group_id AND leader_id = :current_user_id;
```

---

## 3. UI Модель вкладок
- **Tab 1: Инфо** (Форма редактирования `groups`).
- **Tab 2: Участники** (Список из `group_members` с кнопкой удаления).
- **Tab 3: Фото** (Сетка из `group_photos` с кнопкой «Добавить» и «Удалить»).
