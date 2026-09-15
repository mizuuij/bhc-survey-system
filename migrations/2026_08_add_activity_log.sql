CREATE TABLE `activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type` enum('resident','staff') NOT NULL,
  `actor_id` int(10) UNSIGNED NOT NULL,
  `actor_name` varchar(120) NOT NULL,
  `actor_role` varchar(30) NOT NULL,
  `action` enum('Created','Updated','Archived','Deleted') NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_name` varchar(180) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_log_created_at` (`created_at`),
  KEY `activity_log_actor` (`actor_type`,`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;