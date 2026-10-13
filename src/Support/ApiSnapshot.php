<?php

namespace Lathe\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use Symfony\Component\Finder\Finder;

/**
 * Lists the public PHP API of the package: every public or protected class member
 * under the root namespace that is not tagged @internal, one symbol per line.
 *
 * @internal
 */
class ApiSnapshot
{
    /**
     * @return list<string>
     */
    public function php(string $source): array
    {
        $lines = [];

        foreach ($this->classes($source) as $class) {
            $reflection = new ReflectionClass($class);

            if ($this->internal($reflection->getDocComment())) {
                continue;
            }

            $lines[] = $this->kind($reflection).' '.$class;

            foreach ($reflection->getReflectionConstants() as $constant) {
                if ($constant->getDeclaringClass()->getName() === $class && ! $constant->isPrivate() && ! $this->internal($constant->getDocComment())) {
                    $lines[] = "{$class}::{$constant->getName()}";
                }
            }

            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() === $class && ! $property->isPrivate() && ! $this->internal($property->getDocComment())) {
                    $lines[] = $this->property($class, $property);
                }
            }

            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() === $class && ! $method->isPrivate() && ! $this->internal($method->getDocComment())
                    && $method->getFileName() === $reflection->getFileName()) {
                    $lines[] = $this->method($class, $method);
                }
            }
        }

        sort($lines);

        return $lines;
    }

    /**
     * @return list<class-string>
     */
    protected function classes(string $source): array
    {
        $classes = [];

        foreach (Finder::create()->files()->in($source)->name('*.php')->sortByName() as $file) {
            $code = (string) file_get_contents($file->getRealPath());

            if (preg_match('/^namespace\s+([^;]+);/m', $code, $namespace) !== 1
                || preg_match('/^(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $code, $name) !== 1) {
                continue;
            }

            $class = $namespace[1].'\\'.$name[1];

            if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
                require_once $file->getRealPath();
            }

            /** @var class-string $class */
            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    protected function kind(ReflectionClass $reflection): string
    {
        return match (true) {
            $reflection->isEnum() => 'enum',
            $reflection->isInterface() => 'interface',
            $reflection->isTrait() => 'trait',
            default => 'class',
        };
    }

    protected function method(string $class, ReflectionMethod $method): string
    {
        $parameters = implode(', ', array_map($this->parameter(...), $method->getParameters()));
        $return = $method->getReturnType() !== null ? ': '.$this->type($method->getReturnType()) : '';
        $static = $method->isStatic() ? 'static ' : '';
        $visibility = $method->isProtected() ? 'protected ' : '';

        return "{$class}::{$method->getName()}({$parameters}){$return}".($static.$visibility !== '' ? '  ['.trim($visibility.$static).']' : '');
    }

    protected function property(string $class, ReflectionProperty $property): string
    {
        $type = $property->getType() !== null ? $this->type($property->getType()).' ' : '';

        return "{$class}::{$type}\${$property->getName()}".($property->isProtected() ? '  [protected]' : '');
    }

    protected function parameter(ReflectionParameter $parameter): string
    {
        $text = ($parameter->getType() !== null ? $this->type($parameter->getType()).' ' : '')
            .($parameter->isVariadic() ? '...' : '')
            .'$'.$parameter->getName();

        if ($parameter->isDefaultValueAvailable()) {
            $text .= ' = '.($parameter->isDefaultValueConstant()
                ? $parameter->getDefaultValueConstantName()
                : var_export($parameter->getDefaultValue(), true));
        }

        return (string) preg_replace('/\s+/', ' ', $text);
    }

    protected function type(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return ($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null' ? '?' : '').$type->getName();
        }

        return (string) $type;
    }

    protected function internal(string|false $docComment): bool
    {
        return $docComment !== false && str_contains($docComment, '@internal');
    }
}
