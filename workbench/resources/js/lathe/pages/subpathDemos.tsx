import { useTranslation } from '@lathephp/core';
import { Button, Input, Skeleton } from '@lathephp/ui';
import { lazy, useEffect, useRef, useState, type ComponentType, type LazyExoticComponent, type ReactNode } from 'react';

const fruits = ['Apple', 'Banana', 'Cherry'];
const sales = [
  { day: 'Mon', orders: 12 },
  { day: 'Tue', orders: 18 },
  { day: 'Wed', orders: 9 },
];

/** Renders its children once the whole box is on screen, and keeps them from then on. */
function OnScreen({ className, children }: { className: string; children: ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);
  const [shown, setShown] = useState(false);

  useEffect(() => {
    const box = ref.current;

    if (box === null) {
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting === true) {
          setShown(true);
          observer.disconnect();
        }
      },
      { threshold: 1 },
    );
    observer.observe(box);

    return () => {
      observer.disconnect();
    };
  }, []);

  return (
    <div ref={ref} className={className}>
      {shown && children}
    </div>
  );
}

// Each subpath loads through the import map when the gallery first renders it, so neither the
// boot module nor any other page fetches it.
const demo = (load: () => Promise<ComponentType>): LazyExoticComponent<ComponentType> => lazy(async () => ({ default: await load() }));

/** The demos of the 22 @lathephp/ui subpaths, by subpath name. */
export const subpathDemos: [string, LazyExoticComponent<ComponentType>][] = [
  [
    'breadcrumb',
    demo(async () => {
      const { Breadcrumb, BreadcrumbEllipsis, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } = await import(
        '@lathephp/ui/breadcrumb'
      );

      return function BreadcrumbDemo() {
        const { __ } = useTranslation();

        return (
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem>
                <BreadcrumbLink href="/lathe">{__('Home')}</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                <BreadcrumbEllipsis />
              </BreadcrumbItem>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                <BreadcrumbPage>{__('UI Gallery')}</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        );
      };
    }),
  ],
  [
    'button-group',
    demo(async () => {
      const { ButtonGroup, ButtonGroupSeparator, ButtonGroupText } = await import('@lathephp/ui/button-group');

      return function ButtonGroupDemo() {
        const { __ } = useTranslation();

        return (
          <ButtonGroup aria-label={__('Pages')}>
            <Button variant="outline">{__('Previous')}</Button>
            <ButtonGroupSeparator />
            <ButtonGroupText>{__('Page 1')}</ButtonGroupText>
            <Button variant="outline">{__('Next')}</Button>
          </ButtonGroup>
        );
      };
    }),
  ],
  [
    'calendar',
    demo(async () => {
      const { Calendar, CalendarDayButton } = await import('@lathephp/ui/calendar');

      return function CalendarDemo() {
        return (
          <Calendar
            mode="single"
            defaultMonth={new Date(2026, 1, 1)}
            components={{ DayButton: (props) => <CalendarDayButton {...props} /> }}
            className="rounded-md border"
          />
        );
      };
    }),
  ],
  [
    'chart',
    demo(async () => {
      const {
        Area,
        AreaChart,
        Bar,
        BarChart,
        CartesianGrid,
        ChartContainer,
        ChartLegend,
        ChartLegendContent,
        ChartTooltip,
        ChartTooltipContent,
        LabelList,
        Line,
        LineChart,
        Pie,
        PieChart,
        PolarAngleAxis,
        PolarGrid,
        PolarRadiusAxis,
        Radar,
        RadarChart,
        RadialBar,
        RadialBarChart,
        Rectangle,
        ReferenceLine,
        Sector,
        XAxis,
        YAxis,
      } = await import('@lathephp/ui/chart');
      const shares = sales.map((row, index) => ({ ...row, name: row.day, fill: `var(--chart-${String(index + 1)})` }));

      return function ChartDemo() {
        const { __ } = useTranslation();
        const tooltip = <ChartTooltip content={<ChartTooltipContent />} />;
        const legend = <ChartLegend content={<ChartLegendContent />} />;

        return (
          <div className="grid w-full gap-4 sm:grid-cols-2">
            <ChartContainer className="h-40 w-full">
              <BarChart data={sales}>
                <CartesianGrid vertical={false} />
                <XAxis dataKey="day" />
                <YAxis width={28} />
                {tooltip}
                <ReferenceLine y={10} />
                <Bar dataKey="orders" name={__('Orders')} fill="var(--chart-1)" radius={4} shape={(props) => <Rectangle {...props} />}>
                  <LabelList dataKey="orders" position="top" />
                </Bar>
              </BarChart>
            </ChartContainer>
            <ChartContainer className="h-40 w-full">
              <AreaChart data={sales}>
                <XAxis dataKey="day" padding={{ left: 16, right: 16 }} interval={0} />
                {tooltip}
                {legend}
                <Area dataKey="orders" name={__('Orders')} stroke="var(--chart-2)" fill="var(--chart-2)" fillOpacity={0.3} />
              </AreaChart>
            </ChartContainer>
            <ChartContainer className="h-40 w-full">
              <LineChart data={sales}>
                <XAxis dataKey="day" padding={{ left: 16, right: 16 }} interval={0} />
                {tooltip}
                {legend}
                <Line dataKey="orders" name={__('Orders')} stroke="var(--chart-3)" />
              </LineChart>
            </ChartContainer>
            <ChartContainer className="h-40 w-full">
              <PieChart>
                {tooltip}
                {legend}
                <Pie data={shares} dataKey="orders" nameKey="day" shape={(props) => <Sector {...props} />} />
              </PieChart>
            </ChartContainer>
            <ChartContainer className="h-40 w-full">
              <RadarChart data={sales}>
                <PolarGrid />
                <PolarAngleAxis dataKey="day" />
                <PolarRadiusAxis />
                {tooltip}
                {legend}
                <Radar dataKey="orders" name={__('Orders')} stroke="var(--chart-4)" fill="var(--chart-4)" fillOpacity={0.3} />
              </RadarChart>
            </ChartContainer>
            <ChartContainer className="h-40 w-full">
              <RadialBarChart data={shares} innerRadius="30%" outerRadius="100%">
                {tooltip}
                {legend}
                <RadialBar dataKey="orders" name={__('Orders')} background />
              </RadialBarChart>
            </ChartContainer>
          </div>
        );
      };
    }),
  ],
  [
    'code-editor',
    demo(async () => {
      const { CodeEditor } = await import('@lathephp/ui/code-editor');

      return function CodeEditorDemo() {
        const { __ } = useTranslation();
        const [code, setCode] = useState('{"status": "paid"}\n');

        return <CodeEditor value={code} onChange={setCode} language="json" height={120} aria-label={__('Payload')} className="w-full" />;
      };
    }),
  ],
  [
    'collapsible',
    demo(async () => {
      const { Collapsible, CollapsibleContent, CollapsibleTrigger } = await import('@lathephp/ui/collapsible');

      return function CollapsibleDemo() {
        const { __ } = useTranslation();

        return (
          <Collapsible>
            <CollapsibleTrigger render={<Button variant="outline" />}>{__('Details')}</CollapsibleTrigger>
            <CollapsibleContent className="pt-2 text-sm">{__('Hidden detail')}</CollapsibleContent>
          </Collapsible>
        );
      };
    }),
  ],
  [
    'combobox',
    demo(async () => {
      const {
        Combobox,
        ComboboxClear,
        ComboboxCollection,
        ComboboxContent,
        ComboboxEmpty,
        ComboboxGroup,
        ComboboxInput,
        ComboboxItem,
        ComboboxLabel,
        ComboboxList,
        ComboboxSeparator,
        ComboboxTrigger,
        ComboboxValue,
        useComboboxAnchor,
      } = await import('@lathephp/ui/combobox');
      const groups = [
        { value: 'Fruit', items: fruits },
        { value: 'Veg', items: ['Carrot', 'Leek'] },
      ];

      return function ComboboxDemo() {
        const { __ } = useTranslation();
        const anchor = useComboboxAnchor();

        return (
          <div className="flex flex-wrap items-center gap-4">
            <Combobox items={fruits}>
              <ComboboxInput aria-label={__('Fruit')} placeholder={__('Fruit')} />
              <ComboboxContent>
                <ComboboxEmpty>{__('No results')}</ComboboxEmpty>
                <ComboboxList>
                  {(item: string) => (
                    <ComboboxItem key={item} value={item}>
                      {item}
                    </ComboboxItem>
                  )}
                </ComboboxList>
              </ComboboxContent>
            </Combobox>
            <Combobox items={groups} defaultValue="Apple">
              <div ref={anchor} className="flex items-center gap-1">
                <ComboboxTrigger render={<Button variant="outline" />}>
                  <ComboboxValue />
                </ComboboxTrigger>
                <ComboboxClear />
              </div>
              <ComboboxContent anchor={anchor}>
                <ComboboxList>
                  {(group: { value: string; items: string[] }, index: number) => (
                    <ComboboxGroup key={group.value} items={group.items}>
                      {index > 0 && <ComboboxSeparator />}
                      <ComboboxLabel>{index === 0 ? __('Fruit') : __('Vegetables')}</ComboboxLabel>
                      <ComboboxCollection>
                        {(item: string) => (
                          <ComboboxItem key={item} value={item}>
                            {item}
                          </ComboboxItem>
                        )}
                      </ComboboxCollection>
                    </ComboboxGroup>
                  )}
                </ComboboxList>
              </ComboboxContent>
            </Combobox>
          </div>
        );
      };
    }),
  ],
  [
    'command',
    demo(async () => {
      const { Command, CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator, CommandShortcut } = await import(
        '@lathephp/ui/command'
      );

      return function CommandDemo() {
        const { __ } = useTranslation();
        const [open, setOpen] = useState(false);

        return (
          <div className="flex w-full flex-col gap-3">
            {/* An inline Command scrolls its first item into view as it mounts, so it mounts once on screen. */}
            <OnScreen className="min-h-72">
              <Command className="rounded-lg border">
              <CommandInput placeholder={__('Search')} />
              <CommandList>
                <CommandEmpty>{__('No results')}</CommandEmpty>
                <CommandGroup heading={__('Pages')}>
                  <CommandItem>
                    {__('Dashboard')}
                    <CommandShortcut>D</CommandShortcut>
                  </CommandItem>
                  <CommandItem>{__('Orders')}</CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading={__('Account')}>
                  <CommandItem>{__('Profile')}</CommandItem>
                </CommandGroup>
              </CommandList>
              </Command>
            </OnScreen>
            <Button variant="outline" className="self-start" onClick={() => setOpen(true)}>
              {__('Open palette')}
            </Button>
            {/* As shadcn's, the dialog holds a Command root of its own. */}
            <CommandDialog open={open} onOpenChange={setOpen}>
              <Command>
                <CommandInput placeholder={__('Search')} />
                <CommandList>
                  <CommandItem>{__('Dashboard')}</CommandItem>
                </CommandList>
              </Command>
            </CommandDialog>
          </div>
        );
      };
    }),
  ],
  [
    'direction',
    demo(async () => {
      const { DirectionProvider, useDirection } = await import('@lathephp/ui/direction');

      function Probe() {
        return <span className="text-sm">{useDirection()}</span>;
      }

      return function DirectionDemo() {
        return (
          <DirectionProvider direction="rtl">
            <Probe />
          </DirectionProvider>
        );
      };
    }),
  ],
  [
    'empty',
    demo(async () => {
      const { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } = await import('@lathephp/ui/empty');

      return function EmptyDemo() {
        const { __ } = useTranslation();

        return (
          <Empty>
            <EmptyHeader>
              <EmptyMedia variant="icon">0</EmptyMedia>
              <EmptyTitle>{__('No orders')}</EmptyTitle>
              <EmptyDescription>{__('Create one to start.')}</EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
              <Button variant="outline">{__('New order')}</Button>
            </EmptyContent>
          </Empty>
        );
      };
    }),
  ],
  [
    'field',
    demo(async () => {
      const { Field, FieldContent, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldLegend, FieldSeparator, FieldSet, FieldTitle } = await import(
        '@lathephp/ui/field'
      );

      return function FieldDemo() {
        const { __ } = useTranslation();

        return (
          <FieldSet className="w-full">
            <FieldLegend>{__('Contact')}</FieldLegend>
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="ui-gallery-field">{__('Email')}</FieldLabel>
                <Input id="ui-gallery-field" type="email" aria-invalid />
                <FieldDescription>{__('We never share it.')}</FieldDescription>
                <FieldError>{__('Enter a valid email.')}</FieldError>
              </Field>
              <FieldSeparator>{__('or')}</FieldSeparator>
              <Field orientation="horizontal">
                <FieldContent>
                  <FieldTitle>{__('Phone')}</FieldTitle>
                  <FieldDescription>{__('We call only about orders.')}</FieldDescription>
                </FieldContent>
              </Field>
            </FieldGroup>
          </FieldSet>
        );
      };
    }),
  ],
  [
    'hover-card',
    demo(async () => {
      const { HoverCard, HoverCardContent, HoverCardTrigger } = await import('@lathephp/ui/hover-card');

      return function HoverCardDemo() {
        const { __ } = useTranslation();

        return (
          <HoverCard>
            <HoverCardTrigger href="/lathe/resources/users">{__('Admin User')}</HoverCardTrigger>
            <HoverCardContent>{__('Admin since 2020')}</HoverCardContent>
          </HoverCard>
        );
      };
    }),
  ],
  [
    'input-group',
    demo(async () => {
      const { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText, InputGroupTextarea } = await import('@lathephp/ui/input-group');

      return function InputGroupDemo() {
        const { __ } = useTranslation();

        return (
          <div className="flex w-full flex-col gap-3">
            <InputGroup>
              <InputGroupAddon>
                <InputGroupText>$</InputGroupText>
              </InputGroupAddon>
              <InputGroupInput aria-label={__('Price')} />
            </InputGroup>
            <InputGroup>
              <InputGroupTextarea aria-label={__('Note')} placeholder={__('Note')} />
            </InputGroup>
          </div>
        );
      };
    }),
  ],
  [
    'item',
    demo(async () => {
      const { Item, ItemActions, ItemContent, ItemDescription, ItemFooter, ItemGroup, ItemHeader, ItemMedia, ItemSeparator, ItemTitle } = await import(
        '@lathephp/ui/item'
      );

      return function ItemDemo() {
        const { __ } = useTranslation();

        return (
          <ItemGroup className="w-full">
            <Item variant="outline">
              <ItemHeader>{__('Today')}</ItemHeader>
              <ItemMedia variant="icon">#</ItemMedia>
              <ItemContent>
                <ItemTitle>{__('Order 1001')}</ItemTitle>
                <ItemDescription>{__('Paid')}</ItemDescription>
              </ItemContent>
              <ItemActions>
                <Button variant="outline" size="sm">
                  {__('Open')}
                </Button>
              </ItemActions>
              <ItemFooter>{__('Shipped by post')}</ItemFooter>
            </Item>
            <ItemSeparator />
            <Item variant="outline">
              <ItemContent>
                <ItemTitle>{__('Order 1002')}</ItemTitle>
              </ItemContent>
            </Item>
          </ItemGroup>
        );
      };
    }),
  ],
  [
    'otp-field',
    demo(async () => {
      const { OTPField, OTPFieldGroup, OTPFieldSeparator, OTPFieldSlot } = await import('@lathephp/ui/otp-field');

      return function OtpFieldDemo() {
        const { __ } = useTranslation();

        return (
          <OTPField length={4} aria-label={__('Code')}>
            <OTPFieldGroup>
              <OTPFieldSlot aria-label={__('Character 1 of 4')} />
              <OTPFieldSlot aria-label={__('Character 2 of 4')} />
              <OTPFieldSeparator />
              <OTPFieldSlot aria-label={__('Character 3 of 4')} />
              <OTPFieldSlot aria-label={__('Character 4 of 4')} />
            </OTPFieldGroup>
          </OTPField>
        );
      };
    }),
  ],
  [
    'pagination',
    demo(async () => {
      const { Pagination, PaginationContent, PaginationEllipsis, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } = await import(
        '@lathephp/ui/pagination'
      );

      return function PaginationDemo() {
        return (
          <Pagination>
            <PaginationContent>
              <PaginationItem>
                <PaginationPrevious href="#" />
              </PaginationItem>
              <PaginationItem>
                <PaginationLink href="#" isActive>
                  1
                </PaginationLink>
              </PaginationItem>
              <PaginationItem>
                <PaginationEllipsis />
              </PaginationItem>
              <PaginationItem>
                <PaginationNext href="#" />
              </PaginationItem>
            </PaginationContent>
          </Pagination>
        );
      };
    }),
  ],
  [
    'scroll-area',
    demo(async () => {
      const { ScrollArea, ScrollBar } = await import('@lathephp/ui/scroll-area');

      return function ScrollAreaDemo() {
        const { __ } = useTranslation();

        return (
          <ScrollArea className="h-20 w-full rounded-md border">
            <p className="p-3 text-sm">{__('A long text scrolls inside its own box, so the page around it keeps its place while the reader goes down the list.')}</p>
            <ScrollBar orientation="horizontal" />
          </ScrollArea>
        );
      };
    }),
  ],
  [
    'shimmer-bar',
    demo(async () => {
      const { ShimmerBar } = await import('@lathephp/ui/shimmer-bar');

      return function ShimmerBarDemo() {
        return (
          <div className="relative h-1 w-full overflow-hidden">
            <ShimmerBar />
          </div>
        );
      };
    }),
  ],
  [
    'sidebar',
    demo(async () => {
      const {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarGroup,
        SidebarGroupAction,
        SidebarGroupContent,
        SidebarGroupLabel,
        SidebarHeader,
        SidebarInput,
        SidebarInset,
        SidebarMenu,
        SidebarMenuAction,
        SidebarMenuBadge,
        SidebarMenuButton,
        SidebarMenuItem,
        SidebarMenuSkeleton,
        SidebarMenuSub,
        SidebarMenuSubButton,
        SidebarMenuSubItem,
        SidebarProvider,
        SidebarRail,
        SidebarSeparator,
        SidebarTrigger,
        useSidebar,
      } = await import('@lathephp/ui/sidebar');

      function State() {
        const { __ } = useTranslation();

        return <span className="text-sm">{useSidebar().open ? __('Open') : __('Closed')}</span>;
      }

      return function SidebarDemo() {
        const { __ } = useTranslation();

        return (
          <SidebarProvider className="h-72 min-h-0 w-full overflow-hidden rounded-md border">
            <Sidebar collapsible="none" className="h-full">
              <SidebarHeader>
                <SidebarInput aria-label={__('Search')} placeholder={__('Search')} />
              </SidebarHeader>
              <SidebarContent>
                <SidebarGroup>
                  <SidebarGroupLabel>{__('Shop')}</SidebarGroupLabel>
                  <SidebarGroupAction aria-label={__('Add')}>+</SidebarGroupAction>
                  <SidebarGroupContent>
                    <SidebarMenu>
                      <SidebarMenuItem>
                        <SidebarMenuButton isActive>{__('Orders')}</SidebarMenuButton>
                        <SidebarMenuAction aria-label={__('More')}>…</SidebarMenuAction>
                        <SidebarMenuBadge>3</SidebarMenuBadge>
                        <SidebarMenuSub>
                          <SidebarMenuSubItem>
                            <SidebarMenuSubButton>{__('Paid')}</SidebarMenuSubButton>
                          </SidebarMenuSubItem>
                        </SidebarMenuSub>
                      </SidebarMenuItem>
                      <SidebarMenuItem>
                        <SidebarMenuSkeleton />
                      </SidebarMenuItem>
                    </SidebarMenu>
                  </SidebarGroupContent>
                </SidebarGroup>
              </SidebarContent>
              <SidebarSeparator />
              <SidebarFooter>{__('Admin User')}</SidebarFooter>
              <SidebarRail />
            </Sidebar>
            <SidebarInset>
              <div className="flex items-center gap-2 p-2">
                <SidebarTrigger />
                <State />
              </div>
            </SidebarInset>
          </SidebarProvider>
        );
      };
    }),
  ],
  [
    'toast',
    demo(async () => {
      const { Toast, ToastContent, ToastDescription, ToastProvider, ToastTitle, ToastViewport, createToastManager, useToastManager } = await import(
        '@lathephp/ui/toast'
      );
      const manager = createToastManager();

      function Toasts() {
        const { toasts } = useToastManager();

        return toasts.map((item) => (
          <Toast key={item.id} toast={item}>
            <ToastContent>
              <ToastTitle />
              <ToastDescription />
            </ToastContent>
          </Toast>
        ));
      }

      return function ToastDemo() {
        const { __ } = useTranslation();

        return (
          <ToastProvider toastManager={manager}>
            <Button
              variant="outline"
              onClick={() => {
                manager.add({ title: __('Order saved'), description: __('Order 1001 is paid.') });
              }}
            >
              {__('Show toast')}
            </Button>
            <ToastViewport>
              <Toasts />
            </ToastViewport>
          </ToastProvider>
        );
      };
    }),
  ],
  [
    'toggle',
    demo(async () => {
      const { Toggle } = await import('@lathephp/ui/toggle');

      return function ToggleDemo() {
        const { __ } = useTranslation();

        return <Toggle aria-label={__('Bold')}>B</Toggle>;
      };
    }),
  ],
  [
    'toggle-group',
    demo(async () => {
      const { ToggleGroup, ToggleGroupItem } = await import('@lathephp/ui/toggle-group');

      return function ToggleGroupDemo() {
        const { __ } = useTranslation();

        return (
          <ToggleGroup aria-label={__('Align')} defaultValue={['left']}>
            <ToggleGroupItem value="left">{__('Left')}</ToggleGroupItem>
            <ToggleGroupItem value="right">{__('Right')}</ToggleGroupItem>
          </ToggleGroup>
        );
      };
    }),
  ],
];

/** What a subpath demo shows while its module loads: a box of about its height. */
export function DemoFallback() {
  return <Skeleton data-demo-loading className="h-9 w-full" />;
}
