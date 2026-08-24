<?php
// public/api/super/tenants.php
define('ROOT_PATH', realpath(__DIR__ . '/../../../'));
require_once ROOT_PATH . '/app/helpers/Database.php';
require_once ROOT_PATH . '/public/api/_lib/respond.php';
require_once ROOT_PATH . '/app/services/TenantStorageService.php';

if (Database::extractJwtRole() !== 'SUPER_ADMIN') {
    jsonResponse('error', null, 'Unauthorized.', 401);
}

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT t.id, t.business_name, t.package_tier as tenant_tier, t.is_active, s.package_tier as active_package 
        FROM tenants t
        LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.is_active = true
    ");
    $tenants = $stmt->fetchAll();
    
    $storageService = new TenantStorageService($pdo);
    
    foreach ($tenants as &$tenant) {
        $tenant['storage_used'] = $storageService->getFormattedStorage((int)$tenant['id']);
    }
    
    jsonResponse('success', $tenants);
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['tenant_id']) || !isset($input['is_active'])) {
        jsonResponse('error', null, 'Missing tenant_id or is_active', 400);
    }
    
    $stmt = $pdo->prepare("UPDATE tenants SET is_active = :is_active WHERE id = :id");
    $stmt->execute([
        'is_active' => (bool)$input['is_active'] ? 1 : 0,
        'id' => (int)$input['tenant_id']
    ]);
    
    jsonResponse('success', null, 'Tenant status updated.');
} else {
    jsonResponse('error', null, 'Method not allowed', 405);
}
