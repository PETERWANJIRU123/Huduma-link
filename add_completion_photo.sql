-- Migration to add completion_photo column to requests table
-- Run this to update existing databases

USE hudumalink;

ALTER TABLE requests ADD COLUMN completion_photo VARCHAR(180) NULL AFTER staff_note;
