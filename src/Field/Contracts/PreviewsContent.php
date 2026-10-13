<?php

namespace Lathe\Field\Contracts;

use Lathe\Support\SafeHtml;

/**
 * A field whose editor shows a server-rendered preview, such as Markdown. The preview
 * endpoint answers only for these fields.
 */
interface PreviewsContent
{
    public function previewFor(string $value): SafeHtml;
}
