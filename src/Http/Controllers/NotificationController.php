<?php

namespace Lathe\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Notifications\NotificationResource;
use Lathe\Support\ConfigStore;

/**
 * The signed-in user's own database notifications.
 */
class NotificationController
{
    public const int LIMIT = 100;

    public function __construct(protected ConfigStore $config)
    {
        //
    }

    public function index(LatheRequest $request): JsonResponse
    {
        $this->ensureEnabled();

        if (! $this->tableExists()) {
            return new JsonResponse(['notifications' => [], 'unreadCount' => 0]);
        }

        $now = Carbon::now();
        $rows = $this->mine($request)->latest()->latest('id')->limit(self::LIMIT)->get();

        return new JsonResponse([
            'notifications' => $rows->map(fn (DatabaseNotification $row) => (new NotificationResource($row, $now))->toArray())->all(),
            'unreadCount' => $this->mine($request)->whereNull('read_at')->count(),
        ]);
    }

    public function count(LatheRequest $request): JsonResponse
    {
        $this->ensureEnabled();

        return new JsonResponse(['count' => $this->tableExists() ? $this->mine($request)->whereNull('read_at')->count() : 0]);
    }

    public function read(LatheRequest $request, string $id): Response
    {
        return $this->change($request, fn (Builder $query) => Str::isUuid($id) && $query->whereKey($id)->update(['read_at' => Carbon::now()]));
    }

    public function unread(LatheRequest $request, string $id): Response
    {
        return $this->change($request, fn (Builder $query) => Str::isUuid($id) && $query->whereKey($id)->update(['read_at' => null]));
    }

    public function markAllRead(LatheRequest $request): Response
    {
        return $this->change($request, fn (Builder $query) => $query->whereNull('read_at')->update(['read_at' => Carbon::now()]));
    }

    public function destroy(LatheRequest $request, string $id): Response
    {
        return $this->change($request, fn (Builder $query) => Str::isUuid($id) && $query->whereKey($id)->delete());
    }

    public function destroyAll(LatheRequest $request): Response
    {
        return $this->change($request, fn (Builder $query) => $query->delete());
    }

    /**
     * A write for one id skips an id that is no UUID: PostgreSQL refuses it for the uuid
     * key, where SQLite and MySQL match nothing.
     *
     * @param  callable(Builder<DatabaseNotification>): mixed  $write
     */
    protected function change(LatheRequest $request, callable $write): Response
    {
        $this->ensureEnabled();

        if ($this->tableExists()) {
            $write($this->mine($request));
        }

        return new Response(status: 204);
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    protected function mine(LatheRequest $request): Builder
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return DatabaseNotification::where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getAuthIdentifier());
    }

    protected function ensureEnabled(): void
    {
        abort_unless($this->config->boolean('notifications.enabled'), 404);
    }

    protected function tableExists(): bool
    {
        return Cache::remember('lathe.notifications.table', 3600, fn () => Schema::hasTable('notifications'));
    }
}
