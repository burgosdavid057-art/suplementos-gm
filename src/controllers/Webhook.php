<?php
declare(strict_types=1);

class Webhook {
    
    public static function wompi(): void {
        if (!Wompi::isWebhookReady()) {
            self::respond(['ok' => false, 'reason' => 'not configured'], 503);
        }

        $raw = file_get_contents('php://input') ?: '';
        
        
        if (strlen($raw) > 64_000) {
            error_log('Wompi webhook: payload too large (' . strlen($raw) . ' bytes)');
            self::respond(['ok' => false, 'reason' => 'payload too large'], 413);
        }
        $event = json_decode($raw, true);
        if (!is_array($event)) {
            self::respond(['ok' => false, 'reason' => 'invalid json'], 400);
        }

        if (!Wompi::verifyEventSignature($event)) {
            error_log('Wompi webhook: signature mismatch');
            self::respond(['ok' => false, 'reason' => 'bad signature'], 401);
        }

        $tx = $event['data']['transaction'] ?? null;
        if (!is_array($tx)) {
            
            self::respond(['ok' => true, 'ignored' => true]);
        }

        
        $txIdRaw   = (string)($tx['id']        ?? '');
        $refRaw    = (string)($tx['reference'] ?? '');
        $txId      = preg_replace('/[^A-Za-z0-9\-]/', '', $txIdRaw) ?? '';
        $reference = preg_replace('/[^A-Za-z0-9\-]/', '', $refRaw) ?? '';
        $txId      = substr($txId, 0, 64);
        $reference = substr($reference, 0, 40);
        if ($txId === '' || $reference === '') {
            self::respond(['ok' => false, 'reason' => 'missing tx fields'], 400);
        }

        
        
        $confirmed = Wompi::getTransaction($txId);
        if (!$confirmed) {
            error_log("Wompi webhook: lookup failed for tx $txId");
            self::respond(['ok' => false, 'reason' => 'lookup failed'], 502);
        }

        $confirmedRef = (string)($confirmed['reference'] ?? '');
        $order = Order::findByNumber($confirmedRef);
        if (!$order) {
            error_log("Wompi webhook: order $confirmedRef not found");
            self::respond(['ok' => false, 'reason' => 'order not found'], 404);
        }

        $changed = Wompi::applyTransactionToOrder($order, $confirmed);
        self::respond(['ok' => true, 'changed' => $changed]);
    }

    private static function respond(array $data, int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
