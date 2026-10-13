<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use WeakMap;

/**
 * Calls a field closure with the values it asks for: by parameter name, then by type,
 * then from the container, then by position. Each closure is reflected once per request.
 */
class Evaluator
{
    /** @var WeakMap<Closure, list<array{name: string, types: list<string>, optional: bool, default: mixed, nullable: bool}>> */
    private WeakMap $plans;

    /** How many closures this evaluator reflected; tests read it. */
    public int $reflections = 0;

    /** The parameter names each value answers to. */
    private const array NAMES = [
        'record' => 'record', 'resource' => 'record', 'model' => 'record',
        'value' => 'value', 'state' => 'value',
        'attribute' => 'attribute', 'attr' => 'attribute', 'column' => 'attribute',
        'request' => 'request',
        'field' => 'field', 'component' => 'field', 'self' => 'field',
        'context' => 'context', 'operation' => 'context',
    ];

    public function __construct()
    {
        $this->plans = new WeakMap;
    }

    /**
     * The evaluator of this request, so the reflection cache lives exactly as long as the request.
     */
    public static function for(?LatheRequest $request = null): self
    {
        $request ??= self::currentRequest();

        if ($request === null) {
            return new self;
        }

        $evaluator = $request->attributes->get('lathe.evaluator');

        if (! $evaluator instanceof self) {
            $request->attributes->set('lathe.evaluator', $evaluator = new self);
        }

        return $evaluator;
    }

    public static function currentRequest(): ?LatheRequest
    {
        $container = Container::getInstance();

        return $container->bound(LatheRequest::class) ? $container->make(LatheRequest::class) : null;
    }

    /**
     * @param  array{record?: mixed, value?: mixed, attribute?: string|null, request?: LatheRequest|null, field?: Field|null, context?: FieldContext|null}  $values
     * @param  list<mixed>  $positional
     */
    public function call(Closure $callback, array $values = [], array $positional = []): mixed
    {
        $arguments = [];

        foreach ($this->plan($callback) as $index => $parameter) {
            $arguments[] = $this->argument($parameter, $index, $values, $positional);
        }

        return $callback(...$arguments);
    }

    /**
     * @param  array{name: string, types: list<string>, optional: bool, default: mixed, nullable: bool}  $parameter
     * @param  array<string, mixed>  $values
     * @param  list<mixed>  $positional
     */
    private function argument(array $parameter, int $index, array $values, array $positional): mixed
    {
        $named = self::NAMES[$parameter['name']] ?? null;

        if ($named !== null && ($values[$named] ?? null) !== null) {
            return $values[$named];
        }

        foreach ($parameter['types'] as $type) {
            foreach (['record', 'request', 'field', 'context'] as $kind) {
                if (($values[$kind] ?? null) instanceof $type) {
                    return $values[$kind];
                }
            }
        }

        foreach ($parameter['types'] as $type) {
            if (! $this->isProvidedKind($type) && (Container::getInstance()->bound($type) || $this->isInstantiable($type))) {
                return Container::getInstance()->make($type);
            }
        }

        if ($named === null && array_key_exists($index, $positional)) {
            return $positional[$index];
        }

        if ($parameter['optional']) {
            return $parameter['default'];
        }

        if ($parameter['nullable'] || $named !== null) {
            return null;
        }

        throw new LogicException("Lathe cannot fill the parameter \${$parameter['name']} of a field closure.");
    }

    private function isProvidedKind(string $type): bool
    {
        return is_a($type, Model::class, true) || is_a($type, LatheRequest::class, true)
            || is_a($type, Field::class, true) || $type === FieldContext::class;
    }

    private function isInstantiable(string $type): bool
    {
        if ($this->isProvidedKind($type) || ! class_exists($type)) {
            return false;
        }

        $constructor = (new \ReflectionClass($type))->getConstructor();

        return (new \ReflectionClass($type))->isInstantiable()
            && ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0);
    }

    /**
     * @return list<array{name: string, types: list<string>, optional: bool, default: mixed, nullable: bool}>
     */
    private function plan(Closure $callback): array
    {
        if (isset($this->plans[$callback])) {
            return $this->plans[$callback];
        }

        $this->reflections++;

        return $this->plans[$callback] = array_map(fn (ReflectionParameter $parameter) => [
            'name' => $parameter->getName(),
            'types' => $this->classTypes($parameter),
            'optional' => $parameter->isDefaultValueAvailable(),
            'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            'nullable' => $parameter->allowsNull(),
        ], (new ReflectionFunction($callback))->getParameters());
    }

    /**
     * @return list<string>
     */
    private function classTypes(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        $types = $type instanceof ReflectionNamedType ? [$type] : ($type instanceof \ReflectionUnionType ? $type->getTypes() : []);

        return array_values(array_map(
            fn (ReflectionNamedType $type) => $type->getName(),
            array_filter($types, fn (mixed $type) => $type instanceof ReflectionNamedType && ! $type->isBuiltin()),
        ));
    }
}
