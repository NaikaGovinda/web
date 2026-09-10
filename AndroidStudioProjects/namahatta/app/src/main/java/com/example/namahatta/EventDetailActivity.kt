package com.example.namahatta // ← Должен совпадать с твоим package

import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity

class EventDetailActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_event_detail)

        val eventId = intent.getStringExtra("event_id")
        if (eventId != null) {
            loadEvent(eventId)
        } else {
            finish() // Нет ID — закрываем активность
        }
    }

    private fun loadEvent(eventId: String) {
        // Здесь загрузишь данные события по ID
        // Например, через API или WebView
    }
}