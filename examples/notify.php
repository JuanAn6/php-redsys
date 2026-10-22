<?php

// URL de notificación: Redsys hace aquí un POST servidor-a-servidor con el resultado.
require __DIR__ . '/bootstrap.php';

use JuanAn6\Redsys\Exception\RedsysException;

$log = __DIR__ . '/notifications.log';

try {
    $notification = $redsys->handleNotification($_POST);

    // Aquí iría la lógica real: buscar el pedido por getOrder(), comprobar
    // amountEquals($pedido->total) y marcarlo como pagado si isPaid().
    file_put_contents($log, date('c') . ' ' . json_encode($notification->toArray(), JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

    http_response_code(200);
    echo 'OK';
} catch (RedsysException $e) {
    file_put_contents($log, date('c') . ' ERROR ' . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(400);
    echo 'KO';
}
