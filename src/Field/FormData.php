<?php

namespace Lathe\Field;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Query\QueryContext;
use Lathe\Query\ResourceQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The values of the form as the user has them now, for a dependsOn callback.
 */
class FormData
{
    /**
     * @param  array<array-key, mixed>  $values
     */
    public function __construct(protected array $values, protected LatheRequest $request)
    {
        //
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->values, $key, $default);
    }

    public function has(string $key): bool
    {
        return data_get($this->values, $key) !== null && data_get($this->values, $key) !== '';
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->get($key), FILTER_VALIDATE_BOOLEAN);
    }

    public function file(string $key): ?UploadedFile
    {
        $file = $this->request->file($key);

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * The record another Resource's key in the form points at, through that Resource's scoped query.
     */
    public function resource(string $uriKey, mixed $value): ?Model
    {
        $class = Lathe::resources()->forKey($uriKey);

        if ($class === null || ! is_scalar($value) || $value === '') {
            return null;
        }

        try {
            return (new ResourceQuery($class::make(), $this->request, QueryContext::Detail))->find((string) $value);
        } catch (NotFoundHttpException) {
            return null;
        }
    }

    public function request(): LatheRequest
    {
        return $this->request;
    }
}
