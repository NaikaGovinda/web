package com.example.namahatta

import android.os.Bundle
import android.util.Log
import android.view.View
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.viewpager2.widget.ViewPager2
import com.google.android.material.imageview.ShapeableImageView
import com.google.android.material.tabs.TabLayoutMediator
import com.squareup.picasso.Picasso
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

class GroupDetailActivity : AppCompatActivity() {

    private lateinit var rvLeaderAvatar: ShapeableImageView
    private lateinit var tvLeaderName: TextView
    private lateinit var tvGroupName: TextView
    private lateinit var tvGroupDescription: TextView
    private lateinit var tvCity: TextView
    private lateinit var vpGroupPhotos: ViewPager2
    private lateinit var tlPhotoIndicator: com.google.android.material.tabs.TabLayout

    private var groupId: String? = null
    private var photoAdapter: GroupPhotoPagerAdapter? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_group_detail)

        // Инициализация views
        rvLeaderAvatar = findViewById(R.id.rvLeaderAvatar)
        tvLeaderName = findViewById(R.id.tvLeaderName)
        tvGroupName = findViewById(R.id.tvGroupName)
        tvGroupDescription = findViewById(R.id.tvGroupDescription)
        tvCity = findViewById(R.id.tvCity)
        vpGroupPhotos = findViewById(R.id.vpGroupPhotos)
        tlPhotoIndicator = findViewById(R.id.tlPhotoIndicator)

        groupId = intent.getStringExtra("group_id")
        
        if (groupId != null) {
            loadGroupData(groupId!!)
        } else {
            Toast.makeText(this, "Ошибка: не указан ID группы", Toast.LENGTH_SHORT).show()
            finish()
        }
    }

    private fun loadGroupData(groupId: String) {
        showLoading()

        val apiUrl = "https://namahata.ru/api/get_group.php?id=$groupId"
        
        Thread {
            try {
                val url = URL(apiUrl)
                val connection = url.openConnection() as HttpURLConnection
                connection.requestMethod = "GET"
                connection.connectTimeout = 15000
                connection.readTimeout = 15000

                val responseCode = connection.responseCode

                if (responseCode == HttpURLConnection.HTTP_OK) {
                    val reader = BufferedReader(InputStreamReader(connection.inputStream))
                    val response = StringBuilder()
                    var line: String?
                    while (reader.readLine().also { line = it } != null) {
                        response.append(line)
                    }
                    reader.close()

                    val jsonResponse = response.toString()
                    parseGroupData(jsonResponse)
                } else {
                    runOnUiThread {
                        hideLoading()
                        Toast.makeText(this, "Ошибка загрузки данных", Toast.LENGTH_LONG).show()
                    }
                }
                connection.disconnect()
            } catch (e: Exception) {
                e.printStackTrace()
                runOnUiThread {
                    hideLoading()
                    Toast.makeText(this, "Ошибка соединения: ${e.message}", Toast.LENGTH_LONG).show()
                }
            }
        }.start()
    }

    private fun parseGroupData(jsonResponse: String) {
        try {
            val json = JSONObject(jsonResponse)
            
            if (!json.getBoolean("success")) {
                runOnUiThread {
                    hideLoading()
                    Toast.makeText(this, json.getString("error"), Toast.LENGTH_LONG).show()
                }
                return
            }

            val group = json.getJSONObject("group")
            val leaders = json.getJSONArray("leaders")
            val photos = json.getJSONArray("photos")
            
            Log.d("GroupDetail", "=== DEBUG PHOTO LOAD ===")
            Log.d("GroupDetail", "photos.length() = ${photos.length()}")
            Log.d("GroupDetail", "JSON response snippet: ${jsonResponse.take(500)}")

            // Парсим данные группы
            val groupName = group.getString("name")
            val description = group.optString("description", "Описание отсутствует")
            val city = group.optString("city", "")
            val avatarUrl = group.optString("group_avatar_url", "")

            // Парсим лидера
            var leaderName = "Лидер не указан"
            if (leaders.length() > 0) {
                val leader = leaders.getJSONObject(0)
                val firstName = leader.optString("first_name", "")
                val lastName = leader.optString("last_name", "")
                leaderName = if (firstName.isNotEmpty() || lastName.isNotEmpty()) {
                    "$firstName $lastName".trim()
                } else {
                    "Лидер"
                }
            }

            // Парсим фото группы
            val photoUrls = mutableListOf<String>()
            for (i in 0 until photos.length()) {
                val photo = photos.getJSONObject(i)
                val photoUrl = photo.optString("photo_url", "")
                Log.d("GroupDetail", "Photo #$i: url='$photoUrl', id=${photo.optInt("id")}, sort=${photo.optInt("sort_order")}")
                if (photoUrl.isNotEmpty()) {
                    photoUrls.add(photoUrl)
                }
            }
            Log.d("GroupDetail", "Total photo URLs collected: ${photoUrls.size}")
            Log.d("GroupDetail", "========================")

            // Обновляем UI
            runOnUiThread {
                hideLoading()

                // Устанавливаем данные
                tvGroupName.text = groupName
                tvGroupDescription.text = description
                tvCity.text = if (city.isNotEmpty()) city else ""
                tvCity.visibility = if (city.isNotEmpty()) View.VISIBLE else View.GONE
                tvLeaderName.text = leaderName

                // Загружаем аватар лидера (группы)
                if (avatarUrl.isNotEmpty()) {
                    val fullUrl = "https://namahata.ru/$avatarUrl"
                    Picasso.get()
                        .load(fullUrl)
                        .placeholder(R.drawable.placeholder_avatar)
                        .error(R.drawable.placeholder_avatar)
                        .into(rvLeaderAvatar)
                } else {
                    rvLeaderAvatar.setImageResource(R.drawable.placeholder_avatar)
                }

                // Загружаем фото в ViewPager2
                Log.d("GroupDetail", "UI Thread: photoUrls.size = ${photoUrls.size}")
                if (photoUrls.isNotEmpty()) {
                    Log.d("GroupDetail", "Photo URLs list: ${photoUrls.joinToString(", ")}")
                    photoAdapter = GroupPhotoPagerAdapter(photoUrls)
                    vpGroupPhotos.adapter = photoAdapter
                    vpGroupPhotos.visibility = View.VISIBLE
                    
                    // Добавляем индикатор позиций (точки)
                    TabLayoutMediator(tlPhotoIndicator, vpGroupPhotos) { tab, position ->
                        // Точки создаются автоматически TabLayout
                    }.attach()
                    tlPhotoIndicator.visibility = View.VISIBLE
                } else {
                    vpGroupPhotos.visibility = View.GONE
                    tlPhotoIndicator.visibility = View.GONE
                }
            }

        } catch (e: Exception) {
            e.printStackTrace()
            runOnUiThread {
                hideLoading()
                Toast.makeText(this, "Ошибка парсинга данных", Toast.LENGTH_LONG).show()
            }
        }
    }

    private fun showLoading() {
        // Можно добавить ProgressBar и показывать его
    }

    private fun hideLoading() {
        // Скрываем индикатор загрузки
    }
}

