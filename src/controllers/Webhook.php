<?php
declare(strict_types=1);

class Webhook {
    /**
     * POST /api/wompi/webhook
     *
     * Wompi nos avisa cuando una transacción cambia de estado. Validamos:
     *   1. La firma del evento contra WOMPI_EVENTS_SECRET
     *   2. (Defensivo) Re-consultamos la transacción contra Wompi con el
     *      private key para no confiar ciegamente en el payload
     *   3. Encontramos la orden por reference
     *   4. Aplicamos el cambio (PAID / FAILED) de forma idempotente
     */
    public static function wompi(): void {
        if (!Wompi::isWebhookReady()) {
            self::respond(['ok' => false, 'reason' => 'not configured'], 503);
        }

        $raw = file_get_contents('php://input') ?: '';
        // Tope defensivo: un webhook legítimo de Wompi pesa < 4KB. Cualquier
        // cosa más grande es sospechosa (intento de DoS o payload malicioso).
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
            // Evento que no es de transacción → respondemos 200 para que Wompi no reintente
            self::respond(['ok' => true, 'ignored' => true]);
        }

        // Sanea los IDs antes de usar: Wompi tx IDs son alfanuméricos + guiones.
        $txIdRaw   = (string)($tx['id']        ?? '');
        $refRaw    = (string)($tx['reference'] ?? '');
        $txId      = preg_replace('/[^A-Za-z0-9\-]/', '', $txIdRaw) ?? '';
        $reference = preg_replace('/[^A-Za-z0-9\-]/', '', $refRaw) ?? '';
        $txId      = substr($txId, 0, 64);
        $reference = substr($reference, 0, 40);
        if ($txId === '' || $reference === '') {
            self::respond(['ok' => false, 'reason' => 'missing tx fields'], 400);
        }

        // Defensa: re-consultamos el estado contra Wompi en lugar de confiar
        // ciegamente en el payload del evento.
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
