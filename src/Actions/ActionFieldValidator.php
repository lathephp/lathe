<?php

namespace Lathe\Actions;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\FileField;
use Lathe\Field\Relations\Associatable;
use Lathe\Field\Relations\BelongsToField;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Rules\Relatable;

/**
 * Validates the modal form of an action against the `fields` object of the request. A
 * multipart upload under `fields[<attribute>]` is part of that object, so `required` sees it.
 */
class ActionFieldValidator
{
    /**
     * 422 per field key on failure.
     */
    public function validate(LatheRequest $request, Action $action): ActionFields
    {
        $data = $request->all()['fields'] ?? [];
        $data = is_array($data) ? $data : [];
        $rules = [];
        $messages = [];
        $attributes = [];
        $keys = [];
        $relations = [];
        $class = $request->resourceOrFail();

        foreach ($action->fields($request) as $field) {
            if (! $field instanceof Field) {
                continue;
            }

            $rules = [...$rules, ...$field->getRules(FieldContext::Create, $request)];
            $messages = [...$messages, ...$field->getMessages()];
            $attributes = [...$attributes, ...$field->getValidationAttributes()];
            $keys[] = $field->attribute();

            if ($field instanceof BelongsToField) {
                $relations[] = $field->attribute();
                // An action field has no owner, so the key is checked here against the query its picker reads.
                $rules[$field->attribute()][] = new Relatable($request, new Associatable($request, $class, $field), $field);
            }

            // A multipart file skips the chunked upload route, so its size and type limits apply here.
            if ($field instanceof FileField) {
                $rules[$field->attribute()][] = 'max:'.intdiv($field->getMaxSizeBytes(), 1024);
                $rules[$field->attribute()][] = function (string $attribute, mixed $value, Closure $fail) use ($field): void {
                    if ($value instanceof UploadedFile && ! $field->accepts($value->getClientOriginalName(), (string) $value->getClientMimeType())) {
                        $fail(Lathe::__('The file type is not one of: :types.', ['types' => implode(', ', $field->getAcceptedTypes())]));
                    }
                };
            }
        }

        $validator = ValidatorFactory::make($data, $rules, $messages, $attributes);
        $validator->after(fn (Validator $validator) => $action->afterValidation($request, $validator));
        $validator->validate();

        // The modal's picker sends {value, display}, as on a record form; the handler gets the key.
        foreach ($relations as $attribute) {
            if (is_array($data[$attribute] ?? null)) {
                $data[$attribute] = $data[$attribute]['value'] ?? null;
            }
        }

        // Keys no field owns never reach the handler.
        return new ActionFields(array_intersect_key($data, array_flip($keys)));
    }
}
