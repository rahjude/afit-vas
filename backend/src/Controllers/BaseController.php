<?php

namespace App\Controllers;

use App\Config\Database;
use PDO;

abstract class BaseController
{
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }

    protected function success(mixed $data = null, string $message = 'Success', int $status = 200): void
    {
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        $this->json($response, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): void
    {
        $response = ['success' => false, 'message' => $message];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        $this->json($response, $status);
    }

    protected function db(): PDO
    {
        return Database::getConnection();
    }

    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $fieldRules = explode('|', $ruleSet);

            foreach ($fieldRules as $rule) {
                if ($rule === 'required' && (is_null($value) || $value === '')) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                    break;
                }
                if ($rule === 'email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Invalid email format';
                    break;
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if ($value && strlen($value) < $min) {
                        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters";
                        break;
                    }
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if ($value && strlen($value) > $max) {
                        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters";
                        break;
                    }
                }
                if ($rule === 'integer' && $value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
                    break;
                }
            }
        }
        return $errors;
    }

    protected function paginate(string $baseQuery, array $params, array $request, string $countQuery = ''): array
    {
        $page = max(1, (int) ($request['query']['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request['query']['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        if (!$countQuery) {
            $countQuery = preg_replace('/SELECT .+ FROM/i', 'SELECT COUNT(*) as total FROM', $baseQuery);
            $countQuery = preg_replace('/ORDER BY .+$/i', '', $countQuery);
            $countQuery = preg_replace('/LIMIT .+$/i', '', $countQuery);
        }

        $countStmt = $this->db()->prepare($countQuery);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $dataQuery = $baseQuery . " LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $this->db()->prepare($dataQuery);
        $dataStmt->execute($params);
        $data = $dataStmt->fetchAll();

        return [
            'data'        => $data,
            'pagination'  => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'pages'     => (int) ceil($total / $perPage),
            ],
        ];
    }
}
