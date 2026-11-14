<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Revision;
use App\Studio\ContentTypes\ContentTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Renders the public page as it WOULD look if a revision were approved,
 * without writing anything to the live tables. Only the revision's author
 * and the Content Admin can see it.
 */
class PreviewController extends Controller
{
    public function __invoke(Request $request, Revision $revision, ContentTypeRegistry $types): Response
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $revision->author_id === $user->id, 403);

        // For a deletion there is nothing new to show; preview what is live now.
        $type = $types->get($revision->entity_type);
        $payload = $revision->payload ?? ($revision->entity_id ? $type->current($revision->entity_id) : null);

        $page = $payload ? $type->preview($payload, $revision->entity_id) : null;
        abort_if($page === null, 404, 'There is no page preview for this kind of content.');

        return response($page->render());
    }
}
