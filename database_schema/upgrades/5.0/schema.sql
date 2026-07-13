ALTER TABLE `users`
    ADD COLUMN `external_auth_provider` varchar(64) NULL DEFAULT NULL;
