<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \JuanAn6\Redsys\Request\PaymentRequest createPayment()
 * @method static \JuanAn6\Redsys\Response\Notification handleNotification(?array $data = null)
 * @method static \JuanAn6\Redsys\Response\Notification handleReturn(?array $data = null)
 * @method static \JuanAn6\Redsys\Response\Notification|null tryHandleNotification(?array $data = null)
 * @method static \JuanAn6\Redsys\Config getConfig()
 *
 * @see \JuanAn6\Redsys\Redsys
 */
class Redsys extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \JuanAn6\Redsys\Redsys::class;
    }
}
