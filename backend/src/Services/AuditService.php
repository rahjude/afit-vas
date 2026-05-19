<?php

namespace App\Services;

use App\Config\Database;

class AuditService
{
    public static function log(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?array $payload = null): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO audit_logs (admin_id, action, target_type, target_id, payload, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId,
            $action,
            $targetType,
            $targetId,
            $payload ? json_encode($payload) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
