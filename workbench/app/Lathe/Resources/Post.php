<?php

namespace Workbench\App\Lathe\Resources;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;
use Workbench\App\Models\Post as PostModel;
use Workbench\App\Models\User;

class Post extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(PostModel::class)
            ->title('title');
    }

    /**
     * Posts have no owner column, so the editor's own posts are the published ones.
     */
    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        return $user instanceof User && $user->email === 'editor@lathephp.com' ? $query->where('status', 'published') : $query;
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::text('Title')->required()->rules('max:255'),
            Field::slug('Slug')->from('title')->showCustomizeButton()->hideFromIndex(),
            Field::select('Status')->options(['draft', 'published', 'archived'])->default('draft')->required()->inline(),
            Field::image('Cover', 'cover')->hideFromIndex(),
            Field::tiptap('Body')->required()->withFiles(),
            Field::markdown('Markdown body', 'markdown_body')->nullable()->withFiles(),
        ];
    }
}
