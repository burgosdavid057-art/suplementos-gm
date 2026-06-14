<?php
declare(strict_types=1);

class OrderMailer {
    
    public static function notifyPendingToCustomer(array $order, array $items): void {
        try {
            $shippingMethod = Shipping::methodById($order['shipping_method'] ?? 'interrapidisimo');
            $orderUrl = (env('APP_URL') ?: 'https://suplementosequinosgm.co')
                      . '/checkout/orden/' . $order['order_number'];

            $html = Email::render('order-pending-customer', [
                'order'           => $order,
                'items'           => $items,
                'shipping_method' => $shippingMethod,
                'orderUrl'        => $orderUrl,
            ]);
            $subject = 'Tu pedido ' . $order['order_number'] . ' está esperando pago — Suplementos GM';
            Email::send($order['customer_email'], $subject, $html, null, Email::adminAddress(), [
                'kind'     => 'order-pending-customer',
                'ref_id'   => $order['id'],
                'ref_type' => 'order',
            ]);
        } catch (Throwable $e) {
            error_log('[OrderMailer::pending] ' . $e->getMessage());
        }
    }

    
    public static function notifyPaid(array $order, array $items): void {
        $shippingMethod = Shipping::methodById($order['shipping_method'] ?? 'interrapidisimo');
        $orderUrl = (env('APP_URL') ?: 'https://suplementosequinosgm.co')
                  . '/checkout/orden/' . $order['order_number'];

        
        try {
            $html = Email::render('order-paid-customer', [
                'order'           => $order,
                'items'           => $items,
                'shipping_method' => $shippingMethod,
                'orderUrl'        => $orderUrl,
            ]);
            $subject = 'Pago confirmado · pedido ' . $order['order_number'];
            Email::send($order['customer_email'], $subject, $html, null, Email::adminAddress(), [
                'kind'     => 'order-paid-customer',
                'ref_id'   => $order['id'],
                'ref_type' => 'order',
            ]);
        } catch (Throwable $e) {
            error_log('[OrderMailer::paid customer] ' . $e->getMessage());
        }

        
        try {
            $admin = Email::adminAddress();
            if ($admin) {
                $html = Email::render('order-paid-owner', [
                    'order'           => $order,
                    'items'           => $items,
                    'shipping_method' => $shippingMethod,
                    'adminUrl'        => (env('APP_URL') ?: 'https://suplementosequinosgm.co') . '/admin/pedidos',
                ]);
                $subject = 'Nueva venta ' . money((int)$order['total']) . ' · ' . $order['order_number'];
                Email::send($admin, $subject, $html, null, $order['customer_email'], [
                    'kind'     => 'order-paid-owner',
                    'ref_id'   => $order['id'],
                    'ref_type' => 'order',
                ]);
            }
        } catch (Throwable $e) {
            error_log('[OrderMailer::paid owner] ' . $e->getMessage());
        }
    }

    
    public static function notifyShipped(array $order, array $items): void {
        try {
            $shippingMethod = Shipping::methodById($order['shipping_method'] ?? 'interrapidisimo');
            $orderUrl = (env('APP_URL') ?: 'https://suplementosequinosgm.co')
                      . '/checkout/orden/' . $order['order_number'];

            $html = Email::render('order-shipped-customer', [
                'order'           => $order,
                'items'           => $items,
                'shipping_method' => $shippingMethod,
                'orderUrl'        => $orderUrl,
            ]);
            $subject = 'Tu pedido ' . $order['order_number'] . ' va en camino';
            Email::send($order['customer_email'], $subject, $html, null, Email::adminAddress(), [
                'kind'     => 'order-shipped-customer',
                'ref_id'   => $order['id'],
                'ref_type' => 'order',
            ]);
        } catch (Throwable $e) {
            error_log('[OrderMailer::shipped] ' . $e->getMessage());
        }
    }

    
    public static function notifyCancelled(array $order, array $items): void {
        try {
            $html = Email::render('order-cancelled-customer', [
                'order' => $order,
                'items' => $items,
            ]);
            $subject = 'Pedido ' . $order['order_number'] . ' cancelado';
            Email::send($order['customer_email'], $subject, $html, null, Email::adminAddress(), [
                'kind'     => 'order-cancelled-customer',
                'ref_id'   => $order['id'],
                'ref_type' => 'order',
            ]);
        } catch (Throwable $e) {
            error_log('[OrderMailer::cancelled] ' . $e->getMessage());
        }
    }

    
    public static function resend(array $order, string $which): bool {
        $items = Order::items($order['id']);
        switch ($which) {
            case 'pending':
                self::notifyPendingToCustomer($order, $items);
                return true;

            case 'paid-both':
                self::notifyPaid($order, $items);
                return true;

            case 'shipped':
                self::notifyShipped($order, $items);
                return true;

            case 'cancelled':
                self::notifyCancelled($order, $items);
                return true;

            case 'paid-customer':
            case 'paid-owner':
                $shippingMethod = Shipping::methodById($order['shipping_method'] ?? 'interrapidisimo');
                $orderUrl = (env('APP_URL') ?: 'https://suplementosequinosgm.co')
                          . '/checkout/orden/' . $order['order_number'];

                if ($which === 'paid-customer') {
                    $html = Email::render('order-paid-customer', [
                        'order' => $order, 'items' => $items,
                        'shipping_method' => $shippingMethod, 'orderUrl' => $orderUrl,
                    ]);
                    Email::send($order['customer_email'],
                        'Pago confirmado · pedido ' . $order['order_number'],
                        $html, null, Email::adminAddress(),
                        ['kind' => 'order-paid-customer', 'ref_id' => $order['id'], 'ref_type' => 'order']);
                } else {
                    $admin = Email::adminAddress();
                    if (!$admin) return false;
                    $html = Email::render('order-paid-owner', [
                        'order' => $order, 'items' => $items,
                        'shipping_method' => $shippingMethod,
                        'adminUrl' => (env('APP_URL') ?: '') . '/admin/pedidos',
                    ]);
                    Email::send($admin,
                        'Nueva venta ' . money((int)$order['total']) . ' · ' . $order['order_number'],
                        $html, null, $order['customer_email'],
                        ['kind' => 'order-paid-owner', 'ref_id' => $order['id'], 'ref_type' => 'order']);
                }
                return true;
        }
        return false;
    }
}
