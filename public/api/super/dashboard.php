<?php
// public/api/super/dashboard.php
define('ROOT_PATH', realpath(__DIR__ . '/../../../'));
require_once ROOT_PATH . '/app/helpers/Database.php';
require_once ROOT_PATH . '/public/api/_lib/respond.php';

if (Database::extractJwtRole() !== 'SUPER_ADMIN') {
    jsonResponse('error', null, 'Unauthorized.', 401);
}

$pdo = Database::getConnection();

// 1. Total Active Tenants
$stmt = $pdo->query("SELECT COUNT(*) FROM tenants WHERE is_active = true");
$activeTenants = (int) $stmt->fetchColumn();

// 2. Total MRR (Monthly Recurring Revenue)
$stmt = $pdo->query("
    SELECT package_tier, COUNT(*) as count 
    FROM subscriptions 
    WHERE is_active = true
    GROUP BY package_tier
");
$mrr = 0;
// Example pricing tiers
$prices = ['basic' => 15, 'pro' => 49, 'enterprise' => 99];
while ($row = $stmt->fetch()) {
    $tier = strtolower($row['package_tier']);
    $mrr += ($prices[$tier] ?? 0) * (int)$row['count'];
}

// 3. Total System Storage Used
require_once ROOT_PATH . '/app/services/TenantStorageService.php';
$storageService = new TenantStorageService($pdo);

$stmt = $pdo->query("SELECT id FROM tenants");
$totalBytes = 0;
while ($tenantId = $stmt->fetchColumn()) {
    $totalBytes += $storageService->calculateFileStorage($tenantId);
    $totalBytes += ($storageService->calculateDatabaseStorage($tenantId) * 2048);
}

function formatSystemSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

jsonResponse('success', [
    'total_active_tenants' => $activeTenants,
    'total_mrr' => $mrr,
    'total_system_storage_used' => formatSystemSize($totalBytes)
]);
