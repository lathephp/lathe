<?php

namespace Lathe\Theme;

/**
 * The panel's colour tokens, the keys of theme.colors in the config.
 */
enum Color: string
{
    case Background = 'background';
    case Foreground = 'foreground';
    case Card = 'card';
    case CardForeground = 'card-foreground';
    case Popover = 'popover';
    case PopoverForeground = 'popover-foreground';
    case Primary = 'primary';
    case PrimaryForeground = 'primary-foreground';
    case Secondary = 'secondary';
    case SecondaryForeground = 'secondary-foreground';
    case Muted = 'muted';
    case MutedForeground = 'muted-foreground';
    case Accent = 'accent';
    case AccentForeground = 'accent-foreground';
    case Destructive = 'destructive';
    case DestructiveForeground = 'destructive-foreground';
    case Success = 'success';
    case SuccessForeground = 'success-foreground';
    case Warning = 'warning';
    case WarningForeground = 'warning-foreground';
    case Info = 'info';
    case InfoForeground = 'info-foreground';
    case Border = 'border';
    case Input = 'input';
    case Ring = 'ring';
    case Link = 'link';
    case LinkHover = 'link-hover';
    case Chart1 = 'chart-1';
    case Chart2 = 'chart-2';
    case Chart3 = 'chart-3';
    case Chart4 = 'chart-4';
    case Chart5 = 'chart-5';
    case Sidebar = 'sidebar';
    case SidebarForeground = 'sidebar-foreground';
    case SidebarPrimary = 'sidebar-primary';
    case SidebarPrimaryForeground = 'sidebar-primary-foreground';
    case SidebarAccent = 'sidebar-accent';
    case SidebarAccentForeground = 'sidebar-accent-foreground';
    case SidebarBorder = 'sidebar-border';
    case SidebarRing = 'sidebar-ring';

    /**
     * The CSS variable that holds the colour, such as --primary.
     */
    public function variable(): string
    {
        return "--{$this->value}";
    }

    /**
     * The background utility that paints with it, such as bg-primary.
     */
    public function utility(): string
    {
        return "bg-{$this->value}";
    }
}
