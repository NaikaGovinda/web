package com.example.namahatta

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.android.material.imageview.ShapeableImageView
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
    private lateinit var rvPhotos: RecyclerView
    private lateinit var photoAdapter: GroupPhotoAdapter

    private var groupId: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_group_detail)

        // Инициализация views
        rvLeaderAvatar = findViewById(R.id.rvLeaderAvatar)
        tvLeaderName = findViewById(R.id.tvLeaderName)
        tvGroupName = findViewById(R.id.tvGroupName)
        tvGroupDescription = findViewById(R.id.tvGroupDescription)
        tvCity = findViewById(R.id.tvCity)
        rvPhotos = findViewById(R.id.rvPhotos)

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
                if (photoUrl.isNotEmpty()) {
                    photoUrls.add(photoUrl)
                }
            }

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

                // Загружаем фото в карусель
                if (photoUrls.isNotEmpty()) {
                    photoAdapter.updatePhotos(photoUrls)
                    rvPhotos.visibility = View.VISIBLE
                } else {
                    rvPhotos.visibility = View.GONE
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

// Адаптер для карусели фото группы
class GroupPhotoAdapter : RecyclerView.Adapter<GroupPhotoAdapter.PhotoViewHolder>() {

    private var photoUrls: List<String> = emptyList()

    fun updatePhotos(urls: List<String>) {
        photoUrls = urls
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): PhotoViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_group_photo, parent, false)
        return PhotoViewHolder(view)
    }

    override fun onBindViewHolder(holder: PhotoViewHolder, position: Int) {
        holder.bind(photoUrls[position])
    }

    override fun getItemCount() = photoUrls.size

    inner class PhotoViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val imageView: ImageView = itemView.findViewById(R.id.ivGroupPhoto)

        fun bind(photoUrl: String) {
            val fullUrl = "https://namahata.ru/$photoUrl"
            Picasso.get()
                .load(fullUrl)
                .placeholder(R.drawable.placeholder_photo)
                .error(R.drawable.placeholder_photo)
                .into(imageView)
        }
    }
}
