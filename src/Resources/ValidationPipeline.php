<?php

namespace Lathe\Resources;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Lathe\Field\FieldCollection;
use Lathe\Field\FieldContext;
use Lathe\Http\Requests\LatheRequest;

/**
 * One validator per write, built from the fields that will fill the model. Keys no field
 * owns are never validated and never filled.
 */
class ValidationPipeline
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>  $resource
     */
    public function __construct(
        protected FieldCollection $fields,
        protected LatheRequest $request,
        protected FieldContext $context,
        protected string $resource,
        protected ?Model $model = null,
    ) {
        //
    }

    public function validator(): Validator
    {
        $rules = [];
        $messages = [];
        $attributes = [];

        foreach ($this->fields as $field) {
            $rules = [...$rules, ...$field->getRules($this->context, $this->request, $this->model)];
            $messages = [...$messages, ...$field->getMessages()];
            $attributes = [...$attributes, ...$field->getValidationAttributes()];
        }

        $validator = ValidatorFactory::make($this->request->all(), $rules, $messages, $attributes);

        $validator->after(function (Validator $validator) {
            $this->resource::afterValidation($this->request, $validator);

            $this->context === FieldContext::Update
                ? $this->resource::afterUpdateValidation($this->request, $validator)
                : $this->resource::afterCreationValidation($this->request, $validator);
        });

        return $validator;
    }

    /**
     * Validate, throwing a 422 with message and errors on failure.
     */
    public function validate(): void
    {
        $this->validator()->validate();
    }

    /**
     * The fields a write fills: shown in the context, seen by this user, not read-only, not computed.
     */
    public static function writable(FieldCollection $fields, FieldContext $context, LatheRequest $request, ?Model $model = null): FieldCollection
    {
        return $fields->forContext($context, $request, $model)
            ->authorized($request)
            ->withoutReadonly($request, $model)
            ->withoutComputed();
    }
}
