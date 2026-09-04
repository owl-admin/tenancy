# Owl Admin 多租户

共享数据库 + `tenant_id` 列隔离。

| 项 | 值 |
| --- | --- |
| Composer | `owl-admin/tenancy` |
| 命名空间 | `OwlAdmin\Tenancy\` |
| 本地路径 | `extensions/owl-admin/tenancy` |
| 扩展名 | `owl-admin.tenancy` |
| 仓库 | https://github.com/owl-admin/tenancy |

本仓库是扩展的**唯一源码仓**（组织 [owl-admin](https://github.com/owl-admin)）。vendor 为 `owl-admin`，不是 `slowlyo`。核心框架依赖仍是 `slowlyo/owl-admin`。

v1 **不支持** 独立数据库、域名/子域名路由。

## 安装（业务项目 / Packagist）

```bash
composer require owl-admin/tenancy
```

然后在后台 **开发者工具 → 扩展管理** 启用 **多租户**（`owl-admin.tenancy`）。

## 本地 path 安装（开发联调）

把本仓库放到 Laravel 项目的扩展目录（默认 `extensions/`，见 `config/admin.php`）：

```bash
git clone https://github.com/owl-admin/tenancy.git extensions/owl-admin/tenancy
# 或: git submodule add https://github.com/owl-admin/tenancy.git extensions/owl-admin/tenancy
```

框架会扫描 `extensions/`，**不必**再 `composer require`。

1. 确认 `extensions/owl-admin/tenancy/composer.json` 存在。
2. 后台 → **开发者工具 → 扩展管理** → 启用 **多租户**。
3. 菜单未出现时：重新登录，或到 **系统 → 菜单管理** 确认。

也可先手动迁移：

```bash
php artisan migrate --path=extensions/owl-admin/tenancy/database/migrations
```

## 扩展设置

扩展卡片上的 **设置**：

| 项 | 默认 | 作用 |
| --- | --- | --- |
| 强制租户隔离 | 关 | 开：未绑定租户时，带 `BelongsToTenant` 的查询结果为空 |
| 超级管理员旁路 | 开 | 超管未选租户时不加 `tenant_id` 条件；选了则仍按该租户过滤 |
| 租户字段名 | `tenant_id` | 业务表列名，trait / 作用域都读这个值 |

## 后台怎么用

启用后侧栏会出现 **多租户**：

1. **租户管理**：创建租户（名称、唯一 slug、启用）。
2. **成员分配**：把后台用户划进租户，可选租户内角色（负责人 / 管理员 / 成员，与后台 RBAC 无关）。
3. **切换租户**：多租户用户必须选一个；只属于一个租户会自动绑定。顶栏也会出现下拉框。

登录后的行为：

- 只属于 **1 个** 租户：自动绑定，不用选。
- 属于 **多个** 租户：打开「切换租户」或顶栏下拉。
- **超级管理员**：未选择时走旁路（若设置开启），可看全部业务数据；需要排障再选具体租户。

API 可额外带请求头 `X-Tenant-Id`，只作用于 **当前请求**，不覆盖后台已保存的选择。

当前租户存在 cache（按用户 id）里。后台中间件组没有 Session，所以 session 只是尽力写入，不能当主存储。

## 业务表怎么接

1. 表上增加租户列（名字与扩展设置一致，默认 `tenant_id`）：

```php
Schema::table('articles', function (Blueprint $table) {
    $table->unsignedBigInteger('tenant_id')->nullable()->index();
});
```

2. 模型使用 trait：

```php
use OwlAdmin\Tenancy\Traits\BelongsToTenant;
use Slowlyo\OwlAdmin\Models\BaseModel;

class Article extends BaseModel
{
    use BelongsToTenant;
}
```

效果：

- 查询自动加 `where tenant_id = 当前租户`。
- `create` 时如果没传 `tenant_id`，会写入当前租户。
- `Article::allTenants()->get()` 关掉作用域。
- `Article::forTenant($id)->get()` 按指定租户查。

助手与 Facade：

```php
tenancy()->id();
tenancy()->tenant();
tenancy()->setTenant($id);
tenancy()->clear();
tenancy()->bypass(); // 超管旁路

use OwlAdmin\Tenancy\Facades\Tenancy;
Tenancy::id();
```

不要把 trait 加在 `Tenant` / `TenantUser` 或后台用户/角色/菜单上，那些是全局表。

## 限制（v1）

- 只有共享库 + 列隔离，没有 database-per-tenant。
- 没有域名 / 子域名识别（`TenancyManager::resolveByDomain()` 是空钩子）。
- 不改 owl-admin 核心登录页；多租户选择在登录 **之后**（自动绑定或切换页/顶栏）。
- 租户内 `role` 只是标记，不会替换后台权限系统。

## 卸载

扩展管理里 **卸载** 会回滚本扩展 migration、删除扩展菜单。业务表上的 `tenant_id` 列不会自动删。
