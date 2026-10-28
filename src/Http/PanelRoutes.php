<?php

namespace Lathe\Http;

use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Lathe\Auth\FortifyConfig;
use Lathe\Http\Controllers\Assets\AssetController;
use Lathe\Http\Controllers\Auth\AuthenticatedSessionController;
use Lathe\Http\Controllers\Auth\ConfirmablePasswordController;
use Lathe\Http\Controllers\Auth\EmailVerificationController;
use Lathe\Http\Controllers\Auth\LoginPageController;
use Lathe\Http\Controllers\Auth\NewPasswordController;
use Lathe\Http\Controllers\Auth\PasswordResetLinkController;
use Lathe\Http\Controllers\Auth\SecurityController;
use Lathe\Http\Controllers\Auth\TwoFactorAuthenticationController;
use Lathe\Http\Controllers\Auth\TwoFactorChallengeController;
use Lathe\Http\Controllers\BulkRunController;
use Lathe\Http\Controllers\DashboardController;
use Lathe\Http\Controllers\DashboardLayoutController;
use Lathe\Http\Controllers\ExportController;
use Lathe\Http\Controllers\GlobalSearchController;
use Lathe\Http\Controllers\ImpersonateController;
use Lathe\Http\Controllers\ImportController;
use Lathe\Http\Controllers\NotificationController;
use Lathe\Http\Controllers\PageController;
use Lathe\Http\Controllers\PageFallbackController;
use Lathe\Http\Controllers\Plugins\ModuleController;
use Lathe\Http\Controllers\Plugins\PluginController;
use Lathe\Http\Controllers\Plugins\PluginPageController;
use Lathe\Http\Controllers\Plugins\ResourcePluginController;
use Lathe\Http\Controllers\Resources\ActionController;
use Lathe\Http\Controllers\Resources\ActionLookupController;
use Lathe\Http\Controllers\Resources\ActionRunController;
use Lathe\Http\Controllers\Resources\AssociatableController;
use Lathe\Http\Controllers\Resources\BatchDeleteController;
use Lathe\Http\Controllers\Resources\ColumnPreferenceController;
use Lathe\Http\Controllers\Resources\CountController;
use Lathe\Http\Controllers\Resources\DeleteController;
use Lathe\Http\Controllers\Resources\DependencyController;
use Lathe\Http\Controllers\Resources\DetailPageController;
use Lathe\Http\Controllers\Resources\FieldAttachmentController;
use Lathe\Http\Controllers\Resources\FieldFileController;
use Lathe\Http\Controllers\Resources\FieldPreviewController;
use Lathe\Http\Controllers\Resources\FilterController;
use Lathe\Http\Controllers\Resources\FormFieldsController;
use Lathe\Http\Controllers\Resources\FormPageController;
use Lathe\Http\Controllers\Resources\HistoryController;
use Lathe\Http\Controllers\Resources\IndexController;
use Lathe\Http\Controllers\Resources\IndexPageController;
use Lathe\Http\Controllers\Resources\InlineUpdateController;
use Lathe\Http\Controllers\Resources\LensActionController;
use Lathe\Http\Controllers\Resources\LensController;
use Lathe\Http\Controllers\Resources\PeekController;
use Lathe\Http\Controllers\Resources\PreviewPreferenceController;
use Lathe\Http\Controllers\Resources\RecordController;
use Lathe\Http\Controllers\Resources\RelatableAuthorizationController;
use Lathe\Http\Controllers\Resources\RelationAttachController;
use Lathe\Http\Controllers\Resources\RelationIndexController;
use Lathe\Http\Controllers\Resources\SavedTabController;
use Lathe\Http\Controllers\RunPageController;
use Lathe\Http\Controllers\Uploads\StartUploadController;
use Lathe\Http\Controllers\Uploads\UploadController;
use Lathe\Http\Controllers\WidgetController;
use Lathe\Http\Middleware\Authenticate;
use Lathe\Http\Middleware\AuthorizePlugin;
use Lathe\Http\Middleware\EnsureJson;
use Lathe\Http\Middleware\HandleInertiaRequests;
use Lathe\Lathe;
use Lathe\Plugins\PluginRegistry;
use Lathe\Support\ConfigStore;

/**
 * Registers the panel's page and JSON routes under lathe.path, on lathe.domain when set.
 */
class PanelRoutes
{
    public function __construct(
        protected Router $router,
        protected ConfigStore $config,
        protected PluginRegistry $plugins,
        protected FortifyConfig $fortify,
    ) {
        //
    }

    public function register(): void
    {
        // Prebuilt files load without a session, so they set no cookie.
        $this->group()->group(function (Router $router) {
            $router->get('assets/{hash}/{file}', AssetController::class)->name('lathe.assets');
            $router->get('modules/{file}', ModuleController::class)->name('lathe.modules');
            $router->get('styles/{file}', ModuleController::class)->defaults('type', 'styles')->name('lathe.styles');
        });

        $this->group()->middleware('lathe')->name('lathe.')->group(function (Router $router) {
            $guest = 'lathe.guest:'.$this->fortify->guard();

            if (Lathe::hasAuthenticationRoutes()) {
                $router->get('login', LoginPageController::class)->middleware($guest)->name('login');
                $router->post('login', [AuthenticatedSessionController::class, 'store'])->middleware($guest)->name('login.store');
                // POST only: a GET would let any image tag sign the user out.
                $router->post('logout', [AuthenticatedSessionController::class, 'destroy'])->middleware(Authenticate::class.':unverified')->name('logout');
                // RequirePassword::using('lathe.password.confirm') sends a page visit here and answers 423 JSON.
                $router->get('confirm-password', [ConfirmablePasswordController::class, 'show'])->middleware(Authenticate::class)->name('password.confirm');
                $router->post('confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware(Authenticate::class)->name('password.confirm.store');
                $router->get('confirmed-password-status', [ConfirmablePasswordController::class, 'status'])->middleware(Authenticate::class)->name('password.confirmation');

                if ($this->fortify->featureEnabled('twoFactorAuthentication')) {
                    $router->get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->middleware($guest)->name('two-factor.login');
                    $router->post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware($guest)->name('two-factor.login.store');
                }
            }

            if ($this->fortify->featureEnabled('emailVerification')) {
                $unverified = Authenticate::class.':unverified';
                $router->get('email/verify', [EmailVerificationController::class, 'notice'])->middleware($unverified)->name('verification.notice');
                $router->get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware([$unverified, 'signed', 'throttle:6,1'])->name('verification.verify');
                $router->post('email/verification-notification', [EmailVerificationController::class, 'send'])->middleware([$unverified, 'throttle:6,1'])->name('verification.send');
            }

            if (Lathe::hasPasswordResetRoutes()) {
                $router->get('forgot-password', [PasswordResetLinkController::class, 'create'])->middleware($guest)->name('password.request');
                $router->post('forgot-password', [PasswordResetLinkController::class, 'store'])->middleware($guest)->name('password.email');
                $router->get('reset-password/{token}', [NewPasswordController::class, 'create'])->middleware($guest)->name('password.reset');
                $router->post('reset-password', [NewPasswordController::class, 'store'])->middleware($guest)->name('password.update');
            }
        });

        $this->group()->middleware('lathe.api')->group(function (Router $router) {
            $router->get('/', DashboardController::class)->name('lathe.dashboard');
            $router->get('dashboards/{dashboard}', [DashboardController::class, 'page'])->name('lathe.dashboards.show');
            $router->get('resources/{resource}', IndexPageController::class)->name('lathe.resources.index');
            $router->get('resources/{resource}/lens/{lens}', [LensController::class, 'page'])->name('lathe.resources.lens');
            // Before {resourceId}, so "new" never reads as a record id.
            $router->get('resources/{resource}/new', [FormPageController::class, 'create'])->name('lathe.resources.create');
            $router->get('resources/{resource}/{resourceId}', DetailPageController::class)->name('lathe.resources.show');
            $router->get('resources/{resource}/{resourceId}/edit', [FormPageController::class, 'edit'])->name('lathe.resources.edit');
            $router->get('resources/{resource}/{resourceId}/replicate', [FormPageController::class, 'replicate'])->name('lathe.resources.replicate');
            $this->runPageRoutes($router);
            $router->get('security', [SecurityController::class, 'show'])->name('lathe.security');

            if ($this->fortify->featureEnabled('updatePasswords')) {
                $router->put('user-security/password', [SecurityController::class, 'updatePassword'])->name('lathe.user-password.update');
            }

            if ($this->fortify->featureEnabled('twoFactorAuthentication')) {
                $twoFactor = TwoFactorAuthenticationController::class;
                // With confirmPassword (not confirm) each answers 423 until the password is confirmed.
                $router->middleware($this->fortify->twoFactorOption('confirmPassword') ? [RequirePassword::using('lathe.password.confirm')] : [])
                    ->group(function (Router $router) use ($twoFactor) {
                        $router->post('user/two-factor-authentication', [$twoFactor, 'store'])->name('lathe.two-factor.enable');
                        $router->post('user/confirmed-two-factor-authentication', [$twoFactor, 'confirm'])->name('lathe.two-factor.confirm');
                        $router->delete('user/two-factor-authentication', [$twoFactor, 'destroy'])->name('lathe.two-factor.disable');
                        $router->get('user/two-factor-qr-code', [$twoFactor, 'qrCode'])->name('lathe.two-factor.qr-code');
                        $router->get('user/two-factor-secret-key', [$twoFactor, 'secretKey'])->name('lathe.two-factor.secret-key');
                        $router->get('user/two-factor-recovery-codes', [$twoFactor, 'recoveryCodes'])->name('lathe.two-factor.recovery-codes');
                        $router->post('user/two-factor-recovery-codes', [$twoFactor, 'regenerateRecoveryCodes'])->name('lathe.two-factor.regenerate-recovery-codes');
                    });
            }
            $router->get('pages/{page}/{path?}', PageController::class)
                ->where('path', '.*')
                ->name('lathe.pages.show');
            $router->get('plugins/{plugin}/{path?}', PluginPageController::class)
                ->where('path', '.*')
                ->name('lathe.plugins.show');
        });

        foreach ($this->plugins->all() as $plugin) {
            $this->api()
                ->prefix($this->apiPrefix().'/plugins/'.$plugin::uriKey())
                // A nested group, because a second middleware() on one registrar replaces the API middleware.
                ->group(fn (Router $router) => $router->middleware(AuthorizePlugin::class.':'.$plugin::uriKey())
                    ->group(fn (Router $router) => $plugin->routes($router)));
        }

        $this->api()->prefix($this->apiPrefix())->name('lathe.api.')->group(function (Router $router) {
            // Before the {resourceId} routes, so actions, action and batch never read as a record id.
            $this->actionRoutes($router);
            $this->batchRoutes($router);
            $this->bulkRunRoutes($router);
            $this->exportRoutes($router);
            $this->importRoutes($router);
            $this->notificationRoutes($router);
            $this->dashboardRoutes($router);
            $this->resourcePluginRoutes($router);
            $router->get('resources/{resource}', IndexController::class)->name('resources.index');
            $router->get('resources/{resource}/count', CountController::class)->name('resources.count');
            $router->get('resources/{resource}/filters', [FilterController::class, 'index'])->name('resources.filters');
            $router->get('resources/{resource}/filters/{filter}/options', [FilterController::class, 'options'])->name('resources.filters.options');
            $router->get('resources/{resource}/saved-tabs', [SavedTabController::class, 'index'])->name('resources.savedTabs.index');
            $router->post('resources/{resource}/saved-tabs', [SavedTabController::class, 'store'])->name('resources.savedTabs.store');
            $router->put('resources/{resource}/saved-tabs/reorder', [SavedTabController::class, 'reorder'])->name('resources.savedTabs.reorder');
            $router->put('resources/{resource}/saved-tabs/{savedTab}', [SavedTabController::class, 'update'])->name('resources.savedTabs.update');
            $router->delete('resources/{resource}/saved-tabs/{savedTab}', [SavedTabController::class, 'destroy'])->name('resources.savedTabs.destroy');
            $router->put('resources/{resource}/columns', [ColumnPreferenceController::class, 'update'])->name('resources.columns.update');
            $router->delete('resources/{resource}/columns', [ColumnPreferenceController::class, 'destroy'])->name('resources.columns.destroy');
            $router->put('resources/{resource}/preview-panel', [PreviewPreferenceController::class, 'update'])->name('resources.preview-panel');
            // Before the {resourceId} routes, so a lens path never reads as a record id.
            $router->get('resources/{resource}/lenses/{lens}', [LensController::class, 'index'])->name('resources.lenses.index');
            $router->get('resources/{resource}/lenses/{lens}/count', [LensController::class, 'count'])->name('resources.lenses.count');
            $router->get('resources/{resource}/lenses/{lens}/filters', [LensController::class, 'filters'])->name('resources.lenses.filters');
            $router->get('resources/{resource}/creation-fields', [FormFieldsController::class, 'creation'])->name('resources.creationFields');
            $router->get('resources/{resource}/relate-authorization', RelatableAuthorizationController::class)->name('resources.relateAuthorization');
            $router->get('resources/{resource}/associatable/{field}', [AssociatableController::class, 'create'])->name('resources.associatable');
            $router->post('resources/{resource}/field/{field}/preview', [FieldPreviewController::class, 'create'])->name('resources.field.preview');
            $router->get('resources/{resource}/field-attachment/{field}/draft-id', [FieldAttachmentController::class, 'draftId'])->name('resources.fieldAttachment.draftId');
            $router->post('resources/{resource}/field-attachment/{field}', [FieldAttachmentController::class, 'store'])->name('resources.fieldAttachment.store');
            $router->delete('resources/{resource}/field-attachment/{field}', [FieldAttachmentController::class, 'destroy'])->name('resources.fieldAttachment.destroy');
            $router->delete('resources/{resource}/field-attachment/{field}/{draftId}', [FieldAttachmentController::class, 'destroyDraft'])->name('resources.fieldAttachment.destroyDraft');
            $router->post('resources/{resource}', [RecordController::class, 'store'])->name('resources.store');
            $router->post('resources/{resource}/field-dependencies', DependencyController::class)->name('resources.dependencies');
            $router->get('resources/{resource}/{resourceId}/update-fields', [FormFieldsController::class, 'update'])->name('resources.updateFields');
            $router->post('resources/{resource}/{resourceId}/field/{field}/preview', [FieldPreviewController::class, 'update'])->name('resources.record.field.preview');
            $router->get('resources/{resource}/{resourceId}/replicate-fields', [FormFieldsController::class, 'replicate'])->name('resources.replicateFields');
            $router->get('resources/{resource}/{resourceId}/{field}/associatable', [AssociatableController::class, 'record'])->name('resources.record.associatable');
            $router->get('resources/{resource}/{resourceId}/{relationship}/pivot-fields', [RelationAttachController::class, 'pivotFieldsForAttach'])->name('resources.relation.pivotFields');
            $router->get('resources/{resource}/{resourceId}/{relationship}/{relatedId}/pivot-fields', [RelationAttachController::class, 'pivotFieldValues'])->name('resources.relation.pivotFieldValues');
            $router->get('resources/{resource}/{resourceId}/history/latest', HistoryController::class)->name('resources.history');
            $router->get('resources/{resource}/{resourceId}/peek', [PeekController::class, 'peek'])->name('resources.peek');
            $router->get('resources/{resource}/{resourceId}/preview', [PeekController::class, 'preview'])->name('resources.preview');
            $router->put('resources/{resource}/{resourceId}', [RecordController::class, 'update'])->name('resources.update');
            $router->patch('resources/{resource}/{resourceId}/field/{field}', InlineUpdateController::class)->name('resources.inline');
            $router->delete('resources/{resource}/{resourceId}', [DeleteController::class, 'delete'])->name('resources.delete');
            $router->delete('resources/{resource}/{resourceId}/force', [DeleteController::class, 'forceDelete'])->name('resources.forceDelete');
            $router->put('resources/{resource}/{resourceId}/restore', [DeleteController::class, 'restore'])->name('resources.restore');
            $router->get('resources/{resource}/{resourceId}/download/{field}', [FieldFileController::class, 'download'])->name('resources.field.download');
            $router->get('resources/{resource}/{resourceId}/{relationship}', RelationIndexController::class)->name('resources.relation.index');
            // The constraints keep the upload start and field file delete routes below reachable.
            $router->post('resources/{resource}/{resourceId}/{relationship}', [RelationAttachController::class, 'attach'])->where('resourceId', '(?!uploads/)[^/]++')->name('resources.relation.attach');
            $router->delete('resources/{resource}/{resourceId}/{relationship}/{relatedId}', [RelationAttachController::class, 'detach'])->where('relationship', '(?!field/)[^/]++')->name('resources.relation.detach');
            $router->put('resources/{resource}/{resourceId}/{relationship}/{relatedId}/pivot', [RelationAttachController::class, 'updatePivot'])->name('resources.relation.updatePivot');
            $router->delete('resources/{resource}/{resourceId}/field/{field}', [FieldFileController::class, 'destroy'])->where('field', '.*')->name('resources.field.destroy');
            $router->post('resources/{resource}/uploads/{field}', [StartUploadController::class, 'field'])->name('resources.uploads.start');
            $router->post('uploads', [StartUploadController::class, 'custom'])->name('uploads.start');
            $router->get('uploads/{upload}', [UploadController::class, 'show'])->name('uploads.show');
            $router->put('uploads/{upload}/chunks/{index}', [UploadController::class, 'chunk'])->where('index', '[0-9]+')->name('uploads.chunk');
            $router->post('uploads/{upload}/complete', [UploadController::class, 'complete'])->name('uploads.complete');
            $router->delete('uploads/{upload}', [UploadController::class, 'destroy'])->name('uploads.destroy');
            $router->get('search', GlobalSearchController::class)->name('search');
            $router->get('impersonate', [ImpersonateController::class, 'show'])->name('impersonate.show');
            $router->post('impersonate', [ImpersonateController::class, 'store'])->name('impersonate.store');
            $router->delete('impersonate', [ImpersonateController::class, 'destroy'])->name('impersonate.destroy');

            // Registered after every plugin's own routes, so those win.
            $router->any('plugins/{plugin}/{path?}', PluginController::class)
                ->where('path', '.*')
                ->name('plugins');

            // Matches last: an unknown API URL still runs Authenticate, so signed out it answers 401, not 404.
            $router->any('{any}', fn () => abort(404))->where('any', '.*')->fallback()->name('fallback');
        });

        // An unknown page answers the Error page: in the shell when signed in, full screen when not.
        $this->group()->middleware('lathe')->group(function (Router $router) {
            $router->any('{any}', PageFallbackController::class)->where('any', '.*')->fallback()->name('lathe.fallback');
        });
    }

    /**
     * The action list, form, run and lookup routes of a Resource and of its lenses.
     */
    protected function actionRoutes(Router $router): void
    {
        $router->get('resources/{resource}/actions', [ActionController::class, 'index'])->name('resources.actions.index');
        $router->patch('resources/{resource}/action', [ActionLookupController::class, 'dependencies'])->name('resources.action.dependencies');
        $router->get('resources/{resource}/action/associatable', [ActionLookupController::class, 'associatable'])->name('resources.action.associatable');
        $router->get('resources/{resource}/lenses/{lens}/actions', [LensActionController::class, 'actions'])->name('resources.lenses.actions.index');
        $router->get('resources/{resource}/lenses/{lens}/actions/{action}/form', [LensActionController::class, 'form'])->name('resources.lenses.actions.form');
        $router->post('resources/{resource}/lenses/{lens}/action', [LensActionController::class, 'run'])->name('resources.lenses.action.run');
        $router->patch('resources/{resource}/lenses/{lens}/action', [LensActionController::class, 'dependencies'])->name('resources.lenses.action.dependencies');
        $router->get('resources/{resource}/lenses/{lens}/action/associatable', [LensActionController::class, 'associatable'])->name('resources.lenses.action.associatable');
        $router->get('resources/{resource}/actions/{action}/form', [ActionController::class, 'form'])->name('resources.actions.form');
        $router->post('resources/{resource}/action', ActionRunController::class)->name('resources.action.run');
        $router->post('resources/{resource}/actions/{action}/uploads/{field}', [StartUploadController::class, 'action'])->name('resources.actions.uploads.start');
    }

    /**
     * Delete, force delete and restore over a selection.
     */
    protected function batchRoutes(Router $router): void
    {
        $controller = BatchDeleteController::class;

        $router->delete('resources/{resource}/batch', [$controller, 'delete'])->name('resources.batch.delete');
        $router->delete('resources/{resource}/batch/force', [$controller, 'forceDelete'])->name('resources.batch.forceDelete');
        $router->put('resources/{resource}/batch/restore', [$controller, 'restore'])->name('resources.batch.restore');
        $router->delete('resources/{resource}/lenses/{lens}/batch', [LensActionController::class, 'batchDelete'])->name('resources.lenses.batch.delete');
        $router->delete('resources/{resource}/lenses/{lens}/batch/force', [LensActionController::class, 'batchForceDelete'])->name('resources.lenses.batch.forceDelete');
        $router->put('resources/{resource}/lenses/{lens}/batch/restore', [LensActionController::class, 'batchRestore'])->name('resources.lenses.batch.restore');
    }

    /**
     * Progress and cancel of a queued bulk run.
     */
    protected function bulkRunRoutes(Router $router): void
    {
        $router->get('bulk-runs/{id}', [BulkRunController::class, 'show'])->name('bulkRuns.show');
        $router->delete('bulk-runs/{id}', [BulkRunController::class, 'destroy'])->name('bulkRuns.destroy');
    }

    protected function exportRoutes(Router $router): void
    {
        $router->get('exports/{export}', [ExportController::class, 'show'])->name('exports.show');
        $router->get('exports/{export}/download', [ExportController::class, 'download'])->middleware('signed')->name('exports.download');
    }

    protected function importRoutes(Router $router): void
    {
        $controller = ImportController::class;

        $router->get('imports/{import}', [$controller, 'show'])->name('imports.show');
        $router->post('imports/{import}/resume', [$controller, 'resume'])->name('imports.resume');
        $router->get('imports/{import}/download-errors', [$controller, 'downloadErrors'])->middleware('signed')->name('imports.downloadErrors');
    }

    protected function resourcePluginRoutes(Router $router): void
    {
        $router->any('resource-plugins/{component}/{path?}', ResourcePluginController::class)
            ->where('path', '.*')
            ->name('resourcePlugins');
    }

    protected function dashboardRoutes(Router $router): void
    {
        $router->get('dashboards', [DashboardController::class, 'index'])->name('dashboards.index');
        $router->get('dashboards/{dashboard}', [DashboardController::class, 'show'])->name('dashboards.show');
        $router->put('dashboards/{dashboard}/layout', [DashboardLayoutController::class, 'update'])->name('dashboards.layout.update');
        $router->delete('dashboards/{dashboard}/layout', [DashboardLayoutController::class, 'destroy'])->name('dashboards.layout.destroy');
        $router->get('dashboards/{dashboard}/widgets', [WidgetController::class, 'dashboardIndex'])->name('dashboards.widgets.index');
        $router->get('dashboards/{dashboard}/widgets/{widget}', [WidgetController::class, 'dashboardShow'])->name('dashboards.widgets.show');
        $router->get('dashboards/{dashboard}/widgets/{widget}/csv', [WidgetController::class, 'dashboardCsv'])->name('dashboards.widgets.csv');
        $router->get('resources/{resource}/widgets', [WidgetController::class, 'resourceIndex'])->name('resources.widgets.index');
        $router->get('resources/{resource}/widgets/{widget}', [WidgetController::class, 'resourceShow'])->name('resources.widgets.show');
        $router->get('resources/{resource}/lenses/{lens}/widgets', [WidgetController::class, 'lensIndex'])->name('resources.lenses.widgets.index');
        $router->get('resources/{resource}/lenses/{lens}/widgets/{widget}', [WidgetController::class, 'lensShow'])->name('resources.lenses.widgets.show');
    }

    protected function notificationRoutes(Router $router): void
    {
        $controller = NotificationController::class;

        $router->get('notifications', [$controller, 'index'])->name('notifications.index');
        $router->get('notifications/count', [$controller, 'count'])->name('notifications.count');
        $router->post('notifications/mark-all-read', [$controller, 'markAllRead'])->name('notifications.markAllRead');
        $router->put('notifications/{id}', [$controller, 'read'])->name('notifications.read');
        $router->post('notifications/{id}/unread', [$controller, 'unread'])->name('notifications.unread');
        $router->delete('notifications/{id}', [$controller, 'destroy'])->name('notifications.destroy');
        $router->delete('notifications', [$controller, 'destroyAll'])->name('notifications.destroyAll');
    }

    /**
     * The page of a queued export or import run.
     */
    protected function runPageRoutes(Router $router): void
    {
        $router->get('runs/{type}/{id}', RunPageController::class)->where('type', 'export|import')->name('lathe.runs.show');
    }

    protected function api(): RouteRegistrar
    {
        return $this->group()
            ->middleware(['lathe.api', EnsureJson::class])
            ->withoutMiddleware(HandleInertiaRequests::class);
    }

    protected function apiPrefix(): string
    {
        return trim($this->config->string('path'), '/').'/api';
    }

    protected function group(): RouteRegistrar
    {
        $registrar = $this->router->prefix(trim($this->config->string('path'), '/'));
        $domain = $this->config->nullableString('domain');

        return $domain !== null ? $registrar->domain($domain) : $registrar;
    }
}
