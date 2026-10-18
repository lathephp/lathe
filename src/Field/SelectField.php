<?php

namespace Lathe\Field;

use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Lathe\Field\Contracts\FilterableField;

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
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...$this->optionsJsonSerialize(), 'badges' => $this->badges];
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
