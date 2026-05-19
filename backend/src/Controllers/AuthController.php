<?php

namespace App\Controllers;

use Firebase\JWT\JWT;

class AuthController extends BaseController
{
    public function register(array $request): void
    {
        $body = $request['body'];
        $errors = $this->validate($body, [
            'first_name' => 'required|max:100',
            'last_name'  => 'required|max:100',
            'email'      => 'required|email',
            'password'   => 'required|min:8',
            'phone'      => 'required',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed', 422, $errors);
            return;
        }

        // Password strength check
        $pass = $body['password'];
        if (!preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
            $this->error('Validation failed', 422, [
                'password' => 'Password must contain at least one uppercase letter, one lowercase letter, and one digit'
            ]);
            return;
        }

        $db = $this->db();

        // Check duplicate email
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($body['email']))]);
        if ($stmt->fetch()) {
            $this->error('Validation failed', 422, ['email' => 'Email already registered']);
            return;
        }

        $verifyToken = bin2hex(random_bytes(32));
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

        $ins = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, phone, gender, state_of_origin, date_of_birth, role, is_verified, verify_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "applicant", 0, ?)'
        );
        $ins->execute([
            trim($body['first_name']),
            trim($body['last_name']),
            strtolower(trim($body['email'])),
            $hash,
            trim($body['phone']),
            $body['gender'] ?? null,
            $body['state_of_origin'] ?? null,
            $body['date_of_birth'] ?? null,
            $verifyToken,
        ]);

        $userId = (int) $db->lastInsertId();

        // Auto-verify in local environment
        if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
            $db->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$userId]);
        }

        $this->success([
            'user_id'      => $userId,
            'verify_token' => $verifyToken,
        ], 'Registration successful', 201);
    }

    public function login(array $request): void
    {
        $body = $request['body'];
        $errors = $this->validate($body, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed', 422, $errors);
            return;
        }

        $db = $this->db();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($body['email']))]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            $this->error('Invalid email or password', 401);
            return;
        }

        if (!$user['is_verified']) {
            $this->error('Please verify your email before logging in', 403);
            return;
        }

        $now = time();
        $payload = [
            'sub'  => $user['id'],
            'role' => $user['role'],
            'iat'  => $now,
            'exp'  => $now + (int) ($_ENV['APP_JWT_EXPIRY'] ?? 86400),
        ];

        $token = JWT::encode($payload, $_ENV['APP_JWT_SECRET'], 'HS256');

        $this->success([
            'token' => $token,
            'user'  => [
                'id'         => $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
                'phone'      => $user['phone'],
                'role'       => $user['role'],
                'gender'     => $user['gender'],
                'state_of_origin' => $user['state_of_origin'],
                'date_of_birth'   => $user['date_of_birth'],
            ],
        ], 'Login successful');
    }

    public function verifyEmail(array $request): void
    {
        $token = $request['query']['token'] ?? '';
        if (!$token) {
            $this->error('Verification token required');
            return;
        }

        $db = $this->db();
        $stmt = $db->prepare('SELECT id FROM users WHERE verify_token = ? AND is_verified = 0');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->error('Invalid or expired verification token');
            return;
        }

        $db->prepare('UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?')
           ->execute([$user['id']]);

        $this->success(null, 'Email verified successfully');
    }

    public function profile(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $db = $this->db();
        $stmt = $db->prepare('SELECT id, first_name, last_name, email, phone, gender, state_of_origin, date_of_birth, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->error('User not found', 404);
            return;
        }

        $this->success($user);
    }

    public function updateProfile(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $body = $request['body'];

        $fields = [];
        $values = [];

        $allowed = ['first_name', 'last_name', 'phone', 'gender', 'state_of_origin', 'date_of_birth'];
        foreach ($allowed as $field) {
            if (isset($body[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = trim($body[$field]);
            }
        }

        if (empty($fields)) {
            $this->error('No fields to update');
            return;
        }

        $values[] = $userId;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $this->db()->prepare($sql)->execute($values);

        $this->profile($request);
    }
}
