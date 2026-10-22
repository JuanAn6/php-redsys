<?php

/*
 * Configuración compartida de los ejemplos (comercio público de pruebas de Redsys).
 * Abre http://localhost/php-redsys/examples/pay.php con XAMPP arrancado.
 */

require __DIR__ . '/../vendor/autoload.php';

use JuanAn6\Redsys\Redsys;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

// Redsys no puede llamar a localhost: para recibir la notificación expón el puerto con ngrok
// y pon la URL pública aquí (p. ej. https://xxxx.ngrok-free.app/php-redsys/examples).
$publicBase = getenv('REDSYS_PUBLIC_URL') ?: $base;

$redsys = Redsys::fromArray([
    'merchant_code' => '999008881',
    'terminal' => '1',
    'secret_key' => 'sq7HjrUOBfKmC576ILgskD5srU870gJ7',
    'environment' => 'test',
    'merchant_name' => 'php-redsys demo',
    'notification_url' => $publicBase . '/notify.php',
    'ok_url' => $base . '/return.php',
    'ko_url' => $base . '/return.php',
]);
