<?php

namespace Lathe\Testing;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Layout\SectionPresenter;
use Lathe\Resources\Resource;
use Lathe\Search\SearchableColumn;
use PHPUnit\Framework\Assert;

/**
 * Assertions on a Resource class, without a request to the panel.
 */
trait ResourceAssertions
{
    protected function assertResourceExists(string $class): void
    {
        Assert::assertTrue(is_subclass_of($class, Resource::class), "{$class} is not a Lathe Resource.");
    }

    /**
     * Counts the fields inside sections and tabs, not the containers.
     *
     * @param  class-string<resource>  $class
     */
    protected function assertResourceFieldCount(string $class, int $expected): void
    {
        $fields = $this->resourceFields($class);

        Assert::assertCount($expected, $fields, "Expected {$expected} fields on {$class}, found ".count($fields).'.');
    }

    /**
     * @param  class-string<resource>  $class
     */
    protected function assertResourceHasField(string $class, string $attribute): void
    {
        $attributes = array_map(fn (Field $field) => $field->attribute(), $this->resourceFields($class));

        Assert::assertContains($attribute, $attributes, "{$class} has no field for '{$attribute}'.");
    }

    /**
     * @param  class-string<resource>  $class
     * @param  list<string|SearchableColumn>  $columns
     */
    protected function assertResourceSearchable(string $class, array $columns): void
    {
        Assert::assertEquals($columns, $class::make()->searchableColumns(), "The searchable columns of {$class} differ.");
    }

    /**
     * @param  class-string<resource>  $class
     * @return list<Field>
     */
    private function resourceFields(string $class): array
    {
        return SectionPresenter::flattenFields($class::make()->fields(new LatheRequest));
    }
}
