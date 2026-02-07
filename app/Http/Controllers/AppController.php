<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppController extends Controller
{
    /**
     * Check if app update is required
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'current_version' => 'required|string',
            'build_number' => 'required|string',
            'platform' => 'required|string|in:ios,android',
        ]);

        $currentVersion = $request->input('current_version');
        $buildNumber = $request->input('build_number');
        $platform = $request->input('platform');

        // Define minimum required versions
        $minimumVersions = [
            'ios' => [
                'version' => '1.0.0',
                'build' => '1',
            ],
            'android' => [
                'version' => '1.0.0',
                'build' => '1',
            ],
        ];

        $latestVersions = [
            'ios' => [
                'version' => '1.0.0',
                'build' => '1',
            ],
            'android' => [
                'version' => '1.0.0',
                'build' => '1',
            ],
        ];

        $platformKey = $platform;
        $minVersion = $minimumVersions[$platformKey] ?? $minimumVersions['ios'];
        $latestVersion = $latestVersions[$platformKey] ?? $latestVersions['ios'];

        // Compare versions
        $updateRequired = $this->isVersionLower($currentVersion, $minVersion['version']);
        $forceUpdate = $updateRequired && $this->isVersionLower($currentVersion, $minVersion['version']);

        // If version is same but build is lower
        if (!$updateRequired && $currentVersion === $minVersion['version']) {
            $updateRequired = (int)$buildNumber < (int)$minVersion['build'];
            $forceUpdate = $updateRequired;
        }

        $response = [
            'updateRequired' => $updateRequired,
            'forceUpdate' => $forceUpdate,
            'minimumVersion' => $minVersion['version'],
            'currentVersion' => $latestVersion['version'],
            'updateMessage' => $updateRequired 
                ? 'A new version of the app is available. Please update to continue.'
                : null,
            'updateUrl' => $platform === 'ios' 
                ? 'https://apps.apple.com/us/app/alexandria-the-weinternet/id6742987788'
                : 'https://play.google.com/store/apps/details?id=com.alexandria.app',
        ];

        return response()->json($response);
    }

    /**
     * Compare two semantic versions
     *
     * @param string $currentVersion
     * @param string $requiredVersion
     * @return bool True if current is lower than required
     */
    private function isVersionLower(string $currentVersion, string $requiredVersion): bool
    {
        $current = explode('.', $currentVersion);
        $required = explode('.', $requiredVersion);

        for ($i = 0; $i < max(count($current), count($required)); $i++) {
            $c = (int)($current[$i] ?? 0);
            $r = (int)($required[$i] ?? 0);

            if ($c < $r) {
                return true;
            }
            if ($c > $r) {
                return false;
            }
        }

        return false;
    }
}
