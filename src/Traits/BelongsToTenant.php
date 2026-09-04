<?php

namespace OwlAdmin\Tenancy\Traits;

use OwlAdmin\Tenancy\Models\Tenant;
use OwlAdmin\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * 注册全局作用域，并在创建时写入当前租户列。
     *
     * 未绑定租户时不自动填列，避免把 null 写成 0；强制隔离由 TenantScope 负责挡住查询。
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            $column = tenancy()->column();

            // 调用方已手动指定租户时不再覆盖
            if (filled($model->getAttribute($column))) {
                return;
            }

            if (tenancy()->isBypassed()) {
                return;
            }

            $tenantId = tenancy()->id();

            if ($tenantId) {
                $model->setAttribute($column, $tenantId);
            }
        });
    }

    /**
     * 当前行所属租户。列名走扩展设置，默认 tenant_id。
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, tenancy()->column());
    }

    /**
     * 关闭租户作用域，用于跨租户统计或维修脚本。
     */
    public function scopeAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * 临时切到指定租户再查，不依赖当前请求绑定。
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->qualifyColumn(tenancy()->column()), $tenantId);
    }
}
