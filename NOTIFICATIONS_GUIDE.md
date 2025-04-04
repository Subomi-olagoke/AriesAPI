# Edututor Notification System Guide

This document provides a comprehensive guide to the notification system in the Edututor application, including database notifications and push notifications.

## Overview

The application uses Laravel's notification system with two channels:
1. **Database Notifications**: Stored in the database and displayed in-app
2. **Push Notifications**: Sent to mobile devices using Apple Push Notification service (APNs)

All notifications extend from a base `BaseNotification` class that provides common functionality for both channels.

## Setup Required

### 1. Add Device Token Columns

Run the following SQL command to add device token columns to the users table:

```sql
ALTER TABLE users 
ADD COLUMN device_token VARCHAR(255) NULL AFTER api_token,
ADD COLUMN device_type VARCHAR(255) NULL AFTER device_token;
```

### 2. Install APNs Package

```bash
composer require laravel-notification-channels/apn
```

### 3. Run the Notification Update Script

To update all notification classes to support push notifications:

```bash
php notification_update_script.php
```

### 4. Configure APNs Settings

Follow the instructions in `PUSH_NOTIFICATIONS_SETUP.md` to configure your APNs credentials.

## API Endpoints for Device Management

### Register a Device Token

```
POST /api/device/register
```

Request body:
```json
{
  "device_token": "your_device_token_from_ios_app",
  "device_type": "ios"
}
```

### Unregister a Device Token

```
POST /api/device/unregister
```

## Notification Types

The application includes the following notification types:

1. **HireRequestNotification**: Sent when a user requests to hire an educator, when a request is accepted, or when a request is declined.
2. **CommentNotification**: Sent when someone comments on a post.
3. **CourseEnrollmentNotification**: Sent when a user enrolls in a course.
4. **FollowedNotification**: Sent when a user follows another user.
5. **HireInstructorNotification**: Sent related to instructor hiring.
6. **HireSessionNotification**: Sent for tutoring session updates.
7. **LikeNotification**: Sent when someone likes a post or comment.
8. **MentionNotification**: Sent when a user is mentioned in a post or comment.
9. **NewMessage**: Sent when a user receives a new message.
10. **PaymentRequiredNotification**: Sent when payment is required.
11. **SessionCompletedNotification**: Sent when a tutoring session is completed.
12. **SessionScheduledNotification**: Sent when a tutoring session is scheduled.

## Base Notification Structure

All notifications inherit from the `BaseNotification` class which:

1. Determines which channels to use based on user preferences
2. Converts database notification content to push notification format
3. Provides a consistent notification structure

## Notification Data Structure

Each notification contains at least the following data:

```php
[
    'title' => 'Notification Title',
    'message' => 'Notification message text',
    'sender_id' => $senderId,
    'sender_name' => 'Sender Name',
    'notification_type' => 'type_of_notification',
    // Additional data specific to the notification type
]
```

## Handling Notifications in the Mobile App

### iOS

1. Register for remote notifications using `UNUserNotificationCenter`
2. Send the device token to the `/api/device/register` endpoint
3. Handle incoming notifications in the app delegate's `didReceiveRemoteNotification` method

Example Swift code for registering for notifications:

```swift
func registerForPushNotifications() {
    UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge]) { granted, _ in
        guard granted else { return }
        
        DispatchQueue.main.async {
            UIApplication.shared.registerForRemoteNotifications()
        }
    }
}

func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
    let tokenParts = deviceToken.map { data in String(format: "%02.2hhx", data) }
    let token = tokenParts.joined()
    
    // Send token to server
    registerDeviceToken(token: token)
}

func registerDeviceToken(token: String) {
    // API call to /api/device/register with the token
}
```

## Best Practices

1. Always use the `BaseNotification` class as the parent for all notification classes
2. Include meaningful titles and messages for each notification
3. Add sufficient context data in the notification payload
4. Handle different notification types appropriately in the mobile app
5. Implement proper error handling for push notification delivery

## Debugging

If notifications aren't working:

1. Check that the user has a valid device token in the database
2. Verify APNs credentials are correctly configured
3. Ensure the mobile app has requested and received permission for notifications
4. Check Laravel logs for any notification sending errors
5. Use the APNs debug tools in Xcode to monitor notification delivery

## Further Reading

- [Laravel Notifications Documentation](https://laravel.com/docs/10.x/notifications)
- [Apple Push Notification Service Documentation](https://developer.apple.com/documentation/usernotifications)