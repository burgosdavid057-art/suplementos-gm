<?php
declare(strict_types=1);

class Wompi {
    
    public static function isCheckoutReady(): bool {
        return (env('WOMPI_PUBLIC_KEY') ?? '') !== ''
            && (env('WOMPI_INTEGRITY_SECRET') ?? '') !== '';
    }

    
    public static function isWebhookReady(): bool {
        return (env('WOMPI_EVENTS_SECRET') ?? '') !== ''
            && (env('WOMPI_PRIVATE_KEY') ?? '') !== '';
    }

    public static function apiBase(): string {
        return env('WOMPI_ENV', 'sandbox') === 'production'
            ? 'https://production.wompi.co/v1'
            : 'https://sandbox.wompi.co/v1';
    }

    
    public static function integritySignature(
        string $reference,
        int $amountInCents,
        string $currency = 'COP',
    ): string {
        $secret = env('WOMPI_INTEGRITY_SECRET') ?? '';
        return hash('sha256', $reference . $amountInCents . $currency . $secret);
    }

    
    public static function verifyEventSignature(array $event): bool {
        $secret = env('WOMPI_EVENTS_SECRET') ?? '';
        if ($secret === '') return false;

        $signature = $event['signature'] ?? null;
        if (!is_array($signature)) return false;

        $properties = $signature['properties'] ?? null;
        $checksum   = $signature['checksum']   ?? null;
        $timestamp  = $event['timestamp']      ?? null;
        if (!is_array($properties) || !is_string($checksum) || $timestamp === null) {
            return false;
        }

        $concat = '';
        foreach ($properties as $path) {
            $concat .= self::resolvePath($event['data'] ?? [], (string)$path);
        }
        $expected = hash('sha256', $concat . $timestamp . $secret);
        return hash_equals($expected, $checksum);
    }

    
    public static function getTransaction(string $transactionId): ?array {
        $key = env('WOMPI_PRIVATE_KEY') ?? '';
        if ($key === '') return null;

        $url = self::apiBase() . '/transactions/' . urlencode($transactionId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $key,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http !== 200 || !is_string($body)) return null;
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['data'])) return null;
        return $decoded['data'];
    }

    
    public static function applyTransactionToOrder(array $order, array $transaction): bool {
        $status = (string)($transaction['status'] ?? '');
        $txId   = (string)($transaction['id'] ?? '');
        $method = $transaction['payment_method_type'] ?? null;
        $ref    = (string)($transaction['reference'] ?? '');

        
        if ($order['status'] === 'PAID' && $status === 'APPROVED')   return false;
        if ($order['status'] === 'FAILED' && in_array($status, ['DECLINED','ERROR','VOIDED'], true)) return false;

        if ($status === 'APPROVED') {
            Order::markPaid($order['id'], $method, $txId, $ref);
            
            
            
            try {
                $fresh = Order::findById($order['id']);
                if ($fresh) {
                    OrderMailer::notifyPaid($fresh, Order::items($order['id']));
                }
            } catch (Throwable $e) {
                error_log('[Wompi::applyTransaction] mailer failed: ' . $e->getMessage());
            }
            return true;
        }
        if (in_array($status, ['DECLINED', 'ERROR', 'VOIDED'], true)) {
            Order::markFailed($order['id'], $txId);
            return true;
        }
        
        return false;
    }

    
    private static function resolvePath(array $data, string $path): string {
        $parts = explode('.', $path);
        $cur = $data;
        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) return '';
            $cur = $cur[$p];
        }
        return $cur === null ? '' : (string)$cur;
    }
}
