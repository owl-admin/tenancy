<?php

use OwlAdmin\Tenancy\TenancyManager;

if (!function_exists('tenancy')) {
    /**
     * 取出当前请求绑定的租户管理器。
     *
     * 扩展未注册进容器时现场 new 一份，避免业务模型在迁移/命令行场景直接报错。
     */
    function tenancy(): TenancyManager
    {
        if (app()->bound(TenancyManager::class)) {
            return app(TenancyManager::class);
        }

        return app()->instance(TenancyManager::class, new TenancyManager());
    }
}
