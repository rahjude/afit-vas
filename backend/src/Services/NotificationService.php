<?php

namespace App\Services;

use App\Config\Database;

class NotificationService
{
    public static function create(int $userId, string $title, string $message, string $type = 'info'): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $title, $message, $type]);
    }

    public static function markRead(int $notificationId, int $userId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$notificationId, $userId]);
    }

    public static function getUnreadCount(int $userId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetch()['cnt'];
    }
}
