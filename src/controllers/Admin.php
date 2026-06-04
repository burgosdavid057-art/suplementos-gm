<?php
declare(strict_types=1);

class Admin {
    // ─── Dashboard ───────────────────────────────────────
    public static function dashboard(): void {
        require_admin();
        $pdo = db();

        $stats = [
            'products_total'  => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'products_active' => (int)$pdo->query('SELECT COUNT(*) FROM products WHERE active = 1')->fetchColumn(),
            'categories'      => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
            'low_stock'       => $pdo->query("
                SELECT p.*, c.name AS category_name FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE p.active = 1 AND p.stock < 5
                ORDER BY p.stock ASC LIMIT 6
            ")->fetchAll(),
        ];

        $orders = Order::counts();
        $recent = Order::recent(5);
        $last30 = Order::last30dStats();
        $totalRevenue = Order::totalPaidRevenue();

        render_layout('admin/dashboard', [
            'title'        => 'Dashboard',
            'stats'        => $stats,
            'orders'       => $orders,
            'recent'       => $recent,
            'last30'       => $last30,
            'totalRevenue' => $totalRevenue,
            'alegraCount'  => Order::alegraCount(),
            'lastSync'     => Order::lastSyncAt(),
        ], 'layouts/admin');
    }

    // ─── Productos: listado ─────────────────────────────
    public static function productosIndex(): void {
        require_admin();
        $opts = [
            'q'        => Input::text('q', 80, $_GET),
            'category' => Input::slug('categoria', 80, $_GET),
        ];
        $products = Product::adminList($opts);
        $categories = Category::all();

        render_layout('admin/productos/index', [
            'title'      => 'Productos',
            'products'   => $products,
            'categories' => $categories,
            'opts'       => $opts,
            'alegraCount' => Order::alegraCount(),
            'lastSync'   => Order::lastSyncAt(),
        ], 'layouts/admin');
    }

    // ─── Productos: sync con Alegra (lanzado en background) ─
    // El sync con mirror de imágenes puede tardar varios minutos. Para no
    // colgar el navegador (504 Gateway Timeout), lanzamos el script CLI en
    // background y volvemos al admin de inmediato. El estado se ve en el
    // dashboard ("Última sync: hace X min").
    public static function productosSync(): void {
        require_admin();
        csrf_check();

        if (!Alegra::isConfigured()) {
            flash('err', 'Falta configurar ALEGRA_EMAIL y ALEGRA_API_TOKEN en .env');
            redirect('/admin/productos');
        }

        if (AlegraSync::isRunning()) {
            $started = AlegraSync::lockStartedAt();
            $age = $started ? max(0, time() - $started) : 0;
            flash('ok', "Ya hay un sync corriendo (hace {$age}s). Espera unos minutos y refresca.");
            redirect('/admin/productos');
        }

        $script = realpath(__DIR__ . '/../../scripts/sync-alegra.php');
        $php    = '/opt/plesk/php/8.4/bin/php';
        if (!is_file($php))     $php    = 'php';
        if (!$script) {
            flash('err', 'No se encontró el script sync-alegra.php');
            redirect('/admin/productos');
        }

        // Lanza en background y desconecta stdout/stderr → el browser no espera.
        $cmd = sprintf('nohup %s %s > /dev/null 2>&1 &',
            escapeshellcmd($php), escapeshellarg($script));
        @exec($cmd);

        flash('ok', 'Sincronización iniciada en segundo plano. Esto puede tardar varios minutos. Refresca esta página en un rato para ver los productos actualizados.');
        redirect('/admin/productos');
    }

    // ─── Productos: toggle activo ───────────────────────
    public static function productosToggle(string $id): void {
        require_admin();
        csrf_check();
        if (Product::findById($id)) {
            Product::toggleActive($id);
            flash('ok', 'Estado del producto actualizado.');
        }
        redirect('/admin/productos');
    }

    // ─── Productos: borrar ──────────────────────────────
    public static function productosDelete(string $id): void {
        require_admin();
        csrf_check();
        if (Product::findById($id)) {
            Product::delete($id);
            flash('ok', 'Producto eliminado.');
        }
        redirect('/admin/productos');
    }

    // ─── Productos: form nuevo ──────────────────────────
    public static function productosNuevo(): void {
        require_admin();
        render_layout('admin/productos/form', [
            'title'      => 'Nuevo producto',
            'categories' => Category::all(),
            'product'    => null,
            'errors'     => [],
            'old'        => [],
        ], 'layouts/admin');
    }

    // ─── Productos: crear ───────────────────────────────
    public static function productosCreate(): void {
        require_admin();
        csrf_check();

        $data = self::extractProductData($_POST);
        $errors = self::validateProduct($data);
        if (Product::slugExists($data['slug'])) $errors['slug'] = 'Ya existe un producto con ese slug';

        if ($errors) {
            render_layout('admin/productos/form', [
                'title'      => 'Nuevo producto',
                'categories' => Category::all(),
                'product'    => null,
                'errors'     => $errors,
                'old'        => $data,
            ], 'layouts/admin');
            return;
        }

        $id = Product::create($data);
        flash('ok', 'Producto creado.');
        redirect("/admin/productos/$id");
    }

    // ─── Productos: form editar ─────────────────────────
    public static function productosEdit(string $id): void {
        require_admin();
        $product = Product::findById($id);
        if (!$product) not_found('Producto no existe');

        render_layout('admin/productos/form', [
            'title'      => 'Editar: ' . $product['name'],
            'categories' => Category::all(),
            'product'    => $product,
            'errors'     => [],
            'old'        => [],
        ], 'layouts/admin');
    }

    // ─── Productos: actualizar ──────────────────────────
    public static function productosUpdate(string $id): void {
        require_admin();
        csrf_check();
        $product = Product::findById($id);
        if (!$product) not_found('Producto no existe');

        $data = self::extractProductData($_POST);
        $errors = self::validateProduct($data);
        if (Product::slugExists($data['slug'], $id)) $errors['slug'] = 'Ya existe un producto con ese slug';

        if ($errors) {
            render_layout('admin/productos/form', [
                'title'      => 'Editar: ' . $product['name'],
                'categories' => Category::all(),
                'product'    => $product,
                'errors'     => $errors,
                'old'        => $data,
            ], 'layouts/admin');
            return;
        }

        Product::update($id, $data);
        flash('ok', 'Producto actualizado.');
        redirect("/admin/productos/$id");
    }

    // ─── Categorías: listado + create ───────────────────
    public static function categoriasIndex(): void {
        require_admin();
        render_layout('admin/categorias/index', [
            'title'      => 'Categorías',
            'categories' => Category::withCounts(),
            'errors'     => $_SESSION['_cat_errors'] ?? [],
            'old'        => $_SESSION['_cat_old']    ?? [],
        ], 'layouts/admin');
        unset($_SESSION['_cat_errors'], $_SESSION['_cat_old']);
    }

    public static function categoriasCreate(): void {
        require_admin();
        csrf_check();
        $data = self::extractCategoryData($_POST);
        $errors = self::validateCategory($data);
        if (Category::slugExists($data['slug'])) $errors['slug'] = 'Slug ya usado';

        if ($errors) {
            $_SESSION['_cat_errors'] = $errors;
            $_SESSION['_cat_old'] = $data;
            redirect('/admin/categorias');
        }
        Category::create($data);
        flash('ok', 'Categoría creada.');
        redirect('/admin/categorias');
    }

    public static function categoriasUpdate(string $id): void {
        require_admin();
        csrf_check();
        if (!Category::findById($id)) not_found();

        $data = self::extractCategoryData($_POST);
        $errors = self::validateCategory($data);
        if (Category::slugExists($data['slug'], $id)) $errors['slug'] = 'Slug ya usado';

        if ($errors) {
            flash('err', 'Errores: ' . implode(', ', $errors));
            redirect('/admin/categorias');
        }
        Category::update($id, $data);
        flash('ok', 'Categoría actualizada.');
        redirect('/admin/categorias');
    }

    public static function categoriasDelete(string $id): void {
        require_admin();
        csrf_check();
        if (!Category::findById($id)) not_found();
        if (!Category::delete($id)) {
            flash('err', 'No se puede borrar: la categoría tiene productos asignados.');
        } else {
            flash('ok', 'Categoría eliminada.');
        }
        redirect('/admin/categorias');
    }

    // ─── Pedidos (placeholder) ──────────────────────────
    public static function pedidosIndex(): void {
        require_admin();
        $opts = [
            'status'  => Input::enum('status', ['PENDING','PAID','FAILED','SHIPPED','DELIVERED','CANCELLED'], null, $_GET),
            'q'       => Input::text('q', 80, $_GET),
            'page'    => Input::int('p', 1, 1, 9999, $_GET),
            'perPage' => 20,
        ];
        $result = Order::search($opts);

        render_layout('admin/pedidos/index', [
            'title'   => 'Pedidos',
            'orders'  => Order::counts(),
            'result'  => $result,
            'opts'    => $opts,
        ], 'layouts/admin');
    }

    public static function pedidoShow(string $id): void {
        require_admin();
        $order = Order::findById($id);
        if (!$order) not_found('Pedido no existe');
        $items = Order::items($id);
        $emailLogs = Email::logsForOrder($id);
        $shippingMethod = Shipping::methodById($order['shipping_method'] ?? 'interrapidisimo');

        render_layout('admin/pedidos/show', [
            'title'           => 'Pedido ' . $order['order_number'],
            'order'           => $order,
            'items'           => $items,
            'emailLogs'       => $emailLogs,
            'shippingMethod'  => $shippingMethod,
        ], 'layouts/admin');
    }

    public static function pedidoMarkShipped(string $id): void {
        require_admin();
        csrf_check();
        $order = Order::findById($id);
        if (!$order) not_found('Pedido no existe');

        $carrier  = Input::text('tracking_carrier', 80);
        $tracking = Input::text('tracking_number', 80);

        Order::markShipped($id, $carrier, $tracking);
        // Email al cliente con la info de envío. No-op silencioso si Resend falla.
        try {
            $fresh = Order::findById($id);
            if ($fresh) OrderMailer::notifyShipped($fresh, Order::items($id));
        } catch (Throwable $e) {
            error_log('[pedidoMarkShipped] mailer: ' . $e->getMessage());
        }
        flash('ok', 'Pedido marcado como enviado. Se notificó al cliente.');
        redirect('/admin/pedidos/' . $id);
    }

    public static function pedidoMarkDelivered(string $id): void {
        require_admin();
        csrf_check();
        $order = Order::findById($id);
        if (!$order) not_found('Pedido no existe');
        Order::markDelivered($id);
        flash('ok', 'Pedido marcado como entregado.');
        redirect('/admin/pedidos/' . $id);
    }

    public static function pedidoCancel(string $id): void {
        require_admin();
        csrf_check();
        $order = Order::findById($id);
        if (!$order) not_found('Pedido no existe');
        Order::markCancelled($id);
        // Avisar al cliente
        try {
            $fresh = Order::findById($id);
            if ($fresh) OrderMailer::notifyCancelled($fresh, Order::items($id));
        } catch (Throwable $e) {
            error_log('[pedidoCancel] mailer: ' . $e->getMessage());
        }
        flash('ok', 'Pedido cancelado. Se notificó al cliente.');
        redirect('/admin/pedidos/' . $id);
    }

    public static function pedidoUpdateNotes(string $id): void {
        require_admin();
        csrf_check();
        $order = Order::findById($id);
        if (!$order) not_found('Pedido no existe');
        $notes = Input::textArea('admin_notes', 2000);
        Order::updateAdminNotes($id, $notes);
        flash('ok', 'Notas guardadas.');
        redirect('/admin/pedidos/' . $id);
    }

    // ─── Emails: log + reenviar + test ──────────────────
    public static function emailsIndex(): void {
        require_admin();
        render_layout('admin/emails/index', [
            'title'    => 'Emails enviados',
            'logs'     => Email::recentLogs(100),
            'fromAddr' => Email::fromAddress(),
            'adminAddr' => Email::adminAddress(),
            'configured' => Email::isConfigured(),
        ], 'layouts/admin');
    }

    /** POST /admin/emails/test — manda un email de prueba al admin. */
    public static function emailsTest(): void {
        require_admin();
        csrf_check();
        $to = Email::adminAddress();
        if (!$to) {
            flash('err', 'ADMIN_EMAIL no configurado en .env');
            redirect('/admin/emails');
        }
        $id = Email::send(
            $to,
            'Test Suplementos GM — sistema de emails',
            '<h2 style="font-family:sans-serif;">Test OK</h2>'
            . '<p style="font-family:sans-serif;">Si recibes este correo en <strong>' . e($to) . '</strong>, el sistema de emails está funcionando.</p>'
            . '<p style="font-family:sans-serif;font-size:12px;color:#888;">Enviado desde: ' . e(Email::fromAddress()) . '</p>',
            null, null,
            ['kind' => 'test', 'ref_type' => 'test']
        );
        if ($id) {
            flash('ok', "Test enviado a $to (id Resend: $id). Revisa tu bandeja.");
        } else {
            flash('err', "Falló el envío de prueba. Revisa /admin/emails para ver el error.");
        }
        redirect('/admin/emails');
    }

    /** POST /admin/pedidos/{id}/resend-email — reenvía un email a partir de una orden. */
    public static function pedidoResendEmail(string $id): void {
        require_admin();
        csrf_check();
        $order = Order::findById($id);
        if (!$order) not_found('Pedido no existe');

        $which = Input::enum('which', ['pending', 'paid-customer', 'paid-owner', 'paid-both'], 'pending');
        $ok = OrderMailer::resend($order, $which);
        if ($ok) {
            flash('ok', "Email '$which' reenviado.");
        } else {
            flash('err', 'No se pudo reenviar el email.');
        }
        redirect('/admin/pedidos');
    }

    // ─── Helpers de validación ──────────────────────────
    private static function extractProductData(array $post): array {
        // Imágenes vienen como textarea con una URL por línea. Solo aceptamos
        // URLs http/https en hosts permitidos (Cloudinary + nuestro propio
        // dominio para assets locales).
        $images = [];
        $imagesRaw = is_string($post['images'] ?? null)
            ? (string) $post['images']
            : '';
        $imagesRaw = mb_substr($imagesRaw, 0, 20_000, 'UTF-8'); // tope total
        if ($imagesRaw !== '') {
            $allowedHosts = ['res.cloudinary.com', 'suplementosequinosgm.co'];
            foreach (preg_split('/[\r\n]+/', $imagesRaw) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || mb_strlen($line) > 2048) continue;
                $valid = filter_var($line, FILTER_VALIDATE_URL);
                if ($valid === false) continue;
                $scheme = strtolower((string) parse_url($valid, PHP_URL_SCHEME));
                if (!in_array($scheme, ['http', 'https'], true)) continue;
                $host = strtolower((string) parse_url($valid, PHP_URL_HOST));
                $hostOk = false;
                foreach ($allowedHosts as $h) {
                    if ($host === $h || str_ends_with($host, '.' . $h)) {
                        $hostOk = true;
                        break;
                    }
                }
                if (!$hostOk) continue;
                $images[] = $valid;
                if (count($images) >= 20) break; // máx 20 imágenes
            }
        }

        $name        = Input::text('name', 200, $post)              ?? '';
        $slugRaw     = Input::text('slug', 120, $post)              ?? '';
        $description = Input::textArea('description', 5000, $post)  ?? '';
        $brand       = Input::text('brand', 80, $post)              ?? '';
        // category_id es un ID interno (formato cuid-like) — limit a alfanumérico
        $catId       = Input::text('category_id', 40, $post)        ?? '';
        $catId       = preg_replace('/[^a-zA-Z0-9]/', '', $catId)   ?? '';

        return [
            'name'        => $name,
            'slug'        => $slugRaw !== '' ? slug($slugRaw) : slug($name),
            'description' => $description,
            'price'       => Input::int('price', 0, 0, 999_999_999, $post) ?? 0,
            'stock'       => Input::int('stock', 0, 0, 999_999, $post) ?? 0,
            'images'      => $images,
            'active'        => Input::bool('active', $post),
            'featured'      => Input::bool('featured', $post),
            'images_locked' => Input::bool('images_locked', $post),
            'brand'         => $brand,
            'category_id'   => $catId,
        ];
    }

    private static function validateProduct(array $d): array {
        $e = [];
        if ($d['name'] === '')        $e['name'] = 'Requerido';
        if ($d['slug'] === '')        $e['slug'] = 'Requerido (se genera del nombre)';
        if ($d['description'] === '') $e['description'] = 'Requerida';
        if ($d['price'] < 0)          $e['price'] = 'No puede ser negativo';
        if ($d['stock'] < 0)          $e['stock'] = 'No puede ser negativo';
        if ($d['category_id'] === '') $e['category_id'] = 'Selecciona una categoría';
        return $e;
    }

    private static function extractCategoryData(array $post): array {
        $name      = Input::text('name', 80, $post) ?? '';
        $slugInput = Input::text('slug', 120, $post);
        // Si no dieron slug, generarlo del nombre.
        $slugSrc = $slugInput !== null && $slugInput !== '' ? $slugInput : $name;
        // image_url: solo aceptamos URLs en hosts permitidos
        $imageUrl = Input::urlInHosts('image_url',
            ['res.cloudinary.com', 'suplementosequinosgm.co'],
            2048, $post) ?? '';

        return [
            'name'        => $name,
            'slug'        => slug($slugSrc),
            'description' => Input::textArea('description', 1000, $post) ?? '',
            'image_url'   => $imageUrl,
            'order'       => Input::int('order', 100, 0, 9999, $post) ?? 100,
        ];
    }

    private static function validateCategory(array $d): array {
        $e = [];
        if ($d['name'] === '') $e['name'] = 'Requerido';
        if ($d['slug'] === '') $e['slug'] = 'Slug inválido';
        return $e;
    }

    /**
     * Sanea un ID de URL (route param). Permite alfanumérico + guion.
     * Se llama explícitamente en cada handler que recibe $id como param.
     */
    private static function safeId(string $id, int $maxLen = 40): string {
        $clean = preg_replace('/[^a-zA-Z0-9\-]/', '', $id) ?? '';
        return mb_substr($clean, 0, $maxLen, 'UTF-8');
    }
}
