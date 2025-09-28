-- User Profiles Table Migration
-- This table stores application-specific user data linked to IDP identities

CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idp_user_id` varchar(255) NOT NULL COMMENT 'IDP User ID - links to identity provider',
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT 0.00 COMMENT 'Application-specific rate or score',
  `metadata` JSON DEFAULT NULL COMMENT 'Additional application-specific data',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_idp_user` (`idp_user_id`),
  UNIQUE KEY `unique_email` (`email`),
  INDEX `idx_email` (`email`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Add indexes for better performance
-- ALTER TABLE user_profiles ADD INDEX idx_rate (rate);
-- ALTER TABLE user_profiles ADD INDEX idx_updated_at (updated_at);