-- Quick Installation Script for Highlights Feature
-- Run this on your PostgreSQL database

-- ============================================
-- 1. Create the highlights table
-- ============================================

CREATE TABLE IF NOT EXISTS highlights (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    url_id VARCHAR(255) NOT NULL,
    user_id UUID NOT NULL,
    url TEXT NOT NULL,
    selected_text TEXT NOT NULL,
    note TEXT,
    color VARCHAR(7) DEFAULT '#FFEB3B',
    range_start INT NOT NULL,
    range_end INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_highlights_user_id FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_range_valid CHECK (range_end > range_start),
    CONSTRAINT chk_selected_text_not_empty CHECK (selected_text <> '')
);

-- ============================================
-- 2. Create indexes
-- ============================================

CREATE INDEX IF NOT EXISTS idx_highlights_url_id ON highlights(url_id);
CREATE INDEX IF NOT EXISTS idx_highlights_user_id ON highlights(user_id);
CREATE INDEX IF NOT EXISTS idx_highlights_user_url ON highlights(user_id, url_id);
CREATE INDEX IF NOT EXISTS idx_highlights_created_at ON highlights(created_at DESC);

-- ============================================
-- 3. Create trigger for auto-updating updated_at
-- ============================================

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

-- ============================================
-- 4. Verify installation
-- ============================================

SELECT 
    'Highlights table created successfully!' as status,
    COUNT(*) as initial_count 
FROM highlights;
