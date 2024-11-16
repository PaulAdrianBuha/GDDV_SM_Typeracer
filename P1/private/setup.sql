CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY,
    user_name VARCHAR(63),
    user_password VARCHAR(255),
    user_email VARCHAR(255) UNIQUE,
    user_verified INTEGER NOT NULL DEFAULT 0,
    user_verification_code VARCHAR(255)
);

CREATE UNIQUE INDEX user_name_UNIQUE ON users (user_name ASC);

CREATE TABLE IF NOT EXISTS cookies (
    cookie varchar(255) PRIMARY KEY,
    user_id INTEGER
);

CREATE TABLE IF NOT EXISTS user_secrets (
    secret_id INTEGER PRIMARY KEY,
    user_id INTEGER,
    secret_text varchar(255)
);