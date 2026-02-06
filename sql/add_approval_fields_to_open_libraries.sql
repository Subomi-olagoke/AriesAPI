-- Add approval fields to the open_libraries table
ALTER TABLE open_libraries 
ADD COLUMN is_approved BOOLEAN DEFAULT FALSE,
ADD COLUMN approval_status VARCHAR(255) DEFAULT 'pending',
ADD COLUMN approval_date TIMESTAMP NULL,
ADD COLUMN approved_by BIGINT UNSIGNED NULL,
ADD COLUMN has_ai_cover BOOLEAN DEFAULT FALSE,
ADD COLUMN cover_prompt TEXT NULL,
ADD COLUMN cover_image_url VARCHAR(255) NULL,
ADD FOREIGN KEY (approved_by) REFERENCES users(id);

-- Add index for faster queries on approval status
CREATE INDEX idx_open_libraries_approval_status ON open_libraries(approval_status);

-- Add index for faster queries on approved libraries
CREATE INDEX idx_open_libraries_is_approved ON open_libraries(is_approved);