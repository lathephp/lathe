<?php

namespace Lathe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lathe\Dashboards\Dashboard;
use Lathe\Dashboards\DashboardRegistry;
use Lathe\Enums\CardWidth;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Preferences\DashboardLayout;
use Lathe\Preferences\UserPreference;
use Lathe\Widgets\WidgetList;

/**
 * The signed-in user's layout of a dashboard. The route names no user, so each user writes only their own row.
 */
class DashboardLayoutController
{
    public function __construct(protected DashboardRegistry $dashboards)
    {
        //
    }

    public function update(LatheRequest $request, string $dashboard): JsonResponse
    {
        $found = $this->customizable($request, $dashboard);

        Validator::make($request->all(), [
            'widgets' => ['present', 'list'],
            'widgets.*.uriKey' => ['required', 'string'],
            'widgets.*.width' => ['required', Rule::enum(CardWidth::class)],
            'widgets.*.hidden' => ['required', 'boolean'],
        ])->validate();

        $widgets = (new WidgetList($found->widgets($request), $found::class))->visible($request);
        $layout = DashboardLayout::age($request->array('widgets'), DashboardLayout::codeWidths($widgets));

        UserPreference::updateOrCreate(
            ['user_id' => UserPreference::userId($request), 'key' => DashboardLayout::key($found::uriKey())],
            ['value' => $layout],
        );

        return new JsonResponse(['layout' => $layout]);
    }

    public function destroy(LatheRequest $request, string $dashboard): Response
    {
        $found = $this->customizable($request, $dashboard);

        UserPreference::where('user_id', UserPreference::userId($request))->where('key', DashboardLayout::key($found::uriKey()))->delete();

        return new Response(null, 204);
    }

    /**
     * Unknown keys answer 404; a dashboard the user may not see, or that is not customizable, answers 403.
     */
    protected function customizable(LatheRequest $request, string $uriKey): Dashboard
    {
        $dashboard = $this->dashboards->forKey($uriKey);

        abort_if($dashboard === null, 404);
        abort_unless($dashboard->authorize($request) && $dashboard->isCustomizable(), 403);

        return $dashboard;
    }
}
