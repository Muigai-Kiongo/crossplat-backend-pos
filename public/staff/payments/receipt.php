<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);
$ids = array_values(array_unique(array_filter(array_map(
    'intval',
    preg_split('/[^0-9]+/', (string) ($_GET['payment_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
))));
$payments = $O->paymentReceiptRows($ids);
if (!$payments) {
    http_response_code(404);
    echo 'Payment receipt not found.';
    exit;
}

$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId()) ?: [];
$currency = $tenant['currency'] ?? 'KES';
$shop = $tenant['name'] ?? ReceiptFooter::SHOP_NAME;
$logo = Branding::loginLogo();
$customerName = trim((string) ($payments[0]['customer_name'] ?? ''));
$customerId = (int) ($payments[0]['customer_id'] ?? 0);
$paymentTotal = array_sum(array_map(fn(array $p): float => (float) $p['amount'], $payments));
$lastPaymentId = max(array_map(fn(array $p): int => (int) $p['id'], $payments));
$paidAt = max(array_map(fn(array $p): int => strtotime($p['created_at']), $payments));
$showInvoices = ($_GET['view'] ?? 'balance') === 'invoices';
$autoPrint = ($_GET['print'] ?? '') === '1';
$isStaffViewer = TenantContext::role() === 'staff';
$receiptCode = count($payments) === 1
    ? 'PAY-' . str_pad((string) $payments[0]['id'], 6, '0', STR_PAD_LEFT)
    : 'PAY-' . str_pad((string) min($ids), 6, '0', STR_PAD_LEFT) . '-' . str_pad((string) max($ids), 6, '0', STR_PAD_LEFT);

$balanceSql = "SELECT COALESCE(SUM(GREATEST(o.total - COALESCE((
                    SELECT SUM(op.amount) FROM order_payments op
                     WHERE op.tenant_id = o.tenant_id AND op.order_id = o.id AND op.id <= ?
                ), 0), 0)), 0)
                 FROM orders o
                WHERE o.tenant_id = ? AND o.status <> 'void' AND o.created_at <= ?";
$balanceParams = [$lastPaymentId, TenantContext::tenantId(), date('Y-m-d H:i:s', $paidAt)];
if ($customerId > 0) {
    $balanceSql .= ' AND o.customer_id = ?';
    $balanceParams[] = $customerId;
} else {
    $balanceSql .= ' AND LOWER(TRIM(o.table_name)) = LOWER(?)';
    $balanceParams[] = $customerName;
}
$balanceStmt = $pdo->prepare($balanceSql);
$balanceStmt->execute($balanceParams);
$customerBalanceAfter = round((float) $balanceStmt->fetchColumn(), 2);

$methods = [];
foreach ($payments as $payment) {
    $label = PaymentOptions::label([
        'payment_method' => $payment['method'],
        'payment_provider' => $payment['provider'] ?? null,
        'payment_account_name' => $payment['account_name'] ?? null,
    ]);
    $methods[$label] = true;
}
$methodLabel = implode(' + ', array_keys($methods));
function payment_money($amount): string
{
    global $currency;
    return $currency . ' ' . number_format((float) $amount, 2);
}

$baseUrl = ($isStaffViewer ? public_url('staff/payments/receipt.php') : public_url('super/payments/receipt.php'))
    . '?payment_ids=' . urlencode(implode(',', $ids));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($receiptCode . ' - ' . $shop); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f1f5f9;margin:0;padding:24px;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif}.sheet{background:#fff;max-width:380px;margin:0 auto 18px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:24px;color:#000}.sheet,.sheet *{font-weight:900!important}.actions{max-width:380px;margin:0 auto}@page{margin:8mm}@media print{body{background:#fff;padding:0}.actions{display:none!important}.sheet{box-shadow:none;border-radius:0;margin:0 auto!important;width:80mm;max-width:80mm;padding:10px 12px;font-size:15px!important}}
</style>
</head>
<body>
<div class="sheet">
  <div style="text-align:center;border-bottom:2px dashed #000;padding-bottom:10px;margin-bottom:10px">
    <?php if ($logo): ?><img src="<?php echo htmlspecialchars($logo); ?>" alt="" style="max-height:104px;max-width:285px;object-fit:contain;margin-bottom:8px"><?php endif; ?>
    <div style="font-size:24px"><?php echo htmlspecialchars($shop); ?></div>
    <div style="font-size:16px">Payment receipt <?php echo htmlspecialchars($receiptCode); ?></div>
    <div style="font-size:14px"><?php echo htmlspecialchars(date('j M Y, g:i a', $paidAt)); ?></div>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:16px">
    <tr><td style="padding:4px 0">Customer</td><td style="padding:4px 0;text-align:right"><?php echo htmlspecialchars($customerName ?: 'Customer'); ?></td></tr>
    <tr><td style="padding:4px 0">Amount received</td><td style="padding:4px 0;text-align:right;font-size:19px"><?php echo payment_money($paymentTotal); ?></td></tr>
    <tr><td style="padding:4px 0">Payment mode</td><td style="padding:4px 0;text-align:right"><?php echo htmlspecialchars($methodLabel); ?></td></tr>
    <tr style="border-top:2px dashed #000"><td style="padding:8px 0 4px">Balance remaining</td><td style="padding:8px 0 4px;text-align:right;font-size:19px"><?php echo payment_money($customerBalanceAfter); ?></td></tr>
  </table>
  <?php if ($showInvoices): ?>
  <div style="border-top:2px dashed #000;margin-top:10px;padding-top:8px">
    <div style="font-size:13px;margin-bottom:4px">INVOICES COVERED (<?php echo count($payments); ?>)</div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <?php foreach ($payments as $payment): ?>
      <tr>
        <td style="padding:3px 0"><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
        <td style="padding:3px 0;text-align:right"><?php echo payment_money($payment['amount']); ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
  <div style="margin-top:12px"><?php echo ReceiptFooter::html($tenant, $receiptCode); ?></div>
</div>
<div class="actions">
  <div class="d-flex gap-2 mb-2">
    <a class="btn btn-outline-secondary flex-fill" href="<?php echo htmlspecialchars($baseUrl . '&view=balance'); ?>">Balance only</a>
    <a class="btn btn-outline-secondary flex-fill" href="<?php echo htmlspecialchars($baseUrl . '&view=invoices'); ?>">List invoices</a>
  </div>
  <button type="button" class="btn btn-primary w-100" onclick="window.print()">Print receipt</button>
</div>
<?php if ($autoPrint): ?><script>window.addEventListener('load',function(){setTimeout(function(){window.print()},250)})</script><?php endif; ?>
</body>
</html>
