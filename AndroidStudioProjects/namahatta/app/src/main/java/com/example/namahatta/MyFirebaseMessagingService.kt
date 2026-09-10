package com.example.namahatta

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.core.content.edit
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import okhttp3.*
import org.json.JSONObject
import java.io.IOException
import android.content.SharedPreferences
import okhttp3.MediaType
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody

import android.app.PendingIntent
import androidx.core.app.NotificationCompat
import com.example.namahatta.MainActivity
import android.os.Build
import com.example.namahatta.R

class MyFirebaseMessagingService : FirebaseMessagingService() {

    private val TAG = "MyFirebaseMsgService"

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        val data = remoteMessage.data
        val action = data["action"] ?: "default"
        val groupId = data["group_id"]

        Log.d(TAG, "Получен пуш. Действие: $action, Группа: $groupId")

        // 🔥 ДОБАВЛЕНО ДЛЯ УМНЫХ УВЕДОМЛЕНИЙ:
        // Проверяем, не находится ли пользователь прямо сейчас внутри этого чата
        if (action == "new_chat_message" && !groupId.isNullOrEmpty()) {
            val prefs = getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
            val activeChatId = prefs.getString("active_group_chat_id", null)
            if (activeChatId == groupId) {
                Log.d(TAG, "Пропуск уведомления: пользователь уже открыл этот чат.")
                return // Завершаем выполнение метода, шторка сверху НЕ появится
            }
        }

        // Создаем интент для перехода в нужное место на сайте внутри WebView
        // 🔥 ИСПРАВЛЕНО: заранее достаем чистую строку ID группы без кавычек
        val cleanGroupId = data["group_id"] ?: ""

        // Создаем интент для перехода в нужное место на сайте внутри WebView
        val domain = "https://namahata.ru"
        val senderId = remoteMessage.data["sender_id"] ?: ""
        val senderName = remoteMessage.data["sender_name"] ?: "Преданный"

        val intent = when (action) {
            // 1. ПОДДЕРЖКА ЛИЧНЫХ СООБЩЕНИЙ (ЛС) — Ведет прямо в диалог с другом!
            "new_private_chat_message" -> {
                Intent(this, MainActivity::class.java).apply {
                    putExtra("load_url", domain + "/group.html?id=" + cleanGroupId + "&open_private_chat=" + senderId + "&open_private_name=" + java.net.URLEncoder.encode(senderName, "UTF-8"))
                }
            }

            // 2. ПОДДЕРЖКА ОБЩЕГО ЧАТА ГРУППЫ — Ведет в общую ленту Нама-Хатты
            "new_group_chat_message" -> {
                Intent(this, MainActivity::class.java).apply {
                    putExtra("load_url", domain + "/group.html?id=" + cleanGroupId + "&open_chat=1")
                }
            }

            // 3. Заявки в группу (для Лидеров)
            "view_group_applications" -> {
                Intent(this, MainActivity::class.java).apply {
                    putExtra("load_url", domain + "/profile.html?view=applications&group_id=" + cleanGroupId)
                }
            }

            // 4. Список участников группы
            "view_group_members" -> {
                Intent(this, MainActivity::class.java).apply {
                    putExtra("load_url", domain + "/group.html?id=" + cleanGroupId + "&tab=members")
                }
            }

            // 5. События и Будильники группы
            "view_event", "event_reminder" -> {
                Intent(this, MainActivity::class.java).apply {
                    putExtra("load_url", domain + "/group.html?id=" + cleanGroupId + "&tab=events")
                }
            }

            else -> Intent(this, MainActivity::class.java)
        }

        // Делаем интент "одноразовым"
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)

        val pendingIntent = PendingIntent.getActivity(
            this,
            System.currentTimeMillis().toInt(), // Уникальный ID
            intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        // 🔥 ИСПРАВЛЕНО: Передаем чистый ID группы (или хэш sender_id для ЛС) для группировки
        val notificationId = cleanGroupId.toIntOrNull() ?: senderId.toIntOrNull() ?: 0

        showNotification(
            remoteMessage.notification?.title ?: data["title"] ?: "Нама-Хатта",
            remoteMessage.notification?.body ?: data["body"] ?: "Новое уведомление",
            pendingIntent,
            notificationId // Добавляем четвертый параметр
        )
    }

    private fun showNotification(title: String, message: String, pendingIntent: PendingIntent, notificationId: Int) {
        val channelId = "default_channel"
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        // Создаём канал для Android 8.0+
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                "Уведомления",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Канал для важных уведомлений"
            }
            manager.createNotificationChannel(channel)
        }

        // Строим уведомление
        val builder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(message)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)

        // Отправляем уведомление с уникальным ID
        manager.notify(notificationId, builder.build())
    }

    override fun onNewToken(token: String) {
        Log.d(TAG, "Новый FCM-токен: $token")
        sendTokenToServer(token)
    }

    private fun sendTokenToServer(token: String) {
        // Получаем user_id из SharedPreferences
        val prefs: SharedPreferences = getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
        val userId = prefs.getString("user_id", null)

        if (userId == null) {
            Log.w(TAG, "user_id не найден, пропускаем отправку токена")
            return
        }

        // Подготавливаем JSON
        val json = JSONObject().apply {
            put("user_id", userId)
            put("fcm_token", token)
        }.toString()

        // Настройка OkHttp
        val client = OkHttpClient()
        val mediaType = "application/json; charset=utf-8".toMediaType()
        val requestBody = json.toRequestBody(mediaType)
        val request = Request.Builder()
            .url("https://namahata.ru")
            .post(requestBody)
            .build()

        // Асинхронная отправка
        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                Log.e(TAG, "Ошибка отправки FCM-токена: ${e.message}", e)
            }

            override fun onResponse(call: Call, response: Response) {
                if (response.isSuccessful) {
                    Log.d(TAG, "FCM-токен успешно отправлен на сервер")
                } else {
                    Log.e(TAG, "Ошибка сервера: ${response.code} - ${response.body?.string()}")
                }
            }
        })
    }
}
