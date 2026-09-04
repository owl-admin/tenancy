<?php

namespace OwlAdmin\Tenancy\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 最小后台用户桩，用来测 userCanAccess / 超管旁路，不启动完整 AdminUser。
 */
class FakeAdminUser implements Authenticatable
{
    public function __construct(
        public int $id,
        public bool $administrator = false,
    ) {
    }

    /**
     * Eloquent 风格主键，TenancyManager 用它拼 cache key 和成员表查询。
     */
    public function getKey(): int
    {
        return $this->id;
    }

    /**
     * 超管走全部启用租户，普通用户只看成员表。
     */
    public function isAdministrator(): bool
    {
        return $this->administrator;
    }

    /**
     * Auth 标识字段名，桩用户用 id。
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Auth 标识值，与 getKey 保持一致。
     */
    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    /**
     * 测试不校验密码，返回空串即可。
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * 密码字段名，接口要求实现，测试不会读。
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * 不使用记住登录。
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * 记住登录 token 对桩用户无意义。
     */
    public function setRememberToken($value): void
    {
    }

    /**
     * 记住登录字段名，接口要求实现。
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
