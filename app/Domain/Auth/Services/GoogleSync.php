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
        $logFile = APP_ROOT . '/sync_debug.log';
        $msg = "[" . date('Y-m-d H:i:s') . "] GoogleSync Triggered for Ticket: " . ($payload['entity']['id'] ?? 'unknown') . "\n";
        file_put_contents($logFile, $msg, FILE_APPEND);
        
        $ticket = $payload['entity'] ?? null;
        if (!$ticket) {
            file_put_contents($logFile, "GoogleSync Error: No ticket entity in payload\n", FILE_APPEND);
            return;
        }

        $userId = session('userdata.id');
        if (!$userId) {
            file_put_contents($logFile, "GoogleSync Error: No userId in session\n", FILE_APPEND);
            return;
        }

        $user = $this->userRepo->getUser($userId);
        if (!$user) {
            file_put_contents($logFile, "GoogleSync Error: User not found for ID: " . $userId . "\n", FILE_APPEND);
            return;
        }

        $settings = $user['settings'] ? unserialize($user['settings']) : [];
        if (!isset($settings['google_token'])) {
            file_put_contents($logFile, "GoogleSync Error: google_token not set for user: " . $userId . "\n", FILE_APPEND);
            return;
        }

        $accessToken = $this->getValidToken($userId, $settings);
        if (!$accessToken) {
            file_put_contents($logFile, "GoogleSync Error: Failed to obtain valid access token for user: " . $userId . "\n", FILE_APPEND);
            return;
        }

        file_put_contents($logFile, "GoogleSync: Attempting to push to Tasks and Calendar for user: " . $userId . "\n", FILE_APPEND);

        // Sync to Google Tasks
        $this->pushToGoogleTasks($accessToken, $ticket);

        // Sync to Google Calendar if it has a due date
        $dueDate = $ticket['hourToFinish'] ?? $ticket['dateToFinish'] ?? null;
        if ($dueDate && $dueDate != '0000-00-00 00:00:00') {
            $this->pushToGoogleCalendar($accessToken, $ticket);
        } else {
            file_put_contents($logFile, "GoogleSync: Skipping Calendar sync, no valid finish date found.\n", FILE_APPEND);
        }
    }

    /**
     * Ensures we have a valid access token, refreshing it if necessary.
     */
    private function getValidToken(int $userId, array $settings): ?string
    {
        // Check if token is expired or about to expire (expires_in is usually 3600)
        // For now, if we have a refresh token, let's try to get a fresh access token to be safe
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
                error_log("Google Token Refresh Error: " . $e->getMessage());
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
            // Prioritize dateToFinish (Deadline) for Google Tasks
            $dueDate = !empty($ticket['dateToFinish']) && $ticket['dateToFinish'] != '0000-00-00 00:00:00' 
                ? $ticket['dateToFinish'] 
                : ($ticket['hourToFinish'] ?? null);
            
            $dueRFC = null;
            if ($dueDate && $dueDate != '0000-00-00 00:00:00') {
                $dueRFC = date('Y-m-d\TH:i:s\Z', strtotime($dueDate));
            }

            $this->client->post('https://www.googleapis.com/tasks/v1/lists/@default/tasks', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'title' => '[Lean Tool] ' . ($ticket['headline'] ?? 'Nueva Tarea'),
                    'notes' => strip_tags($ticket['description'] ?? ''),
                    'due' => $dueRFC,
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Google Tasks Sync Error: " . $e->getMessage());
        }
    }

    /**
     * Pushes an event to Google Calendar.
     */
    private function pushToGoogleCalendar(string $token, array $ticket): void
    {
        try {
            // Prioritize dateToFinish (Deadline) for Calendar
            $finishDate = !empty($ticket['dateToFinish']) && $ticket['dateToFinish'] != '0000-00-00 00:00:00' 
                ? $ticket['dateToFinish'] 
                : ($ticket['hourToFinish'] ?? null);
                
            $startDate = $ticket['hourToStart'] ?? $ticket['dateToStart'] ?? $finishDate;

            if (!$finishDate || $finishDate == '0000-00-00 00:00:00') {
                return;
            }

            $startTime = date('Y-m-d\TH:i:s\Z', strtotime($startDate));
            $endTime = date('Y-m-d\TH:i:s\Z', strtotime($finishDate));

            // If same time or end before start, add 1 hour to end
            if (strtotime($endTime) <= strtotime($startTime)) {
                $endTime = date('Y-m-d\TH:i:s\Z', strtotime($startTime) + 3600);
            }

            $this->client->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
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
        } catch (\Exception $e) {
            error_log("Google Calendar Sync Error: " . $e->getMessage());
        }
    }
}
