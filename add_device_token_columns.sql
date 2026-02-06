-- SQL script to add device_token and device_type columns to users table
-- Run this against your PostgreSQL database

-- Add device_token column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'device_token'
    ) THEN
        ALTER TABLE users ADD COLUMN device_token VARCHAR(255) NULL;
        RAISE NOTICE 'Added device_token column to users table';
    ELSE
        RAISE NOTICE 'device_token column already exists';
    END IF;
END $$;

-- Add device_type column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'device_type'
    ) THEN
        ALTER TABLE users ADD COLUMN device_type VARCHAR(20) NULL;
        RAISE NOTICE 'Added device_type column to users table';
    ELSE
        RAISE NOTICE 'device_type column already exists';
    END IF;
END $$;

-- Create index on device_token for faster lookups
CREATE INDEX IF NOT EXISTS idx_users_device_token ON users(device_token) WHERE device_token IS NOT NULL;

-- Verify the columns were added
SELECT 
    column_name,
    data_type,
    is_nullable,
    character_maximum_length
FROM information_schema.columns
WHERE table_name = 'users' 
    AND column_name IN ('device_token', 'device_type')
ORDER BY column_name;
