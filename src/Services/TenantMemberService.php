<?php

namespace OwlAdmin\Tenancy\Services;

use Slowlyo\OwlAdmin\Admin;
use OwlAdmin\Tenancy\Models\TenantUser;
use Slowlyo\OwlAdmin\Services\AdminService;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method TenantUser getModel()
 * @method TenantUser|Builder query()
 */
class TenantMemberService extends AdminService
{
    protected string $modelName = TenantUser::class;

    /**
     * 列表带上租户名和用户名，非超管只看自己能进的租户。
     */
    public function listQuery()
    {
        $query = parent::listQuery()->with(['tenant:id,name,slug', 'user:id,username,name']);

        $user = Admin::user();
        if ($user && !$user->isAdministrator()) {
            $query->whereIn('tenant_id', tenancy()->userTenantIds($user));
        }

        if ($this->request->filled('tenant_id')) {
            $query->where('tenant_id', $this->request->input('tenant_id'));
        }

        if ($this->request->filled('admin_user_id')) {
            $query->where('admin_user_id', $this->request->input('admin_user_id'));
        }

        return $query;
    }

    /**
     * 详情/编辑也加载关联，表单才能回显租户和用户。
     */
    public function addRelations($query, string $scene = 'list')
    {
        $query->with(['tenant:id,name,slug', 'user:id,username,name']);
    }

    /**
     * 同一用户不能重复加入同一租户。
     */
    public function store($data)
    {
        $this->assertUnique($data);

        return parent::store($data);
    }

    /**
     * 改绑租户或用户时同样检查唯一。
     */
    public function update($primaryKey, $data)
    {
        $this->assertUnique($data, $primaryKey);

        return parent::update($primaryKey, $data);
    }

    /**
     * 中间表没有 name 这类搜索列，这里只保留精确筛选。
     */
    public function searchable($query)
    {
        // parent 会对所有 query 参数做 like，id 类字段不适合 like
    }

    /**
     * 阻止重复成员行，否则唯一索引会抛 SQL 异常。
     */
    protected function assertUnique(array $data, mixed $exceptId = null): void
    {
        $tenantId = $data['tenant_id'] ?? null;
        $userId = $data['admin_user_id'] ?? null;

        admin_abort_if(!$tenantId || !$userId, '请选择租户和管理员');

        $exists = $this->query()
            ->where('tenant_id', $tenantId)
            ->where('admin_user_id', $userId)
            ->when($exceptId, fn ($query) => $query->where('id', '<>', $exceptId))
            ->exists();

        admin_abort_if($exists, '该管理员已加入此租户');
    }
}
