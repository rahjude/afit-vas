<?php

namespace App\Controllers;

use App\Services\NotificationService;

class NotificationController extends BaseController
{
    public function index(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $limit = min(50, max(1, (int) ($request['query']['limit'] ?? 20)));

        $stmt = $this->db()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);

        $this->success([
            'notifications' => $stmt->fetchAll(),
            'unread_count'  => NotificationService::getUnreadCount($userId),
        ]);
    }

    public function markRead(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $id = (int) $request['params']['id'];

        NotificationService::markRead($id, $userId);
        $this->success(null, 'Notification marked as read');
    }

    public function markAllRead(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $this->db()->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0'
        )->execute([$userId]);

        $this->success(null, 'All notifications marked as read');
    }
}
