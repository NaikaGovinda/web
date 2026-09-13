package com.example.namahatta

import android.app.Activity // 🔥 Добавлено для проверки результата выбора фото
import android.content.Context
import android.content.Intent // 🔥 Добавлено для создания системного интента Галереи
import android.net.Uri // 🔥 Добавлено для работы со ссылкой на изображение
import android.os.Bundle
import android.webkit.GeolocationPermissions
import android.webkit.JavascriptInterface
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.OnBackPressedCallback
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts // 🔥 Добавлено для безопасного вызова окна Галереи
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import com.google.firebase.FirebaseApp
import android.util.Log
import android.webkit.CookieManager
import com.google.firebase.messaging.FirebaseMessaging
import okhttp3.*
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.MediaType.Companion.toMediaType
import org.json.JSONObject
import java.io.IOException
import android.app.NotificationManager
import android.Manifest

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView

    // 🔥 ДОБАВЛЕНО: Регистрируем лаунчер для открытия галереи и получения результата
    private val pickImageLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val imageUri: Uri? = result.data?.data
            if (imageUri != null) {
                // Передаем полученный URI выбранного фото обратно в WebView
                sendImageToWebView(imageUri.toString())
            }
        }
    }


    override fun onCreate(savedInstanceState: Bundle?) {
        Log.d("APP_DEBUG", "MainActivity started")
        super.onCreate(savedInstanceState)
        FirebaseApp.initializeApp(this)
        Log.d("APP_DEBUG", "Firebase initialized")

        // Получаем и сохраняем FCM-токен (но НЕ отправляем его)
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) {
                val token = task.result
                Log.d("FCM_TOKEN", "Получен токен: $token")

                // Сохраняем токен локально
                val prefs = getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
                prefs.edit().putString("fcm_token", token).apply()

                // Попытка отправить токен, если уже известны user_id и PHPSESSID
                trySendTokenIfPossible()
            } else {
                Log.e("FCM_TOKEN", "Ошибка получения токена", task.exception)
            }
        }

        enableEdgeToEdge()
        setContentView(R.layout.activity_main)

        // [ТОЧЕЧНОЕ ИСПРАВЛЕНИЕ]: Запрашиваем разрешение на уведомления один раз при старте
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
            val permissionCheck = androidx.core.content.ContextCompat.checkSelfPermission(
                this,
                android.Manifest.permission.POST_NOTIFICATIONS
            )
            if (permissionCheck != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                // Регистрируем лаунчер и вызываем окно один раз
                val requestPermissionLauncher = registerForActivityResult(
                    androidx.activity.result.contract.ActivityResultContracts.RequestPermission()
                ) { isGranted: Boolean ->
                    if (isGranted) android.util.Log.d("APP_DEBUG", "Уведомления разрешены!")
                }
                requestPermissionLauncher.launch(android.Manifest.permission.POST_NOTIFICATIONS)
            }
        }

        webView = findViewById(R.id.webview)
        val cookieManager = android.webkit.CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        cookieManager.setAcceptThirdPartyCookies(webView, true)

        webView.settings.apply {
            mixedContentMode = android.webkit.WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
            domStorageEnabled = true
            javaScriptEnabled = true
            allowFileAccess = true
            allowContentAccess = true
            setGeolocationEnabled(true) // ВКЛЮЧАЕМ геолокацию для карты
            cacheMode = WebSettings.LOAD_DEFAULT
        }

        // [ИСПРАВЛЕНИЕ КАРТЫ]: Настраиваем WebChromeClient для обработки геолокации
        webView.webChromeClient = object : WebChromeClient() {
            // Запрашиваем разрешение на геолокацию
            override fun onGeolocationPermissionsShowPrompt(
                origin: String?,
                callback: GeolocationPermissions.Callback
            ) {
                Log.d("GEO_LOCATION", "Запрос геолокации от: $origin")
                // Проверяем, есть ли разрешение
                if (ContextCompat.checkSelfPermission(
                        this@MainActivity,
                        Manifest.permission.ACCESS_FINE_LOCATION
                    ) == android.content.pm.PackageManager.PERMISSION_GRANTED
                ) {
                    callback.invoke(origin, true, false)
                    Log.d("GEO_LOCATION", "Разрешение на геолокацию предоставлено")
                } else {
                    // Запрашиваем разрешение
                    ActivityCompat.requestPermissions(
                        this@MainActivity,
                        arrayOf(Manifest.permission.ACCESS_FINE_LOCATION),
                        1001
                    )
                    // Пока отклоняем, разрешение запросим в onRequestPermissionsResult
                    callback.invoke(origin, false, false)
                }
            }

            // Результат запроса разрешения
            override fun onPermissionRequest(request: android.webkit.PermissionRequest) {
                Log.d("GEO_LOCATION", "Запрос разрешения: ${request.resources.joinToString()}")
                if (ContextCompat.checkSelfPermission(
                        this@MainActivity,
                        Manifest.permission.ACCESS_FINE_LOCATION
                    ) == android.content.pm.PackageManager.PERMISSION_GRANTED
                ) {
                    request.grant(request.resources)
                } else {
                    request.deny()
                }
            }
        }
        //webView.webViewClient = WebViewClient()
        // [ИСПРАВЛЕНО]: Внутренний клиент, который плавно открывает страницы чатов из балуна карты
        webView.webViewClient = object : WebViewClient() {
            @Deprecated("Deprecated in Java")
            override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                if (url != null) { view?.loadUrl(url) }
                return true
            }
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val url = request?.url?.toString()
                if (url != null) { view?.loadUrl(url) }
                return true
            }
        }
        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.addJavascriptInterface(WebAppInterface(this), "Android")

        // [ИСПРАВЛЕНО]: Умный перехват ссылки из пуш-уведомления при старте приложения
        val loadUrl = intent.getStringExtra("load_url")
        if (!loadUrl.isNullOrEmpty()) {
            Log.d("APP_DEBUG", "Холодный старт из пуша. Загружаем: $loadUrl")
            // 🔥 ТОЧЕЧНОЕ ИСПРАВЛЕНИЕ: Гасим шторку при холодном старте из пуша
            val notificationManager = this@MainActivity.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.cancelAll()
            webView.loadUrl(loadUrl)
        } else {
            // Если запустили просто с иконки — открываем главную
            webView.loadUrl("https://namahata.ru/index.html")
        }

        // 🔥 Загружаем URL ОДИН РАЗ
        /*val loadUrl = intent.getStringExtra("load_url")
        if (loadUrl != null) {
            webView.loadUrl(loadUrl)
        } else {
            webView.loadUrl("https://namahata.ru/index.html")
        }*/

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                val currentUrl = webView.url ?: ""

                // Если мы на странице входа/регистрации — идём на главную
                if (currentUrl.contains("login") || currentUrl.contains("auth") || currentUrl.contains("register")) {
                    webView.loadUrl("https://namahata.ru/index.html")
                    return
                }
                if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main)) { v, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
            insets
        }
    }

    private fun sendTokenToServer(token: String, sessionId: String) {
        val json = JSONObject().apply {
            put("fcm_token", token)
        }.toString()

        val client = OkHttpClient()
        val mediaType = "application/json; charset=utf-8".toMediaType()
        val requestBody = json.toRequestBody(mediaType)
        val request = Request.Builder()
            .url("https://namahata.ru/api/save_fcm_token.php")
            .addHeader("Cookie", "PHPSESSID=$sessionId")
            .post(requestBody)
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                Log.e("FCM_SEND", "Ошибка отправки: ${e.message}", e)
            }

            override fun onResponse(call: Call, response: Response) {
                val result = response.body?.string() ?: "No response body"
                if (response.isSuccessful) {
                    Log.d("FCM_SEND", "Токен успешно отправлен")
                } else {
                    Log.e("FCM_SEND", "Ошибка сервера: ${response.code} - $result")
                }
            }
        })
    }

    private fun trySendTokenIfPossible() {
        val prefs = getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
        val fcmToken = prefs.getString("fcm_token", null)
        val userId = prefs.getString("user_id", null)

        if (fcmToken != null && userId != null) {
            // Получаем PHPSESSID из WebView
            val cookieManager = CookieManager.getInstance()
            val cookies = cookieManager.getCookie("https://namahata.ru")
            var phpSessionId: String? = null

            cookies?.split(";")?.forEach { cookie ->
                val parts = cookie.trim().split("=", limit = 2)
                if (parts.size == 2 && parts[0] == "PHPSESSID") {
                    phpSessionId = parts[1]
                }
            }

            if (phpSessionId != null) {
                sendTokenToServer(fcmToken, phpSessionId)
            }
        }
    }

    // 🔥 ДОБАВЛЕНО: Функция выполнения JS-скрипта на странице для передачи URI картинки
    private fun sendImageToWebView(uriString: String) {
        webView.post {
            webView.evaluateJavascript("javascript:receiveImageFromAndroid('$uriString');", null)
        }
    }

    // 🔥 ДОБАВЬ inner ЗДЕСЬ:
    inner class WebAppInterface(private val context: Context) {
        @JavascriptInterface
        fun setUser(userId: String) {
            val prefs = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
            prefs.edit().putString("user_id", userId).apply()
            Log.d("WEB_APP", "user_id установлен: $userId")

            // Попробуем отправить токен, если всё готово
            if (context is MainActivity) {
                context.trySendTokenIfPossible()
            } else {
                Log.w("WEB_APP", "Контекст не является MainActivity — пропуск отправки токена")
            }
        }

        // 🔥 ДОБАВЛЕНО: Метод интерфейса, вызываемый из JS (window.Android.openGallery())
        @JavascriptInterface
        fun openGallery() {
            val intent = Intent(Intent.ACTION_PICK).apply {
                type = "image/*"
            }
            pickImageLauncher.launch(intent)
        }

        // 🔥 ДОБАВЛЕНО ДЛЯ УМНЫХ УВЕДОМЛЕНИЙ ЧАТА:
        // Этот метод ваш сайт из android.js будет вызывать при входе и выходе из чата группы,
        // чтобы шторка сверху не спамила пользователя, когда он уже ведет переписку!
        @JavascriptInterface
        fun setActiveChat(groupId: String) {
            val prefs = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
            if (groupId.isEmpty()) {
                prefs.edit().remove("active_group_chat_id").apply()
                Log.d("WEB_APP", "Пользователь вышел из контекста чата")
            } else {
                prefs.edit().putString("active_group_chat_id", groupId).apply()
                Log.d("WEB_APP", "Пользователь вошел в чат группы: $groupId")
            }
        }

        // [ДОБАВЛЕНО ШАГ 1]: Локальная фиксация открытого чата в память смартфона преданного
        // [ИСПРАВЛЕНО]: Использование системного applicationContext вместо ошибочного entryContext
        @JavascriptInterface
        fun updateActiveChatContextInAndroid(groupId: String?) {
            // [ТОЧЕЧНО]: Заменили entryContext на встроенный applicationContext
            val prefs = applicationContext.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)

            if (!groupId.isNullOrEmpty() && groupId != "null") {
                android.util.Log.d("FCM_CHAT", "Преданный вошел в чат группы: $groupId")
                prefs.edit().putString("active_group_chat_id", groupId).apply()
            } else {
                android.util.Log.d("FCM_CHAT", "Преданный вышел из чата")
                prefs.edit().putString("active_group_chat_id", "").apply()
            }
        }
    }

    // [ДОБАВЛЕНО]: Перехват клика по пушу, если приложение было просто свернуто в фоновом режиме
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent) // Перезаписываем текущий интент новым, прилетевшим из шторки

        val loadUrl = intent.getStringExtra("load_url")
        if (!loadUrl.isNullOrEmpty()) {
            Log.d("APP_DEBUG", "Приложение проснулось из пуша. Переходим: $loadUrl")
            val notificationManager = this@MainActivity.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.cancelAll()
            webView.post {
                webView.loadUrl(loadUrl)
            }
        }
    }

    // 🔥 БРОНЕБОЙНОЕ ИСПРАВЛЕНИЕ: Очищаем контекст чата при любом сворачивании приложения!
    override fun onStop() {
        super.onStop()
        val prefs = applicationContext.getSharedPreferences("app_prefs", Context.MODE_PRIVATE)
        // Как только приложение ушло в фон, стираем активный чат из памяти.
        // Теперь пуши в шторку прилетят ГАРАНТИРОВАННО!
        prefs.edit().putString("active_group_chat_id", "").apply()
        android.util.Log.d("FCM_CHAT", "Приложение свернуто. Контекст чата сброшен.")
    }

    // [ИСПРАВЛЕНИЕ КАРТЫ]: Результат запроса разрешения на геолокацию
    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == 1001) {
            if (grantResults.isNotEmpty() && grantResults[0] == android.content.pm.PackageManager.PERMISSION_GRANTED) {
                Log.d("GEO_LOCATION", "Разрешение на геолокацию получено")
            } else {
                Log.w("GEO_LOCATION", "Разрешение на геолокацию ОТКЛОНЕНО")
            }
        }
    }

}
