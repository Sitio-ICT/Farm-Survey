-- Marketplace members (collectors and buyers) and their identity verification.
-- Members are kept apart from staff `users`: a self-registered account can never
-- receive a staff designation or reach the staff dashboard.

CREATE TABLE IF NOT EXISTS `members` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(26) NOT NULL,                     -- random, shown in URLs and exports instead of `id`
  `role` ENUM('collector', 'buyer') NOT NULL,
  `first_name` VARCHAR(60) NOT NULL,
  `middle_name` VARCHAR(60) NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(16) NOT NULL,                      -- E.164, e.g. +2348031234567
  `state` VARCHAR(60) NOT NULL,
  `lga` VARCHAR(100) NOT NULL,
  `organisation` VARCHAR(120) NULL,                  -- buyers only
  `password_hash` VARCHAR(255) NOT NULL,
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `id_status` ENUM('none', 'manual_review', 'verified', 'rejected') NOT NULL DEFAULT 'none',
  `id_verified_at` DATETIME NULL,
  `account_status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `terms_version` VARCHAR(20) NOT NULL,
  `terms_accepted_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `uq_members_public_id` (`public_id`),
  UNIQUE INDEX `uq_members_email` (`email`),
  UNIQUE INDEX `uq_members_phone` (`phone`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Single-use codes and links: phone OTP, email verification, password reset.
-- Only a hash of the secret is stored.
CREATE TABLE IF NOT EXISTS `member_tokens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `member_id` INT NOT NULL,
  `purpose` ENUM('phone_otp', 'email_verify', 'password_reset') NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_member_tokens_lookup` (`member_id`, `purpose`, `used_at`),
  CONSTRAINT `fk_member_tokens_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

-- One row per identity check attempt. The raw ID number is never stored:
-- `id_number_hmac` (keyed hash) detects the same ID on two accounts, `id_number_last4` helps support staff.
-- Selfie and document images live encrypted on disk (`*_file`), readable only through the staff review page.
CREATE TABLE IF NOT EXISTS `identity_verifications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `member_id` INT NOT NULL,
  `id_type` ENUM('nin', 'drivers_licence', 'passport', 'voters_card') NOT NULL,
  `id_number_hmac` CHAR(64) NOT NULL,
  `id_number_last4` CHAR(4) NOT NULL,
  `provider` VARCHAR(30) NOT NULL,                   -- sandbox, dojah, manual
  `provider_ref` VARCHAR(100) NULL,
  `outcome` ENUM('verified', 'manual_review', 'rejected', 'error') NOT NULL,
  `name_score` DECIMAL(4,3) NULL,                    -- 0..1 similarity of declared vs registry name
  `dob_match` TINYINT(1) NULL,
  `selfie_confidence` DECIMAL(5,2) NULL,             -- provider face-match score, 0..100
  `reasons` TEXT NULL,                               -- JSON array of reason codes
  `selfie_file` VARCHAR(80) NULL,
  `document_file` VARCHAR(80) NULL,
  `consent_at` DATETIME NOT NULL,
  `reviewed_by` INT NULL,                            -- staff users.id
  `reviewed_at` DATETIME NULL,
  `review_decision` ENUM('approved', 'rejected') NULL,
  `review_note` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_identity_member` (`member_id`),
  INDEX `idx_identity_hmac` (`id_type`, `id_number_hmac`),
  INDEX `idx_identity_queue` (`outcome`, `reviewed_at`),
  CONSTRAINT `fk_identity_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Sliding-window rate limits (sign-up, login, OTP resend, ID checks).
CREATE TABLE IF NOT EXISTS `rate_limit_events` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `action` VARCHAR(40) NOT NULL,
  `key_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_rate_limit` (`action`, `key_hash`, `created_at`)
) ENGINE = InnoDB;

-- Every time staff open a member's selfie or ID document.
CREATE TABLE IF NOT EXISTS `vault_access_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `verification_id` INT NOT NULL,
  `item` ENUM('selfie', 'document') NOT NULL,
  `purpose` VARCHAR(60) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_vault_access_verification` (`verification_id`)
) ENGINE = InnoDB;
