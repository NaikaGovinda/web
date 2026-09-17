# Настройка входа через VK и Google

## Что было добавлено

### Frontend
1. **login.html** - Добавлены кнопки "Войти через VK" и "Войти через Google"
2. **auth_vk_callback.html** - Страница обработки callback от VK
3. **auth_google_callback.html** - Страница обработки callback от Google
4. **style.css** - Стили для социальных кнопок

### Backend (PHP API)
1. **api/auth_vk_token.php** - Обмен кода авторизации VK на access token
2. **api/auth_vk_login.php** - Вход/регистрация пользователя через VK
3. **api/auth_google_token.php** - Обмен кода авторизации Google на access token
4. **api/auth_google_login.php** - Вход/регистрация пользователя через Google

### Database
1. **migrations/008_add_social_login_columns.sql** - Миграция для добавления колонок vk_id, google_id, login_source

## Инструкция по настройке

### 1. Примените миграцию базы данных

Выполните SQL-файл в phpMyAdmin или через командную строку:

```bash
mysql -u root -p namahatta_db < migrations/008_add_social_login_columns.sql
```

Или откройте файл `migrations/008_add_social_login_columns.sql` в phpMyAdmin и выполните SQL-запрос.

### 2. Настройте VK OAuth

1. Зайдите на [dev.vk.com](https://dev.vk.com/)
2. Создайте новое приложение (тип: "Веб-приложение")
3. Перейдите в "Настройки" приложения
4. Скопируйте **ID приложения** (Client ID)
5. Скопируйте **Защищенный ключ** (Client Secret)
6. Добавьте **URI переадресации** (Redirect URI):
   ```
   http://yourdomain.com/auth_vk_callback.html
   ```
   Или для локальной разработки:
   ```
   http://localhost/namahat/auth_vk_callback.html
   ```

7. Откройте файл **api/auth_vk_token.php** и обновите константы:
   ```php
   define('VK_APP_ID', 'ВАШ_APP_ID'); // Например: 22209065
   define('VK_APP_SECRET', 'ВАШ_SECRET_KEY');
   define('VK_REDIRECT_URI', 'http://yourdomain.com/auth_vk_callback.html');
   ```

### 3. Настройте Google OAuth

1. Зайдите в [Google Cloud Console](https://console.cloud.google.com/)
2. Создайте новый проект или выберите существующий
3. Перейдите в **APIs & Services > Credentials**
4. Нажмите **Create Credentials > OAuth client ID**
5. Если еще не настроили экран согласия OAuth, настройте его:
   - Добавьте email поддержки
   - Добавьте домены (для тестирования можно добавить localhost)
6. Тип приложения: **Web application**
7. Добавьте **Authorized redirect URIs**:
   ```
   http://yourdomain.com/auth_google_callback.html
   ```
   Или для локальной разработки:
   ```
   http://localhost/namahat/auth_google_callback.html
   ```

8. Скопируйте **Client ID** и **Client Secret**

9. Откройте файл **api/auth_google_token.php** и обновите константы:
   ```php
   define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
   define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
   define('GOOGLE_REDIRECT_URI', 'http://yourdomain.com/auth_google_callback.html');
   ```

### 4. Настройте CORS (если нужно)

Если фронтенд и бэкенд находятся на разных доменах, убедитесь, что PHP-файлы разрешают запросы с вашего домена. В уже созданных файлах есть заголовки:

```php
header('Access-Control-Allow-Origin: *');
```

Для продакшена замените `*` на конкретный домен.

### 5. Проверьте работу

1. Откройте `login.html` в браузере
2. Нажмите "Войти через VK" или "Войти через Google"
3. Разрешите доступ к данным
4. Вы должны быть автоматически перенаправлены в профиль

## Структура таблицы users

После применения миграции таблица `users` будет иметь следующие колонки для социального входа:

- `vk_id` - ID пользователя во VK (BIGINT)
- `google_id` - ID пользователя в Google (VARCHAR)
- `login_source` - Источник входа: 'email', 'vk', или 'google'

## Безопасность

- Токены хранятся в таблице `auth_tokens` и истекают через 30 дней
- Пароли не используются для социального входа
- Email генерируется автоматически, если пользователь не предоставил свой
- Все запросы к API валидируются

## Известные ограничения

- Для локальной разработки используйте `http://localhost` вместо `yourdomain.com`
- Для Google OAuth может потребоваться подтверждение домена в Google Console
- VK требует верификации приложения для продакшена

## Troubleshooting

### Ошибка "Код авторизации не предоставлен"
- Убедитесь, что Redirect URI совпадает в настройках приложения и в PHP-файлах

### Ошибка "Недействительный токен"
- Проверьте Client ID и Client Secret
- Убедитесь, что приложение активное в настройках VK/Google

### Пользователь не создается
- Проверьте, что миграция базы данных применена
- Посмотрите ошибки в консоли браузера и в PHP error log
