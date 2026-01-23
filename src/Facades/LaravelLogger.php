<?php

namespace Ermetix\LaravelLogger\Facades;

use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\CronLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Illuminate\Support\Facades\Facade;

/**
 * Type-safe logging facade.
 * 
 * All methods require the corresponding LogObject type, ensuring compile-time type safety.
 * 
 * @method static void general(GeneralLogObject $object, bool $defer = true) Log a general application event. Parameter is required. Default is deferred.
 * @method static void api(ApiLogObject $object, bool $defer = true) Log an API request/response event. Parameter is required. Default is deferred.
 * @method static void cron(CronLogObject $object, bool $defer = true) Log a cron job or scheduled task event. Parameter is required. Default is deferred.
 * @method static void integration(IntegrationLogObject $object, bool $defer = true) Log an external integration call event. Parameter is required. Default is deferred.
 * @method static void orm(OrmLogObject $object, bool $defer = true) Log an ORM/database operation event. Parameter is required. Default is deferred.
 * @method static void error(ErrorLogObject $object, bool $defer = true) Log an error/exception event. Parameter is required. Default is deferred.
 *
 * @see \Ermetix\LaravelLogger\Support\Logging\TypedLogger
 */
class LaravelLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel_logger';
    }
}
