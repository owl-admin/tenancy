<?php

namespace OwlAdmin\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class TenantScope implements Scope
{
    /**
     * 按当前绑定的租户过滤查询；未绑定且强制隔离时故意查不到数据。
     *
     * 超级管理员旁路或尚未解析租户（非强制）时不加条件，避免挡住系统表操作。
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = tenancy();

        // 旁路模式下不过滤，超级管理员需要看全部数据
        if ($tenancy->isBypassed()) {
            return;
        }

        $column = $model->qualifyColumn($tenancy->column());
        $tenantId = $tenancy->id();

        // 已绑定租户：只返回该租户的行
        if ($tenantId) {
            $builder->where($column, $tenantId);

            return;
        }

        // 强制隔离且当前没有租户时，用恒假条件挡住误查全表
        if ($tenancy->forced()) {
            $builder->whereRaw('0 = 1');
        }
    }

    /**
     * 给查询宏加 withoutTenant，方便一次性任务显式关掉作用域。
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
