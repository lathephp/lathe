import { useTranslation } from '@lathephp/core';
import { Button, Input, Skeleton } from '@lathephp/ui';
import { lazy, useState, type ComponentType, type LazyExoticComponent } from 'react';

const fruits = ['Apple', 'Banana', 'Cherry'];
const sales = [
  { day: 'Mon', orders: 12 },
  { day: 'Tue', orders: 18 },
  { day: 'Wed', orders: 9 },
];

// Each subpath loads through the import map when the gallery first renders it, so neither the
// boot module nor any other page fetches it.
const demo = (load: () => Promise<ComponentType>): LazyExoticComponent<ComponentType> => lazy(async () => ({ default: await load() }));

/** The demos of the 22 @lathephp/ui subpaths, by subpath name. */
export const subpathDemos: [string, LazyExoticComponent<ComponentType>][] = [
  [
    'breadcrumb',
    demo(async () => {
      const { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } = await import('@lathephp/ui/breadcrumb');

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
      const { ButtonGroup } = await import('@lathephp/ui/button-group');

      return function ButtonGroupDemo() {
        const { __ } = useTranslation();

        return (
          <ButtonGroup aria-label={__('Pages')}>
            <Button variant="outline">{__('Previous')}</Button>
            <Button variant="outline">{__('Next')}</Button>
          </ButtonGroup>
        );
      };
    }),
  ],
  [
    'calendar',
    demo(async () => {
      const { Calendar } = await import('@lathephp/ui/calendar');

      return function CalendarDemo() {
        return <Calendar mode="single" defaultMonth={new Date(2026, 1, 1)} className="rounded-md border" />;
      };
    }),
  ],
  [
    'chart',
    demo(async () => {
      const { Bar, BarChart, ChartContainer } = await import('@lathephp/ui/chart');

      return function ChartDemo() {
        return (
          <ChartContainer className="h-40 w-full">
            <BarChart data={sales}>
              <Bar dataKey="orders" fill="var(--chart-1)" radius={4} />
            </BarChart>
          </ChartContainer>
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
      const { Combobox, ComboboxContent, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } = await import('@lathephp/ui/combobox');

      return function ComboboxDemo() {
        const { __ } = useTranslation();

        return (
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
        );
      };
    }),
  ],
  [
    'command',
    demo(async () => {
      const { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } = await import('@lathephp/ui/command');

      return function CommandDemo() {
        const { __ } = useTranslation();

        return (
          <Command className="rounded-lg border">
            <CommandInput placeholder={__('Search')} />
            <CommandList>
              <CommandEmpty>{__('No results')}</CommandEmpty>
              <CommandGroup heading={__('Pages')}>
                <CommandItem>{__('Dashboard')}</CommandItem>
                <CommandItem>{__('Orders')}</CommandItem>
              </CommandGroup>
            </CommandList>
          </Command>
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
      const { Empty, EmptyDescription, EmptyHeader, EmptyTitle } = await import('@lathephp/ui/empty');

      return function EmptyDemo() {
        const { __ } = useTranslation();

        return (
          <Empty>
            <EmptyHeader>
              <EmptyTitle>{__('No orders')}</EmptyTitle>
              <EmptyDescription>{__('Create one to start.')}</EmptyDescription>
            </EmptyHeader>
          </Empty>
        );
      };
    }),
  ],
  [
    'field',
    demo(async () => {
      const { Field, FieldDescription, FieldLabel } = await import('@lathephp/ui/field');

      return function FieldDemo() {
        const { __ } = useTranslation();

        return (
          <Field>
            <FieldLabel htmlFor="ui-gallery-field">{__('Email')}</FieldLabel>
            <Input id="ui-gallery-field" type="email" />
            <FieldDescription>{__('We never share it.')}</FieldDescription>
          </Field>
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
      const { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } = await import('@lathephp/ui/input-group');

      return function InputGroupDemo() {
        const { __ } = useTranslation();

        return (
          <InputGroup>
            <InputGroupAddon>
              <InputGroupText>$</InputGroupText>
            </InputGroupAddon>
            <InputGroupInput aria-label={__('Price')} />
          </InputGroup>
        );
      };
    }),
  ],
  [
    'item',
    demo(async () => {
      const { Item, ItemContent, ItemDescription, ItemTitle } = await import('@lathephp/ui/item');

      return function ItemDemo() {
        const { __ } = useTranslation();

        return (
          <Item variant="outline" className="w-full">
            <ItemContent>
              <ItemTitle>{__('Order 1001')}</ItemTitle>
              <ItemDescription>{__('Paid')}</ItemDescription>
            </ItemContent>
          </Item>
        );
      };
    }),
  ],
  [
    'otp-field',
    demo(async () => {
      const { OTPField, OTPFieldGroup, OTPFieldSlot } = await import('@lathephp/ui/otp-field');

      return function OtpFieldDemo() {
        const { __ } = useTranslation();

        return (
          <OTPField length={4} aria-label={__('Code')}>
            <OTPFieldGroup>
              <OTPFieldSlot aria-label={__('Character 1 of 4')} />
              <OTPFieldSlot aria-label={__('Character 2 of 4')} />
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
      const { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } = await import('@lathephp/ui/pagination');

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
      const { ScrollArea } = await import('@lathephp/ui/scroll-area');

      return function ScrollAreaDemo() {
        const { __ } = useTranslation();

        return (
          <ScrollArea className="h-20 w-full rounded-md border">
            <p className="p-3 text-sm">{__('A long text scrolls inside its own box, so the page around it keeps its place while the reader goes down the list.')}</p>
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
      const { Sidebar, SidebarContent, SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarProvider } = await import(
        '@lathephp/ui/sidebar'
      );

      return function SidebarDemo() {
        const { __ } = useTranslation();

        return (
          <SidebarProvider className="min-h-0 w-full">
            <Sidebar collapsible="none" className="h-auto rounded-md border">
              <SidebarContent>
                <SidebarGroup>
                  <SidebarGroupLabel>{__('Shop')}</SidebarGroupLabel>
                  <SidebarMenu>
                    <SidebarMenuItem>
                      <SidebarMenuButton isActive>{__('Orders')}</SidebarMenuButton>
                    </SidebarMenuItem>
                  </SidebarMenu>
                </SidebarGroup>
              </SidebarContent>
            </Sidebar>
          </SidebarProvider>
        );
      };
    }),
  ],
  [
    'sonner',
    demo(async () => {
      const { Toaster } = await import('@lathephp/ui/sonner');

      return function SonnerDemo() {
        const { __ } = useTranslation();

        return (
          <>
            <Toaster />
            <span className="text-sm">{__('The Sonner toaster is mounted.')}</span>
          </>
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
