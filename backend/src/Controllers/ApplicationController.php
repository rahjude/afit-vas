<?php

namespace App\Controllers;

use App\Services\EligibilityService;
use App\Services\NotificationService;
use App\Services\AuditService;

class ApplicationController extends BaseController
{
    public function index(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $stmt = $this->db()->prepare(
            'SELECT a.*, p.name as programme_name, p.code as programme_code, p.department
             FROM applications a
             JOIN programmes p ON p.id = a.programme_id
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC'
        );
        $stmt->execute([$userId]);
        $apps = $stmt->fetchAll();

        foreach ($apps as &$app) {
            $app['o_level_results'] = json_decode($app['o_level_results'], true);
            $app['eligibility_score'] = $app['eligibility_score'] ? json_decode($app['eligibility_score'], true) : null;
        }

        $this->success($apps);
    }

    public function show(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $id = (int) $request['params']['id'];

        $stmt = $this->db()->prepare(
            'SELECT a.*, p.name as programme_name, p.code as programme_code, p.department, p.degree_type
             FROM applications a
             JOIN programmes p ON p.id = a.programme_id
             WHERE a.id = ? AND a.user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $app = $stmt->fetch();

        if (!$app) {
            $this->error('Application not found', 404);
            return;
        }

        $app['o_level_results'] = json_decode($app['o_level_results'], true);
        $app['eligibility_score'] = $app['eligibility_score'] ? json_decode($app['eligibility_score'], true) : null;

        // Get documents
        $docs = $this->db()->prepare('SELECT * FROM documents WHERE application_id = ?');
        $docs->execute([$id]);
        $app['documents'] = $docs->fetchAll();

        // Get status history
        $history = $this->db()->prepare(
            'SELECT sh.*, u.first_name, u.last_name
             FROM status_history sh
             LEFT JOIN users u ON u.id = sh.changed_by
             WHERE sh.application_id = ?
             ORDER BY sh.changed_at ASC'
        );
        $history->execute([$id]);
        $app['status_history'] = $history->fetchAll();

        $this->success($app);
    }

    public function create(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $body = $request['body'];

        $errors = $this->validate($body, [
            'programme_id'   => 'required|integer',
            'jamb_score'     => 'required|integer',
            'o_level_results' => 'required',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed', 422, $errors);
            return;
        }

        $programmeId = (int) $body['programme_id'];
        $jambScore = (int) $body['jamb_score'];

        if ($jambScore < 0 || $jambScore > 400) {
            $this->error('Validation failed', 422, ['jamb_score' => 'JAMB score must be between 0 and 400']);
            return;
        }

        // Check programme exists
        $prog = $this->db()->prepare('SELECT id FROM programmes WHERE id = ? AND is_active = 1');
        $prog->execute([$programmeId]);
        if (!$prog->fetch()) {
            $this->error('Programme not found or inactive', 404);
            return;
        }

        // Check duplicate
        $dup = $this->db()->prepare('SELECT id FROM applications WHERE user_id = ? AND programme_id = ?');
        $dup->execute([$userId, $programmeId]);
        if ($dup->fetch()) {
            $this->error('You have already applied to this programme', 422);
            return;
        }

        $oLevelResults = is_array($body['o_level_results']) ? $body['o_level_results'] : json_decode($body['o_level_results'], true);
        if (!is_array($oLevelResults) || empty($oLevelResults)) {
            $this->error('Validation failed', 422, ['o_level_results' => 'At least one O-level result is required']);
            return;
        }

        // Generate reference number
        $reference = 'AFIT-' . date('Y') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        $ins = $this->db()->prepare(
            'INSERT INTO applications (user_id, programme_id, reference_number, jamb_reg_number, jamb_score,
             previous_institution, previous_qualification, o_level_results, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "draft")'
        );
        $ins->execute([
            $userId,
            $programmeId,
            $reference,
            $body['jamb_reg_number'] ?? null,
            $jambScore,
            $body['previous_institution'] ?? null,
            $body['previous_qualification'] ?? null,
            json_encode($oLevelResults),
        ]);

        $appId = (int) $this->db()->lastInsertId();

        // Record status history
        $this->db()->prepare(
            'INSERT INTO status_history (application_id, old_status, new_status, changed_by) VALUES (?, NULL, "draft", ?)'
        )->execute([$appId, $userId]);

        $this->success([
            'id'               => $appId,
            'reference_number' => $reference,
        ], 'Application created', 201);
    }

    public function submit(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $id = (int) $request['params']['id'];

        $stmt = $this->db()->prepare(
            'SELECT a.*, p.required_subjects, p.min_credits
             FROM applications a
             JOIN programmes p ON p.id = a.programme_id
             WHERE a.id = ? AND a.user_id = ? AND a.status = "draft"'
        );
        $stmt->execute([$id, $userId]);
        $app = $stmt->fetch();

        if (!$app) {
            $this->error('Application not found or already submitted', 404);
            return;
        }

        $oLevelResults = json_decode($app['o_level_results'], true);
        $requiredSubjects = json_decode($app['required_subjects'], true);

        // Run eligibility engine
        $eligibility = new EligibilityService();
        $result = $eligibility->check($oLevelResults, $requiredSubjects, (int) $app['min_credits']);

        $newStatus = $result['passed'] ? 'eligible' : 'not_eligible';

        $this->db()->prepare(
            'UPDATE applications SET status = ?, eligibility_score = ?, submitted_at = NOW() WHERE id = ?'
        )->execute([$newStatus, json_encode($result), $id]);

        // Status history
        $this->db()->prepare(
            'INSERT INTO status_history (application_id, old_status, new_status, changed_by, notes) VALUES (?, "draft", ?, ?, ?)'
        )->execute([$id, $newStatus, $userId, $result['notes']]);

        // Notification
        $statusLabel = $result['passed'] ? 'Eligible' : 'Not Eligible';
        NotificationService::create(
            $userId,
            'Application Submitted',
            "Your application has been submitted and screened. Eligibility status: {$statusLabel}. {$result['notes']}",
            $result['passed'] ? 'success' : 'warning'
        );

        $this->success([
            'status'      => $newStatus,
            'eligibility' => $result,
        ], 'Application submitted and screened');
    }

    public function statusHistory(array $request): void
    {
        $userId = $request['auth']['user_id'];
        $id = (int) $request['params']['id'];

        // Verify ownership
        $stmt = $this->db()->prepare('SELECT id FROM applications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            $this->error('Application not found', 404);
            return;
        }

        $history = $this->db()->prepare(
            'SELECT sh.*, u.first_name, u.last_name
             FROM status_history sh
             LEFT JOIN users u ON u.id = sh.changed_by
             WHERE sh.application_id = ?
             ORDER BY sh.changed_at ASC'
        );
        $history->execute([$id]);
        $this->success($history->fetchAll());
    }
}
