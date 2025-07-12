-- SQL script to make all users follow 'subomi'
-- This script will:
-- 1. Find the user with username 'subomi'
-- 2. Get all other users
-- 3. Insert follow relationships, avoiding duplicates

-- First, let's see if the user 'subomi' exists
SELECT id, username, email FROM users WHERE username = 'subomi';

-- Insert follow relationships for all users to follow 'subomi'
-- This uses a subquery to get all users except 'subomi' and inserts follows
INSERT INTO follows (user_id, followeduser, created_at, updated_at)
SELECT 
    u.id as user_id,
    (SELECT id FROM users WHERE username = 'subomi') as followeduser,
    NOW() as created_at,
    NOW() as updated_at
FROM users u
WHERE u.username != 'subomi'
AND NOT EXISTS (
    -- Check if this follow relationship already exists
    SELECT 1 FROM follows f 
    WHERE f.user_id = u.id 
    AND f.followeduser = (SELECT id FROM users WHERE username = 'subomi')
);

-- Show the results
SELECT 
    'Total users following subomi:' as description,
    COUNT(*) as count
FROM follows 
WHERE followeduser = (SELECT id FROM users WHERE username = 'subomi');

-- Show some sample follows
SELECT 
    u.username as follower,
    'follows' as action,
    'subomi' as followed
FROM follows f
JOIN users u ON f.user_id = u.id
WHERE f.followeduser = (SELECT id FROM users WHERE username = 'subomi')
LIMIT 10; 