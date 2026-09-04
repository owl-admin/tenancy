<?php

namespace OwlAdmin\Tenancy;

use Slowlyo\OwlAdmin\Admin;
use Illuminate\Http\Request;
use OwlAdmin\Tenancy\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use OwlAdmin\Tenancy\Models\TenantUser;

class TenancyManager
{
    public const HEADER = 'X-Tenant-Id';

    public const SESSION_KEY = 'owl_admin_tenancy.current_id';

    public const CACHE_PREFIX = 'owl_admin_tenancy.current.';

    protected ?Tenant $tenant = null;

    protected bool $bypassed = false;

    protected bool $resolved = false;

    /**
     * 清掉请求级状态，避免 Octane / 单例把上一请求的租户带过来。
     */
    public function reset(): static
    {
        $this->tenant = null;
        $this->bypassed = false;
        $this->resolved = false;

        return $this;
    }

    /**
     * 按请求解析当前租户：Header > 已保存选择 > 单租户自动绑定 > 超管旁路。
     *
     * Header 只作用于本次请求，不覆盖用户上次在后台选中的租户。
     */
    public function bindCurrentRequest(?Request $request = null): static
    {
        $this->reset();
        $this->resolved = true;

        $request ??= request();
        $user = $this->user();

        // 未登录不绑定，登录页和公开接口保持原样
        if (!$user) {
            return $this;
        }

        // 扩展已启用但 migration 还没跑时，不能让整站 500
        if (!$this->tenantsTableReady()) {
            return $this;
        }

        $headerId = $request?->header(self::HEADER);
        if (filled($headerId) && $this->userCanAccess((int) $headerId, $user)) {
            $this->setTenant((int) $headerId, persist: false);

            return $this;
        }

        $storedId = $this->storedTenantId($user);
        if ($storedId && $this->userCanAccess($storedId, $user)) {
            $this->setTenant($storedId, persist: false);

            return $this;
        }

        // 已保存的租户失效（停用、移出成员）时丢掉，避免一直绑着脏 id
        if ($storedId) {
            $this->forgetStored($user);
        }

        $ids = $this->userTenantIds($user);
        if ($ids->count() === 1) {
            $this->setTenant((int) $ids->first(), persist: true);

            return $this;
        }

        if ($this->shouldBypass($user)) {
            $this->bypassed = true;
        }

        return $this;
    }

    /**
     * 当前绑定的租户主键，未绑定返回 null。
     */
    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    /**
     * 当前绑定的租户模型。
     */
    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * 绑定租户。persist 时写入 cache/session，供后续请求恢复。
     *
     * 后台没有 StartSession 时 session 可能写不进去，所以 cache 才是主存储。
     */
    public function setTenant(Tenant|int|null $tenant, bool $persist = true): static
    {
        $model = $this->resolveTenantModel($tenant);

        $this->tenant = $model;
        $this->bypassed = false;
        $this->resolved = true;

        if ($persist) {
            $this->persist($model?->getKey());
        }

        return $this;
    }

    /**
     * 清掉当前租户。persist 时同时清掉已保存选择。
     */
    public function clear(bool $persist = true): static
    {
        $this->tenant = null;
        $this->resolved = true;

        $user = $this->user();
        if ($persist && $user) {
            $this->forgetStored($user);
        }

        if ($user && $this->shouldBypass($user)) {
            $this->bypassed = true;
        }

        return $this;
    }

    /**
     * 手动打开/关闭超级管理员旁路。打开时会清掉当前绑定。
     */
    public function bypass(bool $bypass = true): static
    {
        $this->bypassed = $bypass;
        $this->resolved = true;

        if ($bypass) {
            $this->tenant = null;
        }

        return $this;
    }

    /**
     * 当前是否处于旁路（不加 tenant_id 条件）。
     */
    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * 业务表上的租户列名，默认 tenant_id，可在扩展设置里改。
     */
    public function column(): string
    {
        $column = (string) $this->setting('column', 'tenant_id');

        return $column !== '' ? $column : 'tenant_id';
    }

    /**
     * 是否强制隔离：未绑定租户时作用域返回空结果。
     */
    public function forced(): bool
    {
        return $this->toBool($this->setting('forced', false));
    }

    /**
     * 超级管理员在未选择租户时是否跳过隔离。
     */
    public function superAdminBypass(): bool
    {
        return $this->toBool($this->setting('super_admin_bypass', true));
    }

    /**
     * 当前请求是否已经解析过租户。
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * 当前登录用户是否必须先选租户（多租户、未绑定、且不能旁路）。
     */
    public function needsSelection(): bool
    {
        $user = $this->user();

        if (!$user || $this->id() || $this->isBypassed()) {
            return false;
        }

        return $this->userTenantIds($user)->count() > 1;
    }

    /**
     * 用户可切换的租户列表。超管看全部启用租户，其他人只看自己加入的。
     */
    public function availableTenants($user = null): Collection
    {
        $user ??= $this->user();

        if (!$user || !$this->tenantsTableReady()) {
            return collect();
        }

        $query = Tenant::query()->enabled()->orderBy('id');

        if (!$this->isAdministrator($user)) {
            $query->whereIn('id', $this->userTenantIds($user));
        }

        return $query->get();
    }

    /**
     * 用户所属启用租户的 id 列表。超管不走成员表，避免漏掉未分配的租户。
     */
    public function userTenantIds($user = null): Collection
    {
        $user ??= $this->user();

        if (!$user) {
            return collect();
        }

        if ($this->isAdministrator($user)) {
            return Tenant::query()->enabled()->pluck('id');
        }

        if (!$this->membershipTableReady()) {
            return collect();
        }

        return TenantUser::query()
            ->where('admin_user_id', $user->getKey())
            ->whereIn('tenant_id', Tenant::query()->enabled()->select('id'))
            ->pluck('tenant_id');
    }

    /**
     * 用户能否进入指定租户：必须存在、启用，且超管或在成员表里。
     */
    public function userCanAccess(int $tenantId, $user = null): bool
    {
        $user ??= $this->user();

        if (!$user || $tenantId <= 0) {
            return false;
        }

        $tenant = Tenant::query()->enabled()->whereKey($tenantId)->first();

        if (!$tenant) {
            return false;
        }

        if ($this->isAdministrator($user)) {
            return true;
        }

        if (!$this->membershipTableReady()) {
            return false;
        }

        return TenantUser::query()
            ->where('admin_user_id', $user->getKey())
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * 按域名/子域名解析租户。v1 只做共享库 + tenant_id，这里留空钩子。
     */
    public function resolveByDomain(?string $host = null): ?Tenant
    {
        // TODO: 独立数据库 / 域名路由尚未实现
        return null;
    }

    /**
     * tenants 表是否已经建好，避免启用后、迁移前的请求打到不存在的表。
     */
    public function tenantsTableReady(): bool
    {
        try {
            return Schema::hasTable('tenants');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 成员表是否可用。缺表时成员查询返回空，而不是抛 SQL 异常。
     */
    public function membershipTableReady(): bool
    {
        try {
            return Schema::hasTable('admin_tenant_users');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 取出后台当前用户，未登录返回 null。
     */
    public function user()
    {
        if (class_exists(Admin::class)) {
            return Admin::user();
        }

        return Auth::guard('admin')->user();
    }

    /**
     * 扩展设置项。扩展未启用时退回默认值。
     */
    protected function setting(string $key, mixed $default = null): mixed
    {
        if (!class_exists(TenancyServiceProvider::class)) {
            return $default;
        }

        $value = TenancyServiceProvider::setting($key, $default);

        return $value ?? $default;
    }

    /**
     * 设置表单里的开关可能是 bool / 1 / "1" / "true"。
     */
    protected function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 超管且开启旁路设置时，才允许不加 tenant_id 条件。
     */
    protected function shouldBypass($user): bool
    {
        return $this->superAdminBypass() && $this->isAdministrator($user);
    }

    /**
     * 兼容 AdminUser::isAdministrator，避免测试桩没有这个方法时致命错误。
     */
    protected function isAdministrator($user): bool
    {
        return is_object($user) && method_exists($user, 'isAdministrator') && $user->isAdministrator();
    }

    /**
     * 把传入值收成 Tenant 模型，停用或已删除的一律视为无效。
     */
    protected function resolveTenantModel(Tenant|int|null $tenant): ?Tenant
    {
        if ($tenant instanceof Tenant) {
            return $tenant->enabled ? $tenant : null;
        }

        if (!$tenant) {
            return null;
        }

        return Tenant::query()->enabled()->whereKey((int) $tenant)->first();
    }

    /**
     * 从 cache（主）和 session（辅）读出上次选择。
     */
    protected function storedTenantId($user): ?int
    {
        $cached = cache()->get($this->cacheKey($user));
        if (filled($cached)) {
            return (int) $cached;
        }

        try {
            $sessionId = session()->get(self::SESSION_KEY);
            if (filled($sessionId)) {
                return (int) $sessionId;
            }
        } catch (\Throwable) {
            // 后台中间件组没有 StartSession 时这里会失败，忽略即可
        }

        return null;
    }

    /**
     * 把选择写到 cache 和 session。session 写失败不影响 cache。
     */
    protected function persist(?int $tenantId): void
    {
        $user = $this->user();

        if (!$user) {
            return;
        }

        if ($tenantId) {
            cache()->forever($this->cacheKey($user), $tenantId);
        } else {
            cache()->forget($this->cacheKey($user));
        }

        try {
            if ($tenantId) {
                session()->put(self::SESSION_KEY, $tenantId);
            } else {
                session()->forget(self::SESSION_KEY);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * 清掉该用户已保存的租户选择。
     */
    protected function forgetStored($user): void
    {
        cache()->forget($this->cacheKey($user));

        try {
            session()->forget(self::SESSION_KEY);
        } catch (\Throwable) {
        }
    }

    /**
     * cache key 带上用户 id，避免多人共用一个选择。
     */
    protected function cacheKey($user): string
    {
        return self::CACHE_PREFIX . $user->getKey();
    }
}
