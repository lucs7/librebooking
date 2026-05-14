ALTER TABLE `reservation_instances`
    ADD INDEX `idx_series_start_end` (`series_id`, `start_date`, `end_date`);

ALTER TABLE `reservation_users`
    ADD INDEX `idx_instance_level` (`reservation_instance_id`, `reservation_user_level`);

ALTER TABLE `reservation_reminders`
    ADD INDEX `idx_series_type_minutes` (`series_id`, `reminder_type`, `minutes_prior`);

ALTER TABLE `blackout_instances`
    ADD INDEX `idx_series_start_end` (`blackout_series_id`, `start_date`, `end_date`);

ALTER TABLE `custom_attribute_values`
    ADD INDEX `idx_entity_category` (`entity_id`, `attribute_category`, `custom_attribute_id`);
