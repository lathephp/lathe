<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Tests\Fixtures\Models\Gadget;
use Lathe\Tests\Fixtures\Models\Invoice;
use Lathe\Tests\Fixtures\Policies\BatchPolicy;
use Lathe\Tests\Fixtures\Policies\CreateUpdatePolicy;
use Lathe\Tests\Fixtures\Policies\EmptyPolicy;
use Lathe\Tests\Fixtures\Resources\Invoice as InvoiceResource;
use Workbench\App\Models\User;

function authorization(): ResourceAuthorization
{
    return app(ResourceAuthorization::class);
}

function someone(): User
{
    return (new User)->forceFill(['id' => 7, 'email' => 'someone@example.test']);
}

it('denies every ability of a Resource whose model has no policy', function (string $ability) {
    expect(authorization()->allows(someone(), InvoiceResource::class, $ability, new Invoice))->toBeFalse();
})->with(['viewAny', 'view', 'create', 'update', 'delete', 'replicate']);

it('names allowAll() when it denies for want of a policy', function () {
    expect(authorization()->denialMessage(InvoiceResource::class))->toContain('->allowAll()');
});

it('denies each ability whose policy method is missing', function (string $ability) {
    Gate::policy(Invoice::class, EmptyPolicy::class);

    expect(authorization()->allows(someone(), InvoiceResource::class, $ability, new Invoice))->toBeFalse();
})->with(['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'attachTags', 'inlineEdit', 'export', 'import']);

it('lets replicate fall back to create and update both passing', function () {
    Gate::policy(Invoice::class, CreateUpdatePolicy::class);
    CreateUpdatePolicy::$update = true;
    expect(authorization()->allows(someone(), InvoiceResource::class, 'replicate', new Invoice))->toBeTrue();

    CreateUpdatePolicy::$update = false;
    expect(authorization()->allows(someone(), InvoiceResource::class, 'replicate', new Invoice))->toBeFalse();
});

it('denies a request with no user', function () {
    Gate::policy(Invoice::class, CreateUpdatePolicy::class);

    expect(authorization()->allows(null, InvoiceResource::class, 'create'))->toBeFalse();
});

it('skips every check for a Resource that calls allowAll(), with one boot warning', function () {
    Log::spy();
    $open = new class extends Resource
    {
        protected function configure(ResourceDefinition $definition): ResourceDefinition
        {
            return $definition->model(Gadget::class)->allowAll();
        }

        public function fields(LatheRequest $request): array
        {
            return [];
        }
    };
    Lathe::registerResources($open::class);
    Lathe::resources()->manifest();
    Lathe::resources()->manifest();

    expect(authorization()->allows(someone(), $open::class, 'forceDelete', new Gadget))->toBeTrue();
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, $open::class));
});

it('hides a Resource with canSee(false) whatever the policy says', function () {
    Gate::policy(Invoice::class, CreateUpdatePolicy::class);
    $hidden = new class extends Resource
    {
        protected function configure(ResourceDefinition $definition): ResourceDefinition
        {
            return $definition->model(Invoice::class)->allowAll()->canSee(fn (Request $request) => false);
        }

        public function fields(LatheRequest $request): array
        {
            return [];
        }
    };

    expect(authorization()->canSee(Request::create('/lathe'), $hidden::class))->toBeFalse()
        ->and(authorization()->canSee(Request::create('/lathe'), InvoiceResource::class))->toBeTrue();
});

it('answers a page of abilities in one batched call when the policy has abilitiesFor', function () {
    Gate::policy(Invoice::class, BatchPolicy::class);
    BatchPolicy::$batchCalls = BatchPolicy::$rowCalls = 0;
    $rows = collect([1, 2, 3])->map(fn (int $id) => (new Invoice)->forceFill(['id' => $id]));

    $abilities = authorization()->abilitiesFor(someone(), InvoiceResource::class, $rows, ['view', 'update', 'delete']);

    expect(BatchPolicy::$batchCalls)->toBe(1)
        ->and(BatchPolicy::$rowCalls)->toBe(0)
        ->and($abilities[2])->toBe(['view' => true, 'update' => true, 'delete' => false])
        ->and($abilities[3])->toBe(['view' => true, 'update' => false, 'delete' => false]);
});

it('asks the gate per row and ability when the policy has no abilitiesFor', function () {
    Gate::policy(Invoice::class, CreateUpdatePolicy::class);
    CreateUpdatePolicy::$update = true;
    $rows = collect([1, 2])->map(fn (int $id) => (new Invoice)->forceFill(['id' => $id]));

    expect(authorization()->abilitiesFor(someone(), InvoiceResource::class, $rows, ['view', 'update']))
        ->toBe([1 => ['view' => false, 'update' => true], 2 => ['view' => false, 'update' => true]]);
});

it('calls the policy a Resource names in \$policy', function () {
    $named = new class extends InvoiceResource
    {
        public static ?string $policy = CreateUpdatePolicy::class;
    };
    CreateUpdatePolicy::$update = true;

    expect(authorization()->allows(someone(), $named::class, 'update', new Invoice))->toBeTrue()
        ->and(authorization()->allows(someone(), InvoiceResource::class, 'update', new Invoice))->toBeFalse();
});
