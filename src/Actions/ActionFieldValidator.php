<?php

namespace Lathe\Actions;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Lathe\Field\Field;
use Lathe\Field\FieldContext;
use Lathe\Field\Relations\BelongsToField;
use Lathe\Http\Requests\LatheRequest;

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
