# Profile Picture Caching Issue - FIXED

## Problem Identified

The issue was that **avatar/profile picture data was stored in two different places**:

1. `users.avatar` - Avatar field in the User model
2. `profiles.avatar` - Avatar field in the Profile model

When users updated their profile picture through the `uploadAvatar` endpoint, only the `profiles.avatar` field was being updated, but the profile show methods were prioritizing `users.avatar` over `profiles.avatar`, causing the old picture to still be displayed.

## Root Cause

In `ProfileController.php`, the profile show methods had this logic:
```php
'avatar' => $user->avatar ?? ($user->profile ? $user->profile->avatar : null)
```

This prioritizes `$user->avatar` (old value) over `$user->profile->avatar` (newly updated value).

## Solution Implemented

### 1. **Fixed Avatar Upload Methods**
Updated both `uploadAvatar()` and `update()` methods in `ProfileController.php` to sync both avatar fields:

```php
// OLD - Only updated profile avatar
$profile->avatar = $avatarUrl;

// NEW - Updates both fields
$profile->avatar = $avatarUrl;
$user->avatar = $avatarUrl; // Sync with user avatar
```

### 2. **Fixed Avatar Priority Logic**
Changed all profile show methods to prioritize profile avatar over user avatar:

```php
// OLD - Prioritized user avatar
'avatar' => $user->avatar ?? ($profile ? $profile->avatar : null)

// NEW - Prioritizes profile avatar
'avatar' => ($profile && $profile->avatar) ? $profile->avatar : $user->avatar
```

### 3. **Added Helper Method in User Model**
Created `getAvatarUrl()` method for consistent avatar retrieval:

```php
public function getAvatarUrl()
{
    // Prioritize profile avatar, then user avatar
    if ($this->profile && $this->profile->avatar) {
        return $this->profile->avatar;
    }
    
    return $this->avatar;
}
```

### 4. **Fixed Incorrect Route**
Fixed duplicate/incorrect avatar upload route in `routes/api.php`:

```php
// Fixed to use correct method name
Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
```

## Files Modified

1. **`app/Http/Controllers/ProfileController.php`**:
   - `uploadAvatar()` method - Now syncs both avatar fields
   - `update()` method - Now syncs both avatar fields
   - `show()` method - Fixed avatar priority logic
   - `showByUserId()` method - Fixed avatar priority logic
   - `showByUsername()` method - Fixed avatar priority logic
   - `showByShareKey()` method - Fixed avatar priority logic

2. **`app/Models/User.php`**:
   - Added `getAvatarUrl()` helper method

3. **`routes/api.php`**:
   - Fixed incorrect route method name

## How It Works Now

1. **Upload Process**:
   - User uploads new avatar via `/api/profile/avatar`
   - System updates both `users.avatar` AND `profiles.avatar`
   - Both fields now have the same new avatar URL

2. **Fetch Process**:
   - Profile endpoints check profile avatar first
   - If profile avatar exists, use it
   - Otherwise, fall back to user avatar
   - Always returns the most recent avatar

## Testing

To verify the fix works:

1. **Upload a new avatar**:
   ```bash
   POST /api/profile/avatar
   Content-Type: multipart/form-data
   
   avatar: [image file]
   ```

2. **Fetch profile immediately**:
   ```bash
   GET /api/profile
   ```

3. **Verify avatar URL matches the uploaded image**

## Future Improvements

Consider consolidating to a single avatar field to eliminate this dual-field complexity in a future database migration:

```sql
-- Future migration to standardize avatar storage
ALTER TABLE users ADD COLUMN new_avatar_url VARCHAR(255);
UPDATE users SET new_avatar_url = COALESCE(
    (SELECT avatar FROM profiles WHERE profiles.user_id = users.id),
    users.avatar
);
ALTER TABLE users DROP COLUMN avatar;
ALTER TABLE users CHANGE new_avatar_url avatar VARCHAR(255);
ALTER TABLE profiles DROP COLUMN avatar;
```

## Cache Invalidation (if applicable)

If you're using any caching layers (Redis, CDN, etc.), make sure to invalidate user profile caches when avatar is updated:

```php
// Example cache invalidation
Cache::forget("user_profile_{$user->id}");
Cache::forget("user_avatar_{$user->id}");
```

The profile picture caching issue is now resolved! ✅