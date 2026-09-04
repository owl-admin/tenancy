<?php

namespace OwlAdmin\Tenancy\Models;

use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUser extends BaseModel
{
    protected $table = 'admin_tenant_users';

    protected $guarded = [];

    /**
     * 所属租户。
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * 对应的后台管理员。模型类走配置，方便项目替换 AdminUser。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Admin::adminUserModel(), 'admin_user_id');
    }
}
