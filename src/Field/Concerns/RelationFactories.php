<?php

namespace Lathe\Field\Concerns;

use Lathe\Field\Relations\BelongsToField;
use Lathe\Field\Relations\BelongsToManyField;
use Lathe\Field\Relations\HasManyField;
use Lathe\Field\Relations\HasManyThroughField;
use Lathe\Field\Relations\HasOneField;
use Lathe\Field\Relations\HasOneThroughField;
use Lathe\Field\Relations\MorphedByManyField;
use Lathe\Field\Relations\MorphManyField;
use Lathe\Field\Relations\MorphOneField;
use Lathe\Field\Relations\MorphToField;
use Lathe\Field\Relations\MorphToManyField;
use Lathe\Field\Relations\TagField;
use Lathe\Resources\Resource;

/**
 * The factories of the relation fields. The attribute is the Eloquent relation name, guessed
 * from the label when left out; the Resource class is guessed from the related model.
 */
trait RelationFactories
{
    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function belongsTo(string $name, ?string $attribute = null, ?string $resource = null): BelongsToField
    {
        $field = new BelongsToField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function hasMany(string $name, ?string $attribute = null, ?string $resource = null): HasManyField
    {
        $field = new HasManyField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function hasOne(string $name, ?string $attribute = null, ?string $resource = null): HasOneField
    {
        $field = new HasOneField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * A read-only view of a latestOfMany, oldestOfMany or ofMany relation.
     *
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function hasOneOfMany(string $name, ?string $attribute = null, ?string $resource = null): HasOneField
    {
        $field = (new HasOneField($name, $attribute))->ofMany();

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function belongsToMany(string $name, ?string $attribute = null, ?string $resource = null): BelongsToManyField
    {
        $field = new BelongsToManyField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function morphTo(string $name, ?string $attribute = null, ?string $resource = null): MorphToField
    {
        $field = new MorphToField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function morphMany(string $name, ?string $attribute = null, ?string $resource = null): MorphManyField
    {
        $field = new MorphManyField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function morphOne(string $name, ?string $attribute = null, ?string $resource = null): MorphOneField
    {
        $field = new MorphOneField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function morphToMany(string $name, ?string $attribute = null, ?string $resource = null): MorphToManyField
    {
        $field = new MorphToManyField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function morphedByMany(string $name, ?string $attribute = null, ?string $resource = null): MorphedByManyField
    {
        $field = new MorphedByManyField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function hasOneThrough(string $name, ?string $attribute = null, ?string $resource = null): HasOneThroughField
    {
        $field = new HasOneThroughField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function hasManyThrough(string $name, ?string $attribute = null, ?string $resource = null): HasManyThroughField
    {
        $field = new HasManyThroughField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }

    /**
     * @param  class-string<\Lathe\Resources\Resource>|null  $resource
     */
    public static function tag(string $name, ?string $attribute = null, ?string $resource = null): TagField
    {
        $field = new TagField($name, $attribute);

        return $resource === null ? $field : $field->resource($resource);
    }
}
