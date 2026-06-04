<?php
declare(strict_types=1);

/**
 * Cliente Resend para enviar emails transaccionales.
 *
 * Docs: https://resend.com/docs/api-reference/emails/send-email
 *
 * Configuración (.env):
 *   RESEND_API_KEY  — la API key
 *   EMAIL_FROM      — "Nombre <email@dominio>" (debe estar verificado en Resend
 *                     en producción; el default `onboarding@resend.dev` solo
 *                     puede mandar emails al dueño de la cuenta)
 *   ADMIN_EMAIL     — destino de las notificaciones a la dueña
 *
 * Estrategia: nunca rompe el flujo principal. Si Resend falla, log de error
 * y devuelve false. Quien llama decide si reintentar o ignorar.
 */
class Email {
    public static function isConfigured(): bool {
        return !empty(env('RESEND_API_KEY'));
    }

    public static function fromAddress(): string {
        return env('EMAIL_FROM') ?: 'Suplementos GM <onboarding@resend.dev>';
    }

    public static function adminAddress(): ?string {
        return env('ADMIN_EMAIL') ?: null;
    }

    /**
     * Envía un email vía Resend y registra el intento en `email_log`.
     *
     * @param string|array $to        email destino o array de emails
     * @param string       $subject   asunto
     * @param string       $html      cuerpo HTML
     * @param ?string      $text      cuerpo plano (opcional, generado del HTML si null)
     * @param ?string      $replyTo   reply-to (opcional)
     * @param array        $meta      ['kind' => 'order-paid-customer', 'ref_id' => $orderId, 'ref_type' => 'order']
     */
    public static function send(
        string|array $to,
        string $subject,
        string $html,
        ?string $text = null,
        ?string $replyTo = null,
        array $meta = [],
    ): ?string {
        $toStr = is_array($to) ? implode(',', $to) : $to;
        $fromAddr = self::fromAddress();
        $kind = (string)($meta['kind']    ?? 'generic');
        $refId   = (string)($meta['ref_id']   ?? '');
        $refType = (string)($meta['ref_type'] ?? '');

        // Crear el row de log en 'pending' antes de intentar
        $logId = self::logInsert($kind, $toStr, $fromAddr, $subject, $refId, $refType);

        if (!self::isConfigured()) {
            self::logUpdate($logId, 'failed', null, 'RESEND_API_KEY no configurado');
            error_log('[Email] RESEND_API_KEY no configurado');
            return null;
        }

        $payload = [
            'from'    => $fromAddr,
            'to'      => is_array($to) ? array_values($to) : [$to],
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text ?? self::htmlToText($html),
        ];
        if ($replyTo) $payload['reply_to'] = $replyTo;

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . env('RESEND_API_KEY'),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            self::logUpdate($logId, 'failed', null, 'curl error');
            error_log('[Email] curl error enviando a ' . $toStr);
            return null;
        }
        $data = json_decode((string)$body, true);
        if ($code >= 400 || !is_array($data)) {
            $errMsg = is_array($data) && isset($data['message'])
                ? (string)$data['message']
                : ('Resend ' . $code . ': ' . substr((string)$body, 0, 200));
            self::logUpdate($logId, 'failed', null, $errMsg);
            error_log('[Email] ' . $errMsg);
            return null;
        }
        $providerId = $data['id'] ?? null;
        self::logUpdate($logId, 'sent', $providerId, null);
        return $providerId;
    }

    // ─── Email log (audit trail) ─────────────────────────

    private static function logInsert(string $kind, string $to, string $from, string $subject, string $refId, string $refType): string {
        try {
            $id = db_id();
            $stmt = db()->prepare('INSERT INTO email_log
                (id, kind, to_address, from_address, subject, status, ref_id, ref_type)
                VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$id, $kind, $to, $from, $subject, 'pending', $refId ?: null, $refType ?: null]);
            return $id;
        } catch (Throwable $e) {
            error_log('[Email::logInsert] ' . $e->getMessage());
            return '';
        }
    }

    private static function logUpdate(string $id, string $status, ?string $providerId, ?string $err): void {
        if ($id === '') return;
        try {
            $stmt = db()->prepare('UPDATE email_log
                SET status = ?, provider_id = ?, error = ?
                WHERE id = ?');
            $stmt->execute([$status, $providerId, $err, $id]);
        } catch (Throwable $e) {
            error_log('[Email::logUpdate] ' . $e->getMessage());
        }
    }

    /** Últimos N registros del log para el admin. */
    public static function recentLogs(int $limit = 50): array {
        try {
            $stmt = db()->prepare('SELECT * FROM email_log ORDER BY created_at DESC LIMIT ?');
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Logs filtrados por order_id (cualquier email asociado a esa orden). */
    public static function logsForOrder(string $orderId): array {
        try {
            $stmt = db()->prepare('SELECT * FROM email_log
                WHERE ref_id = ? AND ref_type = ?
                ORDER BY created_at DESC');
            $stmt->execute([$orderId, 'order']);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Renderiza un template phtml de src/views/emails y devuelve el HTML. */
    public static function render(string $template, array $vars = []): string {
        $path = __DIR__ . '/../views/emails/' . $template . '.phtml';
        if (!is_file($path)) {
            throw new RuntimeException("Template de email no encontrado: $template");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }

    /** Convierte HTML a texto plano básico para el alt text de los emails. */
    private static function htmlToText(string $html): string {
        $t = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $t = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n", $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
        return trim($t);
    }
}
