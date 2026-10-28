<?php

namespace Lathe\Field;

use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Lathe\Field\Contracts\FilterableField;
use LogicException;

/**
 * One value out of a list of options.
 */
class SelectField extends Field implements FilterableField
{
    use HasOptions {
        jsonSerialize as optionsJsonSerialize;
    }

    protected string $component = 'select-field';

    /** The tones a badge can take, each a pair of the theme's colour tokens. */
    public const array BADGE_TONES = ['neutral', 'primary', 'info', 'success', 'warning', 'danger'];

    /** @var array<array-key, string>|null */
    protected ?array $badges = null;

    /** @var list<string|int>|null */
    protected ?array $meterExcept = null;

    /**
     * Show each value as a badge in the tone the map gives it: neutral, primary, info,
     * success, warning or danger. A value the map does not name is neutral.
     *
     * @param  array<array-key, string>  $tones  value => tone
     */
    public function badges(array $tones): static
    {
        foreach ($tones as $tone) {
            if (! in_array($tone, self::BADGE_TONES, true)) {
                throw new InvalidArgumentException("The badge tone [{$tone}] is not one of ".implode(', ', self::BADGE_TONES).'.');
            }
        }

        $this->mutate();
        $this->badges = $tones;

        return $this;
    }

    /**
     * Show the value as a status meter: the options in declared order, minus the ones in
     * $except, are its ticks (2 to 5). A value fills the ticks up to its place; a value in
     * $except shows every tick empty. The count is checked when fields() builds.
     *
     * @param  list<string|int>  $except  option values that are not ticks
     */
    public function meter(array $except = []): static
    {
        $this->mutate();
        $this->meterExcept = $except;

        return $this;
    }

    /**
     * Check the meter once the options are known, so meter() and options() work in either order.
     */
    public function freeze(): static
    {
        if ($this->meterExcept !== null) {
            if ($this->isRemote()) {
                throw new LogicException("The meter of the field [{$this->label}] needs inline options, not remote ones.");
            }

            $count = count($this->meterTicks() ?? []);

            if ($count < 2 || $count > 5) {
                throw new LogicException("The meter of the field [{$this->label}] needs 2 to 5 ticks; it has {$count}.");
            }
        }

        return parent::freeze();
    }

    /**
     * The tick values in declared order, or null without a meter.
     *
     * @return list<string|int>|null
     */
    private function meterTicks(): ?array
    {
        if ($this->meterExcept === null) {
            return null;
        }

        $except = array_map(strval(...), $this->meterExcept);

        return array_values(array_filter($this->optionValues(), fn (string $value): bool => ! in_array($value, $except, true)));
    }

    /**
     * Whether the values show as badges.
     */
    public function hasBadges(): bool
    {
        return $this->badges !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...$this->optionsJsonSerialize(), 'badges' => $this->badges, 'meter' => $this->meterTicks()];
    }

    /**
     * The submitted value must be one of the options. A select with no options of its own
     * (its list arrives from a dependency) states no list to check against.
     */
    protected function impliedRules(): array
    {
        $values = $this->optionValues();
        $remote = $this->remoteValuesRule();

        return match (true) {
            $values !== [] => [Rule::in($values)],
            $remote !== null => [$remote],
            default => [],
        };
    }

    protected function supportsInline(): bool
    {
        return true;
    }
}
