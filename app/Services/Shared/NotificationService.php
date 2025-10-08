<?php

namespace App\Services\Shared;

use App\Models\User;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send notification to a single user
     *
     * @param string $title
     * @param string $message
     * @param int $userId
     * @param string|null $actionUrl
     * @param string $type
     * @return void
     */
    public function sendToUser(
        string $title,
        string $message,
        int $userId,
        ?string $actionUrl = null,
        string $type = 'info'
    ): void {
        try {
            $user = User::find($userId);
            
            if ($user) {
                // For now, just log the notification
                // This can be extended to use Laravel's notification system
                \Log::info('Notification sent to user', [
                    'user_id' => $userId,
                    'title' => $title,
                    'message' => $message,
                    'action_url' => $actionUrl,
                    'type' => $type
                ]);
                
                // Example of how to extend with Laravel notifications:
                // $user->notify(new CustomNotification($title, $message, $actionUrl, $type));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send notification to user: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to multiple users
     *
     * @param string $title
     * @param string $message
     * @param array $userIds
     * @param string|null $actionUrl
     * @param string $type
     * @return void
     */
    public function sendToUsers(
        string $title,
        string $message,
        array $userIds,
        ?string $actionUrl = null,
        string $type = 'info'
    ): void {
        try {
            $users = User::whereIn('id', $userIds)->get();
            
            foreach ($users as $user) {
                $this->sendToUser($title, $message, $user->id, $actionUrl, $type);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send notifications to users: ' . $e->getMessage());
        }
    }
}