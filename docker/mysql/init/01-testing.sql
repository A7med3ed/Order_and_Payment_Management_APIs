CREATE DATABASE IF NOT EXISTS app_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'app_test'@'%' IDENTIFIED BY 'testing_password';
GRANT ALL PRIVILEGES ON app_testing.* TO 'app_test'@'%';
