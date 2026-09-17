package com.example.namahatta

import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import androidx.recyclerview.widget.RecyclerView
import com.squareup.picasso.Picasso

/**
 * Адаптер для ViewPager2 — отображает фото группы
 */
class GroupPhotoPagerAdapter(
    private val photoUrls: List<String>
) : RecyclerView.Adapter<GroupPhotoPagerAdapter.PhotoViewHolder>() {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): PhotoViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.view_pager_photo_item, parent, false)
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
            Log.d("PhotoAdapter", "Loading photo: $fullUrl (original: $photoUrl)")
            Picasso.get()
                .load(fullUrl)
                .placeholder(R.drawable.placeholder_photo)
                .error(R.drawable.placeholder_photo)
                .into(imageView)
        }
    }
}
