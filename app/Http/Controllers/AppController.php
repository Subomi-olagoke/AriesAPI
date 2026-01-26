<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppController extends Controller
{
    /**
     * Check if app update is required
     * 
     * This endpoint compares the client's app version with the minimum required version
     * and returns whether an update is required, and if it's forced.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkUpdate(Request $request): JsonResponse
    {
        $currentVersion = $request->input('current_version', '1.0.0');
        $buildNumber = $request->input('build_number', '1');
        $platform = $request->input('platform', 'ios');
        
        // Get minimum required version from environment or config
        // You can set these in your .env file:
        // APP_MIN_VERSION_IOS=1.0.0
        // APP_FORCE_UPDATE_VERSION_IOS=0.9.0
        $minVersion = env('APP_MIN_VERSION_IOS', '1.0.0');
        $forceUpdateVersion = env('APP_FORCE_UPDATE_VERSION_IOS', '0.9.0');
        $currentAppVersion = env('APP_CURRENT_VERSION_IOS', '1.0.0');
        
        // Get App Store URL from environment (default to Alexandria app)
        $appStoreUrl = env('APP_STORE_URL_IOS', 'https://apps.apple.com/us/app/alexandria-the-weinternet/id6742987788');
        
        // Compare versions
        $updateRequired = $this->compareVersions($currentVersion, $minVersion) < 0;
        $forceUpdate = $this->compareVersions($currentVersion, $forceUpdateVersion) < 0;
        
        // Custom message for updates
        $updateMessage = $forceUpdate 
            ? "A critical update is required. Please update to the latest version to continue using the app."
            : "A new version of Aries is available with exciting features and improvements. Update now to get the best experience!";
        
        return response()->json([
            'updateRequired' => $updateRequired,
            'forceUpdate' => $forceUpdate,
            'minimumVersion' => $minVersion,
            'currentVersion' => $currentAppVersion,
            'updateMessage' => $updateMessage,
            'updateUrl' => $appStoreUrl
        ], 200);
    }
    
    /**
     * Compare two version strings
     * Returns: -1 if version1 < version2, 0 if equal, 1 if version1 > version2
     * 
     * @param string $version1
     * @param string $version2
     * @return int
     */
    private function compareVersions(string $version1, string $version2): int
    {
        // Normalize versions (remove any non-numeric/non-dot characters)
        $v1 = preg_replace('/[^0-9.]/', '', $version1);
        $v2 = preg_replace('/[^0-9.]/', '', $version2);
        
        // Split into parts
        $parts1 = array_map('intval', explode('.', $v1));
        $parts2 = array_map('intval', explode('.', $v2));
        
        // Pad arrays to same length
        $maxLength = max(count($parts1), count($parts2));
        $parts1 = array_pad($parts1, $maxLength, 0);
        $parts2 = array_pad($parts2, $maxLength, 0);
        
        // Compare each part
        for ($i = 0; $i < $maxLength; $i++) {
            if ($parts1[$i] < $parts2[$i]) {
                return -1;
            } elseif ($parts1[$i] > $parts2[$i]) {
                return 1;
            }
        }
        
        return 0;
    }
}
