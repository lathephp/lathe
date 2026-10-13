<?php

namespace Lathe\Field;

/**
 * A Gravatar picture. The server hashes the email and builds the URL, and makes no call:
 * the browser loads the image, so Gravatar sees the hash. Without an email the field shows initials.
 */
class GravatarField extends UiAvatarField
{
    protected ?string $source = 'email';

    protected function imageUrl(object $record): ?string
    {
        $email = $this->emailOf($record);

        return $email === '' ? null : 'https://www.gravatar.com/avatar/'.hash('sha256', $email).'?s=300';
    }

    /**
     * The first letters of the email's name part, or "?" when there is no email.
     */
    protected function initials(object $record): ?string
    {
        $email = $this->emailOf($record);

        return $email === '' ? '?' : Avatar::initialsOf(str_replace(['.', '_', '-', '+'], ' ', strstr($email, '@', true) ?: $email));
    }

    protected function emailOf(object $record): string
    {
        $email = $this->source === null ? null : data_get($record, $this->source);

        return is_string($email) ? strtolower(trim($email)) : '';
    }
}
