<?php
declare(strict_types=1);

class Carrito {
    public static function index(): void {
        // El carrito es 100% cliente — esta vista lo renderiza con JS.
        render_layout('carrito/index', [
            'title'       => 'Carrito — Suplementos GM',
            'description' => 'Revisa tu pedido antes de pagar.',
        ]);
    }
}
