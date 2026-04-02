-- PV comment likes
CREATE TABLE IF NOT EXISTS `pv_comment_likes` (
  `id` CHAR(36) NOT NULL,
  `comment_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pv_like` (`comment_id`, `user_id`),
  CONSTRAINT `fk_pv_like_comment` FOREIGN KEY (`comment_id`) REFERENCES `pv_comments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pv_like_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
