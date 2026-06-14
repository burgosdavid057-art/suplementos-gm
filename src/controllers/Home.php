<?php
declare(strict_types=1);

class Home {
    public static function index(): void {
        $featured   = Product::featured(4);
        $categories = Category::withCounts();
        $flagship   = Product::findBySlug('body-builder-5000');

        
        if ($flagship) {
            $featured = array_values(array_filter($featured, fn($p) => $p['id'] !== $flagship['id']));
        }

        render_layout('home/index', [
            'title'       => 'Suplementos GM — Suplementos veterinarios para caballos',
            'description' => 'Tienda online de suplementos e insumos veterinarios. Envíos a todo Colombia.',
            'featured'    => $featured,
            'categories'  => $categories,
            'flagship'    => $flagship,
        ]);
    }
}
