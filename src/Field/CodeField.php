<?php

namespace Lathe\Field;

use Closure;
use JsonException;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

/**
 * Source code in a code editor, hidden from the index. In json() mode the column holds
 * decoded JSON (an array or json cast): the form gets it as an encoded string, a save
 * decodes it again and a body that is not JSON returns 422.
 */
class CodeField extends Field
{
    protected string $component = 'code-field';

    protected bool $json = false;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->hideFromIndex();
        $this->options['language'] = 'plaintext';
        $this->options['height'] = null;
        $this->options['fullHeight'] = false;
        $this->options['autoHeight'] = false;
        $this->options['editorOptions'] = [];
    }

    public function language(string $language): static
    {
        return $this->option('language', $language);
    }

    /**
     * Edit the attribute as JSON. The flags encode the value for the editor and default
     * to pretty printing with unicode left as it is, so nothing is escaped twice.
     */
    public function json(?int $flags = null): static
    {
        $this->mutate();
        $this->json = true;
        $this->options['language'] = 'json';
        $this->lifecycle = new class($flags ?? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) extends DefaultLifecycle
        {
            public function __construct(protected int $flags) {}

            public function present(mixed $raw, object $record, string $attribute): mixed
            {
                return is_array($raw) || is_object($raw) ? json_encode($raw, $this->flags) : parent::present($raw, $record, $attribute);
            }

            public function parse(LatheRequest $request, string $attribute): mixed
            {
                $value = parent::parse($request, $attribute);

                return is_string($value) && $value !== '' ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
            }
        };

        return $this;
    }

    /** The editor height in pixels. */
    public function height(string|int $height): static
    {
        return $this->option('height', $height);
    }

    public function fullHeight(): static
    {
        return $this->option('fullHeight', true);
    }

    public function autoHeight(): static
    {
        return $this->option('autoHeight', true);
    }

    /**
     * Settings for the editor, such as tabSize or lineWrapping. Each call merges into the last.
     *
     * @param  array<string, mixed>  $options
     */
    public function editorOptions(array $options): static
    {
        return $this->option('editorOptions', [...(array) $this->options['editorOptions'], ...$options]);
    }

    protected function impliedRules(): array
    {
        if (! $this->json) {
            return [];
        }

        return [function (string $attribute, mixed $value, Closure $fail) {
            try {
                json_decode(is_string($value) ? $value : '', true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $fail(Lathe::__('The :attribute field must be valid JSON.'));
            }
        }];
    }
}
