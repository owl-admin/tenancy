<?php

namespace OwlAdmin\Tenancy\Facades;

use OwlAdmin\Tenancy\Models\Tenant;
use OwlAdmin\Tenancy\TenancyManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static int|null id()
 * @method static Tenant|null tenant()
 * @method static TenancyManager setTenant(Tenant|int|null $tenant, bool $persist = true)
 * @method static TenancyManager clear(bool $persist = true)
 * @method static TenancyManager bypass(bool $bypass = true)
 * @method static bool isBypassed()
 * @method static string column()
 * @method static bool forced()
 *
 * @see TenancyManager
 */
class Tenancy extends Facade
{
    /**
     * 指向请求级 TenancyManager，保证 Facade 与 tenancy() 助手拿到同一实例。
     */
    protected static function getFacadeAccessor(): string
    {
        return TenancyManager::class;
    }
}
