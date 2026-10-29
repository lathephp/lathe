<?php

namespace Lathe\Field\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lathe\Field\Field;
use Lathe\Field\HeadingField;
use Lathe\Field\PasswordField;
use Lathe\Field\TextareaField;
use Lathe\Field\TextField;

/**
 * The factories of the text, textarea, email, url, color, password, hidden and heading fields.
 */
trait TextFactories
{
    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  a Closure makes a computed field
     */
    public static function text(string $label, string|Closure|null $attribute = null): TextField
    {
        return new TextField($label, $attribute);
    }

    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  a Closure makes a computed field
     */
    public static function textarea(string $label, string|Closure|null $attribute = null): TextareaField
    {
        return new TextareaField($label, $attribute);
    }

    /**
     * @param  string|(Closure(Model): mixed)|null  $attribute  a Closure makes a computed field
     */
    public static function email(string $label = 'Email', string|Closure|null $attribute = null): TextField
    {
        return (new TextField($label, $attribute))->component('email-field')->autocomplete('email');
    }

    public static function url(string $label = 'URL', ?string $attribute = null): TextField
    {
        return (new TextField($label, $attribute))->component('url-field')->autocomplete('url')->textAlign('center');
    }

    public static function color(string $label, ?string $attribute = null): Field
    {
        return (new Field($label, $attribute))->component('color-field')->textAlign('center');
    }

    public static function password(string $label = 'Password', string $attribute = 'password'): PasswordField
    {
        return new PasswordField($label, $attribute);
    }

    /**
     * The retyped password: it shows on forms only and never reaches the model. Pair
     * password() with rules('confirmed') to validate the match.
     */
    public static function passwordConfirmation(string $label, string $attribute = 'password_confirmation'): PasswordField
    {
        return (new PasswordField($label, $attribute))->onlyOnForms()->fillUsing(fn () => null);
    }

    /**
     * A value the form submits with no control, and no label row.
     */
    public static function hidden(string $label, string $attribute): Field
    {
        return (new Field($label, $attribute))->component('hidden-field')->onlyOnForms()->compact();
    }

    /**
     * A heading on the page: the label is its content, as sanitized HTML, so no label row repeats it.
     */
    public static function heading(string $label): HeadingField
    {
        return (new HeadingField($label))->compact();
    }
}
