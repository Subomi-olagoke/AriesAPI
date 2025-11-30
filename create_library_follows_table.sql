-- Create library_follows table
-- Note: This assumes users and open_libraries tables exist
-- If they don't exist, create them first or remove foreign key constraints temporarily

CREATE TABLE IF NOT EXISTS library_follows (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    library_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Unique constraint to prevent duplicate follows
    CONSTRAINT unique_user_library_follow 
        UNIQUE (user_id, library_id)
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_library_follows_user_id ON library_follows(user_id);
CREATE INDEX IF NOT EXISTS idx_library_follows_library_id ON library_follows(library_id);

-- Add foreign keys if the referenced tables exist
-- Uncomment these after users and open_libraries tables are created
-- ALTER TABLE library_follows 
--     ADD CONSTRAINT fk_library_follows_user 
--     FOREIGN KEY (user_id) 
--     REFERENCES users(id) 
--     ON DELETE CASCADE;

-- ALTER TABLE library_follows 
--     ADD CONSTRAINT fk_library_follows_library 
--     FOREIGN KEY (library_id) 
--     REFERENCES open_libraries(id) 
--     ON DELETE CASCADE;

