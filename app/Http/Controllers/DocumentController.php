<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\ToggleDocumentPinRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\User;
use App\Support\DocumentTagSynchronizer;
use App\Support\MarkdownTextExtractor;
use App\Support\WorkspaceLayoutData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(
        private readonly WorkspaceLayoutData $layout,
        private readonly MarkdownTextExtractor $extractor,
        private readonly DocumentTagSynchronizer $tags,
    ) {}

    public function index(Request $request): View
    {
        return $this->documentsView($request);
    }

    public function show(Request $request, Document $document): View
    {
        $document = $this->owned($request, $document)->load('tags:id,name');

        return $this->documentsView($request, $document);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $document = $user->documents()->create([
            'title' => null,
            'markdown' => '',
            'content_text' => '',
        ]);

        return to_route('markdown.show', $document)->with('status', 'New document created.');
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse|RedirectResponse
    {
        $document = $this->owned($request, $document);
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $markdown = $validated['markdown'];
        $contentText = $this->extractor->extract($markdown);
        $tagNames = $this->tags->parse($validated['tags'] ?? null);
        $expectedVersion = (int) $validated['version'];

        $updated = DB::transaction(function () use ($document, $user, $validated, $markdown, $contentText, $tagNames, $expectedVersion): bool {
            $changed = Document::query()->ownedBy($user)->whereKey($document->id)->where('lock_version', $expectedVersion)->update([
                'title' => $validated['title'],
                'markdown' => $markdown,
                'content_text' => $contentText,
                'lock_version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]);

            if ($changed !== 1) {
                return false;
            }

            $this->tags->sync($document, $user, $tagNames);

            return true;
        });

        if (! $updated) {
            return $this->conflict($request);
        }

        $fresh = $document->fresh(['tags:id,name']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Saved.',
                'document' => [
                    'id' => $fresh->id,
                    'title' => $fresh->display_title,
                    'version' => $fresh->lock_version,
                    'updated_at' => $fresh->updated_at?->toAtomString(),
                    'preview' => filled($fresh->content_text) ? Str::limit($fresh->content_text, 58) : 'Empty document',
                    'tags' => $fresh->tags->pluck('name')->values(),
                ],
            ]);
        }

        return to_route('markdown.show', $fresh)->with('status', 'Document saved.');
    }

    public function togglePin(ToggleDocumentPinRequest $request, Document $document): RedirectResponse
    {
        $document = $this->owned($request, $document);
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = (int) $request->validated('version');
        $pinned = ! $document->is_pinned;

        $updated = Document::query()->ownedBy($user)->whereKey($document->id)->where('lock_version', $expectedVersion)->update([
            'is_pinned' => $pinned,
            'lock_version' => $expectedVersion + 1,
            'updated_at' => now(),
        ]);

        if ($updated !== 1) {
            return $this->conflict($request);
        }

        return to_route('markdown.show', $document)->with('status', $pinned ? 'Document pinned.' : 'Document unpinned.');
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $document = $this->owned($request, $document);
        $document->delete();

        return to_route('markdown.index')->with('status', 'Document deleted.');
    }

    public function export(Request $request, Document $document): Response
    {
        $document = $this->owned($request, $document);
        $filename = Str::slug($document->display_title) ?: 'untitled-document';

        return response($document->markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.md"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function documentsView(Request $request, ?Document $selected = null): View
    {
        $query = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($query) > 120, 422, 'Search may not be longer than 120 characters.');
        /** @var User $user */
        $user = $request->user();
        $documents = $this->documentsFor($user, $query)->simplePaginate(50)->withQueryString();

        return view('pages.markdown.index', [
            ...$this->layout->for($request, 'markdown.index'),
            'documents' => $documents,
            'selectedDocument' => $selected,
            'search' => $query,
        ]);
    }

    /** @return Builder<Document> */
    private function documentsFor(User $user, string $search): Builder
    {
        return Document::query()->ownedBy($user)->active()->with('tags:id,name')
            ->when($search !== '', function (Builder $query) use ($search, $user): void {
                $needle = '%'.$search.'%';
                $query->where(function (Builder $query) use ($needle, $user): void {
                    $query->whereLike('title', $needle)
                        ->orWhereLike('content_text', $needle)
                        ->orWhereHas('tags', fn (Builder $tags) => $tags->where('user_id', $user->id)
                            ->where('module', 'markdown')->whereLike('name', $needle));
                });
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at');
    }

    private function owned(Request $request, Document $document): Document
    {
        abort_unless($document->user_id === $request->user()?->id, 404);
        Gate::authorize('view', $document);

        return $document;
    }

    private function conflict(Request $request): JsonResponse|RedirectResponse
    {
        $message = 'This document changed in another tab. Reload it before saving again.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 409);
        }

        return back()->withInput()->withErrors(['version' => $message]);
    }
}
