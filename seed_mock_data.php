<?php
require_once __DIR__ . '/app/app.php';

$pdo = Database::pdo();

try {
    $pdo->beginTransaction();

    // 1. Ensure at least one tenant
    $stmt = $pdo->query("SELECT id FROM tenants LIMIT 1");
    $tenantId = $stmt->fetchColumn();
    if (!$tenantId) {
        $pdo->exec("INSERT INTO tenants (name, domain) VALUES ('Mock Shop', 'mockshop')");
        $tenantId = $pdo->lastInsertId();
    }

    // 2. Ensure at least one user for this tenant
    $stmt = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $userId = $stmt->fetchColumn();
    if (!$userId) {
        $pdo->prepare("INSERT INTO users (tenant_id, username, email, password_hash, role) VALUES (?, 'mock_admin', 'mock@example.com', 'hash', 'admin')")
            ->execute([$tenantId]);
        $userId = $pdo->lastInsertId();
    }

    // 3. Ensure some products exist
    $stmt = $pdo->prepare("SELECT id, name, selling_price, buying_price FROM products WHERE tenant_id = ? LIMIT 10");
    $stmt->execute([$tenantId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        for ($i = 1; $i <= 5; $i++) {
            $pdo->prepare("INSERT INTO products (tenant_id, name, selling_price, buying_price, current_stock) VALUES (?, ?, ?, ?, ?)")
                ->execute([$tenantId, "Mock Product $i", $i * 10, $i * 5, 100]);
            $products[] = [
                'id' => $pdo->lastInsertId(),
                'name' => "Mock Product $i",
                'selling_price' => $i * 10,
                'buying_price' => $i * 5
            ];
        }
    }

    // 4. Ensure some customers exist
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? LIMIT 5");
    $stmt->execute([$tenantId]);
    $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($customers)) {
        for ($i = 1; $i <= 3; $i++) {
            $pdo->prepare("INSERT INTO customers (tenant_id, name, phone) VALUES (?, ?, ?)")
                ->execute([$tenantId, "Mock Customer $i", "123456789$i"]);
            $customers[] = $pdo->lastInsertId();
        }
    }

    // 5. Create some orders (open and closed)
    $orderStatuses = ['open', 'open', 'paid'];
    for ($i = 0; $i < 6; $i++) {
        $status = $orderStatuses[$i % count($orderStatuses)];
        $customerId = $customers[array_rand($customers)];
        $receipt = 'ORD-' . time() . '-' . $i;

        $pdo->prepare("INSERT INTO orders (tenant_id, customer_id, opened_by, status, total, amount_paid, amount_due, table_name, receipt_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$tenantId, $customerId, $userId, $status, 0, 0, 0, "Table $i", $receipt]);
        $orderId = $pdo->lastInsertId();

        // Add 1-3 items
        $numItems = rand(1, 3);
        $orderTotal = 0;
        for ($j = 0; $j < $numItems; $j++) {
            $prod = $products[array_rand($products)];
            $qty = rand(1, 4);
            $subtotal = $prod['selling_price'] * $qty;
            $orderTotal += $subtotal;
            
            $pdo->prepare("INSERT INTO order_items (tenant_id, order_id, product_id, product_name, unit_price, quantity, line_total, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$tenantId, $orderId, $prod['id'], $prod['name'], $prod['selling_price'], $qty, $subtotal, $userId]);
        }

        $paid = $status === 'paid' ? $orderTotal : rand(0, $orderTotal - 1);
        $pdo->prepare("UPDATE orders SET total = ?, amount_paid = ?, amount_due = ? WHERE id = ?")
            ->execute([$orderTotal, $paid, $orderTotal - $paid, $orderId]);
    }

    // 6. Create some sales
    for ($i = 0; $i < 5; $i++) {
        $receipt = 'REC-' . time() . '-' . $i;
        $pdo->prepare("INSERT INTO sales (tenant_id, staff_id, receipt_number, customer_name, total, payment_method) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$tenantId, $userId, $receipt, "Walk-in $i", 0, 'cash']);
        $saleId = $pdo->lastInsertId();

        // Add 1-2 items
        $numItems = rand(1, 2);
        $saleTotal = 0;
        for ($j = 0; $j < $numItems; $j++) {
            $prod = $products[array_rand($products)];
            $qty = rand(1, 2);
            $subtotal = $prod['selling_price'] * $qty;
            $saleTotal += $subtotal;
            
            $pdo->prepare("INSERT INTO sale_items (tenant_id, sale_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$tenantId, $saleId, $prod['id'], $prod['name'], $prod['selling_price'], $qty, $subtotal]);
        }

        $pdo->prepare("UPDATE sales SET total = ? WHERE id = ?")
            ->execute([$saleTotal, $saleId]);
    }

    $pdo->commit();
    echo "Mock data seeded successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Failed: " . $e->getMessage() . "\n";
}
