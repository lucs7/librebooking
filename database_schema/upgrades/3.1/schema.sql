-- Add description column to custom_attributes table
-- This allows for longer help text/descriptions to be shown to users
ALTER TABLE `custom_attributes` ADD COLUMN `description` TEXT;
