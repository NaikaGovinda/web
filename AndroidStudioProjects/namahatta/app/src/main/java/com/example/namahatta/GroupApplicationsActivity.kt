package com.example.namahatta // ← Должен совпадать с твоим package

import androidx.appcompat.app.AppCompatActivity
import android.os.Bundle
import android.widget.Toast
import com.example.namahatta.GroupApplicationsFragment

class GroupApplicationsActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_group_applications)

        val groupId = intent.getStringExtra("group_id")
        if (groupId != null) {
            loadApplications(groupId)
        } else {
            Toast.makeText(this, "Ошибка: не указан ID группы", Toast.LENGTH_SHORT).show()
            finish()
        }
    }

    private fun loadApplications(groupId: String) {
        // Загружаем заявки для группы
        val fragment = GroupApplicationsFragment.newInstance(groupId)
        supportFragmentManager.beginTransaction()
            .replace(R.id.container, fragment)
            .commit()
    }
}