<?php

// URL OK/KO: el cliente vuelve aquí. Solo informativo, el estado fiable llega a notify.php.
require __DIR__ . '/bootstrap.php';

$notification = $redsys->tryHandleNotification($_GET);
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Resultado del pago</title></head>
<body style="font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto;">
<?php if ($notification === null): ?>
    <h1>No se ha podido verificar la respuesta</h1>
<?php else: ?>
    <h1><?= htmlspecialchars($notification->status->label()) ?></h1>
    <p><?= htmlspecialchars($notification->getResponseMessage()) ?> (<?= htmlspecialchars((string) $notification->getResponseCode()) ?>)</p>
    <pre><?= htmlspecialchars(json_encode($notification->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
<?php endif; ?>
<p><a href="pay.php">Volver</a></p>
</body>
</html>
