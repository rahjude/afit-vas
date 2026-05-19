<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\NotificationService;

class AdminController extends BaseController
{
    public function stats(array $request): void
    {
        $db = $this->db();
        $stats = $db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'submitted') AS submitted,
                SUM(status = 'eligible') AS eligible,
                SUM(status = 'not_eligible') AS not_eligible,
                SUM(status = 'under_review') AS under_review,
                SUM(status = 'admitted') AS admitted,
                SUM(status = 'rejected') AS rejected,
                SUM(status = 'draft') AS draft
             FROM applications"
        )->fetch();

        // Programme breakdown
        $programmes = $db->query(
            'SELECT p.name, p.code, COUNT(a.id) as count,
                    SUM(a.status = "admitted") as admitted,
                    SUM(a.status = "rejected") as rejected
             FROM programmes p
             LEFT JOIN applications a ON a.programme_id = p.id
             GROUP BY p.id
             ORDER BY count DESC'
        )->fetchAll();

        $this->success([
            'overview'   => $stats,
            'programmes' => $programmes,
        ]);
    }

    public function applications(array $request): void
    {
        $query = $request['query'];
        $conditions = [];
        $params = [];

        $baseSelect = 'SELECT a.*, u.first_name, u.last_name, u.email, u.phone,
                        p.name as programme_name, p.code as programme_code';
        $baseFrom = ' FROM applications a
                      JOIN users u ON u.id = a.user_id
                      JOIN programmes p ON p.id = a.programme_id';
        $where = '';

        if (!empty($query['status']) && $query['status'] !== 'all') {
            $conditions[] = 'a.status = ?';
            $params[] = $query['status'];
        }

        if (!empty($query['search'])) {
            $search = '%' . $query['search'] . '%';
            $conditions[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.reference_number LIKE ?)';
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        if (!empty($query['programme_id'])) {
            $conditions[] = 'a.programme_id = ?';
            $params[] = (int) $query['programme_id'];
        }

        if (!empty($query['from_date'])) {
            $conditions[] = 'a.created_at >= ?';
            $params[] = $query['from_date'];
        }

        if (!empty($query['to_date'])) {
            $conditions[] = 'a.created_at <= ?';
            $params[] = $query['to_date'] . ' 23:59:59';
        }

        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $countQuery = 'SELECT COUNT(*) as total' . $baseFrom . $where;
        $fullQuery = $baseSelect . $baseFrom . $where . ' ORDER BY a.created_at DESC';

        $result = $this->paginate($fullQuery, $params, $request, $countQuery);

        foreach ($result['data'] as &$app) {
            $app['o_level_results'] = json_decode($app['o_level_results'], true);
            $app['eligibility_score'] = $app['eligibility_score'] ? json_decode($app['eligibility_score'], true) : null;
        }

        $this->success($result);
    }

    public function viewApplication(array $request): void
    {
        $id = (int) $request['params']['id'];
        $db = $this->db();

        $stmt = $db->prepare(
            'SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.gender,
                    u.state_of_origin, u.date_of_birth,
                    p.name as programme_name, p.code as programme_code,
                    p.department, p.degree_type, p.required_subjects
             FROM applications a
             JOIN users u ON u.id = a.user_id
             JOIN programmes p ON p.id = a.programme_id
             WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        $app = $stmt->fetch();

        if (!$app) {
            $this->error('Application not found', 404);
            return;
        }

        $app['o_level_results'] = json_decode($app['o_level_results'], true);
        $app['eligibility_score'] = $app['eligibility_score'] ? json_decode($app['eligibility_score'], true) : null;
        $app['required_subjects'] = json_decode($app['required_subjects'], true);

        // Documents
        $docs = $db->prepare('SELECT * FROM documents WHERE application_id = ?');
        $docs->execute([$id]);
        $app['documents'] = $docs->fetchAll();

        // Status history
        $history = $db->prepare(
            'SELECT sh.*, u.first_name, u.last_name, u.role
             FROM status_history sh
             LEFT JOIN users u ON u.id = sh.changed_by
             WHERE sh.application_id = ?
             ORDER BY sh.changed_at ASC'
        );
        $history->execute([$id]);
        $app['status_history'] = $history->fetchAll();

        $this->success($app);
    }

    public function updateStatus(array $request): void
    {
        $id = (int) $request['params']['id'];
        $body = $request['body'];
        $adminId = $request['auth']['user_id'];

        $newStatus = $body['status'] ?? '';
        $notes = trim($body['notes'] ?? '');

        $validStatuses = ['under_review', 'admitted', 'rejected'];
        if (!in_array($newStatus, $validStatuses, true)) {
            $this->error('Invalid status. Allowed: ' . implode(', ', $validStatuses));
            return;
        }

        $stmt = $this->db()->prepare('SELECT id, status, user_id FROM applications WHERE id = ?');
        $stmt->execute([$id]);
        $app = $stmt->fetch();

        if (!$app) {
            $this->error('Application not found', 404);
            return;
        }

        $oldStatus = $app['status'];

        $updateFields = 'status = ?';
        $updateParams = [$newStatus];

        if ($notes) {
            $updateFields .= ', admin_notes = ?';
            $updateParams[] = $notes;
        }

        if (in_array($newStatus, ['admitted', 'rejected'], true)) {
            $updateFields .= ', decided_at = NOW()';
        }

        $updateParams[] = $id;
        $this->db()->prepare("UPDATE applications SET {$updateFields} WHERE id = ?")->execute($updateParams);

        // Status history
        $this->db()->prepare(
            'INSERT INTO status_history (application_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, $oldStatus, $newStatus, $adminId, $notes]);

        // Audit log
        AuditService::log($adminId, "Changed application #{$id} status", 'application', $id, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes'      => $notes,
        ]);

        // Notify applicant
        $statusLabels = [
            'under_review' => 'Under Review',
            'admitted'     => 'Admitted',
            'rejected'     => 'Rejected',
        ];
        NotificationService::create(
            $app['user_id'],
            'Application Status Update',
            "Your application status has been updated to: {$statusLabels[$newStatus]}." . ($notes ? " Note: {$notes}" : ''),
            $newStatus === 'admitted' ? 'success' : ($newStatus === 'rejected' ? 'danger' : 'info')
        );

        $this->success(null, "Application status updated to {$newStatus}");
    }

    public function verifyDocument(array $request): void
    {
        $id = (int) $request['params']['id'];
        $body = $request['body'];
        $adminId = $request['auth']['user_id'];

        $action = $body['action'] ?? '';
        if (!in_array($action, ['verify', 'reject'], true)) {
            $this->error('Action must be "verify" or "reject"');
            return;
        }

        $stmt = $this->db()->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if (!$doc) {
            $this->error('Document not found', 404);
            return;
        }

        if ($action === 'verify') {
            $this->db()->prepare('UPDATE documents SET status = "verified", verified_by = ? WHERE id = ?')
                ->execute([$adminId, $id]);
        } else {
            $reason = trim($body['reason'] ?? 'Document rejected');
            $this->db()->prepare('UPDATE documents SET status = "rejected", rejection_reason = ?, verified_by = ? WHERE id = ?')
                ->execute([$reason, $adminId, $id]);
        }

        AuditService::log($adminId, "Document #{$id} {$action}d", 'document', $id);

        $this->success(null, "Document {$action}d successfully");
    }

    public function reports(array $request): void
    {
        $query = $request['query'];
        $db = $this->db();

        $fromDate = $query['from_date'] ?? date('Y-m-d', strtotime('-90 days'));
        $toDate = $query['to_date'] ?? date('Y-m-d');

        // Summary stats for period
        $stats = $db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'eligible') AS eligible,
                SUM(status = 'not_eligible') AS not_eligible,
                SUM(status = 'admitted') AS admitted,
                SUM(status = 'rejected') AS rejected,
                SUM(status = 'under_review') AS under_review
             FROM applications
             WHERE created_at BETWEEN ? AND ?"
        );
        $stats->execute([$fromDate, $toDate . ' 23:59:59']);

        // Programme breakdown
        $programmes = $db->prepare(
            'SELECT p.name, p.code,
                    COUNT(a.id) as total,
                    SUM(a.status = "admitted") as admitted,
                    SUM(a.status = "rejected") as rejected,
                    SUM(a.status = "eligible") as eligible
             FROM programmes p
             LEFT JOIN applications a ON a.programme_id = p.id
                AND a.created_at BETWEEN ? AND ?
             GROUP BY p.id
             ORDER BY total DESC'
        );
        $programmes->execute([$fromDate, $toDate . ' 23:59:59']);

        // Daily submissions
        $daily = $db->prepare(
            'SELECT DATE(created_at) as date, COUNT(*) as count
             FROM applications
             WHERE created_at BETWEEN ? AND ?
             GROUP BY DATE(created_at)
             ORDER BY date'
        );
        $daily->execute([$fromDate, $toDate . ' 23:59:59']);

        $this->success([
            'period'     => ['from' => $fromDate, 'to' => $toDate],
            'summary'    => $stats->fetch(),
            'programmes' => $programmes->fetchAll(),
            'daily'      => $daily->fetchAll(),
        ]);
    }

    public function exportCsv(array $request): void
    {
        $db = $this->db();
        $rows = $db->query(
            'SELECT a.reference_number, u.first_name, u.last_name, u.email, u.phone,
                    p.name as programme, p.department, a.jamb_score,
                    a.status, a.submitted_at, a.decided_at, a.admin_notes
             FROM applications a
             JOIN users u ON u.id = a.user_id
             JOIN programmes p ON p.id = a.programme_id
             ORDER BY a.created_at DESC'
        )->fetchAll();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="afit_applications_' . date('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Reference', 'First Name', 'Last Name', 'Email', 'Phone',
                        'Programme', 'Department', 'JAMB Score', 'Status',
                        'Submitted', 'Decided', 'Admin Notes']);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    public function auditLogs(array $request): void
    {
        $countQuery = 'SELECT COUNT(*) as total FROM audit_logs';
        $fullQuery = 'SELECT al.*, u.first_name, u.last_name
                      FROM audit_logs al
                      JOIN users u ON u.id = al.admin_id
                      ORDER BY al.logged_at DESC';

        $result = $this->paginate($fullQuery, [], $request, $countQuery);

        foreach ($result['data'] as &$log) {
            $log['payload'] = $log['payload'] ? json_decode($log['payload'], true) : null;
        }

        $this->success($result);
    }
}
