<?php

namespace Lathe\Doctor;

/**
 * One line of lathe:doctor output.
 */
class CheckResult
{
    public const string OK = 'OK';

    public const string WARN = 'WARN';

    public const string FAIL = 'FAIL';

    public const string SKIP = 'SKIP';

    public function __construct(
        public readonly string $status,
        public readonly string $message,
    ) {
        //
    }

    public static function ok(string $message): self
    {
        return new self(self::OK, $message);
    }

    public static function warn(string $message): self
    {
        return new self(self::WARN, $message);
    }

    public static function fail(string $message): self
    {
        return new self(self::FAIL, $message);
    }

    public static function skip(string $message): self
    {
        return new self(self::SKIP, $message);
    }
}
