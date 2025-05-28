# Live Class Cleanup System

This system automatically removes expired live classes and their related data from the database to keep storage clean and performant.

## How It Works

### 1. **Automatic Cleanup**
- **Schedule**: Runs daily at 3:00 AM
- **Trigger**: Live classes that ended more than 1 day ago
- **Command**: `php artisan live-classes:cleanup --days=1`

### 2. **What Gets Cleaned Up**
When a live class is cleaned up, the following data is removed:
- The live class record itself
- All chat messages from that class
- All participant records for that class

### 3. **Safety Measures**
- **Grace Period**: Classes are only deleted 1 day after their end date (configurable)
- **Transaction Safety**: All deletions happen in database transactions
- **Logging**: All cleanup actions are logged for auditing
- **Error Handling**: If one class fails to delete, others continue

## Configuration

### Automatic Schedule
The cleanup is scheduled in `app/Console/Kernel.php`:
```php
// Clean up expired live classes daily at 3am
$schedule->command('live-classes:cleanup --days=1')->daily()->at('03:00');
```

### Manual Execution
You can run the cleanup manually:

#### Via Command Line
```bash
# Clean up classes ended more than 1 day ago
php artisan live-classes:cleanup

# Clean up classes ended more than 3 days ago
php artisan live-classes:cleanup --days=3
```

#### Via API (Admin Only)
```http
POST /api/live-classes/cleanup-expired
Content-Type: application/json
Authorization: Bearer {admin_token}

{
    "days": 1
}
```

## Model Methods Added

### LiveClass Model
- `isExpired()` - Check if a class is past its end date
- `scopeExpired($query)` - Query scope for expired classes
- `cleanupExpired($daysOld)` - Static method to clean up expired classes

### Usage Examples
```php
// Check if a specific class is expired
$class = LiveClass::find(1);
if ($class->isExpired()) {
    // Handle expired class
}

// Get all expired classes
$expiredClasses = LiveClass::expired()->get();

// Clean up classes ended more than 2 days ago
$cleanedCount = LiveClass::cleanupExpired(2);
```

## Database Changes
No new migrations needed - the system uses existing `ended_at` timestamps.

## Logging
All cleanup actions are logged with:
- Class ID and title
- End date
- Success/failure status
- Error messages (if any)

Check logs at: `storage/logs/laravel.log`

## Monitoring
To monitor the cleanup process:

1. **Check scheduled commands**:
   ```bash
   php artisan schedule:list
   ```

2. **Run cleanup manually to test**:
   ```bash
   php artisan live-classes:cleanup --days=0
   ```

3. **Check logs for cleanup activity**:
   ```bash
   tail -f storage/logs/laravel.log | grep "live class"
   ```

## Customization

### Change Cleanup Schedule
Edit `app/Console/Kernel.php` to modify when cleanup runs:
```php
// Run twice daily
$schedule->command('live-classes:cleanup --days=1')->twiceDaily(3, 15);

// Run weekly
$schedule->command('live-classes:cleanup --days=7')->weekly();
```

### Change Grace Period
Modify the days parameter in the schedule or API call:
```php
// Wait 3 days before cleanup
$schedule->command('live-classes:cleanup --days=3')->daily()->at('03:00');
```

### Disable Cleanup
Comment out the line in `app/Console/Kernel.php`:
```php
// $schedule->command('live-classes:cleanup --days=1')->daily()->at('03:00');
```

## Troubleshooting

### Command Not Found
If you get "Command not found", ensure the command is registered in `app/Console/Kernel.php`:
```php
protected $commands = [
    \App\Console\Commands\CleanupExpiredLiveClasses::class,
];
```

### Permissions Error
Ensure the web server has write permissions to `storage/logs/`.

### Manual Recovery
If you need to restore a deleted class, check your database backups. The cleanup is permanent and cannot be undone.