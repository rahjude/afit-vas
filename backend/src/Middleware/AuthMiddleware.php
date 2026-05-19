<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class AuthMiddleware
{
    public function handle(array $request): ?array
    {
        $authHeader = $request['headers']['authorization'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            return null;
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key($_ENV['APP_JWT_SECRET'], 'HS256'));
            $request['auth'] = [
                'user_id' => $decoded->sub,
                'role'    => $decoded->role,
            ];
            return $request;
        } catch (ExpiredException $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token expired']);
            return null;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            return null;
        }
    }
}
