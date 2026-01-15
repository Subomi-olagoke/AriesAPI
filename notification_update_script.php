<?php
/**
 * This script updates all notification classes to extend from BaseNotification
 * and adds support for push notifications.
 * 
 * Run this script from the command line:
 * php notification_update_script.php
 */

$notificationClasses = [
    'CommentNotification.php',
    'CourseEnrollmentNotification.php',
    'followedNotification.php',
    'HireInstructorNotification.php',
    'HireSessionNotification.php',
    'LikeNotification.php',
    'MentionNotification.php',
    'NewMessage.php',
    'PaymentRequiredNotification.php',
    'SessionCompletedNotification.php',
    'SessionScheduledNotification.php'
    // HireRequestNotification.php is already updated
];

$notificationsPath = __DIR__ . '/app/Notifications/';

foreach ($notificationClasses as $classFile) {
    $filePath = $notificationsPath . $classFile;
    
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // 1. Update class extension
    $content = preg_replace(
        '/extends\s+Notification/',
        'extends BaseNotification',
        $content
    );
    
    // 2. Remove Queueable trait use
    $content = preg_replace(
        '/use\s+Queueable;/',
        '',
        $content
    );
    
    // 3. Remove via method if it exists
    $content = preg_replace(
        '/public\s+function\s+via\s*\(.*?\)\s*{.*?}[\r\n\s]*/s',
        '',
        $content
    );
    
    // 4. Add title to toArray method if it exists
    if (preg_match('/public\s+function\s+toArray\s*\(.*?\)\s*{/s', $content)) {
        $content = preg_replace(
            '/(public\s+function\s+toArray\s*\(.*?\)\s*{[\r\n\s]*return\s*\[)/',
            '$1' . "\n            'title' => 'Edututor Notification',",
            $content
        );
    }
    
    // 5. Add notification_type to toArray method
    $notificationType = strtolower(str_replace('.php', '', $classFile));
    $content = preg_replace(
        '/(return\s*\[\s*(?:\'.*?\'\s*=>\s*.*?,\s*)*)(\'.*?\'\s*=>\s*.*?\s*\])/s',
        '$1\'notification_type\' => \'' . $notificationType . '\',' . "\n            $2",
        $content
    );
    
    // Save the updated file
    file_put_contents($filePath, $content);
    echo "Updated: $classFile\n";
}

echo "All notification classes have been updated!\n";
echo "Remember to add the APNs package with: composer require laravel-notification-channels/apn\n";
echo "And run the SQL to add device_token column to the users table.\n";