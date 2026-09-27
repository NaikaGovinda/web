# Модель данных: Система заявок на вступление (006-group-applications)

## 1. Схема Таблицы Заявок (SQL)

```sql
-- Таблица заявок на вступление в группу
CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Спецификация API Контрактов

### А. Подача заявки (POST /api/apply_to_group.php)
**Запрос:** `{"userId": 5, "groupId": 1}`
**Результат:** Инициация пуша лидеру группы.

### Б. Список заявок для лидера (GET /api/get_applications.php?leaderId={id})
**Ответ (JSON):**
```json
[
  {
    "id": 10,
    "candidateName": "Мария Смирнова",
    "groupName": "Бхакти-врикша Центр",
    "groupId": 1,
    "userId": 5
  }
]
```

### В. Обработка заявки (POST /api/process_application.php)
**Запрос:**
```json
{
  "applicationId": 10,
  "status": "approved"
}
```
**Действие при 'approved':**
1. `UPDATE applications SET status='approved' WHERE id=10`
2. `INSERT INTO group_members (group_id, user_id, role) VALUES (1, 5, 'участник')`

---

## 3. Нативная Модель (GroupApplicationsUIState)

```kotlin
sealed interface GroupApplicationsUIState {
    object Loading : GroupApplicationsUIState
    data class Success(val applications: List<ApplicationItem>) : GroupApplicationsUIState
    data class Error(val message: String) : GroupApplicationsUIState
}

data class ApplicationItem(
    val id: Int,
    val candidateName: String,
    val groupName: String
)
```
