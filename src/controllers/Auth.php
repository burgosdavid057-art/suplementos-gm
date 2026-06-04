<?php
declare(strict_types=1);

class Auth {
    public static function loginForm(): void {
        if (admin_user()) redirect('/admin');
        render('auth/login', [
            'title' => 'Iniciar sesión — Admin Suplementos GM',
        ]);
    }

    public static function doLogin(): void {
        csrf_check();
        // Email saneado y validado por FILTER_VALIDATE_EMAIL.
        $email = Input::email('email');
        // Password: raw (queremos el literal) pero con tope de longitud para
        // prevenir DoS por bcrypt en strings gigantes.
        $rawPass = $_POST['password'] ?? '';
        $password = is_string($rawPass) ? substr($rawPass, 0, 200) : '';

        if (!$email || $password === '') {
            flash('err', 'Completa ambos campos.');
            redirect('/admin/login');
        }
        if (!attempt_login($email, $password)) {
            flash('err', 'Email o contraseña incorrectos.');
            redirect('/admin/login');
        }

        // Open redirect protection: solo permitir paths internos.
        $redirect = $_SESSION['login_redirect'] ?? '/admin';
        if (!is_string($redirect) || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/admin';
        }
        unset($_SESSION['login_redirect']);
        redirect($redirect);
    }

    public static function doLogout(): void {
        csrf_check();
        logout();
        redirect('/');
    }

    /** Compat: /login → /admin/login (301). */
    public static function redirectLogin(): void {
        redirect('/admin/login', 301);
    }

    /** Compat: POST /logout → POST /admin/logout (307 preserva el método). */
    public static function redirectLogout(): void {
        redirect('/admin/logout', 307);
    }
}
