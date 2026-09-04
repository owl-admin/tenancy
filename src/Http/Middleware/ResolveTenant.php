<?php

namespace OwlAdmin\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OwlAdmin\Tenancy\TenancyManager;

class ResolveTenant
{
    /**
     * 在权限中间件之前把当前租户绑到 TenancyManager。
     *
     * requestBoot 里也会解析一次；这里再跑是为了 Header 覆盖和后续控制器查询都走同一结果。
     */
    public function handle(Request $request, Closure $next)
    {
        $manager = app(TenancyManager::class);

        // 已经在 requestBoot 里解析过就不要 reset，否则会丢掉刚绑上的租户
        if (!$manager->isResolved()) {
            $manager->bindCurrentRequest($request);
        }

        return $next($request);
    }
}
