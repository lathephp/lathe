<?php

namespace Workbench\App\Lathe\Plugins;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lathe\Enums\Icon;
use Lathe\Lathe;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Plugins\Plugin;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Workbench\App\Http\Middleware\InsightsHeader;
use Workbench\App\Lathe\Dashboards\WidgetGallery;
use Workbench\App\Lathe\Search\InsightsPages;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;

/**
 * A plugin that uses every hook: a middleware, script data, a sidebar section with a cached
 * badge, a plugin page, a dashboard, an API endpoint and a palette provider.
 */
class InsightsPlugin extends Plugin
{
    /** Orders that are neither shipped nor cancelled. */
    protected const array OPEN = ['pending', 'paid'];

    public function requires(): array
    {
        return [Carbon::class];
    }

    public function middleware(): array
    {
        return [InsightsHeader::class];
    }

    public function boot(): void
    {
        parent::boot();

        Lathe::registerDashboards(WidgetGallery::class);
        Lathe::registerSearchProviders(new InsightsPages($this));
    }

    public function serving(Request $request): void
    {
        Lathe::provideToScript([
            'insightsViewer' => $request->user(Lathe::fortify()->guard())?->getAttribute('name'),
            'insightsServerTime' => Carbon::now()->toIso8601String(),
        ]);
    }

    public function menu(Request $request): array
    {
        // Sorted ahead of the app's sections, so Insights sits right under the dashboards.
        return [MenuSection::section('Insights', [
            // MenuItem::plugin() would label the link Insights, after the plugin.
            MenuItem::link('Overview', 'plugins/'.static::uriKey())->icon(Icon::BarChart)
                ->withBadge(fn () => Order::whereIn('status', self::OPEN)->count(), 60),
            MenuItem::dashboard(WidgetGallery::uriKey()),
        ])->sort(1)];
    }

    public function handleRequest(Request $request, string $path): Response
    {
        if ($path !== 'summary' || ! $request->isMethod('GET')) {
            throw new NotFoundHttpException;
        }

        return new JsonResponse([
            'orders' => Order::count(),
            'openOrders' => Order::whereIn('status', self::OPEN)->count(),
            'customers' => Customer::count(),
            'products' => Product::count(),
        ]);
    }
}
