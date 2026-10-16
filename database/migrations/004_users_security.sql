ALTER TABLE users
    ADD COLUMN email VARCHAR(191) NULL AFTER username,
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1,
    ADD UNIQUE KEY users_email_unique (email);

ALTER TABLE login_attempts ADD INDEX login_attempts_username_time (username, time);
