-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Авг 09 2026 г., 06:40
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `u3385428_namahatta_db`
--

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `telegram_username` varchar(32) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `fcm_token` varchar(255) DEFAULT NULL,
  `device_type` enum('web','android','ios') DEFAULT 'web',
  `city` varchar(100) DEFAULT NULL,
  `avatar_url` varchar(1024) DEFAULT NULL,
  `role` enum('user','leader','admin') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `is_blocked` tinyint(1) DEFAULT 0,
  `last_active_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `auth_token` varchar(64) DEFAULT NULL,
  `auth_expires` int(10) UNSIGNED DEFAULT NULL,
  `login_source` varchar(20) DEFAULT 'email',
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `telegram_id`, `telegram_username`, `first_name`, `last_name`, `phone`, `email`, `fcm_token`, `device_type`, `city`, `avatar_url`, `role`, `is_active`, `is_blocked`, `last_active_at`, `created_at`, `updated_at`, `deleted_at`, `password_hash`, `is_verified`, `auth_token`, `auth_expires`, `login_source`, `is_admin`) VALUES
(1, 755266246, 'annabogomazova', 'Анна', 'Фёдорова (Богомазова)', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'user', 1, 0, '2026-02-01 07:10:10', '2026-01-21 12:09:17', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(2, 1092729077, 'Nikolai_Fedorovs', 'Николай', 'Фёдоров', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'leader', 1, 0, '2026-02-21 10:26:25', '2026-01-21 11:05:06', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(3, 1115984144, 'AchyutaGaura', 'Артем', '', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'admin', 1, 0, '2026-02-22 00:17:52', '2026-01-21 12:44:53', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(6, 1661938546, 'Radhakundadas', 'Achyuta Gaura das', '', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'user', 1, 0, '2026-02-15 11:59:14', '2026-01-22 05:22:55', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(7, 5519642112, 'Kishori Priya', 'Екатерина', '', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'leader', 1, 0, NULL, '2026-01-26 14:35:00', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(8, 1826534731, 'Vovic1980', 'Владимир', 'Шарыпов', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'leader', 1, 0, '2026-02-01 07:14:24', '2026-01-26 15:05:09', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(9, 2038564463, 'Elena', 'Елена', 'Серебренникова г.Красноярск', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'leader', 1, 0, '2026-02-01 07:19:08', '2026-02-01 07:09:42', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(10, 5527027219, NULL, 'Александр', 'Григорьев', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'leader', 1, 0, NULL, '2026-02-01 07:09:57', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(11, 889502448, NULL, 'Лидия', 'Храмцова', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'user', 1, 0, '2026-02-15 19:45:59', '2026-02-03 04:19:12', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(12, 1330208482, 'Madhu_Gopal_das', 'Madhu Gopal das', '', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'leader', 1, 0, '2026-02-14 15:47:09', '2026-02-14 12:46:23', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(20, 1377882799, 'Anna_Tartinskaya', 'Тарангини Ганга', '', NULL, NULL, NULL, 'web', 'Красноярск', NULL, 'leader', 1, 0, '2026-02-15 11:59:14', '2026-01-22 05:22:55', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(21, 663844130, NULL, 'Пользователь', '', NULL, NULL, NULL, 'web', NULL, NULL, 'user', 1, 0, NULL, '2026-03-19 07:50:41', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(22, 399783526, NULL, 'Sofi', '', NULL, NULL, NULL, 'web', NULL, NULL, 'leader', 1, 0, NULL, '2026-03-19 08:23:07', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(23, 8478601891, NULL, 'Лиларани', 'Чинтамани', NULL, NULL, NULL, 'web', NULL, NULL, 'leader', 1, 0, NULL, '2026-03-19 08:24:42', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(24, 851600667, NULL, 'Владимир', '', NULL, NULL, NULL, 'web', NULL, NULL, 'leader', 1, 0, NULL, '2026-03-19 09:19:14', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(25, 5139473938, NULL, 'Анна', 'Жарова', NULL, NULL, NULL, 'web', NULL, NULL, 'leader', 1, 0, NULL, '2026-03-19 09:43:00', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(26, 6067828802, NULL, 'Ольга', 'Галаган', NULL, NULL, NULL, 'web', NULL, NULL, 'leader', 1, 0, NULL, '2026-03-19 13:11:37', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(27, 173565980, 'AlexBeeswax', 'Alex', 'Beeswax', NULL, NULL, NULL, 'web', NULL, NULL, 'leader', 1, 0, NULL, '2026-04-11 02:05:42', '2026-05-03 15:55:40', NULL, NULL, 0, NULL, NULL, 'email', 0),
(64, 0, NULL, 'Ачьюта', '', '', 'achyutagauradas@mail.ru', NULL, 'web', 'Красноярск', '/assets/avatars/user_64_1786032750.jpg', 'admin', 1, 0, NULL, '2026-05-07 08:33:35', '2026-08-06 23:12:30', NULL, '$2y$10$kG2uaXCGiEPs53UuflFK4.m26/VrHIbjGx9oHbeXDFc1mjrRujAES', 1, NULL, NULL, 'email', 1),
(76, NULL, NULL, 'Николай', 'Федоров', '', 'fedorov.ru-88@mail.ru', NULL, 'web', 'Красноярск', '/assets/avatars/user_76_1785250897.jpeg', 'leader', 1, 0, NULL, '2026-05-07 15:42:32', '2026-07-28 18:01:37', NULL, '$2y$10$69GSj/TgXZFZ/hg4Qb5s0uGtqFuIjzVXsPpE21YreLYuKR0d0FE.W', 1, NULL, NULL, 'email', 0),
(77, NULL, NULL, 'ИШВАРАНАНДА ДАС', '', '', 'Isvarananda@gmail.com', NULL, 'web', NULL, NULL, 'user', 1, 0, NULL, '2026-05-07 16:34:32', '2026-05-07 16:35:00', NULL, '$2y$10$5fyuTNsFRI/vvhfVMZJM0eo5Kmqyg6S3tE0L1vkXBbfFckLUVqBAe', 1, NULL, NULL, 'email', 0),
(79, NULL, NULL, 'Artem2', '', '', 'stylecrew@mail.ru', NULL, 'web', '', '/assets/avatars/user_79_1785254721.jpg', 'user', 1, 0, NULL, '2026-05-08 08:20:38', '2026-08-06 12:06:02', NULL, '$2y$10$TafRikHBM10KIRRGVZeNPO8jV/.dioIJ6iHfMx.K.eTTdfT0C7se.', 1, NULL, NULL, 'email', 0),
(153, NULL, NULL, 'Лили', '', '', 'Vermilion2295@yandex.ru', NULL, 'web', '', '/assets/avatars/user_153_1786032851.jpg', 'user', 1, 0, NULL, '2026-07-17 16:22:08', '2026-08-06 23:14:11', NULL, '$2y$10$jMoEKuir/rDfMpIs8Xvwauaz5ahbI8yKJ72mO5ZRURT8SJrMS3/J6', 1, NULL, NULL, 'email', 0),
(156, NULL, NULL, 'U108', 'Dharmananda', '', 'user108108@gmail.com', NULL, 'web', NULL, NULL, 'user', 1, 0, NULL, '2026-08-06 12:30:36', '2026-08-06 12:31:04', NULL, '$2y$10$a7Sw21niVr/SZw2qw6qdxuixusWh34KNy.2FU1Vu3ZL1Br3ShHMF2', 1, NULL, NULL, 'email', 0),
(157, NULL, NULL, 'Дамананда дас', '', '+79135941889', 'damanandadas@gmail.com', NULL, 'web', '', NULL, 'leader', 1, 0, NULL, '2026-08-06 13:31:55', '2026-08-08 18:25:53', NULL, '$2y$10$k5wecIDtcZEhdYTh3BBV0OlU2TnAwFy8di5VEvsqxkJO.ljCw1s5i', 1, NULL, NULL, 'email', 0),
(158, NULL, NULL, 'Камала Лочана', '', '79029229102', 'eserebrennikova8796@gmail.com', NULL, 'web', NULL, NULL, 'user', 1, 0, NULL, '2026-08-09 11:00:18', '2026-08-09 11:01:16', NULL, '$2y$10$VDtI9oN08ZxTmnCLFkRhz.IKsSy4HraaRTC3JwaGC7hY61ULBH436', 1, NULL, NULL, 'email', 0);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telegram_id` (`telegram_id`),
  ADD UNIQUE KEY `uniq_email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_auth_token` (`auth_token`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
