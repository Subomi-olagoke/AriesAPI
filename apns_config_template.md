# APNs Configuration Template

Add these environment variables to your `.env` file for push notifications to work:

```env
# APNs Configuration
APNS_KEY_ID=your_key_id_here
APNS_TEAM_ID=your_team_id_here
APNS_APP_BUNDLE_ID=com.Oubomi.Ariess
APNS_PRIVATE_KEY_CONTENT=base64_encoded_p8_file_content
APNS_PRODUCTION=false
```

## How to get these values:

1. **APNS_KEY_ID**: From your Apple Developer account, go to Keys section
2. **APNS_TEAM_ID**: Your Apple Developer Team ID (found in your account)
3. **APNS_APP_BUNDLE_ID**: Should match your iOS app's bundle identifier (`com.Oubomi.Ariess`)
4. **APNS_PRIVATE_KEY_CONTENT**: 
   - Download your .p8 file from Apple Developer account
   - Convert it to base64: `base64 -i AuthKey_XXXXXXXXXX.p8`
   - Use the output as the value
5. **APNS_PRODUCTION**: Set to `true` for production, `false` for development

## Testing:

1. Check configuration: `GET /api/notifications/debug-apns`
2. Test notification: `POST /api/notifications/test` with `{"user_id": 1}`

## Common Issues:

1. **Bundle ID mismatch**: Ensure `APNS_APP_BUNDLE_ID` matches your iOS app's bundle identifier
2. **Key format**: The private key must be in base64 format
3. **Environment**: Use `development` for testing, `production` for App Store builds
4. **Token registration**: Ensure the iOS app successfully registers the device token 