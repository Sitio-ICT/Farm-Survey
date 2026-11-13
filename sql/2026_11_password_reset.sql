-- Password reset tokens and forced password change on first login.
ALTER TABLE `users` ADD `must_change_password` TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_password_resets_token` (`token_hash`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;
