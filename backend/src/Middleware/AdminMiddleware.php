<?php

namespace App\Middleware;

class AdminMiddleware
{
    public function handle(array $request): ?array
    {
        $role = $request['auth']['role'] ?? '';
        if (!in_array($role, ['admin', 'super_admin'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            return null;
        }
        return $request;
    }
}
