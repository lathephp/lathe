import { Icon, useTranslation } from '@lathephp/core';
import {
  Avatar,
  AvatarFallback,
  Badge,
  Button,
  Card,
  Checkbox,
  Input,
  Kbd,
  Label,
  Progress,
  RadioGroup,
  RadioGroupItem,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  Skeleton,
  Spinner,
  Switch,
  Tabs,
  TabsList,
  TabsTrigger,
  Textarea,
} from '@lathephp/ui';
import { Combobox, ComboboxChip, ComboboxChips, ComboboxChipsInput, ComboboxValue } from '@lathephp/ui/combobox';
import { Field, FieldDescription, FieldLabel } from '@lathephp/ui/field';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput, InputGroupText } from '@lathephp/ui/input-group';
import type { ReactNode } from 'react';

// The six primitive boards of the design, drawn with the kit's own components. A hover, a press
// or a keyboard focus cannot be held still on a page, so those columns put the state's look on
// with important utilities; the Rest column shows the real thing to hover, press and tab to.

// The kit's button styles an icon through a [data-icon] svg, which core's Icon does not take,
// so the icon sits in a wrapper that carries the attribute and adds no box of its own.
function Slot({ side, children }: { side: 'inline-start' | 'inline-end'; children: ReactNode }) {
  return (
    <span data-icon={side} className="contents">
      {children}
    </span>
  );
}

function Board({ name, title, description, children }: { name: string; title: string; description: string; children: ReactNode }) {
  return (
    <section data-board={name} className="flex flex-col gap-8">
      <div className="flex flex-col gap-1">
        <h2 className="m-0 text-[22px] leading-7 font-[650] tracking-[-0.01em]">{title}</h2>
        <p className="m-0 text-sm text-muted-foreground">{description}</p>
      </div>
      {children}
    </section>
  );
}

function Panel({ title, className = '', children }: { title?: string; className?: string; children: ReactNode }) {
  return (
    <Card className={`gap-4 p-6 ${className}`}>
      {title !== undefined && <h3 className="m-0 text-sm leading-5 font-semibold">{title}</h3>}
      {children}
    </Card>
  );
}

function Head({ children }: { children?: ReactNode }) {
  return <span className="text-xs leading-4 font-medium text-muted-foreground">{children}</span>;
}

function Note({ children }: { children: ReactNode }) {
  return <span className="block text-xs leading-4 text-muted-foreground">{children}</span>;
}

function RowLabel({ title, description }: { title: string; description?: string }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-sm leading-5 font-medium">{title}</span>
      {description !== undefined && <span className="text-xs leading-4 text-muted-foreground">{description}</span>}
    </div>
  );
}

function ErrorText({ children, size = 'xs' }: { children: ReactNode; size?: 'xs' | 'sm' }) {
  return (
    <span className={`flex gap-1 text-destructive-subtle-foreground ${size === 'xs' ? 'items-start text-xs leading-4' : 'items-center text-sm'}`}>
      <Icon name="alert-circle" className={size === 'xs' ? 'mt-px size-3.5 flex-none' : 'size-3.5 flex-none'} />
      {children}
    </span>
  );
}

type ButtonVariant = 'default' | 'outline' | 'ghost' | 'destructive' | 'destructive-solid' | 'link';

const pressedShadow = 'shadow-[inset_0_1px_2px_#0000001f]!';
const solidPressed = 'brightness-85! shadow-[inset_0_1px_2px_#0000003d]!';
const focusRing = 'ring-2! ring-focus! ring-offset-2! ring-offset-background!';

const held: Record<ButtonVariant, { hover: string; pressed: string }> = {
  default: { hover: 'brightness-92!', pressed: solidPressed },
  outline: { hover: 'bg-accent! text-accent-foreground!', pressed: `bg-muted! ${pressedShadow}` },
  ghost: { hover: 'bg-accent! text-accent-foreground!', pressed: 'bg-muted!' },
  destructive: { hover: 'bg-destructive-subtle!', pressed: `bg-destructive-subtle! ${pressedShadow}` },
  'destructive-solid': { hover: 'brightness-92!', pressed: solidPressed },
  link: { hover: 'text-link-hover! underline!', pressed: 'text-link-hover! underline!' },
};

function ButtonsBoard() {
  const { __ } = useTranslation();
  const rows: { title: string; description: string; variant: ButtonVariant; label: string; pending: string | null; note?: string }[] = [
    { title: __('Primary'), description: __('The one main action'), variant: 'default', label: __('Create order'), pending: __('Creating…') },
    { title: __('Secondary'), description: __('Every other action'), variant: 'outline', label: __('Export'), pending: __('Exporting…') },
    { title: __('Ghost'), description: __('Cancel, toolbars, menus'), variant: 'ghost', label: __('Cancel'), pending: null, note: __('Not used: ghost actions finish at once') },
    { title: __('Destructive'), description: __('Delete, cancel order'), variant: 'destructive', label: __('Delete order'), pending: null, note: __('Opens a confirm dialog first') },
    { title: __('Destructive, solid'), description: __('Confirm dialogs only'), variant: 'destructive-solid', label: __('Delete order'), pending: __('Deleting…') },
    { title: __('Link'), description: __('Inline navigation'), variant: 'link', label: __('View all orders'), pending: null, note: __('Navigation shows in the page, not the link') },
  ];

  return (
    <Board name="buttons" title={__('Buttons')} description={__("One primary per view, rightmost. Sentence case. Pressed and pending show on pointerdown; pending keeps the button's width.")}>
      <Panel className="grid! grid-cols-[150px_repeat(6,minmax(0,1fr))] items-center gap-x-3! gap-y-4!">
        <span />
        <Head>{__('Rest')}</Head>
        <Head>{__('Hover')}</Head>
        <Head>{__('Pressed')}</Head>
        <Head>{__('Keyboard focus')}</Head>
        <Head>{__('Disabled')}</Head>
        <Head>{__('Pending')}</Head>
        {rows.map((row) => (
          <div key={row.variant} className="contents">
            <RowLabel title={row.title} description={row.description} />
            <div>
              <Button variant={row.variant}>{row.label}</Button>
            </div>
            <div>
              <Button variant={row.variant} className={held[row.variant].hover}>
                {row.label}
              </Button>
            </div>
            <div>
              <Button variant={row.variant} className={held[row.variant].pressed}>
                {row.label}
              </Button>
            </div>
            <div>
              <Button variant={row.variant} className={focusRing}>
                {row.label}
              </Button>
            </div>
            <div>
              <Button variant={row.variant} disabled>
                {row.label}
              </Button>
            </div>
            <div>
              {row.pending === null ? (
                <Note>{row.note}</Note>
              ) : (
                <Button variant={row.variant} aria-busy="true" className="min-w-[116px]">
                  <Spinner aria-label={row.pending} />
                  {row.pending}
                </Button>
              )}
            </div>
          </div>
        ))}
      </Panel>
      <div className="grid grid-cols-3 gap-6">
        <Panel title={__('Sizes')}>
          <div className="flex flex-wrap items-center gap-2">
            <Button size="sm">{__('Small')}</Button>
            <Button>{__('Default')}</Button>
            <Button size="lg">{__('Large')}</Button>
          </div>
          <p className="m-0 text-sm text-muted-foreground">{__('28px in toolbars and table rows, 34px by default, 40px on sign-in and single-form pages.')}</p>
        </Panel>
        <Panel title={__('With icons')}>
          <div className="flex flex-wrap items-center gap-2">
            <Button>
              <Slot side="inline-start">
                <Icon name="plus" className="size-4" />
              </Slot>
              {__('Create order')}
            </Button>
            <Button variant="outline">
              {__('Actions')}
              <Slot side="inline-end">
                <Icon name="chevron-down" className="size-4 text-muted-foreground" />
              </Slot>
            </Button>
          </div>
          <div className="flex items-center gap-1">
            <Button variant="ghost" size="icon" aria-label={__('Edit order')}>
              <Icon name="pencil" className="size-4" />
            </Button>
            <Button variant="ghost" size="icon" aria-label={__('Download CSV')} className={held.ghost.hover}>
              <Icon name="download" className="size-4" />
            </Button>
            <Button variant="ghost" size="icon" aria-label={__('More actions')}>
              <Icon name="ellipsis" className="size-4" />
            </Button>
            <span className="ml-2 text-xs leading-4 text-muted-foreground">{__('Icon-only: ghost, muted icon, a tooltip and an aria-label')}</span>
          </div>
        </Panel>
        <Panel title={__('In place')}>
          <div className="flex flex-col gap-1.5">
            <Head>{__('Page header')}</Head>
            <div className="flex justify-end gap-2">
              <Button variant="outline">{__('Import')}</Button>
              <Button>{__('Create order')}</Button>
            </div>
          </div>
          <div className="flex flex-col gap-1.5">
            <Head>{__('Confirm dialog footer')}</Head>
            <div className="flex justify-end gap-2">
              <Button variant="outline">{__('Keep order')}</Button>
              <Button variant="destructive-solid">{__('Delete order')}</Button>
            </div>
          </div>
          <p className="m-0 text-sm text-muted-foreground">{__('Paired buttons share one height. The safe choice names what it keeps.')}</p>
        </Panel>
      </div>
    </Board>
  );
}

function InputsBoard() {
  const { __ } = useTranslation();

  return (
    <Board name="inputs" title={__('Inputs')} description={__('Labels sit above fields. The border holds 3:1 so a field reads as a field on its own. An error says how to fix it and clears as soon as the value is valid.')}>
      <Panel className="grid! grid-cols-7 gap-x-3! gap-y-2.5!">
        <Head>{__('Rest')}</Head>
        <Head>{__('Hover')}</Head>
        <Head>{__('Focus')}</Head>
        <Head>{__('Filled')}</Head>
        <Head>{__('Disabled')}</Head>
        <Head>{__('Read-only')}</Head>
        <Head>{__('Error')}</Head>
        <Input aria-label={__('Rest')} placeholder="ORD-00000000" />
        <Input aria-label={__('Hover')} placeholder="ORD-00000000" className="border-muted-foreground!" />
        <Input aria-label={__('Focus')} defaultValue="ORD-7919" className="border-ring! shadow-none! ring-1! ring-ring!" />
        <Input aria-label={__('Filled')} defaultValue="ORD-79194426" />
        <Input aria-label={__('Disabled')} defaultValue="ORD-79194426" disabled />
        <Input aria-label={__('Read-only')} defaultValue="ORD-79194426" readOnly />
        <div className="flex flex-col gap-1.5">
          <Input aria-label={__('Error')} defaultValue="ORD-7919" aria-invalid="true" />
          <ErrorText>{__('Use 8 digits after ORD-')}</ErrorText>
        </div>
      </Panel>
      <div className="grid grid-cols-2 gap-6">
        <Panel title={__('A field')} className="gap-5!">
          <Field>
            <FieldLabel htmlFor="gallery-number">{__('Order number')}</FieldLabel>
            <Input id="gallery-number" defaultValue="ORD-79194426" />
            <FieldDescription>{__('Shown to the customer on every email.')}</FieldDescription>
          </Field>
          <div className="flex flex-col gap-1.5">
            <div className="flex items-baseline gap-2">
              <Label htmlFor="gallery-notes">{__('Notes')}</Label>
              <span className="text-xs leading-4 text-muted-foreground">{__('Optional')}</span>
            </div>
            <Textarea id="gallery-notes" rows={3} placeholder={__('Anything the packer should know')} />
            <span className="self-end text-xs leading-4 text-muted-foreground tabular-nums">0 / 500</span>
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="gallery-total">{__('Total')}</Label>
            <InputGroup>
              <InputGroupAddon>
                <InputGroupText>$</InputGroupText>
              </InputGroupAddon>
              <InputGroupInput id="gallery-total" aria-invalid="true" defaultValue="-12.00" className="text-right tabular-nums" />
            </InputGroup>
            <ErrorText size="sm">{__('Enter a total of 0 or more')}</ErrorText>
          </div>
        </Panel>
        <Panel title={__('Add-ons, in place of browser controls')}>
          <InputGroup>
            <InputGroupAddon>
              <Icon name="search" className="size-4 text-muted-foreground" />
            </InputGroupAddon>
            <InputGroupInput aria-label={__('Search orders')} placeholder={__('Search orders')} />
            <InputGroupAddon align="inline-end">
              <Kbd>/</Kbd>
            </InputGroupAddon>
          </InputGroup>
          <InputGroup>
            <InputGroupAddon>
              <InputGroupText>$</InputGroupText>
            </InputGroupAddon>
            <InputGroupInput aria-label={__('Price')} defaultValue="1,649.89" className="text-right tabular-nums" />
            <InputGroupAddon align="inline-end">
              <InputGroupText>USD</InputGroupText>
            </InputGroupAddon>
          </InputGroup>
          <button type="button" aria-label={__('Placed on')} className="cn-input flex items-center gap-2 text-left outline-none">
            <Icon name="calendar" className="size-4 text-muted-foreground" />
            <span className="flex-1">Oct 17, 2026, 23:38</span>
            <span className="text-xs leading-4 text-muted-foreground">UTC</span>
          </button>
          <InputGroup>
            <InputGroupInput aria-label={__('Customer name')} defaultValue="Ann Taylor" />
            <InputGroupAddon align="inline-end">
              <InputGroupButton size="icon-xs" aria-label={__('Clear')}>
                <Icon name="x" className="size-3.5" />
              </InputGroupButton>
            </InputGroupAddon>
          </InputGroup>
          <div className="flex items-center gap-2.5">
            <Button variant="outline">
              <Slot side="inline-start">
                <Icon name="upload" className="size-4" />
              </Slot>
              {__('Choose file')}
            </Button>
            <span className="text-sm text-muted-foreground">cover.jpg · 412 KB</span>
          </div>
        </Panel>
      </div>
      <Panel className="flex-row! items-end gap-6!">
        <h3 className="m-0 w-[120px] self-center text-sm leading-5 font-semibold">{__('Sizes')}</h3>
        <div className="flex flex-1 flex-col gap-1.5">
          <Note>{__('Small, 28px: toolbars')}</Note>
          <Input data-size="sm" aria-label={__('Small')} placeholder={__('Search orders')} />
        </div>
        <div className="flex flex-1 flex-col gap-1.5">
          <Note>{__('Default, 34px: forms')}</Note>
          <Input aria-label={__('Default')} placeholder={__('Customer name')} />
        </div>
        <div className="flex flex-1 flex-col gap-1.5">
          <Note>{__('Large, 40px: sign-in')}</Note>
          <Input data-size="lg" type="email" aria-label={__('Large')} placeholder="you@company.com" />
        </div>
      </Panel>
    </Board>
  );
}

const statuses = [
  { value: 'pending', label: 'Pending', dot: 'bg-warning' },
  { value: 'paid', label: 'Paid', dot: 'bg-info' },
  { value: 'shipped', label: 'Shipped', dot: 'bg-success' },
  { value: 'cancelled', label: 'Cancelled', dot: 'bg-chart-other' },
];

function StatusSelect({ placeholder, value, className, disabled = false, invalid = false }: { placeholder: string; value?: string; className?: string; disabled?: boolean; invalid?: boolean }) {
  const { __ } = useTranslation();

  return (
    <Select items={statuses.map((status) => ({ value: status.value, label: __(status.label) }))} defaultValue={value ?? null} disabled={disabled}>
      <SelectTrigger aria-label={__('Status')} aria-invalid={invalid || undefined} className={`w-full ${className ?? ''}`}>
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        {statuses.map((status) => (
          <SelectItem key={status.value} value={status.value}>
            {__(status.label)}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

const openRing = 'border-ring! shadow-none! ring-1! ring-ring!';

function SelectsBoard() {
  const { __ } = useTranslation();
  const customers = [
    ['Ann', ' Taylor', '', 'ann.taylor@gmail.com · 21 orders'],
    ['Ann', 'a Kowalski', '', 'anna.kowalski@gmail.com · 4 orders'],
    ['Ann', 'ette Fox', '', 'annette.fox@gmail.com · 9 orders'],
    ['', 'Adrain Hartm', 'ann', 'adrain.hartmann@example.com · 2 orders'],
  ];
  const tags: [string, number, boolean][] = [
    ['sale', 48, true],
    ['featured', 31, true],
    ['new arrival', 22, false],
    ['clearance', 9, false],
  ];

  return (
    <Board name="selects" title={__('Selects and pickers')} description={__('No browser select anywhere. A list longer than ten searches; a related record searches on the server and shows a second line to tell look-alikes apart. The chosen option carries a check.')}>
      <Panel className="grid! grid-cols-6 gap-x-3! gap-y-2.5!">
        <Head>{__('Placeholder')}</Head>
        <Head>{__('Chosen')}</Head>
        <Head>{__('Hover')}</Head>
        <Head>{__('Open')}</Head>
        <Head>{__('Disabled')}</Head>
        <Head>{__('Error')}</Head>
        <StatusSelect placeholder={__('Choose a status')} />
        <StatusSelect placeholder={__('Choose a status')} value="pending" />
        <StatusSelect placeholder={__('Choose a status')} value="pending" className="border-muted-foreground!" />
        <StatusSelect placeholder={__('Choose a status')} value="pending" className={openRing} />
        <StatusSelect placeholder={__('Choose a status')} value="pending" disabled />
        <div className="flex flex-col gap-1.5">
          <StatusSelect placeholder={__('Choose a customer')} invalid />
          <span className="text-xs leading-4 text-destructive-subtle-foreground">{__('Choose who placed the order')}</span>
        </div>
      </Panel>
      <div className="grid grid-cols-3 items-start gap-6">
        <div className="flex flex-col gap-1.5">
          <Head>{__('Select, open')}</Head>
          <StatusSelect placeholder={__('Choose a status')} value="pending" className={openRing} />
          {/* The open list held still: visual fixture, do not copy. */}
          <div role="listbox" aria-label={__('Status')} className="cn-select-content flex flex-col">
            {[...statuses, { value: 'refunded', label: 'Refunded', dot: '' }].map((status) => (
              <div
                key={status.value}
                role="option"
                aria-selected={status.value === 'pending'}
                aria-disabled={status.value === 'refunded' || undefined}
                data-highlighted={status.value === 'paid' ? '' : undefined}
                className={`cn-select-item relative flex w-full items-center ${status.value === 'refunded' ? 'text-muted-foreground' : ''}`}
              >
                <span className={`size-2 flex-none rounded-full ${status.dot === '' ? 'border border-input' : status.dot}`} />
                <span className="flex-1">{__(status.label)}</span>
                {status.value === 'refunded' && <span className="text-xs">{__('Paid orders only')}</span>}
                {status.value === 'pending' && (
                  <span className="cn-select-item-indicator">
                    <Icon name="check" className="size-4" />
                  </span>
                )}
              </div>
            ))}
          </div>
        </div>
        <div className="flex flex-col gap-1.5">
          <Head>{__('Related record picker, searching')}</Head>
          <StatusSelect placeholder={__('Choose a customer')} className={openRing} />
          {/* The open list held still: visual fixture, do not copy. A real picker is a Combobox with ComboboxContent. */}
          <div className="cn-combobox-content flex flex-col">
            <InputGroup>
              <InputGroupAddon>
                <Icon name="search" className="size-4 text-muted-foreground" />
              </InputGroupAddon>
              <InputGroupInput aria-label={__('Search customers')} defaultValue="Ann" />
            </InputGroup>
            <div role="listbox" aria-label={__('Customers')} className="flex flex-col p-1">
              {customers.map(([before, middle, after, second], index) => (
                <div key={second} role="option" aria-selected={false} data-highlighted={index === 0 ? '' : undefined} className="cn-combobox-item relative flex w-full flex-col items-start! gap-0!">
                  <span className="text-sm leading-5">
                    <strong className="font-semibold">{before}</strong>
                    {middle}
                    <strong className="font-semibold">{after}</strong>
                  </span>
                  <span className="text-xs leading-4 text-muted-foreground">{second}</span>
                </div>
              ))}
            </div>
            <div className="flex items-center justify-between border-t border-border bg-muted px-3 py-2">
              <span className="text-xs leading-4 text-muted-foreground tabular-nums">{__('4 of 1,000 customers')}</span>
              <Button variant="link" size="sm" className="gap-1!">
                <Icon name="plus" className="size-3.5" />
                {__('Create customer')}
              </Button>
            </div>
          </div>
        </div>
        <div className="flex flex-col gap-1.5">
          <Head>{__('Multi-select with chips')}</Head>
          <Combobox multiple defaultValue={['sale', 'featured']}>
            <ComboboxChips className="border-ring! shadow-none! ring-1! ring-ring!">
              <ComboboxValue>
                {(selected: string[]) => (
                  <>
                    {selected.map((tag) => (
                      <ComboboxChip key={tag} value={tag} removeLabel={__('Remove :tag', { tag })}>
                        {tag}
                      </ComboboxChip>
                    ))}
                    <ComboboxChip showRemove={false} className="text-muted-foreground!">
                      +2
                    </ComboboxChip>
                    <ComboboxChipsInput aria-label={__('Add tags')} placeholder={__('Add tags')} />
                  </>
                )}
              </ComboboxValue>
            </ComboboxChips>
          </Combobox>
          {/* The open list held still: visual fixture, do not copy. */}
          <div role="listbox" aria-multiselectable="true" aria-label={__('Tags')} className="cn-select-content flex flex-col">
            {tags.map(([tag, count, checked], index) => (
              <div key={tag} role="option" aria-selected={checked} data-highlighted={index === 1 ? '' : undefined} className="cn-select-item relative flex w-full items-center pr-2!">
                <Checkbox checked={checked} aria-label={tag} />
                <span className="flex-1">{tag}</span>
                <span className="text-xs text-muted-foreground tabular-nums">{count}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
      <div className="grid grid-cols-[2fr_1fr_1fr] items-start gap-6">
        <div className="flex flex-col gap-1.5">
          <Head>{__('In a table row: the status is a badge that opens a menu')}</Head>
          <div className="grid h-12 grid-cols-[140px_minmax(0,1fr)_130px_100px] items-center gap-3 rounded-[10px] border border-border bg-card px-4">
            <span className="text-sm font-medium">ORD-79194426</span>
            <a href="#customer" className="truncate text-sm text-link no-underline hover:text-link-hover">
              Dr. Russ Schmeler
            </a>
            <Badge variant="warning" render={<button type="button" aria-label={__('Status: Pending. Change status')} />} className="h-6! justify-self-start pr-1!">
              <span className="size-1.5 rounded-full bg-warning" />
              {__('Pending')}
              <Icon name="chevron-down" className="size-3.5" />
            </Badge>
            <span className="text-right text-sm tabular-nums">$1,649.89</span>
          </div>
        </div>
        <div className="flex flex-col gap-1.5">
          <Head>{__('Nothing matches')}</Head>
          <div className="cn-popover-content items-center gap-2! p-4! text-center">
            <span className="text-sm text-muted-foreground">{__('No customers match “Zed”')}</span>
            <Button variant="outline" size="sm" className="shadow-none!">
              {__('Create “Zed”')}
            </Button>
          </div>
        </div>
        <div className="flex flex-col gap-1.5">
          <Head>{__('Searching')}</Head>
          <div className="cn-popover-content gap-1.5! p-2!">
            <Skeleton className="mx-1 mt-1 h-3.5 w-[70%]" />
            <Skeleton className="mx-1 mb-1.5 h-2.5 w-1/2" />
            <Skeleton className="mx-1 h-3.5 w-[60%]" />
            <Skeleton className="mx-1 mb-1 h-2.5 w-2/5" />
          </div>
        </div>
      </div>
    </Board>
  );
}

function Count({ children, tone = 'muted' }: { children: ReactNode; tone?: 'muted' | 'primary' }) {
  return (
    <span className={`inline-flex h-5 items-center rounded-[6px] px-1.5 text-xs tabular-nums ${tone === 'muted' ? 'bg-muted font-medium text-muted-foreground' : 'bg-primary-subtle font-semibold text-primary-subtle-foreground'}`}>
      {children}
    </span>
  );
}

function FilterChip({ name, value, remove }: { name: string; value: string; remove: string }) {
  return (
    <span className="inline-flex h-[26px] items-center gap-1 rounded-full border border-border bg-card pr-0.5 pl-2.5 text-sm">
      <span className="text-muted-foreground">{name}</span>
      <strong className="font-medium">{value}</strong>
      <button type="button" aria-label={remove} className="inline-flex size-[22px] items-center justify-center rounded-full text-muted-foreground hover:bg-accent">
        <Icon name="x" className="size-3" />
      </button>
    </span>
  );
}

function BadgesBoard() {
  const { __ } = useTranslation();
  const rows: { meaning: string; variant: 'warning' | 'info' | 'success' | 'secondary' | 'destructive'; dot: string; dotted: string; plain: string; use: string }[] = [
    { meaning: __('Waiting'), variant: 'warning', dot: 'bg-warning', dotted: __('Pending'), plain: __('Pending'), use: __('Pending, awaiting review, scheduled') },
    { meaning: __('In progress'), variant: 'info', dot: 'bg-info', dotted: __('Paid'), plain: __('Processing'), use: __('Paid, processing, running') },
    { meaning: __('Done'), variant: 'success', dot: 'bg-success', dotted: __('Shipped'), plain: __('Published'), use: __('Shipped, published, active') },
    { meaning: __('Ended'), variant: 'secondary', dot: 'bg-chart-other', dotted: __('Cancelled'), plain: __('Draft'), use: __('Cancelled, draft, archived: not an error') },
    { meaning: __('Failed'), variant: 'destructive', dot: 'bg-destructive', dotted: __('Failed'), plain: __('Payment failed'), use: __('Failed, rejected, overdue') },
  ];

  return (
    <Board name="badges" title={__('Badges and chips')} description={__('A badge always carries a word; colour never carries meaning alone. Status colours mean state, never the brand.')}>
      <div className="grid grid-cols-[1.3fr_1fr] gap-6">
        <Panel title={__('Status')}>
          <div className="grid grid-cols-[110px_130px_130px_minmax(0,1fr)] items-center gap-x-4 gap-y-3">
            <Head>{__('Meaning')}</Head>
            <Head>{__('With dot')}</Head>
            <Head>{__('Plain')}</Head>
            <Head>{__('Use for')}</Head>
            {rows.map((row) => (
              <div key={row.meaning} className="contents">
                <span className="text-sm leading-5">{row.meaning}</span>
                <Badge variant={row.variant} className="justify-self-start">
                  <span className={`size-1.5 rounded-full ${row.dot}`} />
                  {row.dotted}
                </Badge>
                <Badge variant={row.variant} className="justify-self-start">
                  {row.plain}
                </Badge>
                <span className="text-sm text-muted-foreground">{row.use}</span>
              </div>
            ))}
          </div>
        </Panel>
        <div className="flex flex-col gap-6">
          <Panel title={__('Counts')} className="gap-3.5!">
            <div className="flex items-center gap-1 border-b border-border">
              {[
                [__('All'), '10,000'],
                [__('Pending'), '2,424'],
                [__('Paid'), '2,552'],
              ].map(([label, count], index) => (
                <a key={label} href={`#tab-${String(index)}`} className={`inline-flex h-9 items-center gap-1.5 px-2.5 text-sm font-medium no-underline ${index === 0 ? 'text-foreground shadow-[inset_0_-2px_0_var(--primary)]' : 'text-muted-foreground hover:text-foreground'}`}>
                  {label}
                  <Count>{count}</Count>
                </a>
              ))}
            </div>
            <div className="flex items-center gap-4">
              <Button variant="outline" size="sm" className="gap-1.5! px-2! shadow-none!">
                <Icon name="filter" className="size-3.5" />
                {__('Filter')}
                <span className="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-primary px-1 text-[11px] font-semibold text-primary-foreground tabular-nums">2</span>
              </Button>
              <span className="inline-flex h-8 w-[180px] items-center gap-2.5 rounded-[6px] bg-sidebar pr-2 pl-2.5 text-sm font-medium text-sidebar-foreground">
                {__('Notifications')}
                <span className="ml-auto">
                  <Count tone="primary">12</Count>
                </span>
              </span>
              <span className="relative inline-flex size-[34px] items-center justify-center text-muted-foreground" role="img" aria-label={__('Notifications, 3 unread')}>
                <Icon name="bell" className="size-[18px]" />
                <span className="absolute top-1.5 right-[7px] size-2 rounded-full bg-brand shadow-[0_0_0_2px_var(--background)]" />
              </span>
            </div>
          </Panel>
          <Panel title={__('Change')} className="gap-3.5!">
            <div className="flex flex-wrap items-center gap-2.5">
              <Badge variant="success" className="gap-0.5! px-1.5! tabular-nums">
                <Icon name="arrow-up-right" className="size-3.5" />
                +8.2%
              </Badge>
              <Badge variant="destructive" className="gap-0.5! px-1.5! tabular-nums">
                <Icon name="arrow-down-right" className="size-3.5" />
                −3.1%
              </Badge>
              <Badge variant="success" className="gap-0.5! px-1.5! tabular-nums">
                <Icon name="arrow-down-right" className="size-3.5" />
                −4.0%
              </Badge>
              <Badge variant="secondary" className="px-1.5! tabular-nums">
                0.0%
              </Badge>
            </div>
            <p className="m-0 text-sm text-muted-foreground">{__('Up is green and down is red, unless the widget says down is good: refunds falling 4.0% is shown in green.')}</p>
          </Panel>
        </div>
      </div>
      <div className="grid grid-cols-[1.3fr_1fr] gap-6">
        <Panel title={__('Chips')} className="gap-3.5!">
          <div className="flex flex-wrap items-center gap-2">
            <FilterChip name={__('Status is')} value={__('Pending')} remove={__('Remove status filter')} />
            <FilterChip name={__('Customer is')} value="Ann Taylor" remove={__('Remove customer filter')} />
            <FilterChip name={__('Total')} value="$100 – $500" remove={__('Remove total filter')} />
            <Button variant="link" size="sm" className="ml-1">
              {__('Clear filters')}
            </Button>
          </div>
          <div className="flex flex-wrap items-center gap-1.5">
            <Badge variant="secondary">sale</Badge>
            <Badge variant="secondary">featured</Badge>
            <Badge variant="secondary">new arrival</Badge>
            <span className="ml-1.5 text-xs leading-4 text-muted-foreground">{__('Tags read as values, so they stay neutral')}</span>
          </div>
        </Panel>
        <Panel title={__('In a page title')} className="gap-2.5!">
          <span className="text-xs leading-4 text-muted-foreground">{__('Orders')} / ORD-79194426</span>
          <div className="flex items-center gap-2.5">
            <span className="text-[22px] leading-7 font-[650] tracking-[-0.01em]">ORD-79194426</span>
            <Badge variant="warning">
              <span className="size-1.5 rounded-full bg-warning" />
              {__('Pending')}
            </Badge>
          </div>
          <span className="text-sm text-muted-foreground">
            {__('Placed Oct 17, 2026, 23:38 by')}{' '}
            <a href="#customer" className="text-link no-underline hover:text-link-hover">
              Dr. Russ Schmeler
            </a>
          </span>
        </Panel>
      </div>
    </Board>
  );
}

function Radio({ label, checked = false, className, disabled = false, invalid = false }: { label: string; checked?: boolean; className?: string; disabled?: boolean; invalid?: boolean }) {
  return (
    <RadioGroup aria-label={label} defaultValue={checked ? 'on' : null} disabled={disabled} className="w-auto">
      <RadioGroupItem value="on" aria-label={label} aria-invalid={invalid || undefined} className={className} />
    </RadioGroup>
  );
}

function LabelledCheckbox({ title, description, checked }: { title: string; description: string; checked: boolean }) {
  return (
    <label className="flex cursor-pointer items-start gap-2.5">
      <Checkbox defaultChecked={checked} className="mt-0.5" />
      <span className="flex flex-col gap-0.5">
        <span className="text-sm leading-5 font-medium">{title}</span>
        <span className="text-sm text-muted-foreground">{description}</span>
      </span>
    </label>
  );
}

function ChoicesBoard() {
  const { __ } = useTranslation();
  const shipping = [
    ['standard', __('Standard'), __('3 to 5 business days'), '$4.99'],
    ['express', __('Express'), __('Next business day'), '$14.99'],
    ['pickup', __('Store pickup'), __('Ready in 2 hours'), __('Free')],
  ];

  return (
    <Board name="choices" title={__('Checkboxes, radios, switches')} description={__('Checked controls fill with primary. Each control has a 32px hit area even where it draws at 16px. A switch acts at once; a checkbox waits for Save.')}>
      <Panel className="grid! grid-cols-[120px_repeat(6,minmax(0,1fr))] items-center gap-x-3! gap-y-3.5!">
        <span />
        <Head>{__('Off')}</Head>
        <Head>{__('On')}</Head>
        <Head>{__('Mixed')}</Head>
        <Head>{__('Keyboard focus')}</Head>
        <Head>{__('Disabled')}</Head>
        <Head>{__('Error')}</Head>
        <span className="text-sm leading-5 font-medium">{__('Checkbox')}</span>
        <Checkbox aria-label={__('Off')} />
        <Checkbox aria-label={__('On')} defaultChecked />
        <Checkbox aria-label={__('Mixed')} indeterminate />
        <Checkbox aria-label={__('Focus')} defaultChecked className={focusRing} />
        <Checkbox aria-label={__('Disabled')} disabled />
        <Checkbox aria-label={__('Error')} aria-invalid="true" />
        <span className="text-sm leading-5 font-medium">{__('Radio')}</span>
        <Radio label={__('Off')} />
        <Radio label={__('On')} checked />
        <Note>{__('None')}</Note>
        <Radio label={__('Focus')} checked className={focusRing} />
        <Radio label={__('Disabled')} disabled />
        <Radio label={__('Error')} invalid />
        <span className="text-sm leading-5 font-medium">{__('Switch')}</span>
        <Switch aria-label={__('Off')} />
        <Switch aria-label={__('On')} defaultChecked />
        <Note>{__('None')}</Note>
        <Switch aria-label={__('Focus')} defaultChecked className={focusRing} />
        <Switch aria-label={__('Disabled')} disabled />
        <Note>{__('None: a switch is never invalid')}</Note>
      </Panel>
      <div className="grid grid-cols-3 gap-6">
        <Panel title={__('With labels')} className="gap-3.5!">
          <LabelledCheckbox title={__('Track inventory')} description={__('Stops selling at zero stock.')} checked />
          <LabelledCheckbox title={__('Featured on the storefront')} description={__('Shown on the home page for 30 days.')} checked={false} />
          <div className="h-px bg-border" />
          <label className="flex cursor-pointer items-center justify-between gap-3">
            <span className="flex flex-col gap-0.5">
              <span className="text-sm leading-5 font-medium">{__('Email the customer when it ships')}</span>
              <span className="text-sm text-muted-foreground">{__('Saves as soon as you switch it.')}</span>
            </span>
            <Switch defaultChecked />
          </label>
        </Panel>
        <Panel title={__('Radio cards')} className="gap-2.5!">
          <RadioGroup aria-label={__('Shipping method')} defaultValue="standard" className="gap-2.5!">
            {shipping.map(([value, title, description, price]) => (
              <label key={value} className="flex cursor-pointer items-start gap-2.5 rounded-md border border-border p-3 has-data-checked:border-primary has-data-checked:bg-primary-subtle">
                <RadioGroupItem value={value} className="mt-0.5" />
                <span className="flex flex-1 flex-col gap-0.5">
                  <span className="text-sm leading-5 font-medium">{title}</span>
                  <span className="text-sm text-muted-foreground">{description}</span>
                </span>
                <span className="text-sm leading-5 tabular-nums">{price}</span>
              </label>
            ))}
          </RadioGroup>
        </Panel>
        <Panel title={__('Segmented')}>
          <div className="flex flex-col gap-1.5">
            <Note>{__('Widget range')}</Note>
            <Tabs defaultValue="30">
              <TabsList aria-label={__('Range')}>
                <TabsTrigger value="7">{__('7 days')}</TabsTrigger>
                <TabsTrigger value="30">{__('30 days')}</TabsTrigger>
                <TabsTrigger value="365">{__('12 months')}</TabsTrigger>
              </TabsList>
            </Tabs>
          </div>
          <div className="flex flex-col gap-1.5">
            <Note>{__('Theme, in the user menu')}</Note>
            <Tabs defaultValue="system">
              <TabsList aria-label={__('Theme')}>
                <TabsTrigger value="light">
                  <Icon name="sun" className="size-3.5" />
                  {__('Light')}
                </TabsTrigger>
                <TabsTrigger value="dark">
                  <Icon name="moon" className="size-3.5" />
                  {__('Dark')}
                </TabsTrigger>
                <TabsTrigger value="system">
                  <Icon name="monitor" className="size-3.5" />
                  {__('System')}
                </TabsTrigger>
              </TabsList>
            </Tabs>
          </div>
          <div className="flex flex-col gap-1.5">
            <Note>{__('Icon-only, with tooltips')}</Note>
            <Tabs defaultValue="table">
              <TabsList aria-label={__('Layout')}>
                <TabsTrigger value="table" aria-label={__('Table')} className="w-[30px] px-0!">
                  <Icon name="rows" className="size-3.5" />
                </TabsTrigger>
                <TabsTrigger value="cards" aria-label={__('Cards')} className="w-[30px] px-0!">
                  <Icon name="grid" className="size-3.5" />
                </TabsTrigger>
              </TabsList>
            </Tabs>
          </div>
        </Panel>
      </div>
    </Board>
  );
}

function MiniPage({ step, tone, title, rows, bar = false, note }: { step: string; tone: 'primary' | 'success'; title: string; rows: 'muted' | 'shimmer' | string[]; bar?: boolean; note: string }) {
  const { __ } = useTranslation();

  return (
    <div className="flex flex-col gap-2">
      <span className={`text-xs leading-4 font-semibold tabular-nums ${tone === 'primary' ? 'text-primary-subtle-foreground' : 'text-success-subtle-foreground'}`}>{step}</span>
      <div className="relative flex h-[120px] overflow-hidden rounded-md border border-border bg-background">
        {bar && <div className="absolute top-0 left-0 h-[3px] w-[46%] bg-brand" />}
        <div className="flex w-[76px] flex-col gap-1 border-r border-sidebar-border bg-sidebar px-1.5 py-2 text-[10px] leading-[18px] text-sidebar-foreground">
          <span className="h-[18px] rounded-[4px] px-1.5">{__('Orders')}</span>
          <span className="h-[18px] rounded-[4px] bg-sidebar-accent px-1.5 font-semibold text-sidebar-accent-foreground shadow-[inset_2px_0_0_var(--brand)]">{__('Customers')}</span>
          <span className="h-[18px] rounded-[4px] px-1.5">{__('Products')}</span>
        </div>
        <div className="flex flex-1 flex-col gap-1.5 p-2">
          <span className="text-[11px] font-[650]">{title}</span>
          {rows === 'muted' && [0, 1, 2].map((row) => <div key={row} className="h-2 rounded-[3px] bg-muted" />)}
          {rows === 'shimmer' && [0, 1, 2].map((row) => <Skeleton key={row} className="h-2" />)}
          {Array.isArray(rows) &&
            rows.map((row) => (
              <span key={row} className="h-2 text-[8px] leading-2">
                {row}
              </span>
            ))}
        </div>
      </div>
      <span className="text-sm text-muted-foreground">{note}</span>
    </div>
  );
}

function FeedbackBoard() {
  const { __ } = useTranslation();

  return (
    <Board name="feedback" title={__('Loading and small parts')} description={__('A click answers within 50ms whether or not the server has. Fast visits never flash a bar or a skeleton; slow ones never leave the old page looking ready.')}>
      <Panel title={__('From click to page: Orders to Customers')} className="gap-3.5! px-6! py-5!">
        <div className="grid grid-cols-4 gap-4">
          <MiniPage step={__('0 ms · pointerdown')} tone="primary" title={__('Orders')} rows="muted" note={__('The nav item turns active at once. Hovering a link for 75ms already fetched the page.')} />
          <MiniPage step={__('100 ms · still waiting')} tone="primary" title={__('Customers')} rows="shimmer" note={__("The target's header shows over a skeleton of its own layout. The old list is gone.")} />
          <MiniPage step={__('150 ms · slow visit')} tone="primary" title={__('Customers')} rows="shimmer" bar note={__('Only now does the 3px brand bar appear at the top.')} />
          <MiniPage step={__('Arrived')} tone="success" title={__('Customers')} rows={['Ann Taylor · ann.taylor@gmail.com', 'Anna Kowalski · anna.k@gmail.com', 'Annette Fox · annette.fox@gmail.com']} note={__("Rows cross-fade into the skeleton's exact place. Counts come with the page, so nothing pops in.")} />
        </div>
      </Panel>
      <div className="grid grid-cols-3 gap-6">
        <Panel title={__('Progress')} className="px-6! py-5!">
          {[
            [__('Marking orders shipped'), __('1,200 of 2,424'), 49, ''],
            [__('Export ready'), __('10,000 rows'), 100, 'success'],
            [__('Counting rows'), __('Size not known yet'), null, ''],
          ].map(([label, value, progress, tone]) => (
            <div key={String(label)} className="flex flex-col gap-1.5">
              <div className="flex justify-between text-sm">
                <span>{label}</span>
                <span className={`tabular-nums ${tone === 'success' ? 'text-success-subtle-foreground' : 'text-muted-foreground'}`}>{value}</span>
              </div>
              <Progress value={progress as number | null} aria-label={String(label)} className={tone === 'success' ? '[&_[data-slot=progress-indicator]]:bg-success!' : ''} />
            </div>
          ))}
          <div className="flex items-center gap-4 text-muted-foreground">
            <Spinner className="size-3.5" aria-label={__('Loading')} />
            <Spinner className="size-4" aria-label={__('Loading')} />
            <Spinner className="size-5" aria-label={__('Loading')} />
            <span className="text-xs leading-4">{__('Spinners only inside a control')}</span>
          </div>
        </Panel>
        <Panel title={__('Skeletons, sized to what replaces them')} className="gap-3.5! px-6! py-5!">
          <div className="flex flex-col gap-2 rounded-md border border-border p-3">
            <Skeleton className="h-3 w-2/5" />
            <Skeleton className="h-7 w-3/5 rounded-[6px]!" />
            <Skeleton className="h-3 w-[30%]" />
          </div>
          <div className="flex flex-col overflow-hidden rounded-md border border-border">
            <div className="flex h-10 items-center gap-3 bg-muted px-3 text-xs font-medium text-muted-foreground">
              <span className="w-[90px]">{__('Number')}</span>
              <span className="flex-1">{__('Customer')}</span>
              <span className="w-14 text-right">{__('Total')}</span>
            </div>
            <div className="flex h-12 items-center gap-3 border-t border-border px-3">
              <Skeleton className="h-3 w-[90px]" />
              <Skeleton className="h-3 flex-1" />
              <Skeleton className="h-3 w-14" />
            </div>
            <div className="flex h-12 items-center gap-3 border-t border-border px-3">
              <Skeleton className="h-3 w-[90px]" />
              <Skeleton className="h-3 flex-[0.7]" />
              <div className="flex-[0.3]" />
              <Skeleton className="h-3 w-14" />
            </div>
          </div>
          <span className="text-xs leading-4 text-muted-foreground">{__('Headers and labels are real text at once; only values shimmer.')}</span>
        </Panel>
        <Panel title={__('Tooltip, keys, avatars')} className="px-6! py-5!">
          <div className="flex flex-col items-start gap-1.5">
            <span role="tooltip" data-slot="tooltip-content" className="cn-tooltip-content bg-foreground text-background">
              {__('Edit order')}
              <Kbd>E</Kbd>
            </span>
            <Button variant="ghost" size="icon" aria-label={__('Edit order')} className={held.ghost.hover}>
              <Icon name="pencil" className="size-4" />
            </Button>
          </div>
          <div className="flex flex-wrap items-center gap-1.5">
            <Kbd>⌘</Kbd>
            <Kbd>K</Kbd>
            <span className="mr-2 text-[13px] text-muted-foreground">{__('Search')}</span>
            <Kbd>Esc</Kbd>
            <span className="text-[13px] text-muted-foreground">{__('Close')}</span>
          </div>
          <div className="flex items-center gap-3">
            <Avatar size="sm">
              <AvatarFallback>AU</AvatarFallback>
            </Avatar>
            <Avatar>
              <AvatarFallback>AU</AvatarFallback>
            </Avatar>
            <span className="inline-flex">
              <Avatar className="size-7! shadow-[0_0_0_2px_var(--card)]">
                <AvatarFallback className="bg-info-subtle! text-[11px]! text-info-subtle-foreground!">AT</AvatarFallback>
              </Avatar>
              <Avatar className="-ml-1.5 size-7! shadow-[0_0_0_2px_var(--card)]">
                <AvatarFallback className="bg-success-subtle! text-[11px]! text-success-subtle-foreground!">EU</AvatarFallback>
              </Avatar>
              <Avatar className="-ml-1.5 size-7! shadow-[0_0_0_2px_var(--card)]">
                <AvatarFallback className="bg-secondary! text-[11px]! text-secondary-foreground!">+4</AvatarFallback>
              </Avatar>
            </span>
          </div>
        </Panel>
      </div>
    </Board>
  );
}

/** The six primitive boards, one after another. */
export function PrimitiveBoards() {
  return (
    <div className="grid gap-16">
      <ButtonsBoard />
      <InputsBoard />
      <SelectsBoard />
      <BadgesBoard />
      <ChoicesBoard />
      <FeedbackBoard />
    </div>
  );
}
