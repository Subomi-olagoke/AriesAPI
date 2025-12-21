# Database Schema Updates

This file documents manual database schema changes that were applied directly.

## 2025-12-21/22 - User Follows & Setup Completed

### 1. Created `follows` table for user-to-user follows
```sql
CREATE TABLE IF NOT EXISTS follows (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    followeduser UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, followeduser)
);

CREATE INDEX IF NOT EXISTS idx_follows_user_id ON follows(user_id);
CREATE INDEX IF NOT EXISTS idx_follows_followeduser ON follows(followeduser);
```

### 2. Added `creator_id` to track library creators
```sql
ALTER TABLE open_libraries ADD COLUMN IF NOT EXISTS creator_id UUID REFERENCES users(id) ON DELETE SET NULL;
```

### 3. Added `setup_completed` to users table
```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS setup_completed BOOLEAN DEFAULT true;
```

### 4. Approved all existing libraries
```sql
UPDATE open_libraries 
SET is_approved = true, 
    approval_status = 'approved',
    approval_date = NOW(),
    updated_at = NOW()
WHERE approval_status != 'approved';
```

## Purpose

- `follows` table: Enables user-to-user following for private libraries feature
- `creator_id`: Tracks which user created each library for auto-approval and permissions
- `setup_completed`: Tracks if user has completed onboarding/setup process
- Library approvals: All existing libraries were approved as they were created by admins
