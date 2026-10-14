<?php

namespace Lathe\Testing;

use Lathe\Actions\Action;
use Lathe\Actions\ActionResult;
use PHPUnit\Framework\Assert;

/**
 * Assertions on an action class and on the ActionResult its handle() returns.
 */
trait ActionAssertions
{
    protected function assertActionExists(string $class): void
    {
        Assert::assertTrue(is_subclass_of($class, Action::class), "{$class} is not a Lathe action.");
    }

    /**
     * The type is message or danger for the toast, or the effect: redirect, visit, download, modal, emit or deleted.
     */
    protected function assertActionResultType(ActionResult $result, string $type): void
    {
        $data = $result->jsonSerialize();
        $actual = match ($type) {
            'message' => $data['message'] !== null && $data['level'] === 'success' ? 'message' : null,
            'danger' => $data['message'] !== null && $data['level'] === 'danger' ? 'danger' : null,
            default => $data['effect']['type'] ?? null,
        };

        Assert::assertSame($type, $actual, "The action result is not a {$type} result.");
    }

    /**
     * A success is a message with no danger level.
     */
    protected function assertActionSuccess(ActionResult $result): void
    {
        $this->assertActionResultType($result, 'message');
    }
}
