# Setting up Push Notifications with APNs

This document outlines the steps required to set up Apple Push Notification Service (APNs) with the Laravel application.

## Prerequisites

1. An Apple Developer account
2. Access to the Apple Developer Portal
3. Xcode installed on a Mac (for testing)

## Installation Steps

1. Install the APNs package:

```bash
composer require laravel-notification-channels/apn
```

2. Run the migration to add device token fields to the users table:

```bash
php artisan migrate
```

3. Create an App ID in the Apple Developer Portal:
   - Go to [Apple Developer Portal](https://developer.apple.com/account/resources/identifiers/list)
   - Click "+" to register a new App ID
   - Select "App IDs" and click "Continue"
   - Choose "App" as the type and click "Continue"
   - Enter a description and the Bundle ID for your app
   - Enable "Push Notifications" capability
   - Complete the registration

4. Create a Push Notification Key:
   - Go to [Keys section](https://developer.apple.com/account/resources/authkeys/list)
   - Click "+" to add a new key
   - Enter a name for the key and select "Apple Push Notifications service (APNs)"
   - Click "Continue" and then "Register"
   - Download the key file (.p8) - you can only download it once

5. Configure the environment variables in your `.env` file:

```
APNS_KEY_ID=your_key_id_from_developer_portal
APNS_TEAM_ID=your_team_id_from_developer_portal
APNS_APP_BUNDLE_ID=your_app_bundle_id
APNS_PRIVATE_KEY_PATH=/path/to/your/AuthKey_KEYID.p8
APNS_PRODUCTION=false  # Set to true for production
```

6. Update the `config/services.php` file to include APNs configuration:

```php
'apn' => [
    'key_id' => env('APNS_KEY_ID'),
    'team_id' => env('APNS_TEAM_ID'),
    'app_bundle_id' => env('APNS_APP_BUNDLE_ID'),
    'private_key_path' => env('APNS_PRIVATE_KEY_PATH'),
    'production' => env('APNS_PRODUCTION', false),
],
```

## API Routes for Device Registration

The following endpoints are available for device registration:

- POST `/api/device/register`: Register a device token
  - Request body: 
    ```json
    {
      "device_token": "your_device_token_from_ios_app",
      "device_type": "ios"
    }
    ```
  - This endpoint should be called when a user logs in or allows notifications

- POST `/api/device/unregister`: Unregister a device token
  - No request body needed
  - This endpoint should be called when a user logs out

## Testing

1. Use the Xcode development environment to test notifications
2. Set up a test iOS app with the correct bundle ID
3. Implement the code to register for remote notifications
4. Use the Apple Push Notification Service testing tool in Xcode

## Troubleshooting

- Ensure that the device token is properly formatted
- Check the Laravel logs for any errors related to APNs
- Verify that your app is properly registered for remote notifications on the iOS device
- Make sure your development certificate is valid and installed correctly

## Resources

- [Laravel Notification Channels Documentation](https://laravel-notification-channels.com/apn/)
- [Apple Push Notification Service Documentation](https://developer.apple.com/documentation/usernotifications)