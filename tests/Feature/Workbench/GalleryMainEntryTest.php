<?php

// Every main-entry part of @lathephp/ui that has a visible form shows up in the gallery as JSX, not only as an import.
function gallerySource(): string
{
    $dir = dirname(__DIR__, 3).'/workbench/resources/js/lathe/pages';
    $page = (string) file_get_contents($dir.'/UiGallery.tsx');
    $source = $page;

    preg_match_all("/from '\\.\\/([A-Za-z]+)'/", $page, $siblings);

    foreach (array_unique($siblings[1]) as $name) {
        if (is_file($dir.'/'.$name.'.tsx')) {
            $source .= "\n".file_get_contents($dir.'/'.$name.'.tsx');
        }
    }

    return $source;
}

it('shows each main-entry part in the gallery', function (string $name) {
    expect(gallerySource())->toMatch('/<'.$name.'[\s>\/]/');
})->with([
    'AlertAction', 'AlertDialogMedia', 'AvatarImage', 'AvatarGroup', 'AvatarGroupCount', 'AvatarBadge',
    'CardFooter', 'CardAction', 'DialogClose', 'DialogFooter', 'DropdownMenuGroup', 'DropdownMenuLabel',
    'DropdownMenuRadioGroup', 'DropdownMenuRadioItem', 'DropdownMenuShortcut', 'DropdownMenuSub',
    'DropdownMenuSubTrigger', 'DropdownMenuSubContent', 'PopoverDescription', 'PopoverHeader', 'PopoverTitle',
    'ProgressLabel', 'ProgressValue', 'SelectGroup', 'SelectLabel',
    'SelectSeparator', 'SheetClose', 'SheetFooter', 'TableFooter', 'TableCaption',
]);
