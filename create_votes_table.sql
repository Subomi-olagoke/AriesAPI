-- Create votes table for Reddit-style voting system
-- Run this script to add voting functionality to your database

CREATE TABLE IF NOT EXISTS votes (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    voteable_id BIGINT NOT NULL,
    voteable_type VARCHAR(255) NOT NULL,
    vote_type VARCHAR(10) NOT NULL CHECK (vote_type IN ('up', 'down')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Ensure one vote per user per content item
    UNIQUE(user_id, voteable_id, voteable_type)
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_votes_voteable ON votes(voteable_type, voteable_id);
CREATE INDEX IF NOT EXISTS idx_votes_user_id ON votes(user_id);
CREATE INDEX IF NOT EXISTS idx_votes_type ON votes(vote_type);

-- Add foreign key constraint
ALTER TABLE votes 
ADD CONSTRAINT fk_votes_user 
FOREIGN KEY (user_id) 
REFERENCES users(id) 
ON DELETE CASCADE;

-- Add comment to the table
COMMENT ON TABLE votes IS 'Stores upvotes and downvotes for library content (Reddit-style)';
COMMENT ON COLUMN votes.vote_type IS 'Type of vote: "up" for upvote, "down" for downvote';
COMMENT ON COLUMN votes.voteable_type IS 'Polymorphic type - typically App\Models\LibraryUrl';
COMMENT ON COLUMN votes.voteable_id IS 'ID of the voted content';

