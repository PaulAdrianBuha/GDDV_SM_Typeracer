sqlite3 users.db "CREATE TABLE IF NOT EXISTS `users` (`user_id` INTEGER PRIMARY KEY, `user_name` varchar(63), `user_password` varchar(255), 'user_email' varchar(255) UNIQUE);"
sqlite3 users.db "CREATE UNIQUE INDEX `user_name_UNIQUE` ON `users` (`user_name` ASC);"
