<?php

namespace Leantime\Domain\Auth\Services;

use GuzzleHttp\Client;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Illuminate\Support\Facades\Log;

class GoogleSync
{
    private UserRepository $userRepo;
    private Client $client;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
        $this->client = new Client();
    }

    /**
     * Synchronizes a ticket to Google Tasks and Google Calendar.
     *
     * @param array $payload The event payload containing the ticket entity.
     * @return void
     */
    public function syncTicketToGoogle(array $payload): void
    {
        $ticket = $payload['entity'] ?? null;
        if (!$ticket) {
            return;
        }

        $userId = session('userdata.id');
        if (!$userId) {
            return;
        }

        $user = $this->userRepo->getUser($userId);
        if (!$user || empty($user['settings'])) {
            return;
        }

        $settings = $user['settings'] ? unserialize($user['settings']) : [];

        if (!isset($settings['google_token'])) {
            return;
        }

        $accessToken = $this->getValidToken($userId, $settings);
        if (!$accessToken) {
            return;
        }

        // Sync to Google Tasks
        $this->pushToGoogleTasks($accessToken, $ticket);

        // Sync to Google Calendar if it has a due date
        if (isset($ticket['dateToFinish']) && $ticket['dateToFinish'] != '0000-00-00 00:00:00' && !empty($ticket['dateToFinish'])) {
            $this->pushToGoogleCalendar($accessToken, $ticket);
        }
    }

    /**
     * Ensures we have a valid access token, refreshing it if necessary.
     */
    private function getValidToken(int $userId, array $settings): ?string
    {
        // For simplicity in this version, we'll check if we have a refresh token and just try to use it if the current one is likely expired.
        // A more robust implementation would check 'google_expires_in' vs time().
        
        if (isset($settings['google_refresh_token'])) {
            try {
                $response = $this->client->post('https://oauth2.googleapis.com/token', [
                    'form_params' => [
                        'client_id' => env('LEAN_GOOGLE_CLIENT_ID'),
                        'client_secret' => env('LEAN_GOOGLE_CLIENT_SECRET'),
                        'refresh_token' => $settings['google_refresh_token'],
                        'grant_type' => 'refresh_token',
                    ],
                ]);

                $data = json_decode($response->getBody(), true);
                if (isset($data['access_token'])) {
                    $settings['google_token'] = $data['access_token'];
                    // Update user settings with new token
                    $user = $this->userRepo->getUser($userId);
                    $user['settings'] = serialize($settings);
                    $this->userRepo->editUser($user, $userId);
                    
                    return $data['access_token'];
                }
            } catch (\Exception $e) {
                Log::error("Google Token Refresh Error: " . $e->getMessage());
            }
        }

        return $settings['google_token'] ?? null;
    }

    /**
     * Pushes a task to Google Tasks.
     */
    private function pushToGoogleTasks(string $token, array $ticket): void
    {
        try {
            $response = $this->client->post('https://www.googleapis.com/tasks/v1/lists/@default/tasks', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'title' => '[Lean Tool] ' . ($ticket['headline'] ?? 'Nueva Tarea'),
                    'notes' => strip_tags($ticket['description'] ?? ''),
                    'due' => !empty($ticket['dateToFinish']) ? date('Y-m-d\TH:i:s\Z', strtotime($ticket['dateToFinish'])) : null,
                ]
            ]);
            
            if ($response->getStatusCode() !== 201) {
                Log::warning("Google Tasks Sync: Unexpected status code " . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            Log::error("Google Tasks Sync Error: " . $e->getMessage());
        }
    }

    /**
     * Pushes an event to Google Calendar.
     */
    private function pushToGoogleCalendar(string $token, array $ticket): void
    {
        try {
            $startTime = date('Y-m-d\TH:i:s\Z', strtotime($ticket['dateToFinish']));
            $endTime = date('Y-m-d\TH:i:s\Z', strtotime($ticket['dateToFinish']) + 3600);

            $response = $this->client->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'summary' => '[Lean Tool] ' . ($ticket['headline'] ?? 'Nueva Tarea'),
                    'description' => strip_tags($ticket['description'] ?? ''),
                    'start' => ['dateTime' => $startTime],
                    'end' => ['dateTime' => $endTime],
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::warning("Google Calendar Sync: Unexpected status code " . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            Log::error("Google Calendar Sync Error: " . $e->getMessage());
        }
    }
}
