<?php

namespace Lathe\Auth;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceRegistry;

/**
 * The one ability function the page shells and the JSON API both ask.
 * A missing policy or policy method denies; replicate falls back to create and update.
 */
class ResourceAuthorization
{
    /** @var array<class-string, object|null> */
    protected array $policies = [];

    public function __construct(protected Gate $gate, protected ResourceRegistry $resources)
    {
        //
    }

    /**
     * Whether the Resource is visible to this request at all (canSee).
     *
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     */
    public function canSee(Request $request, string $resource): bool
    {
        return $this->resources->definition($resource)->seenBy($request);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     * @param  list<mixed>  $arguments  extra policy arguments after the model
     */
    public function allows(?Authenticatable $user, string $resource, string $ability, ?Model $model = null, array $arguments = []): bool
    {
        $definition = $this->resources->definition($resource);

        if ($definition->allowsAll()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        $policy = $this->policyFor($resource);

        if ($policy === null) {
            return false;
        }

        if ($ability === 'replicate' && ! method_exists($policy, 'replicate')) {
            return $this->allows($user, $resource, 'create', $model)
                && $this->allows($user, $resource, 'update', $model);
        }

        if (! method_exists($policy, $ability)) {
            return false;
        }

        $target = $model ?? $definition->getModel();

        // An explicit $policy on the Resource is called directly; otherwise the Gate
        // resolves the model's policy, so its before() hooks still apply.
        return $resource::$policy !== null
            ? $policy->{$ability}($user, $target, ...$arguments) === true
            : $this->gate->forUser($user)->allows($ability, [$target, ...$arguments]);
    }

    /**
     * Abilities for a whole page of rows, keyed by model key. One call to the policy's
     * abilitiesFor() when it has one; otherwise the gate per row and ability, from memory.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     * @param  Collection<int, Model>  $models
     * @param  list<string>  $abilities
     * @return array<int|string, array<string, bool>>
     */
    public function abilitiesFor(?Authenticatable $user, string $resource, Collection $models, array $abilities): array
    {
        $policy = $user === null || $this->resources->definition($resource)->allowsAll() ? null : $this->policyFor($resource);
        $batched = $policy !== null && method_exists($policy, 'abilitiesFor') ? $policy->abilitiesFor($user, $models) : null;
        $result = [];

        foreach ($models as $model) {
            $key = $model->getKey();
            $key = is_int($key) || is_string($key) ? $key : (string) json_encode($key);

            $row = is_array($batched) && is_array($batched[$key] ?? null) ? $batched[$key] : null;

            foreach ($abilities as $ability) {
                $result[$key][$ability] = is_array($batched)
                    ? ($row[$ability] ?? false) === true
                    : $this->allows($user, $resource, $ability, $model);
            }
        }

        return $result;
    }

    /**
     * The 403 text when a Resource has no policy.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     */
    public function denialMessage(string $resource): string
    {
        if ($this->policyFor($resource) !== null) {
            return 'This action is unauthorized.';
        }

        $model = $this->resources->definition($resource)->getModel() ?? $resource;

        return "{$resource} has no policy for {$model}, so every ability is denied. Write a policy, or call ->allowAll() in its configure() method to skip policy checks.";
    }

    /**
     * The policy instance for a Resource, looked up once per request.
     *
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     */
    protected function policyFor(string $resource): ?object
    {
        if (array_key_exists($resource, $this->policies)) {
            return $this->policies[$resource];
        }

        $policy = $resource::$policy !== null
            ? app($resource::$policy)
            : $this->gate->getPolicyFor((string) $this->resources->definition($resource)->getModel());

        return $this->policies[$resource] = is_object($policy) ? $policy : null;
    }
}
