<?php
declare(strict_types=1);

/**
 * Páginas legales requeridas por la SIC para e-commerce en Colombia.
 *
 *   - Ley 1480 de 2011  — Estatuto del Consumidor
 *   - Ley 1581 de 2012  — Habeas Data / Protección de Datos Personales
 *   - Decreto 1377/2013 — Reglamentación Ley 1581
 *   - Circular Externa 002/2015 SIC — Comercio electrónico
 *
 * Una sola ruta `/legal/{slug}` con allowlist evita listar páginas inexistentes
 * o exponer rutas dinámicas. Los aliases tradicionales `/terminos`,
 * `/privacidad`, `/devoluciones`, `/cookies` redirigen acá.
 */
class Legal {
    private const PAGES = [
        'terminos'     => [
            'view'        => 'legal/terminos',
            'title'       => 'Términos y Condiciones',
            'description' => 'Condiciones de uso de Suplementos GM. Compra, envío, garantías y reversión de pago.',
        ],
        'privacidad'   => [
            'view'        => 'legal/privacidad',
            'title'       => 'Política de Privacidad y Tratamiento de Datos',
            'description' => 'Cómo recolectamos, usamos y protegemos tus datos personales. Ley 1581 de 2012.',
        ],
        'devoluciones' => [
            'view'        => 'legal/devoluciones',
            'title'       => 'Política de Devoluciones y Reversión de Pago',
            'description' => 'Derecho de retracto, reversión de pago y garantías. Ley 1480 de 2011.',
        ],
        'cookies'      => [
            'view'        => 'legal/cookies',
            'title'       => 'Aviso de Cookies',
            'description' => 'Información sobre las cookies y tecnologías similares que usamos.',
        ],
    ];

    public static function show(string $slug): void {
        $slug = preg_replace('/[^a-z\-]/', '', $slug) ?? '';
        if (!isset(self::PAGES[$slug])) {
            not_found('Página legal no encontrada.');
        }
        $page = self::PAGES[$slug];
        render_layout($page['view'], [
            'title'       => $page['title'] . ' — Suplementos GM',
            'description' => $page['description'],
            'pageTitle'   => $page['title'],
            'lastUpdated' => '10 de mayo de 2026',
            'legal'       => self::legalEntity(),
            'pages'       => self::PAGES,
            'currentSlug' => $slug,
        ]);
    }

    // ─── Aliases (redirigen al canónico /legal/{slug}) ──
    public static function aliasTerminos(): void     { redirect('/legal/terminos',     301); }
    public static function aliasPrivacidad(): void   { redirect('/legal/privacidad',   301); }
    public static function aliasDevoluciones(): void { redirect('/legal/devoluciones', 301); }
    public static function aliasCookies(): void      { redirect('/legal/cookies',      301); }

    /** Datos del responsable del tratamiento (ajustar cuando la dueña los confirme). */
    public static function legalEntity(): array {
        return [
            'name'       => 'Suplementos GM',
            'doc_type'   => 'NIT',
            'doc_id'     => '[PENDIENTE — agregar NIT en src/controllers/Legal.php]',
            'address'    => 'Rionegro, Antioquia, Colombia',
            'email'      => 'pedidos@suplementosequinosgm.co',
            'admin_email'=> 'suplementosgm2@gmail.com',
            'phone'      => '+57 310 504 3520',
            'whatsapp'   => 'https://wa.me/573105043520',
            'domain'     => 'suplementosequinosgm.co',
        ];
    }
}
