<?php

namespace OwlAdmin\Tenancy\Services;

use Slowlyo\OwlAdmin\Admin;
use OwlAdmin\Tenancy\Models\Tenant;
use Slowlyo\OwlAdmin\Services\AdminService;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method Tenant getModel()
 * @method Tenant|Builder query()
 */
class TenantService extends AdminService
{
    protected string $modelName = Tenant::class;

    /**
     * 非超管只能管理自己加入的租户，避免越权改别人的租户。
     */
    public function listQuery()
    {
        $query = parent::listQuery();

        $user = Admin::user();
        if ($user && !$user->isAdministrator()) {
            $query->whereIn('id', tenancy()->userTenantIds($user));
        }

        if ($this->request->filled('enabled')) {
            $query->where('enabled', $this->request->input('enabled'));
        }

        return $query;
    }

    /**
     * 不用父类的全字段 like，enabled 是布尔值，like 会把筛选打乱。
     */
    public function searchable($query)
    {
        $query->when(
            filled($this->request->input('name')),
            fn ($q) => $q->where('name', 'like', '%' . $this->request->input('name') . '%')
        );
        $query->when(
            filled($this->request->input('slug')),
            fn ($q) => $q->where('slug', 'like', '%' . $this->request->input('slug') . '%')
        );
    }

    /**
     * 新增前检查 slug 唯一，空 slug 直接拒绝。
     */
    public function store($data)
    {
        $this->assertSlugUnique($data['slug'] ?? '');

        return parent::store($data);
    }

    /**
     * 更新时排除自身再查 slug，避免自己和自己冲突。
     */
    public function update($primaryKey, $data)
    {
        $this->assertSlugUnique($data['slug'] ?? '', $primaryKey);

        return parent::update($primaryKey, $data);
    }

    /**
     * slug 必须填写且全局唯一（含软删，unique 索引也覆盖软删行）。
     */
    protected function assertSlugUnique(string $slug, mixed $exceptId = null): void
    {
        admin_abort_if($slug === '', '标识不能为空');

        $exists = $this->query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($exceptId, fn ($query) => $query->where('id', '<>', $exceptId))
            ->exists();

        admin_abort_if($exists, '标识已被占用');
    }
}
