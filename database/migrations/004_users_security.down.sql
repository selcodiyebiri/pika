ALTER TABLE login_attempts DROP INDEX login_attempts_username_time;

ALTER TABLE users
    DROP INDEX users_email_unique,
    DROP COLUMN session_version,
    DROP COLUMN email;
