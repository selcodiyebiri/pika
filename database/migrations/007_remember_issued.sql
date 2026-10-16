-- remember-me tokens now expire on the server (30 days) and are replaced on every automatic login
ALTER TABLE users ADD COLUMN remember_issued INT UNSIGNED NULL AFTER remember_token;
