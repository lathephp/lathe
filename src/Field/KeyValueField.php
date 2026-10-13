<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Support\ConfigStore;

/**
 * Free-form pairs kept as a JSON object. The form sends them as a list of key and value
 * rows, so a repeated key reaches validation; a plain object is accepted too.
 */
class KeyValueField extends Field
{
    protected string $component = 'key-value-field';

    protected bool|Closure $lockKeys = false;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->hideFromIndex();
        $this->options['keyLabel'] = Lathe::__('Key');
        $this->options['valueLabel'] = Lathe::__('Value');
        $this->options['actionText'] = Lathe::__('Add row');
        $this->options['disableAddingRows'] = false;
        $this->options['disableDeletingRows'] = false;
        $this->options['noValueText'] = Lathe::__('No Data');
        $this->lifecycle = new class extends DefaultLifecycle
        {
            public function present(mixed $raw, object $record, string $attribute): mixed
            {
                return $raw === [] ? null : parent::present($raw, $record, $attribute);
            }

            public function parse(LatheRequest $request, string $attribute): mixed
            {
                $rows = KeyValueField::rows($request->input($attribute));

                return $rows === null ? null : array_column($rows, 1, 0);
            }
        };
    }

    public function keyLabel(string $label): static
    {
        return $this->option('keyLabel', $label);
    }

    public function valueLabel(string $label): static
    {
        return $this->option('valueLabel', $label);
    }

    public function actionText(string $text): static
    {
        return $this->option('actionText', $text);
    }

    /**
     * Lock the keys in the form. A Closure takes the LatheRequest. Locked keys still save.
     */
    public function disableEditingKeys(bool|Closure $disable = true): static
    {
        $this->mutate();
        $this->lockKeys = $disable;

        return $this;
    }

    public function disableAddingRows(bool $disable = true): static
    {
        return $this->option('disableAddingRows', $disable);
    }

    public function disableDeletingRows(bool $disable = true): static
    {
        return $this->option('disableDeletingRows', $disable);
    }

    public function noValueText(string $text): static
    {
        return $this->option('noValueText', $text);
    }

    /**
     * The pairs as a list of [key, value], from key and value rows or from an object;
     * null when the input is neither.
     *
     * @return list<array{string, mixed}>|null
     */
    public static function rows(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $rows = [];

        foreach ($input as $name => $item) {
            if (! array_is_list($input)) {
                $rows[] = [(string) $name, $item];
            } elseif (is_array($item) && is_string($item['key'] ?? null)) {
                $rows[] = [$item['key'], $item['value'] ?? null];
            } else {
                return null;
            }
        }

        return $rows;
    }

    /**
     * A Closure lock needs the request, so only schema() runs it.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...parent::jsonSerialize(), 'disableEditingKeys' => $this->lockKeys === true];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        return [...parent::schema($context, $request, $model), 'disableEditingKeys' => $this->decide($this->lockKeys, $request, $model)];
    }

    protected function impliedRules(): array
    {
        return [function (string $attribute, mixed $value, Closure $fail) {
            $rows = self::rows($value);
            $config = app(ConfigStore::class);
            $maxRows = $config->integer('limits.key_value_rows');
            $maxLength = $config->integer('limits.key_length');

            if ($rows === null) {
                $fail(Lathe::__('The :attribute field must be a list of key and value pairs.'));
            } elseif (count($rows) > $maxRows) {
                $fail(Lathe::__('The :attribute field takes at most :max pairs.', ['max' => $maxRows]));
            } elseif (in_array('', array_column($rows, 0), true)) {
                $fail(Lathe::__('The :attribute field has a pair with an empty key.'));
            } elseif (array_filter(array_column($rows, 0), fn (string $key) => mb_strlen($key) > $maxLength) !== []) {
                $fail(Lathe::__('The :attribute field has a key longer than :max characters.', ['max' => $maxLength]));
            } elseif (count(array_unique(array_column($rows, 0))) !== count($rows)) {
                $fail(Lathe::__('The :attribute field has a key that is used twice.'));
            }
        }];
    }
}
