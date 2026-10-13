<?php

namespace Lathe\Field;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use NumberFormatter;

/**
 * Money. It travels as a decimal string with the currency's fraction digits ("1234.50"), never
 * a float. With asMinorUnits() the stored integer is the amount in minor units and save parses
 * the string with integer arithmetic, rounding half up.
 */
class CurrencyField extends NumberField
{
    protected string $component = 'currency-field';

    protected ?string $currency = null;

    protected ?string $locale = null;

    protected ?string $symbol = null;

    protected bool $minorUnits = false;

    protected ?int $digits = null;

    public function __construct(string $label, string|Closure|null $attribute = null, ?Closure $resolver = null)
    {
        parent::__construct($label, $attribute, $resolver);
        $this->options['textAlign'] = 'right';
    }

    public function currencyCode(string $iso): static
    {
        $this->mutate();
        $this->currency = $iso;
        $this->digits = null;

        return $this;
    }

    public function locale(string $locale): static
    {
        $this->mutate();
        $this->locale = $locale;

        return $this;
    }

    public function symbol(string $symbol): static
    {
        $this->mutate();
        $this->symbol = $symbol;

        return $this;
    }

    public function asMinorUnits(bool $minor = true): static
    {
        $this->mutate();
        $this->minorUnits = $minor;

        return $this;
    }

    public function asMajorUnits(): static
    {
        return $this->asMinorUnits(false);
    }

    /**
     * A major-unit decimal (the value resolve() returns) as the currency's text, such as $1,234.50.
     * The formatter takes a float, so this is for display only and never travels.
     */
    public function formatMoney(mixed $value): ?string
    {
        if (! (is_int($value) || is_float($value) || is_string($value)) || ! is_numeric($value)) {
            return null;
        }

        $formatter = new NumberFormatter($this->locale ?? app()->getLocale(), NumberFormatter::CURRENCY);

        // formatCurrency() ignores a symbol override, so the code is set on the formatter and format() used.
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $this->code());

        if ($this->symbol !== null) {
            $formatter->setSymbol(NumberFormatter::CURRENCY_SYMBOL, $this->symbol);
        }

        $text = $formatter->format((float) $value);

        return $text === false ? null : $text;
    }

    public function resolve(object $record, ?string $attribute = null): mixed
    {
        $value = parent::resolve($record, $attribute);

        return $this->resolveCallback === null ? $this->wireMoney($value) : $value;
    }

    public function cell(Model $model): array
    {
        $cell = parent::cell($model);
        $money = $this->formatMoney($cell['value']);

        return $money === null ? $cell : [...$cell, 'display' => $money];
    }

    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $immutable = $model instanceof Model && $model->exists && $this->isImmutable($request, $model);

        if (! $this->minorUnits || $immutable || $this->fillCallback !== null) {
            return parent::fill($request, $model, $requestAttribute);
        }

        $value = $this->lifecycle->parse($request, $requestAttribute ?? $this->attribute);

        if ($this->isNullable() && $this->isNullValue($value)) {
            $value = null;
        } elseif (is_int($value) || is_float($value) || (is_string($value) && $value !== '')) {
            $value = $this->toMinor((string) $value) ?? $value;
        }

        return $this->lifecycle->persist($model, $this->attribute, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = parent::jsonSerialize();

        foreach (['min', 'max'] as $bound) {
            $payload[$bound] = is_int($payload[$bound]) || is_float($payload[$bound]) ? (string) $payload[$bound] : null;
        }

        return [
            ...$payload,
            'step' => $this->fractionDigits() === 0 ? '1' : '0.'.str_repeat('0', $this->fractionDigits() - 1).'1',
            'currency' => $this->code(),
            'symbol' => $this->symbol,
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function impliedRules(): array
    {
        return [...parent::impliedRules(), 'regex:/^-?\d+(\.\d+)?\z/'];
    }

    protected function code(): string
    {
        return $this->currency ?? Lathe::config()->string('currency');
    }

    /**
     * The fraction digits of the currency (ISO 4217): 2 for USD, 0 for JPY.
     */
    protected function fractionDigits(): int
    {
        return $this->digits ??= (int) (new NumberFormatter("en@currency={$this->code()}", NumberFormatter::CURRENCY))
            ->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    /**
     * The stored value as a major-unit decimal string with the currency's fraction digits.
     */
    protected function wireMoney(mixed $value): mixed
    {
        if (! (is_int($value) || is_float($value) || is_string($value)) || $value === '') {
            return $value;
        }

        if ($this->minorUnits) {
            return preg_match('/^-?\d+\z/', (string) $value) === 1 ? $this->fromMinor((int) $value) : $value;
        }

        $minor = $this->toMinor((string) $value);

        return $minor === null ? $value : $this->fromMinor($minor);
    }

    /**
     * A decimal string as minor units, rounding half up (away from zero) at the first dropped digit.
     */
    protected function toMinor(string $decimal): ?int
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?\z/', $decimal, $parts) !== 1) {
            return null;
        }

        $digits = $this->fractionDigits();
        $fraction = $parts[3] ?? '';
        $minor = (int) ($parts[2].substr(str_pad($fraction, $digits, '0'), 0, $digits));

        if ((int) substr($fraction, $digits, 1) >= 5) {
            $minor++;
        }

        return $parts[1] === '-' ? -$minor : $minor;
    }

    protected function fromMinor(int $minor): string
    {
        $digits = $this->fractionDigits();
        $text = str_pad((string) abs($minor), $digits + 1, '0', STR_PAD_LEFT);
        $amount = $digits === 0 ? $text : substr($text, 0, -$digits).'.'.substr($text, -$digits);

        return ($minor < 0 ? '-' : '').$amount;
    }
}
