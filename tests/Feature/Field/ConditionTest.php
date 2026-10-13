<?php

namespace Lathe\Tests\Feature\Field;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lathe\Field\Condition;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Lathe\Testing\LatheTest;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

class ConditionCustomer extends Model
{
    protected $table = 'condition_customers';

    protected $guarded = [];
}

class ConditionCustomers extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(ConditionCustomer::class)->title('name')->allowAll();
    }

    public function fields(LatheRequest $request): array
    {
        $company = Condition::field('type')->eq('company');

        return [
            Field::text('Name')->required(),
            Field::select('Type')->options(['person', 'company'])->required(),
            Field::text('Company name')->visibleWhen($company)->requiredWhen($company),
            Field::text('Vat')->readonlyWhen(Condition::field('type')->eq('person')),
        ];
    }
}

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Schema::create('condition_customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('type');
        $table->string('company_name')->nullable();
        $table->string('vat')->nullable();
        $table->timestamps();
    });
    Gate::define('viewLathe', fn (User $user) => true);
    Lathe::registerResources(ConditionCustomers::class);
    $this->actingAs(User::where('email', 'admin@lathephp.com')->firstOrFail());
});

it('evaluates each operator against form values', function (Condition $condition, array $values, bool $expected) {
    expect($condition->passes($values))->toBe($expected);
})->with([
    'eq' => [fn () => Condition::field('type')->eq('company'), ['type' => 'company'], true],
    'eq compares text' => [fn () => Condition::field('qty')->eq(1), ['qty' => '1'], true],
    'eq 0.4 is not 0' => [fn () => Condition::field('qty')->eq(0), ['qty' => '0.4'], false],
    'neq' => [fn () => Condition::field('type')->neq('company'), ['type' => 'person'], true],
    'in' => [fn () => Condition::field('type')->in(['a', 'b']), ['type' => 'b'], true],
    'notIn' => [fn () => Condition::field('type')->notIn(['a', 'b']), ['type' => 'b'], false],
    'gt' => [fn () => Condition::field('qty')->gt(5), ['qty' => '6'], true],
    'gte' => [fn () => Condition::field('qty')->gte(5), ['qty' => '5'], true],
    'lt' => [fn () => Condition::field('qty')->lt(5), ['qty' => '5'], false],
    'lte' => [fn () => Condition::field('qty')->lte(5), ['qty' => 5], true],
    'gt on text' => [fn () => Condition::field('qty')->gt(5), ['qty' => 'many'], false],
    'filled' => [fn () => Condition::field('note')->filled(), ['note' => 'x'], true],
    'blank' => [fn () => Condition::field('note')->blank(), ['note' => ''], true],
    'blank when missing' => [fn () => Condition::field('note')->blank(), [], true],
    'allOf' => [fn () => Condition::allOf(Condition::field('a')->eq(1), Condition::field('b')->eq(2)), ['a' => 1, 'b' => 3], false],
    'anyOf' => [fn () => Condition::anyOf(Condition::field('a')->eq(1), Condition::field('b')->eq(2)), ['a' => 0, 'b' => 2], true],
]);

it('sends the conditions to the browser', function () {
    $payload = Field::text('Company name')->visibleWhen(Condition::allOf(Condition::field('type')->eq('company')))->jsonSerialize();

    expect(json_decode((string) json_encode($payload['visibleWhen']), true))->toBe(['op' => 'all', 'conditions' => [['op' => 'eq', 'field' => 'type', 'value' => 'company']]]);
});

it('ignores a field its visibleWhen hides and skips its rules', function () {
    $id = LatheTest::resource('condition-customers')->store(['name' => 'Ann', 'type' => 'person', 'company_name' => 'Acme'])->assertCreated()->json('id');

    expect(ConditionCustomer::findOrFail($id)->company_name)->toBeNull();
});

it('requires a field while its requiredWhen holds, checked on the server', function () {
    LatheTest::resource('condition-customers')->store(['name' => 'Ann', 'type' => 'company', 'company_name' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors(['company_name']);

    $id = LatheTest::resource('condition-customers')->store(['name' => 'Ann', 'type' => 'company', 'company_name' => 'Acme'])->assertCreated()->json('id');
    expect(ConditionCustomer::findOrFail($id)->company_name)->toBe('Acme');
});

it('does not fill a field while its readonlyWhen holds', function () {
    $id = LatheTest::resource('condition-customers')->store(['name' => 'Ann', 'type' => 'person', 'vat' => 'X1'])->assertCreated()->json('id');

    expect(ConditionCustomer::findOrFail($id)->vat)->toBeNull();
});
