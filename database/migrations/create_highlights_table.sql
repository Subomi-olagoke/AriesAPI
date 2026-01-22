-- ============================================
-- Create Highlights Table
-- PostgreSQL Database
-- ============================================

-- Highlights table for text highlighting feature
CREATE TABLE IF NOT EXISTS highlights (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    url_id VARCHAR(255) NOT NULL,  -- Foreign key to library_urls
    user_id UUID NOT NULL,          -- Foreign key to users
    url TEXT NOT NULL,              -- The actual webpage URL
    selected_text TEXT NOT NULL,    -- The highlighted text
    note TEXT,                      -- Optional user note
    color VARCHAR(7) DEFAULT '#FFEB3B',  -- Hex color code
    range_start INT NOT NULL,       -- Character position where highlight starts
    range_end INT NOT NULL,         -- Character position where highlight ends
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraints
    CONSTRAINT fk_highlights_user_id FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    
    -- Check constraints
    CONSTRAINT chk_range_valid CHECK (range_end > range_start),
    CONSTRAINT chk_selected_text_not_empty CHECK (selected_text <> '')
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_highlights_url_id ON highlights(url_id);
CREATE INDEX IF NOT EXISTS idx_highlights_user_id ON highlights(user_id);
CREATE INDEX IF NOT EXISTS idx_highlights_user_url ON highlights(user_id, url_id);
CREATE INDEX IF NOT EXISTS idx_highlights_created_at ON highlights(created_at DESC);

-- Trigger to automatically update updated_at timestamp
CREATE OR REPLACE FUNCTION update_highlights_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_highlights_updated_at
    BEFORE UPDATE ON highlights
    FOR EACH ROW
    EXECUTE FUNCTION update_highlights_updated_at();

-- Add comment to table
COMMENT ON TABLE highlights IS 'Stores user text highlights and annotations for URLs';
COMMENT ON COLUMN highlights.url_id IS 'Reference to library_urls table';
COMMENT ON COLUMN highlights.color IS 'Hex color code for the highlight (e.g., #FFEB3B for yellow)';
COMMENT ON COLUMN highlights.range_start IS 'Character position where highlight starts (0-indexed)';
COMMENT ON COLUMN highlights.range_end IS 'Character position where highlight ends (0-indexed, exclusive)';
