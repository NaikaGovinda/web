# Модель данных: Список участников группы (008-group-members)

## 1. Спецификация API Контракта

### Эндпоинт: `GET /api/get_group_members.php?groupId={id}`

#### Формат ответа сервера (HTTP 200 OK — JSON):
```json
[
  {
    "id": 1,
    "name": "Иван Иванов",
    "spiritualName": "Ишвара Дас",
    "role": "лидер",
    "avatarUrl": "https://namahatta.ru/assets/avatars/u1.jpg"
  },
  {
    "id": 2,
    "name": "Алексей Петров",
    "spiritualName": null,
    "role": "помощник",
    "avatarUrl": "https://namahatta.ru/assets/avatars/u2.jpg"
  }
]
```

---

## 2. Нативная Модель Состояний (MembersUIState)

```kotlin
data class MemberItem(
    val id: Int,
    val name: String,
    val spiritualName: String?,
    val role: String,
    val avatarUrl: String
)

sealed interface MembersUIState {
    object Loading : MembersUIState
    data class Success(val allMembers: List<MemberItem>) : MembersUIState
    data class Error(val message: String) : MembersUIState
}
```

## 3. Маппинг Ролей (UI Logic)
- **Лидер:** Иконка `Icons.Default.Star` (Золотой цвет).
- **Помощник:** Иконка `Icons.Default.Verified` (Синий цвет).
- **Участник:** Без иконки или стандартная `Icons.Default.Person`.
