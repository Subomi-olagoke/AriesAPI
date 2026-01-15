-- Create Hive Channels Table
CREATE TABLE IF NOT EXISTS `hive_channels` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `color` varchar(7) DEFAULT '#007AFF',
    `creator_id` bigint(20) UNSIGNED NOT NULL,
    `privacy` varchar(255) DEFAULT 'public',
    `status` varchar(255) DEFAULT 'active',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Hive Channel Members Table
CREATE TABLE IF NOT EXISTS `hive_channel_members` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` varchar(36) NOT NULL,
    `role` varchar(255) DEFAULT 'member',
    `notifications_enabled` tinyint(1) DEFAULT 1,
    `joined_at` timestamp NULL DEFAULT NULL,
    `last_read_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `hive_channel_members_channel_id_foreign` (`channel_id`),
    CONSTRAINT `hive_channel_members_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `hive_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Hive Communities Table
CREATE TABLE IF NOT EXISTS `hive_communities` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `avatar` varchar(255) DEFAULT NULL,
    `creator_id` varchar(36) NOT NULL,
    `privacy` varchar(255) DEFAULT 'public',
    `status` varchar(255) DEFAULT 'active',
    `join_code` varchar(255) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Hive Community Members Table
CREATE TABLE IF NOT EXISTS `hive_community_members` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `community_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` varchar(36) NOT NULL,
    `role` varchar(255) DEFAULT 'member',
    `joined_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `hive_community_members_community_id_foreign` (`community_id`),
    CONSTRAINT `hive_community_members_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `hive_communities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Hive Activities Table
CREATE TABLE IF NOT EXISTS `hive_activities` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` varchar(36) NOT NULL,
    `target_user_id` varchar(36) DEFAULT NULL,
    `activity_type` varchar(255) NOT NULL,
    `entity_type` varchar(255) NOT NULL,
    `entity_id` bigint(20) UNSIGNED NOT NULL,
    `data` json DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `hive_activities_user_id_index` (`user_id`),
    KEY `hive_activities_target_user_id_index` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;