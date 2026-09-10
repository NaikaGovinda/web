package com.example.namahatta // ← Должен совпадать с твоим пакетом

import android.os.Bundle
import androidx.fragment.app.Fragment
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup

class GroupApplicationsFragment : Fragment() {

    private lateinit var groupId: String

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        arguments?.let {
            groupId = it.getString("group_id") ?: ""
        }
    }

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        // Здесь будет логика загрузки заявок
        return inflater.inflate(R.layout.fragment_group_applications, container, false)
    }

    companion object {
        @JvmStatic
        fun newInstance(group_id: String) =
            GroupApplicationsFragment().apply {
                arguments = Bundle().apply {
                    putString("group_id", group_id)
                }
            }
    }
}