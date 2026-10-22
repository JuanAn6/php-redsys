# php-redsys

Librería para cobrar con el **TPV Virtual de Redsys (modo Redirección)** desde PHP o Laravel con el mínimo código posible.

- Firma `HMAC_SHA256_V1` y validación de las respuestas (notificación online y URL OK/KO).
- Estado del pago claro: `Authorized`, `Denied`, `CancelledByUser`, `Refunded`, `Confirmed`, `Cancelled` o `Error`, junto con el código de Redsys traducido.
- Flujos: pago, preautorización, autenticación, Bizum, tokenización (guardar tarjeta / COF) y pago con token.
- Sin dependencias: solo `ext-openssl` y `ext-json`. PHP 8.1 – 8.5. Laravel 10 – 12 con autodescubrimiento.

## Instalación

```bash
composer require juanan6/php-redsys
```

## Uso en PHP

```php
use JuanAn6\Redsys\Redsys;
use JuanAn6\Redsys\Support\OrderNumber;

$redsys = Redsys::fromArray([
    'merchant_code'    => '999008881',                       // FUC
    'terminal'         => '1',
    'secret_key'       => 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', // clave SHA-256 del comercio
    'environment'      => 'test',                            // o 'production'
    'notification_url' => 'https://mitienda.com/redsys/notify',
    'ok_url'           => 'https://mitienda.com/pago/ok',
    'ko_url'           => 'https://mitienda.com/pago/ko',
]);

// 1) Redirigir al cliente al TPV
echo $redsys->createPayment()
    ->amount(12.50)                       // también '12,50' o amountInCents(1250)
    ->order(OrderNumber::fromId($pedido->id))
    ->description('Pedido #' . $pedido->id)
    ->toHtml();                           // página que se envía sola al TPV
```

```php
// 2) notify.php: Redsys hace un POST servidor-a-servidor con el resultado
try {
    $n = $redsys->handleNotification($_POST);
} catch (\JuanAn6\Redsys\Exception\RedsysException $e) {
    http_response_code(400); // firma inválida o petición que no es de Redsys
    exit;
}

$pedido = buscarPedido($n->getOrder());
if ($n->isPaid() && $n->amountEquals($pedido->total)) {
    $pedido->marcarPagado($n->getAuthorisationCode());
} else {
    $pedido->marcarFallido($n->status->value, $n->getResponseMessage());
}
echo 'OK';
```

```php
// 3) URL OK / KO: solo para informar al cliente
$n = $redsys->tryHandleNotification($_GET); // null si no se puede verificar
echo $n?->status->label();                  // "Autorizado", "Cancelado por el cliente"…
```

> ⚠️ **El estado fiable es el de la notificación online.** El cliente puede cerrar el navegador antes de volver a la URL OK. No marques un pedido como pagado solo porque haya llegado a la URL OK.

Si prefieres montar tu propio formulario:

```php
$form = $redsys->createPayment()->amount(10)->form();
$form->action;          // URL del TPV
$form->fields();        // ['Ds_SignatureVersion' => …, 'Ds_MerchantParameters' => …, 'Ds_Signature' => …]
$form->hiddenInputs();  // <input type="hidden"…> ×3
$form->toHtml(false);   // <form> con botón, sin autoenvío
```

## Uso en Laravel

El paquete se registra solo. Publica la configuración (opcional) y rellena el `.env`:

```bash
php artisan vendor:publish --tag=redsys-config
```

```dotenv
REDSYS_MERCHANT_CODE=999008881
REDSYS_TERMINAL=1
REDSYS_KEY=sq7HjrUOBfKmC576ILgskD5srU870gJ7
REDSYS_ENV=test            # production en real
REDSYS_URL_NOTIFICATION="${APP_URL}/redsys/notify"
REDSYS_URL_OK="${APP_URL}/pago/ok"
REDSYS_URL_KO="${APP_URL}/pago/ko"
# REDSYS_CURRENCY=EUR  REDSYS_LANGUAGE=es  REDSYS_MERCHANT_NAME="Mi tienda"
```

```php
use JuanAn6\Redsys\Laravel\Facades\Redsys;   // o el alias global \Redsys
use JuanAn6\Redsys\Exception\RedsysException;
use JuanAn6\Redsys\Support\OrderNumber;

Route::post('/checkout/{order}', function (Order $order) {
    return response(
        Redsys::createPayment()
            ->amount($order->total)
            ->order(OrderNumber::fromId($order->id))
            ->description("Pedido #{$order->id}")
            ->toHtml()
    );
});

Route::post('/redsys/notify', function (Request $request) {
    try {
        $n = Redsys::handleNotification($request->all());
    } catch (RedsysException $e) {
        Log::warning('Redsys: ' . $e->getMessage());
        abort(400);
    }

    $order = Order::findOrFail((int) $n->getOrder());
    if ($n->isPaid() && $n->amountEquals($order->total)) {
        $order->markAsPaid($n->getAuthorisationCode());
    } else {
        $order->markAsFailed($n->getResponseMessage());
    }

    return response('OK');
});

Route::get('/pago/{result}', function (Request $request) {
    $n = Redsys::tryHandleNotification($request->query());
    return view('pago.resultado', ['notification' => $n]);
})->whereIn('result', ['ok', 'ko']);
```

**Excluye la ruta de notificación del CSRF**: Redsys no envía token.

```php
// Laravel 11/12: bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['redsys/*']);
})

// Laravel 10: app/Http/Middleware/VerifyCsrfToken.php
protected $except = ['redsys/*'];
```

También se puede inyectar `JuanAn6\Redsys\Redsys` en controladores en vez de usar la fachada.

## Flujos de pago

```php
$p = $redsys->createPayment()->amount(50);

$p->authorization();               // pago normal (por defecto)
$p->preauthorization();            // retiene el importe; luego se confirma o anula desde el portal de Redsys
$p->authentication();              // tipo 7
$p->bizum();                       // solo Bizum
$p->card();                        // solo tarjeta
$p->payMethods(PayMethod::Card, PayMethod::Bizum);

// Guardar tarjeta: Redsys devuelve el token en la notificación
$p->requestToken();                // o requestToken('R') para COF recurrente
// …en la notificación:
$token = $n->getIdentifier();
$cofId = $n->getCofTxnId();

// Pagar después con el token
$redsys->createPayment()->amount(50)->useToken($token);                      // el cliente confirma en el TPV
$redsys->createPayment()->amount(50)->useToken($token, directPayment: true); // sin pantallas (debe estar activado)

// Otros parámetros
$p->language('en');                // Language::English, 'ca', 'eu_ES'…
$p->currency('USD');               // o Currency::USD, '840'
$p->holder('Ana García');
$p->merchantData(['order_id' => 42]);   // vuelve en $n->getMerchantDataArray()
$p->scaExemption('LWV');
$p->set('DS_MERCHANT_GROUP', '…');      // cualquier otro Ds_Merchant_*
```

> Las **devoluciones**, **confirmaciones de preautorización** y **anulaciones** no pueden hacerse por Redirección: hay que hacerlas desde el portal de Redsys (o por REST, todavía no incluido). Si Redsys notifica esas operaciones, la librería las interpreta correctamente (`Refunded`, `Confirmed`, `Cancelled`).

## Estados

| `Ds_Response` | `$n->status` | `isPaid()` |
|---|---|---|
| `0000`–`0099` | `PaymentStatus::Authorized` | ✅ |
| `0900` + tipo 3 | `PaymentStatus::Refunded` | ❌ |
| `0900` + tipo 2 | `PaymentStatus::Confirmed` | ❌ |
| `0400` | `PaymentStatus::Cancelled` | ❌ |
| `9915` | `PaymentStatus::CancelledByUser` | ❌ |
| cualquier otro | `PaymentStatus::Denied` | ❌ |
| sin código | `PaymentStatus::Error` | ❌ |

`$n->getResponseMessage()` traduce el código: `0101` → "Tarjeta caducada", `0184` → "Error en la autenticación del titular", `0190` → "Denegación del emisor sin especificar motivo"…

### Getters de la notificación

`getOrder()`, `getAmount()`, `getAmountInCents()`, `amountEquals($importe)`, `getCurrency()`, `getTransactionType()`, `getAuthorisationCode()`, `getResponseCode()`, `getResponseMessage()`, `getErrorCode()`, `isSecurePayment()`, `getDateTime()`, `getIdentifier()`, `getExpiryDate()`, `getCofTxnId()`, `getCardBrand()`, `getCardCountry()`, `getCardType()`, `getCardNumber()`, `getProcessedPayMethod()`, `getMerchantData()`, `getMerchantDataArray()`, `get('Ds_Cualquiera')`, `getRaw()` y `toArray()` (útil para logs y para guardar en BD).

## Números de pedido

Redsys exige entre 4 y 12 caracteres alfanuméricos, **los 4 primeros numéricos** y **únicos** por comercio (no se puede reintentar un pago con el mismo número).

```php
OrderNumber::generate();     // "8123aZ9kLm2Q" (aleatorio)
OrderNumber::fromId(42);     // "000000000042"
OrderNumber::isValid('12AB'); // true
```

Si el cliente puede reintentar el pago, genera un número nuevo en cada intento y guárdalo asociado al pedido.

## Entorno de pruebas

- Comercio: `999008881`, terminal `1`, clave `sq7HjrUOBfKmC576ILgskD5srU870gJ7`
- Tarjeta OK: `4548810000000003`, caducidad `12/49`, CVV `123`
- La URL de notificación debe ser pública (en local, usa ngrok o similar).

En `examples/` hay una demo (`pay.php`, `notify.php`, `return.php`):

```bash
php -S localhost:8000 -t .
# http://localhost:8000/examples/pay.php
```

## Tests

```bash
composer test       # PHP por defecto
composer test:all   # PHP 8.1, 8.2, 8.3, 8.4 y 8.5
```
