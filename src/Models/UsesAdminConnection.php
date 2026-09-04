<?php

namespace OwlAdmin\Tenancy\Models;

use Slowlyo\OwlAdmin\Admin;

trait UsesAdminConnection
{
    /**
     * Admin 配了独立连接时跟后台走同一库；未启动则保持 Laravel 默认连接。
     *
     * 不继承 BaseModel：它的构造函数会立刻读 Admin 配置，测试和命令行在容器未就绪时会直接失败。
     */
    public function initializeUsesAdminConnection(): void
    {
        // 模型自己指定了 connection 时不要覆盖
        if ($this->connection) {
            return;
        }

        if (!class_exists(Admin::class)) {
            return;
        }

        try {
            $connection = Admin::config('admin.database.connection');
            if (filled($connection)) {
                $this->setConnection($connection);
            }
        } catch (\Throwable) {
            // Admin 模块未注册时 config 可能抛错，退回默认连接
        }
    }
}
