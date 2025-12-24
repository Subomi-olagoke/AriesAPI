-- SQL script to ensure comments table exists and supports library URLs
-- Run this against your PostgreSQL database

-- Check if comments table exists, if not create it
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'comments') THEN
        CREATE TABLE comments (
            id BIGSERIAL PRIMARY KEY,
            user_id UUID NOT NULL,
            commentable_type VARCHAR(255) NOT NULL,
            commentable_id BIGINT NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        -- Create index for polymorphic relationship queries
        CREATE INDEX idx_comments_commentable ON comments(commentable_type, commentable_id);
        CREATE INDEX idx_comments_user_id ON comments(user_id);
        CREATE INDEX idx_comments_created_at ON comments(created_at DESC);
        
        RAISE NOTICE 'Created comments table';
    ELSE
        RAISE NOTICE 'Comments table already exists';
    END IF;
END $$;

-- Verify library_urls table exists
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_tables WHERE schemaname = 'public' AND tablename = 'library_urls') THEN
        RAISE EXCEPTION 'library_urls table does not exist. Please run library_urls migration first.';
    ELSE
        RAISE NOTICE 'library_urls table exists';
    END IF;
END $$;

-- Check if we can query comments for library URLs
DO $$
DECLARE
    comment_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO comment_count 
    FROM comments 
    WHERE commentable_type = 'App\Models\LibraryUrl' 
       OR commentable_type = 'library-url' 
       OR commentable_type = 'library_url';
    
    RAISE NOTICE 'Found % comments for library URLs', comment_count;
END $$;

-- Verify the schema is correct
SELECT 
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_name = 'comments'
ORDER BY ordinal_position;


