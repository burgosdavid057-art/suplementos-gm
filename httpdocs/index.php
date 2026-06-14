<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$path   = request_path();
$method = method();

$routes = [
    
    ['GET',  '#^/$#',                           [Home::class,      'index']],
    ['GET',  '#^/productos$#',                  [Productos::class, 'index']],
    ['GET',  '#^/productos/([a-z0-9\-]+)$#',    [Productos::class, 'show']],
    ['GET',  '#^/carrito$#',                    [Carrito::class,   'index']],
    ['GET',  '#^/checkout$#',                   [Checkout::class,  'index']],
    ['POST', '#^/checkout$#',                   [Checkout::class,  'create']],
    ['GET',  '#^/checkout/orden/([A-Z0-9\-]+)$#', [Checkout::class, 'orden']],

    
    ['GET',  '#^/orden$#',                      [Checkout::class,  'track']],
    ['POST', '#^/orden$#',                      [Checkout::class,  'trackLookup']],

    
    ['GET',  '#^/admin/login$#',                [Auth::class,      'loginForm']],
    ['POST', '#^/admin/login$#',                [Auth::class,      'doLogin']],
    ['POST', '#^/admin/logout$#',               [Auth::class,      'doLogout']],
    
    ['GET',  '#^/login$#',                      [Auth::class,      'redirectLogin']],
    ['POST', '#^/logout$#',                     [Auth::class,      'redirectLogout']],

    
    ['GET',  '#^/admin$#',                      [Admin::class, 'dashboard']],

    
    ['GET',  '#^/admin/productos$#',                       [Admin::class, 'productosIndex']],
    ['POST', '#^/admin/productos/sync$#',                  [Admin::class, 'productosSync']],
    ['GET',  '#^/admin/productos/nuevo$#',                 [Admin::class, 'productosNuevo']],
    ['POST', '#^/admin/productos/nuevo$#',                 [Admin::class, 'productosCreate']],
    ['POST', '#^/admin/productos/([a-z0-9]+)/toggle$#',    [Admin::class, 'productosToggle']],
    ['POST', '#^/admin/productos/([a-z0-9]+)/delete$#',    [Admin::class, 'productosDelete']],
    ['GET',  '#^/admin/productos/([a-z0-9]+)$#',           [Admin::class, 'productosEdit']],
    ['POST', '#^/admin/productos/([a-z0-9]+)$#',           [Admin::class, 'productosUpdate']],

    
    ['GET',  '#^/admin/categorias$#',                      [Admin::class, 'categoriasIndex']],
    ['POST', '#^/admin/categorias$#',                      [Admin::class, 'categoriasCreate']],
    ['POST', '#^/admin/categorias/([a-z0-9]+)/delete$#',   [Admin::class, 'categoriasDelete']],
    ['POST', '#^/admin/categorias/([a-z0-9]+)$#',          [Admin::class, 'categoriasUpdate']],

    
    ['GET',  '#^/admin/pedidos$#',                          [Admin::class, 'pedidosIndex']],
    ['GET',  '#^/admin/pedidos/([a-z0-9]+)$#',              [Admin::class, 'pedidoShow']],
    ['POST', '#^/admin/pedidos/([a-z0-9]+)/ship$#',         [Admin::class, 'pedidoMarkShipped']],
    ['POST', '#^/admin/pedidos/([a-z0-9]+)/deliver$#',      [Admin::class, 'pedidoMarkDelivered']],
    ['POST', '#^/admin/pedidos/([a-z0-9]+)/cancel$#',       [Admin::class, 'pedidoCancel']],
    ['POST', '#^/admin/pedidos/([a-z0-9]+)/notes$#',        [Admin::class, 'pedidoUpdateNotes']],
    ['POST', '#^/admin/pedidos/([a-z0-9]+)/resend-email$#', [Admin::class, 'pedidoResendEmail']],

    
    ['GET',  '#^/admin/emails$#',                          [Admin::class, 'emailsIndex']],
    ['POST', '#^/admin/emails/test$#',                     [Admin::class, 'emailsTest']],

    
    ['POST', '#^/api/wompi/webhook$#',          [Webhook::class,   'wompi']],

    
    ['GET',  '#^/api/cron/sync-alegra$#',       [Cron::class,      'syncAlegra']],
    ['POST', '#^/api/cron/sync-alegra$#',       [Cron::class,      'syncAlegra']],
    ['GET',  '#^/api/cron/status$#',            [Cron::class,      'status']],

    
    ['GET',  '#^/legal/([a-z\-]+)$#',           [Legal::class,     'show']],
    
    ['GET',  '#^/terminos$#',                   [Legal::class,     'aliasTerminos']],
    ['GET',  '#^/privacidad$#',                 [Legal::class,     'aliasPrivacidad']],
    ['GET',  '#^/devoluciones$#',               [Legal::class,     'aliasDevoluciones']],
    ['GET',  '#^/cookies$#',                    [Legal::class,     'aliasCookies']],

    
    ['GET',  '#^/sitemap\.xml$#',               [Seo::class,       'sitemap']],
    ['GET',  '#^/robots\.txt$#',                [Seo::class,       'robots']],
];

foreach ($routes as [$m, $regex, $action]) {
    if ($m !== $method) continue;
    if (!preg_match($regex, $path, $matches)) continue;
    [$class, $func] = $action;
    array_shift($matches);
    $class::$func(...$matches);
    exit;
}

not_found('La página que buscas no existe.');
