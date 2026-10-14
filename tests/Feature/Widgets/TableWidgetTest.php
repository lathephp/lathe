<?php

use Lathe\Enums\Icon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\MetricTableRow;
use Lathe\Widgets\TableWidget;

function tableCard(array $columns, array $rows): TableWidget
{
    return new class($columns, $rows) extends TableWidget
    {
        public function __construct(private array $columnList, private array $rowList)
        {
            parent::__construct();
        }

        public function columns(): array
        {
            return $this->columnList;
        }

        public function rows(LatheRequest $request): array
        {
            return $this->rowList;
        }
    };
}

it('sends the column align and drops the unused sortable key', function () {
    $card = tableCard([
        ['key' => 'name', 'label' => 'Name', 'align' => 'left', 'sortable' => true],
        ['key' => 'plan', 'label' => 'Plan', 'align' => 'center'],
        ['key' => 'total', 'label' => 'Total', 'align' => 'right', 'sortable' => false],
    ], []);

    expect($card->resolve(LatheRequest::create('/'))['columns'])->toBe([
        ['key' => 'name', 'label' => 'Name', 'align' => 'left'],
        ['key' => 'plan', 'label' => 'Plan', 'align' => 'center'],
        ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
    ]);
});

it('sends 100 of 150 rows', function () {
    $rows = array_map(fn (int $number) => ['n' => $number], range(1, 150));

    $sent = tableCard([['key' => 'n', 'label' => 'N']], $rows)->resolve(LatheRequest::create('/'))['rows'];

    expect($sent)->toHaveCount(100)
        ->and($sent[99])->toBe(['n' => 100]);
});

it('says "No Results Found..." for an empty table until emptyText() changes it', function () {
    $request = LatheRequest::create('/');
    $card = tableCard([['key' => 'n', 'label' => 'N']], []);

    expect($card->resolve($request)['emptyText'])->toBe('No Results Found...')
        ->and($card->emptyText('Nothing yet')->resolve($request)['emptyText'])->toBe('Nothing yet');
});

it('sizes a table card to its content', function () {
    expect(tableCard([], [])->descriptor())->toMatchArray(['height' => 'dynamic', 'component' => 'table-widget']);
});

it('sends a row object with its icon, title, subtitle and action links', function () {
    $row = MetricTableRow::make()->icon(Icon::Box)->iconClass('text-primary')->title('Ada')->subtitle('ada@example.com')
        ->actions(fn () => [['label' => 'Open', 'url' => '/lathe/customers/1']]);

    $sent = tableCard([['key' => 'name', 'label' => 'Name']], [$row, ['name' => 'Plain']])->resolve(LatheRequest::create('/'))['rows'];

    expect($sent)->toBe([
        [
            'title' => 'Ada',
            'subtitle' => 'ada@example.com',
            'icon' => 'box',
            'iconClass' => 'text-primary',
            'actions' => [['label' => 'Open', 'url' => '/lathe/customers/1']],
        ],
        ['name' => 'Plain'],
    ]);
});

it('leaves empty parts out of a row', function () {
    expect(MetricTableRow::make()->title('Ada')->jsonSerialize())->toBe(['title' => 'Ada']);
});
