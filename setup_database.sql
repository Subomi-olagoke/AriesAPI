-- ============================================
-- Aries API Database Setup Script
-- PostgreSQL Database
-- ============================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================
-- 1. CORE USER TABLES
-- ============================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    username VARCHAR(255) UNIQUE NOT NULL,
    role VARCHAR(20) DEFAULT 'explorer' CHECK (role IN ('educator', 'learner', 'explorer')),
    avatar VARCHAR(255),
    verification_code VARCHAR(255),
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    isAdmin BOOLEAN DEFAULT FALSE,
    is_banned BOOLEAN DEFAULT FALSE,
    banned_at TIMESTAMP,
    ban_reason TEXT,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP,
    alex_points BIGINT DEFAULT 0,
    point_level INTEGER DEFAULT 1,
    points_to_next_level BIGINT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);

-- Profiles table
CREATE TABLE IF NOT EXISTS profiles (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL UNIQUE,
    bio TEXT,
    avatar VARCHAR(255),
    qualifications JSONB,
    teaching_style TEXT,
    availability JSONB,
    hire_rate DECIMAL(10,2),
    hire_currency VARCHAR(3) DEFAULT 'USD',
    social_links JSONB,
    share_key VARCHAR(20) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_profiles_user_id ON profiles(user_id);
CREATE INDEX idx_profiles_share_key ON profiles(share_key);

-- User blocks table
CREATE TABLE IF NOT EXISTS user_blocks (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    blocked_user_id UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_blocks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_blocks_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT unique_user_block UNIQUE (user_id, blocked_user_id)
);

CREATE INDEX idx_user_blocks_user_id ON user_blocks(user_id);
CREATE INDEX idx_user_blocks_blocked_user_id ON user_blocks(blocked_user_id);

-- User mutes table
CREATE TABLE IF NOT EXISTS user_mutes (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    muted_user_id UUID NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_mutes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_mutes_muted FOREIGN KEY (muted_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT unique_user_mute UNIQUE (user_id, muted_user_id)
);

CREATE INDEX idx_user_mutes_user_id ON user_mutes(user_id);
CREATE INDEX idx_user_mutes_muted_user_id ON user_mutes(muted_user_id);

-- ============================================
-- 2. AUTHENTICATION TABLES
-- ============================================

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Password reset temporary tokens
CREATE TABLE IF NOT EXISTS password_reset_temporary_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_password_reset_tokens_user_id ON password_reset_temporary_tokens(user_id);
CREATE INDEX idx_password_reset_tokens_token ON password_reset_temporary_tokens(token);

-- Personal access tokens (Laravel Sanctum)
CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id BIGSERIAL PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    abilities TEXT,
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_personal_access_tokens_tokenable ON personal_access_tokens(tokenable_type, tokenable_id);

-- ============================================
-- 3. LIBRARY TABLES
-- ============================================

-- Open libraries table
CREATE TABLE IF NOT EXISTS open_libraries (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(50),
    thumbnail_url VARCHAR(255),
    cover_image_url VARCHAR(255),
    course_id BIGINT,
    criteria JSONB,
    keywords JSONB,
    url_items JSONB,
    is_approved BOOLEAN DEFAULT FALSE,
    approval_status VARCHAR(20) DEFAULT 'pending',
    approval_date TIMESTAMP,
    approved_by UUID,
    rejection_reason TEXT,
    cover_prompt TEXT,
    ai_generated BOOLEAN DEFAULT FALSE,
    ai_generation_date TIMESTAMP,
    ai_model_used VARCHAR(255),
    has_ai_cover BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_open_libraries_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_open_libraries_approval_status ON open_libraries(approval_status);
CREATE INDEX idx_open_libraries_is_approved ON open_libraries(is_approved);
CREATE INDEX idx_open_libraries_type ON open_libraries(type);

-- Library URLs table
CREATE TABLE IF NOT EXISTS library_urls (
    id BIGSERIAL PRIMARY KEY,
    url VARCHAR(2048) NOT NULL,
    title VARCHAR(255),
    summary TEXT,
    notes TEXT,
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_library_urls_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_library_urls_url ON library_urls(url);
CREATE INDEX idx_library_urls_created_by ON library_urls(created_by);

-- Library content (polymorphic pivot table)
CREATE TABLE IF NOT EXISTS library_content (
    id BIGSERIAL PRIMARY KEY,
    library_id BIGINT NOT NULL,
    content_id BIGINT NOT NULL,
    content_type VARCHAR(255) NOT NULL,
    relevance_score DECIMAL(3,2) DEFAULT 0.5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_library_content_library FOREIGN KEY (library_id) REFERENCES open_libraries(id) ON DELETE CASCADE
);

CREATE INDEX idx_library_content_library_id ON library_content(library_id);
CREATE INDEX idx_library_content_content ON library_content(content_type, content_id);

-- Library follows table (already created, but adding here for completeness)
-- Note: This table was already created earlier

-- ============================================
-- 4. READLIST TABLES
-- ============================================

-- Readlists table
CREATE TABLE IF NOT EXISTS readlists (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    is_public BOOLEAN DEFAULT FALSE,
    is_system BOOLEAN DEFAULT FALSE,
    share_key VARCHAR(10) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_readlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_readlists_user_id ON readlists(user_id);
CREATE INDEX idx_readlists_share_key ON readlists(share_key);
CREATE INDEX idx_readlists_is_public ON readlists(is_public);

-- Readlist items table
CREATE TABLE IF NOT EXISTS readlist_items (
    id BIGSERIAL PRIMARY KEY,
    readlist_id UUID NOT NULL,
    item_id VARCHAR(255),
    item_type VARCHAR(255),
    "order" INTEGER DEFAULT 0,
    notes TEXT,
    title VARCHAR(255),
    description TEXT,
    url VARCHAR(2048),
    type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_readlist_items_readlist FOREIGN KEY (readlist_id) REFERENCES readlists(id) ON DELETE CASCADE
);

CREATE INDEX idx_readlist_items_readlist_id ON readlist_items(readlist_id);
CREATE INDEX idx_readlist_items_item ON readlist_items(item_type, item_id);
CREATE INDEX idx_readlist_items_order ON readlist_items(readlist_id, "order");

-- Readlist links table
CREATE TABLE IF NOT EXISTS readlist_links (
    id BIGSERIAL PRIMARY KEY,
    readlist_id UUID NOT NULL,
    url VARCHAR(2048) NOT NULL,
    title VARCHAR(255),
    description TEXT,
    added_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_readlist_links_readlist FOREIGN KEY (readlist_id) REFERENCES readlists(id) ON DELETE CASCADE,
    CONSTRAINT fk_readlist_links_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_readlist_links_readlist_id ON readlist_links(readlist_id);

-- ============================================
-- 5. ALEX POINTS TABLES
-- ============================================

-- Alex Points transactions table
CREATE TABLE IF NOT EXISTS alex_points_transactions (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    points INTEGER DEFAULT 0,
    action_type VARCHAR(255) NOT NULL,
    reference_type VARCHAR(255),
    reference_id VARCHAR(255),
    description TEXT,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alex_points_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_alex_points_transactions_user_id ON alex_points_transactions(user_id);
CREATE INDEX idx_alex_points_transactions_action_type ON alex_points_transactions(action_type);
CREATE INDEX idx_alex_points_transactions_reference ON alex_points_transactions(reference_type, reference_id);

-- Alex Points rules table
CREATE TABLE IF NOT EXISTS alex_points_rules (
    id BIGSERIAL PRIMARY KEY,
    action_type VARCHAR(255) UNIQUE NOT NULL,
    points INTEGER NOT NULL,
    description VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_one_time BOOLEAN DEFAULT FALSE,
    daily_limit INTEGER DEFAULT 0,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_alex_points_rules_action_type ON alex_points_rules(action_type);
CREATE INDEX idx_alex_points_rules_is_active ON alex_points_rules(is_active);

-- Alex Points levels table
CREATE TABLE IF NOT EXISTS alex_points_levels (
    id BIGSERIAL PRIMARY KEY,
    level INTEGER UNIQUE NOT NULL,
    points_required BIGINT NOT NULL,
    name VARCHAR(255),
    description TEXT,
    rewards JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_alex_points_levels_level ON alex_points_levels(level);

-- ============================================
-- 6. NOTIFICATIONS TABLE
-- ============================================

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id UUID NOT NULL,
    data JSONB NOT NULL,
    read_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_notifiable ON notifications(notifiable_type, notifiable_id);
CREATE INDEX idx_notifications_read_at ON notifications(read_at);

-- ============================================
-- 7. LIKES TABLE (Polymorphic)
-- ============================================

-- Likes table
CREATE TABLE IF NOT EXISTS likes (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    likeable_type VARCHAR(255),
    likeable_id BIGINT,
    comment_id BIGINT,
    post_id BIGINT,
    course_id BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_likes_user_id ON likes(user_id);
CREATE INDEX idx_likes_likeable ON likes(likeable_type, likeable_id);
CREATE INDEX idx_likes_comment_id ON likes(comment_id);
CREATE INDEX idx_likes_post_id ON likes(post_id);
CREATE INDEX idx_likes_course_id ON likes(course_id);

-- ============================================
-- 8. REPORTS TABLE (Polymorphic)
-- ============================================

-- Reports table
CREATE TABLE IF NOT EXISTS reports (
    id BIGSERIAL PRIMARY KEY,
    reporter_id UUID NOT NULL,
    reportable_type VARCHAR(255) NOT NULL,
    reportable_id BIGINT NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'reviewed', 'resolved', 'dismissed')),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_reports_reporter_id ON reports(reporter_id);
CREATE INDEX idx_reports_reportable ON reports(reportable_type, reportable_id);
CREATE INDEX idx_reports_status ON reports(status);

-- ============================================
-- 9. SYSTEM TABLES
-- ============================================

-- Failed jobs table
CREATE TABLE IF NOT EXISTS failed_jobs (
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_failed_jobs_uuid ON failed_jobs(uuid);

-- Jobs table
CREATE TABLE IF NOT EXISTS jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts SMALLINT NOT NULL,
    reserved_at INTEGER,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE INDEX idx_jobs_queue ON jobs(queue);

-- ============================================
-- END OF SETUP
-- ============================================

