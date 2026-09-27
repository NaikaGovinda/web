# Руководство по быстрой проверке Профиля (quickstart.md)

Методы верификации управления профилем и аватаром.

---

## Тест-кейс 1: Сохранение аватара из Base64 (Критерий SC-901)

### Шаг 1: Тестовый запрос к API
Используйте curl для имитации отправки фото:
```bash
curl -X POST http://localhost/api/update_profile.php \
     -H "Content-Type: application/json" \
     -d '{"userId": 1, "avatarBase64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=="}'
```

### Шаг 2: Проверка результата
1. **Ответ API:** `{"status":"success", ...}` в течение < 1.5 сек.
2. **Файловая система:** Проверьте наличие нового файла в `backend/assets/avatars/`.
3. **БД:** `SELECT avatar_url FROM users WHERE id=1` содержит путь к новому файлу.

---

## Тест-кейс 2: Интеграция с нативной галереей (Критерий SC-902)

1. Откройте приложение Android на странице `profile.html`.
2. Нажмите на иконку фотоаппарата.
3. Выберите любое изображение в системной галерее.
4. **Ожидаемый результат:** В течение 200 мс в веб-интерфейсе вместо старого аватара появляется выбранное фото (предпросмотр).
