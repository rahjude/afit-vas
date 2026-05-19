<?php

namespace App\Controllers;

use App\Services\NotificationService;

class DocumentController extends BaseController
{
    private const ALLOWED_TYPES = [
        'waec_result', 'neco_result', 'jamb_slip', 'birth_certificate',
        'passport_photo', 'lga_certificate', 'medical_certificate',
    ];

    private const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];

    public function index(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $appId = (int) ($request['query']['application_id'] ?? 0);

        if (!$appId) {
            $this->error('application_id query parameter required');
            return;
        }

        // Verify ownership
        $stmt = $this->db()->prepare('SELECT id FROM applications WHERE id = ? AND user_id = ?');
        $stmt->execute([$appId, $userId]);
        if (!$stmt->fetch()) {
            $this->error('Application not found', 404);
            return;
        }

        $docs = $this->db()->prepare('SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC');
        $docs->execute([$appId]);
        $this->success($docs->fetchAll());
    }

    public function upload(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $body = $request['body'];

        $appId = (int) ($body['application_id'] ?? 0);
        $docType = $body['doc_type'] ?? '';

        if (!$appId || !in_array($docType, self::ALLOWED_TYPES, true)) {
            $this->error('Valid application_id and doc_type required');
            return;
        }

        // Verify ownership
        $stmt = $this->db()->prepare('SELECT id FROM applications WHERE id = ? AND user_id = ?');
        $stmt->execute([$appId, $userId]);
        if (!$stmt->fetch()) {
            $this->error('Application not found', 404);
            return;
        }

        if (empty($_FILES['file']['name'])) {
            $this->error('File is required');
            return;
        }

        $file = $_FILES['file'];
        $maxSize = (int) ($_ENV['MAX_UPLOAD_SIZE'] ?? 5242880);

        if ($file['size'] > $maxSize) {
            $this->error('File size exceeds 5MB limit');
            return;
        }

        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            $this->error('File type not allowed. Accepted: PDF, JPEG, PNG');
            return;
        }

        // Create upload directory
        $uploadDir = ($_ENV['UPLOAD_DIR'] ?? '/home/ubuntu/repos/afit-vas/uploads') . "/{$appId}";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = $docType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $filePath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $this->error('Failed to save file');
            return;
        }

        // Remove previous document of same type for this application
        $this->db()->prepare('DELETE FROM documents WHERE application_id = ? AND doc_type = ?')
            ->execute([$appId, $docType]);

        $ins = $this->db()->prepare(
            'INSERT INTO documents (application_id, doc_type, file_name, file_path, file_size, mime_type)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$appId, $docType, $file['name'], $filePath, $file['size'], $mimeType]);

        $docId = (int) $this->db()->lastInsertId();

        $this->success([
            'id'        => $docId,
            'doc_type'  => $docType,
            'file_name' => $file['name'],
            'file_size' => $file['size'],
        ], 'Document uploaded successfully', 201);
    }

    public function download(array $request): void
    {
        $id = (int) $request['params']['id'];

        $stmt = $this->db()->prepare(
            'SELECT d.*, a.user_id FROM documents d
             JOIN applications a ON a.id = d.application_id
             WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if (!$doc) {
            $this->error('Document not found', 404);
            return;
        }

        // Check access: owner or admin
        $authRole = $request['auth']['role'] ?? '';
        if ($doc['user_id'] !== $request['auth']['user_id'] && !in_array($authRole, ['admin', 'super_admin'], true)) {
            $this->error('Access denied', 403);
            return;
        }

        if (!file_exists($doc['file_path'])) {
            $this->error('File not found on server', 404);
            return;
        }

        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: inline; filename="' . $doc['file_name'] . '"');
        header('Content-Length: ' . filesize($doc['file_path']));
        readfile($doc['file_path']);
        exit;
    }
}
