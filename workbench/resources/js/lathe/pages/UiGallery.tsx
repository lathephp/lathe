import { emit, on, PageHeader, toast, useShortcut, useTranslation } from '@lathephp/core';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
  Alert,
  AlertAction,
  AlertDescription,
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
  AlertDialogTrigger,
  AlertTitle,
  Avatar,
  AvatarBadge,
  AvatarFallback,
  AvatarGroup,
  AvatarGroupCount,
  AvatarImage,
  Badge,
  Button,
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
  Checkbox,
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
  Input,
  Kbd,
  KbdGroup,
  Label,
  Popover,
  PopoverContent,
  PopoverDescription,
  PopoverHeader,
  PopoverTitle,
  PopoverTrigger,
  Progress,
  ProgressLabel,
  ProgressValue,
  RadioGroup,
  RadioGroupItem,
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
  Separator,
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
  Skeleton,
  Slider,
  Spinner,
  Switch,
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
  Textarea,
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@lathephp/ui';
import { Suspense, useEffect, useState, type ReactNode } from 'react';
import { PrimitiveBoards } from './PrimitiveBoards';
import { DemoFallback, subpathDemos } from './subpathDemos';

/** One component of the kit, found by its module name in data-component. */
function Demo({ name, children }: { name: string; children: ReactNode }) {
  return (
    <section data-component={name} className="grid content-start gap-3 rounded-lg border p-4">
      <h2 className="font-mono text-xs text-muted-foreground">{name}</h2>
      <div className="flex flex-wrap items-center gap-3">{children}</div>
    </section>
  );
}

/** Every @lathephp/ui component, the event bus and a shortcut, on one page. */
export default function UiGallery() {
  const { __ } = useTranslation();
  const [received, setReceived] = useState(0);

  useEffect(
    () =>
      on('ui-gallery:ping', () => {
        setReceived((count) => count + 1);
      }),
    [],
  );

  useShortcut('g d', () => {
    toast.success(__('You pressed g then d.'));
  });

  return (
    <div className="grid gap-6">
      <PageHeader title={__('UI Gallery')} description={__('Every @lathephp/ui component on one page.')} />

      <PrimitiveBoards />

      <h2 className="mt-10 text-[22px] leading-7 font-[650] tracking-[-0.01em]">{__('Every component')}</h2>

      <Card>
        <CardHeader>
          <CardTitle>{__('Event bus and shortcuts')}</CardTitle>
          <CardDescription>{__('Press g then d anywhere on the page for a toast.')}</CardDescription>
        </CardHeader>
        <CardContent className="flex items-center gap-3">
          <Button
            onClick={() => {
              emit('ui-gallery:ping');
            }}
          >
            {__('Emit event')}
          </Button>
          <span className="text-sm">{__('Events received: :count', { count: String(received) })}</span>
        </CardContent>
      </Card>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Demo name="accordion">
          <Accordion className="w-full">
            <AccordionItem value="shipping">
              <AccordionTrigger>{__('Shipping')}</AccordionTrigger>
              <AccordionContent>{__('Ships in two days.')}</AccordionContent>
            </AccordionItem>
          </Accordion>
        </Demo>
        <Demo name="alert">
          <Alert>
            <AlertTitle>{__('Heads up')}</AlertTitle>
            <AlertDescription>{__('The import finished.')}</AlertDescription>
            <AlertAction>
              <Button size="sm" variant="outline">
                {__('Undo')}
              </Button>
            </AlertAction>
          </Alert>
        </Demo>
        <Demo name="alert-dialog">
          <AlertDialog>
            <AlertDialogTrigger render={<Button variant="destructive" />}>{__('Delete')}</AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogMedia>
                  <Spinner aria-label={__('Deleting')} />
                </AlertDialogMedia>
                <AlertDialogTitle>{__('Delete the order?')}</AlertDialogTitle>
                <AlertDialogDescription>{__('It cannot be restored.')}</AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>{__('Cancel')}</AlertDialogCancel>
                <AlertDialogAction variant="destructive-solid">{__('Delete')}</AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </Demo>
        <Demo name="avatar">
          <Avatar>
            <AvatarImage src="" alt={__('Avatar')} />
            <AvatarFallback>AU</AvatarFallback>
            <AvatarBadge />
          </Avatar>
          <AvatarGroup>
            <Avatar>
              <AvatarFallback>AB</AvatarFallback>
            </Avatar>
            <Avatar>
              <AvatarFallback>CD</AvatarFallback>
            </Avatar>
            <AvatarGroupCount>+3</AvatarGroupCount>
          </AvatarGroup>
        </Demo>
        <Demo name="badge">
          <Badge>{__('Paid')}</Badge>
          <Badge variant="secondary">{__('Draft')}</Badge>
        </Demo>
        <Demo name="button">
          <Button>{__('Save')}</Button>
          <Button variant="outline">{__('Cancel')}</Button>
        </Demo>
        <Demo name="card">
          <Card className="w-full">
            <CardHeader>
              <CardTitle>{__('Revenue')}</CardTitle>
              <CardDescription>{__('This month')}</CardDescription>
              <CardAction>
                <Badge variant="secondary">{__('Live')}</Badge>
              </CardAction>
            </CardHeader>
            <CardContent>$1,200</CardContent>
            <CardFooter>
              <Button size="sm" variant="outline">
                {__('Export')}
              </Button>
            </CardFooter>
          </Card>
        </Demo>
        <Demo name="checkbox">
          <Label className="gap-2">
            <Checkbox defaultChecked />
            {__('Paid')}
          </Label>
          <Label className="gap-2">
            <Checkbox indeterminate />
            {__('Some paid')}
          </Label>
          <Label className="gap-2">
            <Checkbox disabled />
            {__('Refunded')}
          </Label>
        </Demo>
        <Demo name="dialog">
          <Dialog>
            <DialogTrigger render={<Button variant="outline" />}>{__('Edit order')}</DialogTrigger>
            <DialogContent closeLabel={__('Close')}>
              <DialogHeader>
                <DialogTitle>{__('Edit order')}</DialogTitle>
                <DialogDescription>{__('Change the address.')}</DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <DialogClose render={<Button variant="outline" />}>{__('Cancel')}</DialogClose>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </Demo>
        <Demo name="dropdown-menu">
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="outline" />}>{__('Actions')}</DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuGroup>
                <DropdownMenuLabel>{__('Order')}</DropdownMenuLabel>
                <DropdownMenuItem>
                  {__('Refund')}
                  <DropdownMenuShortcut>R</DropdownMenuShortcut>
                </DropdownMenuItem>
                <DropdownMenuItem variant="destructive">{__('Delete')}</DropdownMenuItem>
              </DropdownMenuGroup>
              <DropdownMenuSeparator />
              <DropdownMenuCheckboxItem defaultChecked>{__('Notify the customer')}</DropdownMenuCheckboxItem>
              <DropdownMenuSeparator />
              <DropdownMenuRadioGroup defaultValue="all">
                <DropdownMenuRadioItem value="all">{__('All orders')}</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="paid">{__('Paid orders')}</DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>
              <DropdownMenuSub>
                <DropdownMenuSubTrigger>{__('Export')}</DropdownMenuSubTrigger>
                <DropdownMenuSubContent>
                  <DropdownMenuItem>{__('As CSV')}</DropdownMenuItem>
                </DropdownMenuSubContent>
              </DropdownMenuSub>
            </DropdownMenuContent>
          </DropdownMenu>
        </Demo>
        <Demo name="input">
          <Input aria-label={__('Name')} placeholder={__('Name')} />
        </Demo>
        <Demo name="kbd">
          <KbdGroup>
            <Kbd>Ctrl</Kbd>
            <Kbd>K</Kbd>
          </KbdGroup>
        </Demo>
        <Demo name="label">
          <Label htmlFor="ui-gallery-label">{__('Email')}</Label>
          <Input id="ui-gallery-label" type="email" />
        </Demo>
        <Demo name="popover">
          <Popover>
            <PopoverTrigger render={<Button variant="outline" />}>{__('Share')}</PopoverTrigger>
            <PopoverContent>
              <PopoverHeader>
                <PopoverTitle>{__('Share')}</PopoverTitle>
                <PopoverDescription>{__('Anyone with the link can view.')}</PopoverDescription>
              </PopoverHeader>
            </PopoverContent>
          </Popover>
        </Demo>
        <Demo name="progress">
          <Progress value={40} aria-label={__('Upload')} className="w-full" />
          <Progress value={60} className="w-full">
            <ProgressLabel>{__('Import')}</ProgressLabel>
            <ProgressValue />
          </Progress>
        </Demo>
        <Demo name="radio-group">
          <RadioGroup aria-label={__('Size')} defaultValue="s">
            <Label className="gap-2">
              <RadioGroupItem value="s" />
              {__('Small')}
            </Label>
            <Label className="gap-2">
              <RadioGroupItem value="l" />
              {__('Large')}
            </Label>
            <Label className="gap-2">
              <RadioGroupItem value="xl" disabled />
              {__('Extra large')}
            </Label>
          </RadioGroup>
        </Demo>
        <Demo name="select">
          <Select>
            <SelectTrigger aria-label={__('Status')}>
              <SelectValue placeholder={__('Pick one')} />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup>
                <SelectLabel>{__('Payment')}</SelectLabel>
                <SelectItem value="paid">{__('Paid')}</SelectItem>
                <SelectItem value="refunded">{__('Refunded')}</SelectItem>
              </SelectGroup>
              <SelectSeparator />
              <SelectItem value="draft">{__('Draft')}</SelectItem>
            </SelectContent>
          </Select>
        </Demo>
        <Demo name="separator">
          <span className="text-sm">{__('Before')}</span>
          <Separator orientation="vertical" className="h-4" />
          <span className="text-sm">{__('After')}</span>
        </Demo>
        <Demo name="sheet">
          <Sheet>
            <SheetTrigger render={<Button variant="outline" />}>{__('Filters')}</SheetTrigger>
            <SheetContent closeLabel={__('Close')}>
              <SheetHeader>
                <SheetTitle>{__('Filters')}</SheetTitle>
                <SheetDescription>{__('Narrow the list.')}</SheetDescription>
              </SheetHeader>
              <SheetFooter>
                <SheetClose render={<Button variant="outline" />}>{__('Done')}</SheetClose>
              </SheetFooter>
            </SheetContent>
          </Sheet>
        </Demo>
        <Demo name="skeleton">
          <Skeleton className="h-4 w-32" />
        </Demo>
        <Demo name="slider">
          <Slider aria-label={__('Volume')} defaultValue={[20]} className="w-full" />
        </Demo>
        <Demo name="spinner">
          <Spinner aria-label={__('Saving')} />
        </Demo>
        <Demo name="switch">
          <Label className="gap-2">
            <Switch />
            {__('Notify')}
          </Label>
        </Demo>
        <Demo name="table">
          <Table>
            <TableCaption>{__('Recent orders')}</TableCaption>
            <TableHeader>
              <TableRow>
                <TableHead>{__('Number')}</TableHead>
                <TableHead>{__('Status')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow>
                <TableCell>1001</TableCell>
                <TableCell>{__('Paid')}</TableCell>
              </TableRow>
            </TableBody>
            <TableFooter>
              <TableRow>
                <TableCell>{__('Total')}</TableCell>
                <TableCell>1</TableCell>
              </TableRow>
            </TableFooter>
          </Table>
        </Demo>
        <Demo name="tabs">
          <Tabs defaultValue="details">
            <TabsList>
              <TabsTrigger value="details">{__('Details')}</TabsTrigger>
              <TabsTrigger value="history">{__('History')}</TabsTrigger>
            </TabsList>
            <TabsContent value="details">{__('Order details')}</TabsContent>
            <TabsContent value="history">{__('Order history')}</TabsContent>
          </Tabs>
        </Demo>
        <Demo name="textarea">
          <Textarea aria-label={__('Notes')} placeholder={__('Notes')} />
        </Demo>
        <Demo name="tooltip">
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger render={<Button variant="outline" />}>{__('Help')}</TooltipTrigger>
              <TooltipContent>{__('Opens the docs')}</TooltipContent>
            </Tooltip>
          </TooltipProvider>
        </Demo>
        {subpathDemos.map(([name, Component]) => (
          <Demo key={name} name={name}>
            <Suspense fallback={<DemoFallback />}>
              <Component />
            </Suspense>
          </Demo>
        ))}
      </div>
    </div>
  );
}
