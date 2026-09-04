<?php

namespace OwlAdmin\Tenancy\Models;

use Slowlyo\OwlAdmin\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Slowlyo\OwlAdmin\Traits\DatetimeFormatterTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tenant extends Model
{
    use SoftDeletes;
    use UsesAdminConnection;
    use DatetimeFormatterTrait;

    protected $table = 'tenants';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * 只查启用中的租户，切换器和作用域都走这条，避免绑到停用租户。
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * 租户下的管理员成员，含可选的租户内角色。
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            Admin::adminUserModel(),
            'admin_tenant_users',
            'tenant_id',
            'admin_user_id'
        )->withPivot(['id', 'role'])->withTimestamps();
    }

    /**
     * 成员中间表记录，成员分配页直接 CRUD 这一层。
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'tenant_id');
    }

    /**
     * 删除租户时先拆掉成员，避免留下指向已删租户的中间表行。
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Tenant $model) {
            // 用中间表直接删，避免 Admin::adminUserModel() 在测试里不可用
            $model->memberships()->delete();
        });
    }
}
