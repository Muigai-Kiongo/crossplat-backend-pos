<?php
// public/api/super/subscriptions.php
define('ROOT_PATH', realpath(__DIR__ . '/../../../'));
require_once ROOT_PATH . '/app/helpers/Database.php';
require_once ROOT_PATH . '/public/api/_lib/respond.php';

if (Database::extractJwtRole() !== 'SUPER_ADMIN') {
    jsonResponse('error', null, 'Unauthorized.', 401);
}

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['tenant_id']) || !isset($input['package_tier'])) {
        jsonResponse('error', null, 'Missing tenant_id or package_tier', 400);
    }
    
    $tenantId = (int)$input['tenant_id'];
    $tier = $input['package_tier'];
    
    try {
        $pdo->beginTransaction();
        
        // Invalidate existing active subscriptions
        $stmt = $pdo->prepare("UPDATE subscriptions SET is_active = false, ends_at = CURRENT_TIMESTAMP WHERE tenant_id = :id AND is_active = true");
        $stmt->execute(['id' => $tenantId]);
        
        // Create new subscription
        $stmt = $pdo->prepare("INSERT INTO subscriptions (tenant_id, package_tier, is_active) VALUES (:id, :tier, true)");
        $stmt->execute(['id' => $tenantId, 'tier' => $tier]);
        
        // Update tenant's package_tier to sync
        $stmt = $pdo->prepare("UPDATE tenants SET package_tier = :tier WHERE id = :id");
        $stmt->execute(['tier' => $tier, 'id' => $tenantId]);
        
        $pdo->commit();
        jsonResponse('success', null, 'Subscription manually updated successfully.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse('error', null, 'Failed to update subscription: ' . $e->getMessage(), 500);
    }
} else {
    jsonResponse('error', null, 'Method not allowed', 405);
}
