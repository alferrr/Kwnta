-- kwnta schema
-- MariaDB compatible
-- Fresh install — no data

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `expense_splits`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `group_members`;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `email`      varchar(100) NOT NULL,
  `password`   varchar(255) NOT NULL,
  `firstname`  varchar(50)  DEFAULT NULL,
  `lastname`   varchar(50)  DEFAULT NULL,
  `deleted_at` timestamp    NULL DEFAULT NULL,
  `created_at` timestamp    NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `groups` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `name`        varchar(255) NOT NULL,
  `icon`        varchar(100) NOT NULL DEFAULT 'group',
  `created_by`  int(11)      NOT NULL,
  `status`      enum('active','archived') NOT NULL DEFAULT 'active',
  `archived_at` timestamp    NULL DEFAULT NULL,
  `created_at`  timestamp    NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `group_members` (
  `id`        int(11)  NOT NULL AUTO_INCREMENT,
  `group_id`  int(11)  NOT NULL,
  `user_id`   int(11)  NOT NULL,
  `role`      enum('admin','member') NOT NULL DEFAULT 'member',
  `joined_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`group_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`)  REFERENCES `users`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `expenses` (
  `id`          int(11)       NOT NULL AUTO_INCREMENT,
  `group_id`    int(11)       NOT NULL,
  `paid_by`     int(11)       NOT NULL,
  `description` varchar(255)  NOT NULL,
  `amount`      decimal(10,2) NOT NULL,
  `created_at`  timestamp     NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `paid_by`  (`paid_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`paid_by`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `expense_splits` (
  `id`         int(11)       NOT NULL AUTO_INCREMENT,
  `expense_id` int(11)       NOT NULL,
  `user_id`    int(11)       NOT NULL,
  `share`      decimal(10,2) NOT NULL,
  `paid`       tinyint(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `expense_id` (`expense_id`),
  KEY `user_id`    (`user_id`),
  CONSTRAINT `expense_splits_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expense_splits_ibfk_2` FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `leave_requests` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `group_id`    int(11)      NOT NULL,
  `user_id`     int(11)      NOT NULL,
  `status`      enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `message`     varchar(500) DEFAULT NULL,
  `resolved_by` int(11)      DEFAULT NULL,
  `resolved_at` timestamp    NULL DEFAULT NULL,
  `created_at`  timestamp    NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pending` (`group_id`, `user_id`, `status`),
  KEY `user_id`    (`user_id`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`group_id`)    REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`user_id`)     REFERENCES `users`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;