<?php
declare(strict_types=1);

class Checkout {
    /**
     * GET /checkout — formulario de envío.
     */
    public static function index(): void {
        render_layout('checkout/index', [
            'title'        => 'Checkout — Suplementos GM',
            'description'  => 'Finaliza tu compra con varias opciones de envío.',
            'methods'      => Shipping::methods(),
            'terminals'    => Shipping::terminals(),
            'csrf'         => csrf_token(),
        ]);
    }

    /**
     * POST /checkout — crea la orden y redirige a la página de pago.
     * Todos los inputs pasan por Input::* (sanitización + tipado tipo-seguro).
     */
    public static function create(): void {
        csrf_check();

        $errors = [];

        // ─── Datos del comprador (facturación electrónica) ────────
        $customerType = Input::enum('customer_type', ['natural', 'juridica'], 'natural');
        $rawDocType   = strtoupper((string) Input::text('customer_doc_type', 8) ?? '');
        $allowedDocTypes = $customerType === 'juridica' ? ['NIT'] : ['CC', 'CE', 'PP', 'TI'];
        $docType = in_array($rawDocType, $allowedDocTypes, true)
            ? $rawDocType
            : ($customerType === 'juridica' ? 'NIT' : 'CC');

        $name      = Input::text('customer_name', 120)            ?? '';
        $email     = Input::email('customer_email')               ?? '';
        $phone     = Input::phone('customer_phone', 20)           ?? '';
        $docDigits = Input::docId('customer_doc_id', 30)          ?? '';
        $notes     = Input::textArea('shipping_notes', 500);
        $methodId  = Input::text('shipping_method', 32)           ?? '';
        $itemsJson = $_POST['items'] ?? '';

        // ─── Dirección de facturación ───────────────────────
        $billingAddress = Input::text('billing_address', 200) ?? '';
        $billingCity    = Input::text('billing_city', 80)     ?? '';
        $billingState   = Input::text('billing_state', 80)    ?? '';

        // ─── Validaciones de longitud y formato ─────────────
        if (mb_strlen($name) < 3) {
            $errors['customer_name'] = $customerType === 'juridica'
                ? 'Ingresa la razón social.'
                : 'Ingresa tu nombre completo.';
        }
        if ($email === '') {
            $errors['customer_email'] = 'Correo inválido.';
        }
        $phoneDigitsOnly = preg_replace('/\D/', '', $phone) ?? '';
        if (mb_strlen($phoneDigitsOnly) < 7) {
            $errors['customer_phone'] = 'El teléfono es obligatorio (mín. 7 dígitos).';
        }
        if (mb_strlen($docDigits) < 5) {
            $errors['customer_doc_id'] = $docType === 'NIT'
                ? 'El NIT es obligatorio para facturación electrónica.'
                : 'El número de documento es obligatorio para facturación electrónica.';
        }
        if (mb_strlen($billingAddress) < 5) $errors['billing_address'] = 'Dirección de facturación incompleta.';
        if ($billingCity === '')            $errors['billing_city']    = 'Ciudad de facturación es obligatoria.';
        if ($billingState === '')           $errors['billing_state']   = 'Departamento de facturación es obligatorio.';

        // Consentimiento legal (Términos + Privacidad + Devoluciones)
        if (!Input::bool('terms_accepted')) {
            $errors['terms_accepted'] = 'Debes aceptar los Términos, Privacidad y Devoluciones para continuar.';
        }

        // ─── Validar método ─────────────────────────────────
        $method = Shipping::methodById($methodId);
        if (!$method) {
            $errors['shipping_method'] = 'Selecciona un método de envío.';
        }

        // ─── ¿Usa la misma dirección que facturación? ───────
        $shipSameAsBilling = Input::bool('shipping_same_as_billing');

        // ─── Campos de envío según el método ────────────────
        $address = '';
        $city    = '';
        $state   = '';
        $terminal = null;

        if ($method && $method['requires_terminal']) {
            // BUS — terminal del Norte o del Sur (enum)
            $terminal = Input::enum('shipping_terminal', array_keys(Shipping::terminals() ?: []));
            $terminalLabel = $terminal ? Shipping::terminalLabel($terminal) : null;
            if (!$terminalLabel) {
                $errors['shipping_terminal'] = 'Selecciona la terminal donde recoges (Norte o Sur).';
            } else {
                $address = $terminalLabel;
                $city    = 'Medellín';
                $state   = 'Antioquia';
            }
        } elseif ($method) {
            if ($shipSameAsBilling) {
                $address = $billingAddress;
                $city    = $billingCity;
                $state   = $method['id'] === 'oriente_cercano' ? 'Antioquia' : $billingState;
            } else {
                $address = Input::text('shipping_address', 200) ?? '';
                $city    = Input::text('shipping_city', 80)     ?? '';
                $state   = $method['id'] === 'oriente_cercano'
                    ? 'Antioquia'
                    : (Input::text('shipping_state', 80) ?? '');

                if (mb_strlen($address) < 5)  $errors['shipping_address'] = 'Dirección de envío incompleta.';
                if ($city === '')             $errors['shipping_city']    = 'Ingresa la ciudad de envío.';
                if ($state === '' && $method['id'] !== 'oriente_cercano') {
                    $errors['shipping_state'] = 'Ingresa el departamento de envío.';
                }
            }
        }

        // ─── Items del carrito (JSON validado) ──────────────
        if (!is_string($itemsJson) || strlen($itemsJson) > 100_000) {
            $errors['items'] = 'Carrito inválido.';
            $rawItems = [];
        } else {
            $rawItems = json_decode($itemsJson, true);
            if (!is_array($rawItems) || empty($rawItems)) {
                $errors['items'] = 'Tu carrito está vacío.';
                $rawItems = [];
            }
        }

        $rerender = function (array $errors, array $old) {
            render_layout('checkout/index', [
                'title'        => 'Checkout — Suplementos GM',
                'description'  => 'Finaliza tu compra.',
                'methods'      => Shipping::methods(),
                'terminals'    => Shipping::terminals(),
                'csrf'         => csrf_token(),
                'errors'       => $errors,
                'old'          => $old,
            ]);
        };

        $oldData = [
            'customerType'       => $customerType,
            'docType'            => $docType,
            'name'               => $name,
            'email'              => $email,
            'phone'              => $phone,
            'docId'              => $docDigits,
            'notes'              => $notes,
            'methodId'           => $methodId,
            'address'            => $address,
            'city'               => $city,
            'state'              => $state,
            'terminal'           => $terminal,
            'billingAddress'     => $billingAddress,
            'billingCity'        => $billingCity,
            'billingState'       => $billingState,
            'shipSameAsBilling'  => $shipSameAsBilling,
        ];

        if ($errors) {
            $rerender($errors, $oldData);
            return;
        }

        // ─── Validar y resolver productos del carrito ───────
        // El precio y el nombre vienen del lado servidor (no del JSON del
        // carrito) para evitar manipulación. Solo aceptamos id + qty del cliente.
        $items = [];
        $subtotal = 0;
        $maxItems = 50; // sanity cap
        foreach (array_slice($rawItems, 0, $maxItems) as $row) {
            if (!is_array($row)) continue;
            $rawId  = $row['id']  ?? '';
            $rawQty = $row['qty'] ?? 1;
            // Sanea id: solo alfanuméricos + guiones, máx 40 chars
            $pid = is_string($rawId) ? preg_replace('/[^a-zA-Z0-9\-]/', '', $rawId) : '';
            $pid = mb_substr((string) $pid, 0, 40);
            if ($pid === '') continue;
            // Sanea qty: entero entre 1 y 999
            $qty = is_numeric($rawQty) ? (int) $rawQty : 1;
            $qty = max(1, min(999, $qty));

            $p = Product::findById($pid);
            if (!$p || !$p['active']) {
                $errors['items'] = 'Uno de los productos del carrito ya no está disponible.';
                break;
            }
            if ($p['stock'] !== null && $p['stock'] < $qty) {
                $errors['items'] = sprintf('Solo hay %d unidades de "%s".', $p['stock'], $p['name']);
                break;
            }
            $line = (int)$p['price'] * $qty;
            $items[] = [
                'product_id'    => $p['id'],
                'product_name'  => $p['name'],
                'product_price' => (int)$p['price'],
                'quantity'      => $qty,
                'subtotal'      => $line,
            ];
            $subtotal += $line;
        }

        if ($errors) {
            $rerender($errors, $oldData);
            return;
        }

        // ─── Calcular envío y total ─────────────────────────
        $shippingCost = Shipping::quote($methodId);
        $total = $subtotal + $shippingCost;

        // ─── Persistir ──────────────────────────────────────
        // En este punto todos los valores ya fueron saneados arriba via Input::*
        $created = Order::create([
            'customer_type'     => $customerType,
            'customer_doc_type' => $docType,
            'customer_doc_id'   => $docDigits,
            'customer_name'     => $name,
            'customer_email'    => $email,        // Input::email() ya lo lowerea
            'customer_phone'    => $phone,
            'billing_name'      => $name,
            'billing_address'   => $billingAddress,
            'billing_city'      => $billingCity,
            'billing_state'     => $billingState,
            'shipping_method'   => $methodId,
            'shipping_terminal' => $terminal,
            'shipping_address'  => $address,
            'shipping_city'     => $city,
            'shipping_state'    => $state,
            'shipping_notes'    => $notes,        // textArea() retorna null si está vacío
            'subtotal'          => $subtotal,
            'shipping_cost'     => $shippingCost,
            'total'             => $total,
        ], $items);

        // Email al cliente con la orden pendiente de pago. Lanza-y-olvida —
        // si Resend falla, no rompemos el checkout.
        try {
            $fullOrder = Order::findByNumber($created['order_number']);
            if ($fullOrder) {
                $orderItems = Order::items($fullOrder['id']);
                OrderMailer::notifyPendingToCustomer($fullOrder, $orderItems);
            }
        } catch (Throwable $e) {
            error_log('[Checkout::create] notify pending failed: ' . $e->getMessage());
        }

        redirect('/checkout/orden/' . $created['order_number']);
    }

    /**
     * GET /orden — formulario para que el cliente entre el número y email
     * de su pedido. Si pasan ?numero=&email= por query, intenta lookup
     * directo (útil para shareable URLs desde el correo).
     */
    public static function track(): void {
        // Sanitizamos: el número solo acepta [A-Z0-9-], el email se valida.
        $rawNumero = Input::text('numero', 40, $_GET);
        $numero    = $rawNumero ? strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $rawNumero) ?? '') : '';
        $email     = Input::email('email', $_GET) ?? '';

        if ($numero !== '' && $email !== '') {
            $hit = self::lookupOrder($numero, $email);
            if ($hit) {
                redirect('/checkout/orden/' . $hit['order_number']);
            }
            render_layout('checkout/track', [
                'title'       => 'Rastrear pedido — Suplementos GM',
                'description' => 'Sigue el estado de tu pedido en Suplementos GM.',
                'error'       => 'No encontramos un pedido con ese número y correo. Verifica los datos.',
                'old'         => ['numero' => $numero, 'email' => $email],
            ]);
            return;
        }

        render_layout('checkout/track', [
            'title'       => 'Rastrear pedido — Suplementos GM',
            'description' => 'Sigue el estado de tu pedido en Suplementos GM.',
            'old'         => ['numero' => $numero, 'email' => $email],
        ]);
    }

    /**
     * POST /orden — recibe número + email, valida y redirige.
     */
    public static function trackLookup(): void {
        csrf_check();
        $rawNumero = Input::text('numero', 40);
        $numero    = $rawNumero ? strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $rawNumero) ?? '') : '';
        $email     = Input::email('email') ?? '';

        $errors = null;
        if ($numero === '' || $email === '') {
            $errors = 'Ingresa el número de pedido y tu correo.';
        }

        if (!$errors) {
            $hit = self::lookupOrder($numero, $email);
            if ($hit) {
                redirect('/checkout/orden/' . $hit['order_number']);
            }
            $errors = 'No encontramos un pedido con esos datos. Verifica el número y el correo, o escríbenos por WhatsApp.';
        }

        render_layout('checkout/track', [
            'title'       => 'Rastrear pedido — Suplementos GM',
            'description' => 'Sigue el estado de tu pedido en Suplementos GM.',
            'error'       => $errors,
            'old'         => ['numero' => $numero, 'email' => $email],
        ]);
    }

    /**
     * Busca un pedido validando que el email coincida (anti-enumeración).
     * Devuelve el row o null.
     */
    private static function lookupOrder(string $numero, string $email): ?array {
        $order = Order::findByNumber(strtoupper($numero));
        if (!$order) return null;
        if (mb_strtolower($order['customer_email']) !== mb_strtolower($email)) return null;
        return $order;
    }

    /**
     * GET /checkout/orden/{numero} — confirmación + opciones de pago.
     * Si Wompi está totalmente configurado, muestra el botón. Si no,
     * muestra fallback a WhatsApp.
     *
     * Si Wompi nos redirige acá con ?id={transactionId} después del pago,
     * sincronizamos contra el API de Wompi por si el webhook todavía no
     * llegó — así el cliente ve el estado correcto de inmediato.
     */
    public static function orden(string $orderNumber): void {
        $order = Order::findByNumber($orderNumber);
        if (!$order) {
            not_found('Pedido no encontrado.');
        }

        // Sync defensivo si venimos de un redirect de Wompi (?id=...)
        // Wompi tx IDs son tipo "01-1234567890-12345" — alfanuméricos + guiones.
        $txIdRaw    = Input::text('id', 64, $_GET);
        $txIdFromUrl = $txIdRaw ? preg_replace('/[^A-Za-z0-9\-]/', '', $txIdRaw) : null;
        if (
            $txIdFromUrl !== null && $txIdFromUrl !== ''
            && $order['status'] === 'PENDING'
            && Wompi::isWebhookReady()
        ) {
            try {
                $tx = Wompi::getTransaction($txIdFromUrl);
                if ($tx && (string)($tx['reference'] ?? '') === $orderNumber) {
                    if (Wompi::applyTransactionToOrder($order, $tx)) {
                        $order = Order::findByNumber($orderNumber); // refrescar
                    }
                }
            } catch (Throwable $e) {
                error_log('Wompi sync on redirect failed: ' . $e->getMessage());
            }
        }

        $items = Order::items($order['id']);

        $wompiReady = Wompi::isCheckoutReady();
        $integritySignature = null;
        if ($wompiReady) {
            $integritySignature = Wompi::integritySignature(
                $order['order_number'],
                (int)$order['total'] * 100,
            );
        }

        $shippingMethod  = Shipping::methodById($order['shipping_method'] ?? 'interrapidisimo');
        $shippingEtaText = Shipping::etaText(
            $order['shipping_method'] ?? 'interrapidisimo',
            $order['paid_at'] ?? $order['created_at'] ?? null,
        );

        render_layout('checkout/orden', [
            'title'              => 'Pedido ' . $order['order_number'] . ' — Suplementos GM',
            'description'        => 'Confirmación y pago de tu pedido.',
            'order'              => $order,
            'items'              => $items,
            'shipping_method'    => $shippingMethod,
            'shipping_eta_text'  => $shippingEtaText,
            'wompi_configured'   => $wompiReady,
            'wompi_public_key'   => env('WOMPI_PUBLIC_KEY', ''),
            'integrity_signature'=> $integritySignature,
            'redirect_url'       => env('APP_URL') . '/checkout/orden/' . $order['order_number'],
        ]);
    }
}
