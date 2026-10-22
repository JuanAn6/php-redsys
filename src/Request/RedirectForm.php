<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Request;

/** Datos listos para enviar al TPV por POST: acción y los tres campos firmados. */
final class RedirectForm
{
    /** @param array<string, string> $parameters parámetros sin codificar, útiles para logs */
    public function __construct(
        public readonly string $action,
        public readonly string $signatureVersion,
        public readonly string $merchantParameters,
        public readonly string $signature,
        public readonly array $parameters,
    ) {
    }

    /** @return array{Ds_SignatureVersion: string, Ds_MerchantParameters: string, Ds_Signature: string} */
    public function fields(): array
    {
        return [
            'Ds_SignatureVersion' => $this->signatureVersion,
            'Ds_MerchantParameters' => $this->merchantParameters,
            'Ds_Signature' => $this->signature,
        ];
    }

    /** @return array{action: string, fields: array<string, string>} */
    public function toArray(): array
    {
        return ['action' => $this->action, 'fields' => $this->fields()];
    }

    /** Campos ocultos para incrustar en tu propio <form>. */
    public function hiddenInputs(): string
    {
        $html = '';
        foreach ($this->fields() as $name => $value) {
            $html .= sprintf('<input type="hidden" name="%s" value="%s">', self::e($name), self::e($value)) . "\n";
        }

        return $html;
    }

    /**
     * Formulario HTML. Con $autoSubmit = true devuelve una página completa que redirige al TPV sola
     * (y muestra un botón si el navegador no ejecuta JavaScript).
     */
    public function toHtml(bool $autoSubmit = true, string $buttonText = 'Ir a pagar', string $formId = 'redsys-form'): string
    {
        $form = sprintf(
            "<form id=\"%s\" action=\"%s\" method=\"POST\">\n%s<button type=\"submit\">%s</button>\n</form>",
            self::e($formId),
            self::e($this->action),
            $this->hiddenInputs(),
            self::e($buttonText),
        );

        if (!$autoSubmit) {
            return $form;
        }

        $id = json_encode($formId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Redirigiendo al pago…</title></head>
<body>
<p>Redirigiendo a la pasarela de pago…</p>
{$form}
<script>document.getElementById({$id}).submit();</script>
</body>
</html>
HTML;
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
