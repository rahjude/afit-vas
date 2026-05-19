<?php

namespace App\Controllers;

class HealthController extends BaseController
{
    public function check(array $request): void
    {
        try {
            $this->db()->query('SELECT 1');
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        $this->success([
            'status'  => 'ok',
            'db'      => $dbStatus,
            'version' => '1.0.0',
            'time'    => date('c'),
        ]);
    }
}
