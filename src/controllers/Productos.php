<?php
declare(strict_types=1);

class Productos {
    public static function index(): void {
        
        
        
        $opts = [
            'category' => Input::slug('categoria', 80, $_GET),
            'brand'    => Input::text('marca', 60, $_GET),
            'q'        => Input::text('q', 80, $_GET),                       
            'min'      => Input::int('min', null, 0, 100_000_000, $_GET),
            'max'      => Input::int('max', null, 0, 100_000_000, $_GET),
            'page'     => Input::int('p', 1, 1, 9999, $_GET),
            'perPage'  => 24,
        ];

        $result   = Product::list($opts);
        $cats     = Category::all();
        $brands   = Product::brands();
        $current  = $opts['category'] ? Category::findBySlug($opts['category']) : null;

        render_layout('productos/index', [
            'title'       => $current ? ($current['name'] . ' — Suplementos GM') : 'Catálogo — Suplementos GM',
            'description' => $current['description'] ?? 'Catálogo de suplementos veterinarios para caballos',
            'result'      => $result,
            'categories'  => $cats,
            'brands'      => $brands,
            'opts'        => $opts,
            'currentCat'  => $current,
        ]);
    }

    public static function show(string $slug): void {
        
        
        if (mb_strlen($slug) > 120) not_found('Producto no disponible.');
        $product = Product::findBySlug($slug);
        if (!$product) not_found('Producto no disponible.');

        
        $related = Product::list([
            'category' => $product['category_slug'],
            'perPage'  => 4,
        ])['items'];
        $related = array_filter($related, fn($p) => $p['id'] !== $product['id']);

        render_layout('productos/show', [
            'title'       => $product['name'] . ' — Suplementos GM',
            'description' => mb_substr(strip_tags($product['description']), 0, 160),
            'product'     => $product,
            'related'     => array_slice($related, 0, 4),
        ]);
    }
}
