<?php

namespace App\Controllers;

class ProgrammeController extends BaseController
{
    public function index(array $request): void
    {
        $stmt = $this->db()->query('SELECT * FROM programmes WHERE is_active = 1 ORDER BY name');
        $programmes = $stmt->fetchAll();

        foreach ($programmes as &$p) {
            $p['required_subjects'] = json_decode($p['required_subjects'], true);
        }

        $this->success($programmes);
    }

    public function show(array $request): void
    {
        $id = (int) $request['params']['id'];
        $stmt = $this->db()->prepare('SELECT * FROM programmes WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        $programme = $stmt->fetch();

        if (!$programme) {
            $this->error('Programme not found', 404);
            return;
        }

        $programme['required_subjects'] = json_decode($programme['required_subjects'], true);
        $this->success($programme);
    }
}
