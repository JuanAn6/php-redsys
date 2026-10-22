<?php

require __DIR__ . '/bootstrap.php';

use JuanAn6\Redsys\Support\OrderNumber;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment = $redsys->createPayment()
        ->amount($_POST['amount'] ?? '1')
        ->order(OrderNumber::generate())
        ->description('Pedido de prueba')
        ->merchantData(['demo' => true]);

    match ($_POST['flow'] ?? 'payment') {
        'preauth' => $payment->preauthorization(),
        'token' => $payment->requestToken(),
        'bizum' => $payment->bizum(),
        default => $payment,
    };

    echo $payment->toHtml(); // página que redirige sola al TPV
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>php-redsys · demo</title></head>
<body style="font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto;">
<h1>Pago de prueba con Redsys</h1>
<form method="POST">
    <p><label>Importe (€) <input name="amount" value="12,50"></label></p>
    <p>
        <label><input type="radio" name="flow" value="payment" checked> Pago normal</label><br>
        <label><input type="radio" name="flow" value="preauth"> Preautorización</label><br>
        <label><input type="radio" name="flow" value="token"> Pago + guardar tarjeta (token)</label><br>
        <label><input type="radio" name="flow" value="bizum"> Bizum</label>
    </p>
    <button>Pagar</button>
</form>
<p><small>Tarjeta de prueba: 4548810000000003 · caducidad 12/49 · CVV 123</small></p>
</body>
</html>
