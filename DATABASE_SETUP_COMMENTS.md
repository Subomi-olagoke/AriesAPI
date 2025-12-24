# Database Setup for Library URL Comments

## Summary
Successfully connected to PostgreSQL database and set up the comments table for library URL commenting functionality.

## Database Connection
- **Host**: gondola.proxy.rlwy.net
- **Port**: 15799
- **Database**: railway
- **User**: postgres

## Tables Created/Verified

### 1. Comments Table
Created the `comments` table with the following structure:

```sql
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
```

### 2. Indexes Created
- `idx_comments_commentable` - For efficient queries on commentable_type and commentable_id
- `idx_comments_user_id` - For efficient queries on user_id
- `idx_comments_created_at` - For efficient sorting by creation date (DESC)

### 3. Verified Existing Tables
- ✅ `library_urls` table exists with proper structure
- ✅ `users` table exists with UUID primary key
- ✅ Foreign key relationships are properly configured

## Model Updates

### LibraryUrl Model
Added `comments()` relationship method to `app/Models/LibraryUrl.php`:

```php
public function comments(): MorphMany
{
    return $this->morphMany(\App\Models\Comment::class, 'commentable');
}
```

## How It Works

The comments table uses a polymorphic relationship pattern:
- `commentable_type` stores the model class name (e.g., `App\Models\LibraryUrl` or `App\Models\Post`)
- `commentable_id` stores the ID of the commentable item
- This allows comments to be attached to multiple model types

## API Endpoints

The following endpoints are now available:
- `GET /api/comments/library-url/{id}` - Fetch comments for a library URL
- `POST /api/comments/library-url/{id}` - Create a comment on a library URL
- `DELETE /api/comments/{id}` - Delete a comment

## Testing

To test the setup, you can:

1. **Create a comment** via API:
```bash
POST /api/comments/library-url/1
Body: { "body": "This is a test comment" }
```

2. **Query comments directly**:
```sql
SELECT * FROM comments WHERE commentable_type = 'App\Models\LibraryUrl';
```

3. **Check via Laravel**:
```php
$libraryUrl = LibraryUrl::find(1);
$comments = $libraryUrl->comments;
```

## Status
✅ Database table created
✅ Indexes created
✅ Foreign keys configured
✅ Model relationships added
✅ Ready for use


