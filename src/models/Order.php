<?php
declare(strict_types=1);

class Order {
    

    public static function counts(): array {
        $pdo = db();
        return [
            'total'      => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'pending'    => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='PENDING'")->fetchColumn(),
            'paid'       => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='PAID'")->fetchColumn(),
            'shipped'    => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='SHIPPED'")->fetchColumn(),
            'delivered'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='DELIVERED'")->fetchColumn(),
            'cancelled'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='CANCELLED'")->fetchColumn(),
            'failed'     => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='FAILED'")->fetchColumn(),
        ];
    }

    public static function recent(int $limit = 5): array {
        $stmt = db()->prepare('
            SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
            FROM orders o
            ORDER BY o.created_at DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function totalPaidRevenue(): int {
        return (int) db()->query("
            SELECT COALESCE(SUM(total), 0)
            FROM orders
            WHERE status IN ('PAID','SHIPPED','DELIVERED')
        ")->fetchColumn();
    }

    public static function last30dStats(): array {
        $cutoff = (new DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');
        $stmt = db()->prepare("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total
            FROM orders
            WHERE status IN ('PAID','SHIPPED','DELIVERED')
              AND paid_at >= ?
        ");
        $stmt->execute([$cutoff]);
        $r = $stmt->fetch();
        return [
            'count'   => (int)($r['cnt'] ?? 0),
            'revenue' => (int)($r['total'] ?? 0),
        ];
    }

    public static function lastSyncAt(): ?string {
        return db()->query("SELECT MAX(synced_at) FROM products")->fetchColumn() ?: null;
    }

    public static function alegraCount(): int {
        return (int) db()->query("SELECT COUNT(*) FROM products WHERE alegra_id IS NOT NULL")->fetchColumn();
    }

    

    public static function findByNumber(string $orderNumber): ?array {
        $s = db()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
        $s->execute([$orderNumber]);
        $row = $s->fetch();
        return $row ?: null;
    }

    public static function findById(string $id): ?array {
        $s = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $row = $s->fetch();
        return $row ?: null;
    }

    public static function items(string $orderId): array {
        $s = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $s->execute([$orderId]);
        return $s->fetchAll();
    }

    

    
    public static function create(array $data, array $items): array {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $id     = db_id();
            $number = order_number();

            $stmt = $pdo->prepare('INSERT INTO orders (
                id, order_number,
                customer_type, customer_doc_type, customer_doc_id,
                customer_name, customer_email, customer_phone,
                billing_name, billing_address, billing_city, billing_state,
                shipping_method, shipping_terminal,
                shipping_address, shipping_city, shipping_state, shipping_notes,
                subtotal, shipping_cost, total, status
            ) VALUES (?,?, ?,?,?, ?,?,?, ?,?,?,?, ?,?, ?,?,?,?, ?,?,?,?)');

            $stmt->execute([
                $id, $number,
                $data['customer_type']     ?? 'natural',
                $data['customer_doc_type'] ?? 'CC',
                $data['customer_doc_id'],
                $data['customer_name'], $data['customer_email'], $data['customer_phone'],
                $data['billing_name']    ?? $data['customer_name'],
                $data['billing_address'] ?? null,
                $data['billing_city']    ?? null,
                $data['billing_state']   ?? null,
                $data['shipping_method'] ?? 'interrapidisimo',
                $data['shipping_terminal'] ?? null,
                $data['shipping_address'], $data['shipping_city'], $data['shipping_state'], $data['shipping_notes'] ?? null,
                (int)$data['subtotal'], (int)$data['shipping_cost'], (int)$data['total'], 'PENDING',
            ]);

            $itemStmt = $pdo->prepare('INSERT INTO order_items (
                id, order_id, product_id, product_name, product_price, quantity, subtotal
            ) VALUES (?,?,?,?,?,?,?)');

            foreach ($items as $it) {
                $itemStmt->execute([
                    db_id(), $id, $it['product_id'], $it['product_name'],
                    (int)$it['product_price'], (int)$it['quantity'], (int)$it['subtotal'],
                ]);
            }

            $pdo->commit();
            return ['id' => $id, 'order_number' => $number];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function markPaid(string $id, ?string $paymentMethod = null, ?string $paymentTxId = null, ?string $paymentRef = null): void {
        $stmt = db()->prepare('UPDATE orders
            SET status = ?, paid_at = ?, payment_method = ?, payment_tx_id = ?, payment_ref = ?, updated_at = ?
            WHERE id = ?');
        $stmt->execute(['PAID', db_now(), $paymentMethod, $paymentTxId, $paymentRef, db_now(), $id]);
    }

    public static function markFailed(string $id, ?string $paymentTxId = null): void {
        $stmt = db()->prepare('UPDATE orders SET status = ?, payment_tx_id = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(['FAILED', $paymentTxId, db_now(), $id]);
    }

    
    public static function markShipped(string $id, ?string $carrier, ?string $tracking): void {
        $stmt = db()->prepare('UPDATE orders SET
            status = ?, tracking_carrier = ?, tracking_number = ?,
            shipped_at = ?, updated_at = ?
            WHERE id = ?');
        $stmt->execute(['SHIPPED', $carrier, $tracking, db_now(), db_now(), $id]);
    }

    public static function markDelivered(string $id): void {
        $stmt = db()->prepare('UPDATE orders SET
            status = ?, delivered_at = ?, updated_at = ?
            WHERE id = ?');
        $stmt->execute(['DELIVERED', db_now(), db_now(), $id]);
    }

    public static function markCancelled(string $id): void {
        $stmt = db()->prepare('UPDATE orders SET
            status = ?, updated_at = ?
            WHERE id = ?');
        $stmt->execute(['CANCELLED', db_now(), $id]);
    }

    public static function updateAdminNotes(string $id, ?string $notes): void {
        $stmt = db()->prepare('UPDATE orders SET admin_notes = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$notes, db_now(), $id]);
    }

    
    public static function search(array $opts = []): array {
        $where = ['1=1'];
        $args  = [];

        $status = $opts['status'] ?? null;
        $validStatuses = ['PENDING','PAID','FAILED','SHIPPED','DELIVERED','CANCELLED'];
        if ($status && in_array($status, $validStatuses, true)) {
            $where[] = 'status = ?';
            $args[]  = $status;
        }

        $q = trim((string)($opts['q'] ?? ''));
        if ($q !== '') {
            
            $where[] = '(order_number LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ? OR customer_name LIKE ?)';
            $like = '%' . $q . '%';
            $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
        }

        $page    = max(1, (int)($opts['page']    ?? 1));
        $perPage = max(1, min(100, (int)($opts['perPage'] ?? 20)));
        $offset  = ($page - 1) * $perPage;
        $whereSql = implode(' AND ', $where);

        $pdo = db();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $whereSql");
        $stmt->execute($args);
        $total = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT o.*,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
            FROM orders o
            WHERE $whereSql
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $i = 1;
        foreach ($args as $a) $stmt->bindValue($i++, $a);
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        return [
            'items'   => $items,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => max(1, (int)ceil($total / $perPage)),
        ];
    }
}
