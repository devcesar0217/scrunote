<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\ToggleDocumentPinRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\Project;
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
use Illuminate\Validation\Rule;
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
        $projectId = $this->validatedProjectId($request, $user);
        $document = $user->documents()->create([
            'title' => null,
            'markdown' => '',
            'content_text' => '',
            'project_id' => $projectId,
        ]);

        return to_route('markdown.show', array_filter(['document' => $document, 'project' => $projectId]))->with('status', 'New document created.');
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
        $projectId = array_key_exists('project_id', $validated) ? $validated['project_id'] : $document->project_id;

        $updated = DB::transaction(function () use ($document, $user, $validated, $markdown, $contentText, $tagNames, $expectedVersion, $projectId): bool {
            $changed = Document::query()->ownedBy($user)->whereKey($document->id)->where('lock_version', $expectedVersion)->update([
                'title' => $validated['title'],
                'markdown' => $markdown,
                'content_text' => $contentText,
                'project_id' => $projectId,
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

        $fresh = $document->fresh(['tags:id,name', 'project:id,title']);

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
                    'project' => $fresh->project === null ? null : ['id' => $fresh->project->id, 'title' => $fresh->project->title],
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
        $projectFilter = $this->projectFilter($request, $user);
        $projects = Project::query()->ownedBy($user)->orderBy('title')->get(['id', 'title']);
        $documents = $this->documentsFor($user, $query, $projectFilter)->simplePaginate(50)->withQueryString();

        return view('pages.markdown.index', [
            ...$this->layout->for($request, 'markdown.index'),
            'documents' => $documents,
            'selectedDocument' => $selected,
            'search' => $query,
            'projects' => $projects,
            'projectFilter' => $projectFilter,
        ]);
    }

    /** @return Builder<Document> */
    private function documentsFor(User $user, string $search, int|string|null $projectFilter): Builder
    {
        return Document::query()->ownedBy($user)->active()->with('tags:id,name')
            ->when($projectFilter === 'mine', fn (Builder $query) => $query->whereNull('project_id'))
            ->when(is_int($projectFilter), fn (Builder $query) => $query->where('project_id', $projectFilter))
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

    private function projectFilter(Request $request, User $user): int|string|null
    {
        $filter = $request->query('project');
        if ($filter === null || $filter === '') {
            return null;
        }
        if ($filter === 'mine') {
            return 'mine';
        }
        abort_unless(is_string($filter) && ctype_digit($filter), 404);
        $project = Project::query()->ownedBy($user)->find($filter);
        abort_if($project === null, 404);

        return $project->id;
    }

    private function validatedProjectId(Request $request, User $user): ?int
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $user->id)],
        ]);

        return isset($validated['project_id']) ? (int) $validated['project_id'] : null;
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
