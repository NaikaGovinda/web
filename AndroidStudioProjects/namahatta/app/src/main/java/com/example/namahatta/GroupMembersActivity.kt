package com.example.namahatta

import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import android.webkit.WebView
import android.webkit.WebViewClient
import android.util.Log

class GroupMembersActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_group_members)

        val groupId = intent.getStringExtra("group_id")
        if (groupId != null) {
            loadGroupMembers(groupId)
        } else {
            Log.e("GroupMembers", "group_id не передан")
            finish()
        }
    }

    private fun loadGroupMembers(groupId: String) {
        val webView = findViewById<WebView>(R.id.webview)
        webView.webViewClient = WebViewClient()
        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true

        // Загружаем админку с якорем или параметром для отображения участников
        webView.loadUrl("https://namahata.ru/admin.html#members-$groupId")
    }
}