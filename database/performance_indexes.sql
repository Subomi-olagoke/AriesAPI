-- Performance Optimization Indexes
-- Run this script to add critical database indexes for improved query performance

-- 1. Library Content Queries (most frequently accessed)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_library_content_library_type 
ON library_content(library_id, content_type);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_library_content_type_id 
ON library_content(content_type, content_id);

-- 2. Library Views (recently viewed feature)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_library_views_user_recent 
ON library_views(user_id, viewed_at DESC);

-- 3. Notifications (unread notifications query)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notifications_unread 
ON notifications(notifiable_id) WHERE read_at IS NULL;

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notifications_user_created 
ON notifications(notifiable_id, created_at DESC);

-- 4. Library URLs (duplicate check optimization)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_library_urls_url_hash 
ON library_urls(url);

-- 5. Library Follows (followers count)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_library_follows_library 
ON library_follows(library_id);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_library_follows_user 
ON library_follows(user_id);

-- 6. User Follows (followers/following counts)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_follows_user 
ON follows(user_id);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_follows_followed 
ON follows(followeduser);

-- 7. Votes (upvote/downvote counts)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_votes_voteable 
ON votes(voteable_type, voteable_id, vote_type);

-- 8. Comments (comment counts)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_comments_commentable 
ON comments(commentable_type, commentable_id);

-- 9. Library Content Relevance (sorting optimization)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_library_content_relevance 
ON library_content(library_id, relevance_score DESC);

-- Show index creation progress
SELECT 
    schemaname,
    tablename,
    indexname,
    indexdef
FROM pg_indexes 
WHERE indexname LIKE 'idx_%'
ORDER BY tablename, indexname;
