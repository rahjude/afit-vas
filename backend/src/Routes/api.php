<?php

use App\Config\Router;
use App\Controllers\AuthController;
use App\Controllers\ProgrammeController;
use App\Controllers\ApplicationController;
use App\Controllers\DocumentController;
use App\Controllers\NotificationController;
use App\Controllers\AdminController;
use App\Controllers\HealthController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;

$router = new Router();

// Health
$router->get('/health', HealthController::class, 'check');

// Auth
$router->post('/auth/register', AuthController::class, 'register');
$router->post('/auth/login', AuthController::class, 'login');
$router->get('/auth/verify', AuthController::class, 'verifyEmail');
$router->get('/profile', AuthController::class, 'profile', [AuthMiddleware::class]);
$router->put('/profile', AuthController::class, 'updateProfile', [AuthMiddleware::class]);

// Programmes (public)
$router->get('/programmes', ProgrammeController::class, 'index');
$router->get('/programmes/:id', ProgrammeController::class, 'show');

// Applications (authenticated)
$router->get('/applications', ApplicationController::class, 'index', [AuthMiddleware::class]);
$router->post('/applications', ApplicationController::class, 'create', [AuthMiddleware::class]);
$router->get('/applications/:id', ApplicationController::class, 'show', [AuthMiddleware::class]);
$router->post('/applications/:id/submit', ApplicationController::class, 'submit', [AuthMiddleware::class]);
$router->get('/applications/:id/history', ApplicationController::class, 'statusHistory', [AuthMiddleware::class]);

// Documents (authenticated)
$router->get('/documents', DocumentController::class, 'index', [AuthMiddleware::class]);
$router->post('/documents/upload', DocumentController::class, 'upload', [AuthMiddleware::class]);
$router->get('/documents/:id/download', DocumentController::class, 'download', [AuthMiddleware::class]);

// Notifications (authenticated)
$router->get('/notifications', NotificationController::class, 'index', [AuthMiddleware::class]);
$router->put('/notifications/:id/read', NotificationController::class, 'markRead', [AuthMiddleware::class]);
$router->put('/notifications/read-all', NotificationController::class, 'markAllRead', [AuthMiddleware::class]);

// Admin (authenticated + admin role)
$router->get('/admin/stats', AdminController::class, 'stats', [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/applications', AdminController::class, 'applications', [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/applications/:id', AdminController::class, 'viewApplication', [AuthMiddleware::class, AdminMiddleware::class]);
$router->put('/admin/applications/:id/status', AdminController::class, 'updateStatus', [AuthMiddleware::class, AdminMiddleware::class]);
$router->put('/admin/documents/:id/verify', AdminController::class, 'verifyDocument', [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/reports', AdminController::class, 'reports', [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/export', AdminController::class, 'exportCsv', [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/audit-logs', AdminController::class, 'auditLogs', [AuthMiddleware::class, AdminMiddleware::class]);

return $router;
