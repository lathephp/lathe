<?php

namespace Lathe\Permission;

use Illuminate\Http\Request;
use Lathe\Exceptions\PluginDependencyException;
use Lathe\Lathe;
use Lathe\Permission\Policies\PermissionPolicy;
use Lathe\Permission\Policies\RolePolicy;
use Lathe\Permission\Resources\PermissionResource;
use Lathe\Permission\Resources\RoleResource;
use Lathe\Plugins\Plugin;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider as SpatiePermissionServiceProvider;

/**
 * Roles and permissions from spatie/laravel-permission, managed in the panel. Nobody may
 * manage them until the app calls withDefaultPolicies() or names its own policies.
 */
class PermissionPlugin extends Plugin
{
    /** The Spatie class requires() checks for. */
    protected string $registrar = PermissionRegistrar::class;

    /** @var class-string<\Lathe\Resources\Resource> */
    protected string $roleResource = RoleResource::class;

    /** @var class-string<\Lathe\Resources\Resource> */
    protected string $permissionResource = PermissionResource::class;

    /** @var class-string|null */
    protected ?string $rolePolicy = null;

    /** @var class-string|null */
    protected ?string $permissionPolicy = null;

    public function name(): string
    {
        return 'Access Control';
    }

    public static function uriKey(): string
    {
        return 'permission';
    }

    /**
     * Throws itself when Spatie is missing, so the message carries the install command.
     */
    public function requires(): array
    {
        if (! class_exists($this->registrar)) {
            throw new PluginDependencyException(sprintf(
                '%s (%s) needs spatie/laravel-permission. Run composer require spatie/laravel-permission.',
                $this->name(), static::class,
            ));
        }

        return [$this->registrar];
    }

    /**
     * Lathe can discover this plugin in an app that does not discover Laravel packages, such
     * as a Testbench suite; Spatie's provider and this package's then load here.
     */
    public function register(): void
    {
        if (! app()->bound(PermissionRegistrar::class)) {
            app()->register(SpatiePermissionServiceProvider::class);
            app()->register(PermissionServiceProvider::class);
        }

        parent::register();
    }

    public function resources(): array
    {
        return [$this->roleResource, $this->permissionResource];
    }

    /**
     * The registrar's own classes, so a custom Spatie model set in permission.models works.
     */
    public function policies(): array
    {
        // Denied until the app opts in, and with nothing to map no Spatie config is read.
        if ($this->rolePolicy === null && $this->permissionPolicy === null) {
            return [];
        }

        $registrar = app(PermissionRegistrar::class);
        /** @var class-string $role */
        $role = $registrar->getRoleClass();
        /** @var class-string $permission */
        $permission = $registrar->getPermissionClass();

        return array_filter([$role => $this->rolePolicy, $permission => $this->permissionPolicy]);
    }

    public function menu(Request $request): ?array
    {
        return null;
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    public function useRoleResource(string $class): static
    {
        $this->roleResource = $class;

        return $this;
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $class
     */
    public function usePermissionResource(string $class): static
    {
        $this->permissionResource = $class;

        return $this;
    }

    /**
     * @param  class-string  $class
     */
    public function useRolePolicy(string $class): static
    {
        $this->rolePolicy = $class;

        return $this;
    }

    /**
     * @param  class-string  $class
     */
    public function usePermissionPolicy(string $class): static
    {
        $this->permissionPolicy = $class;

        return $this;
    }

    /**
     * Let every user who passes viewLathe manage roles and permissions, with a boot warning.
     */
    public function withDefaultPolicies(): static
    {
        Lathe::warnAllowAll(RolePolicy::class);
        Lathe::warnAllowAll(PermissionPolicy::class);

        return $this->useRolePolicy(RolePolicy::class)->usePermissionPolicy(PermissionPolicy::class);
    }
}
