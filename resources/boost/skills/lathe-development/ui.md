# Lathe UI: build with the kit

Read this before you write any React for Lathe: a field, filter, widget, page, plugin page or render hook under `resources/js/lathe/`, or a plugin package's `resources/js/`. The panel draws itself with `@lathephp/ui`, the shadcn/ui kit on Base UI, and a component built from the same parts looks like the panel in every style and theme. Back to [SKILL.md](SKILL.md) and [custom-extensions.md](custom-extensions.md).

## The rule

- **Never hand-build a button, input, select, textarea, checkbox, switch, dialog, menu, popover, tooltip, tabs, table, card, alert, badge, avatar, skeleton or spinner.** Import it from `@lathephp/ui`. A raw `<button>`, `<input>`, `<select>` or `<textarea>` misses the focus ring, the disabled and invalid looks, dark mode and the active style.
- **Never import `@base-ui/react` or `lucide-react`.** Use the kit's parts and the `Icon` of `@lathephp/core`.
- **Use theme tokens, never palette colours.** Write `bg-muted`, `text-muted-foreground`, `text-destructive`, `border-border`, `bg-primary`, `text-success`, `text-warning`, `text-info`; never `bg-gray-100`, `text-red-500`, `text-white` or a hex colour like `text-[#ff0000]`.
- **Leave corners, shadows, borders and focus rings to the kit.** Each part carries the style's `cn-*` classes. Never write `cn-*` classes yourself, and never put `rounded-*`, `shadow-*`, `p-6` or a font weight on a kit part.

## Page titles and icons

- **Title a page with `PageHeader` from `@lathephp/core`:** `<PageHeader title={__('Reports')} description={__('Monthly numbers')} actions={<Button>{__('Export')}</Button>} />`. It draws the panel's page title, and on a phone it puts the title in the top bar. Never write your own `<h1>`.
- **Draw an icon with `Icon` from `@lathephp/core`:** `<Icon name="shopping-cart" />` or `<Icon name="ShoppingCart" className="size-5" />`. It takes a Lucide name or a case of the PHP `Icon` enum, holds an empty box of its size while the icon set loads, and draws a circle for an unknown name. Never paste SVG paths.

## Composition

- **Card:** `<Card><CardHeader><CardTitle>…</CardTitle><CardDescription>…</CardDescription></CardHeader><CardContent>…</CardContent><CardFooter>…</CardFooter></Card>`. The parts own the padding and the title's size and weight.
- **Dialog:** `Dialog` with `DialogTrigger`, then `DialogContent` holding `DialogHeader` (`DialogTitle`, `DialogDescription`) and `DialogFooter`. `AlertDialog` and `Sheet` follow the same shape.
- **Form fields:** `Field` from `@lathephp/ui/field` around a `FieldLabel htmlFor={id}`, the control and a `FieldDescription` or `FieldError`; group them with `FieldGroup` or `FieldSet`. Put `aria-invalid` and `aria-describedby` on the control.
- **Render a part as another element with Base UI's `render` prop, not `asChild`:** `<Button render={<a href="/lathe/reports" />}>{__('Open')}</Button>`.
- **Widgets:** the panel draws a custom widget's card and its name, so the component returns only the body. Never wrap it in a `Card`.

## Styling

- Use kit parts and let them draw themselves; use utilities only for layout (`grid`, `gap-4`, `flex`, `items-center`).
- The panel's stylesheet holds only the utilities the panel itself uses. For any other utility, add a CSS file next to `boot.ts` and import it there:

  ```css
  @import "tailwindcss/theme.css" layer(theme) theme(reference inline);
  @import "tailwindcss/utilities.css" layer(components) source(none);
  @import "@lathephp/core/tokens.css";

  @source "./";
  ```

  `@lathephp/core/tokens.css` turns the panel's colours, radii and `chart-1` to `chart-5` into Tailwind theme values, so `bg-card` and `text-chart-2` follow the panel's theme.

## Lint

Add the kit's raw-UI rule to the app's `eslint.config.js`, then run ESLint on `resources/js/lathe`. The first React component a generator writes (`lathe:page`, `lathe:field --custom`, `lathe:widget --type=custom`, `lathe:plugin`) prints these two lines; the scaffold never edits the file itself:

```js
import lathe from '@lathephp/vite-plugin/eslint';

export default [
  // …the app's own config, which parses TSX…
  ...lathe,
];
```

It reports a raw `<button>`, `<input>`, `<select>` or `<textarea>` and a raw palette colour as errors. A real exception carries `// eslint-disable-next-line lathe/no-raw-ui -- <reason>`.

## Charts

The kit's chart (`@lathephp/ui/chart`) is not shadcn's: there is no `ChartConfig`, no `config` prop and no `--color-<key>` variables. Colour each series with `var(--chart-N)`, and give `ChartTooltipContent` its own props (`heading`, `valuePrefix`, `valueSuffix`, `showTotal`, `formatValue`):

```tsx
import { Bar, BarChart, ChartContainer, ChartTooltip, ChartTooltipContent, XAxis } from '@lathephp/ui/chart';

<ChartContainer className="h-48 w-full">
  <BarChart data={sales}>
    <XAxis dataKey="month" />
    <ChartTooltip content={<ChartTooltipContent valuePrefix="$" />} />
    <Bar dataKey="total" fill="var(--chart-1)" radius={4} />
  </BarChart>
</ChartContainer>
```

## Every export

Import each part from the path it is listed under. The main entry holds the everyday parts; each subpath loads only when a component imports it.

- `@lathephp/ui`: `Accordion`, `AccordionItem`, `AccordionTrigger`, `AccordionContent`, `Alert`, `AlertTitle`, `AlertDescription`, `AlertAction`, `AlertDialog`, `AlertDialogAction`, `AlertDialogCancel`, `AlertDialogContent`, `AlertDialogDescription`, `AlertDialogFooter`, `AlertDialogHeader`, `AlertDialogMedia`, `AlertDialogOverlay`, `AlertDialogPortal`, `AlertDialogTitle`, `AlertDialogTrigger`, `Avatar`, `AvatarImage`, `AvatarFallback`, `AvatarGroup`, `AvatarGroupCount`, `AvatarBadge`, `Badge`, `badgeVariants`, `Button`, `buttonVariants`, `Card`, `CardHeader`, `CardFooter`, `CardTitle`, `CardAction`, `CardDescription`, `CardContent`, `Checkbox`, `Dialog`, `DialogClose`, `DialogContent`, `DialogDescription`, `DialogFooter`, `DialogHeader`, `DialogOverlay`, `DialogPortal`, `DialogTitle`, `DialogTrigger`, `DropdownMenu`, `DropdownMenuPortal`, `DropdownMenuTrigger`, `DropdownMenuContent`, `DropdownMenuGroup`, `DropdownMenuLabel`, `DropdownMenuItem`, `DropdownMenuCheckboxItem`, `DropdownMenuRadioGroup`, `DropdownMenuRadioItem`, `DropdownMenuSeparator`, `DropdownMenuShortcut`, `DropdownMenuSub`, `DropdownMenuSubTrigger`, `DropdownMenuSubContent`, `Input`, `Kbd`, `KbdGroup`, `Label`, `Popover`, `PopoverContent`, `PopoverDescription`, `PopoverHeader`, `PopoverTitle`, `PopoverTrigger`, `Progress`, `ProgressTrack`, `ProgressIndicator`, `ProgressLabel`, `ProgressValue`, `RadioGroup`, `RadioGroupItem`, `Select`, `SelectContent`, `SelectGroup`, `SelectItem`, `SelectLabel`, `SelectScrollDownButton`, `SelectScrollUpButton`, `SelectSeparator`, `SelectTrigger`, `SelectValue`, `Separator`, `Sheet`, `SheetTrigger`, `SheetClose`, `SheetContent`, `SheetHeader`, `SheetFooter`, `SheetTitle`, `SheetDescription`, `Skeleton`, `Slider`, `Spinner`, `Switch`, `Table`, `TableHeader`, `TableBody`, `TableFooter`, `TableHead`, `TableRow`, `TableCell`, `TableCaption`, `Tabs`, `TabsList`, `TabsTrigger`, `TabsContent`, `tabsListVariants`, `Textarea`, `Tooltip`, `TooltipTrigger`, `TooltipContent`, `TooltipProvider`.
- `@lathephp/ui/breadcrumb`: `Breadcrumb`, `BreadcrumbList`, `BreadcrumbItem`, `BreadcrumbLink`, `BreadcrumbPage`, `BreadcrumbSeparator`, `BreadcrumbEllipsis`.
- `@lathephp/ui/button-group`: `ButtonGroup`, `ButtonGroupSeparator`, `ButtonGroupText`, `buttonGroupVariants`.
- `@lathephp/ui/calendar`: `Calendar`, `CalendarDayButton`.
- `@lathephp/ui/chart`: `Area`, `AreaChart`, `Bar`, `BarChart`, `CartesianGrid`, `Label`, `LabelList`, `Line`, `LineChart`, `Pie`, `PieChart`, `PolarAngleAxis`, `PolarGrid`, `PolarRadiusAxis`, `Radar`, `RadarChart`, `RadialBar`, `RadialBarChart`, `Rectangle`, `ReferenceLine`, `Sector`, `XAxis`, `YAxis`, `ChartContainer`, `ChartTooltip`, `ChartTooltipContent`, `ChartLegend`, `ChartLegendContent`.
- `@lathephp/ui/code-editor`: `CodeEditor`.
- `@lathephp/ui/collapsible`: `Collapsible`, `CollapsibleTrigger`, `CollapsibleContent`.
- `@lathephp/ui/combobox`: `Combobox`, `ComboboxInput`, `ComboboxContent`, `ComboboxList`, `ComboboxItem`, `ComboboxGroup`, `ComboboxLabel`, `ComboboxCollection`, `ComboboxEmpty`, `ComboboxSeparator`, `ComboboxChips`, `ComboboxChip`, `ComboboxChipsInput`, `ComboboxTrigger`, `ComboboxClear`, `ComboboxValue`, `useComboboxAnchor`.
- `@lathephp/ui/command`: `Command`, `CommandDialog`, `CommandInput`, `CommandList`, `CommandEmpty`, `CommandGroup`, `CommandItem`, `CommandShortcut`, `CommandSeparator`.
- `@lathephp/ui/direction`: `DirectionProvider`, `useDirection`.
- `@lathephp/ui/empty`: `Empty`, `EmptyHeader`, `EmptyTitle`, `EmptyDescription`, `EmptyContent`, `EmptyMedia`.
- `@lathephp/ui/field`: `Field`, `FieldLabel`, `FieldDescription`, `FieldError`, `FieldGroup`, `FieldLegend`, `FieldSeparator`, `FieldSet`, `FieldContent`, `FieldTitle`.
- `@lathephp/ui/hover-card`: `HoverCard`, `HoverCardTrigger`, `HoverCardContent`.
- `@lathephp/ui/input-group`: `InputGroup`, `InputGroupAddon`, `InputGroupButton`, `InputGroupText`, `InputGroupInput`, `InputGroupTextarea`.
- `@lathephp/ui/item`: `Item`, `ItemMedia`, `ItemContent`, `ItemActions`, `ItemGroup`, `ItemSeparator`, `ItemTitle`, `ItemDescription`, `ItemHeader`, `ItemFooter`.
- `@lathephp/ui/otp-field`: `OTPField`, `OTPFieldGroup`, `OTPFieldSeparator`, `OTPFieldSlot`.
- `@lathephp/ui/pagination`: `Pagination`, `PaginationContent`, `PaginationEllipsis`, `PaginationItem`, `PaginationLink`, `PaginationNext`, `PaginationPrevious`.
- `@lathephp/ui/popover`: `Popover`, `PopoverContent`, `PopoverDescription`, `PopoverHeader`, `PopoverTitle`, `PopoverTrigger`.
- `@lathephp/ui/scroll-area`: `ScrollArea`, `ScrollBar`.
- `@lathephp/ui/select`: `Select`, `SelectContent`, `SelectGroup`, `SelectItem`, `SelectLabel`, `SelectScrollDownButton`, `SelectScrollUpButton`, `SelectSeparator`, `SelectTrigger`, `SelectValue`.
- `@lathephp/ui/shimmer-bar`: `ShimmerBar`.
- `@lathephp/ui/sidebar`: `Sidebar`, `SidebarContent`, `SidebarFooter`, `SidebarGroup`, `SidebarGroupAction`, `SidebarGroupContent`, `SidebarGroupLabel`, `SidebarHeader`, `SidebarInput`, `SidebarInset`, `SidebarMenu`, `SidebarMenuAction`, `SidebarMenuBadge`, `SidebarMenuButton`, `SidebarMenuItem`, `SidebarMenuSkeleton`, `SidebarMenuSub`, `SidebarMenuSubButton`, `SidebarMenuSubItem`, `SidebarProvider`, `SidebarRail`, `SidebarSeparator`, `SidebarTrigger`, `useSidebar`.
- `@lathephp/ui/toast`: `createToastManager`, `Toast`, `ToastContent`, `ToastDescription`, `ToastProvider`, `ToastTitle`, `ToastViewport`, `useToastManager`.
- `@lathephp/ui/toggle`: `Toggle`, `toggleVariants`.
- `@lathephp/ui/toggle-group`: `ToggleGroup`, `ToggleGroupItem`.
