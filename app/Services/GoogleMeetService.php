<?php

namespace App\Services;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventAttendee;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleMeetService
{
    protected $client;
    protected $calendarService;
    protected $useDevMode;

    public function __construct()
    {
        $this->useDevMode = app()->environment('local') || app()->environment('development');
        
        if (!$this->useDevMode) {
            try {
                $this->client = new Google_Client();
                
                // Check if credentials file exists
                $credentialsPath = storage_path('app/google-credentials.json');
                if (file_exists($credentialsPath)) {
                    $this->client->setAuthConfig($credentialsPath);
                    $this->client->setScopes([
                        Google_Service_Calendar::CALENDAR_EVENTS
                    ]);
                    
                    // Use a service account or stored refresh token for authentication
                    $this->client->useApplicationDefaultCredentials();
                    
                    $this->calendarService = new Google_Service_Calendar($this->client);
                } else {
                    Log::warning('Google credentials file not found. Using development mode.');
                    $this->useDevMode = true;
                }
            } catch (\Exception $e) {
                Log::error('Failed to initialize Google client: ' . $e->getMessage());
                $this->useDevMode = true;
            }
        }
    }

    /**
     * Create a Google Meet link for a tutoring session
     *
     * @param User $tutor The tutor hosting the session
     * @param User $client The client attending the session
     * @param string $topic The topic of the session
     * @param Carbon $scheduledAt When the session is scheduled
     * @param int $durationMinutes Duration of the session in minutes
     * @return string|null The Google Meet link or null if creation failed
     */
    public function createMeetLink(User $tutor, User $client, string $topic, Carbon $scheduledAt, int $durationMinutes = 60)
    {
        // In development mode, generate a fake Google Meet link
        if ($this->useDevMode) {
            $meetId = strtolower(Str::random(12));
            return "https://meet.google.com/" . $meetId;
        }
        
        try {
            $event = new Google_Service_Calendar_Event([
                'summary' => "Tutoring Session: $topic",
                'description' => "Tutoring session between {$tutor->first_name} {$tutor->last_name} (Tutor) and {$client->first_name} {$client->last_name} (Client)",
                'start' => [
                    'dateTime' => $scheduledAt->toRfc3339String(),
                    'timeZone' => config('app.timezone', 'UTC'),
                ],
                'end' => [
                    'dateTime' => $scheduledAt->addMinutes($durationMinutes)->toRfc3339String(),
                    'timeZone' => config('app.timezone', 'UTC'),
                ],
                'attendees' => [
                    ['email' => $tutor->email],
                    ['email' => $client->email],
                ],
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => uniqid(),
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet'
                        ]
                    ]
                ]
            ]);

            $calendarId = 'primary';
            $event = $this->calendarService->events->insert($calendarId, $event, [
                'conferenceDataVersion' => 1
            ]);

            return $event->hangoutLink ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to create Google Meet link: ' . $e->getMessage());
            
            // Fallback to a fake link in case of error
            $meetId = strtolower(Str::random(12));
            return "https://meet.google.com/" . $meetId;
        }
    }
    
    /**
     * Check if the service is in development mode
     */
    public function isInDevMode(): bool
    {
        return $this->useDevMode;
    }
}