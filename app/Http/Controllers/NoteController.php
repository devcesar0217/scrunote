<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notes\ToggleNotePinRequest;
use App\Http\Requests\Notes\UpdateNoteRequest;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use App\Support\NoteTagSynchronizer;
use App\Support\RichTextSanitizer;
use App\Support\WorkspaceLayoutData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function __construct(
        private readonly WorkspaceLayoutData $layout,
        private readonly RichTextSanitizer $sanitizer,
        private readonly NoteTagSynchronizer $tags,
    ) {}

    public function index(Request $request): View
    {
        return $this->notesView($request);
    }

    public function show(Request $request, Note $note): View
    {
        $note = $this->owned($request, $note)->load('tags:id,name');

        return $this->notesView($request, $note);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $projectId = $this->validatedProjectId($request, $user);
        $note = $user->notes()->create([
            'title' => null,
            'content' => ['html' => '<p><br></p>'],
            'content_text' => '',
            'project_id' => $projectId,
        ]);

        return to_route('notes.show', array_filter(['note' => $note, 'project' => $projectId]))->with('status', 'New note created.');
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse|RedirectResponse
    {
        $note = $this->owned($request, $note);
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $content = $this->sanitizer->sanitize($validated['content_html']);
        $tagNames = $this->tags->parse($validated['tags'] ?? null);
        $expectedVersion = (int) $validated['version'];
        $projectId = array_key_exists('project_id', $validated) ? $validated['project_id'] : $note->project_id;

        $updated = DB::transaction(function () use ($note, $user, $validated, $content, $tagNames, $expectedVersion, $projectId): bool {
            $changed = Note::query()->ownedBy($user)->whereKey($note->id)->where('lock_version', $expectedVersion)->update([
                'title' => $validated['title'],
                'content' => json_encode(['html' => $content['html']], JSON_THROW_ON_ERROR),
                'content_text' => $content['text'],
                'project_id' => $projectId,
                'lock_version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]);

            if ($changed !== 1) {
                return false;
            }

            $this->tags->sync($note, $user, $tagNames);

            return true;
        });

        if (! $updated) {
            return $this->conflict($request);
        }

        $fresh = $note->fresh(['tags:id,name', 'project:id,title']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Saved.',
                'note' => [
                    'id' => $fresh->id,
                    'title' => $fresh->display_title,
                    'version' => $fresh->lock_version,
                    'updated_at' => $fresh->updated_at?->toAtomString(),
                    'preview' => filled($fresh->content_text) ? Str::limit($fresh->content_text, 58) : 'Empty note',
                    'tags' => $fresh->tags->pluck('name')->values(),
                    'project' => $fresh->project === null ? null : ['id' => $fresh->project->id, 'title' => $fresh->project->title],
                ],
            ]);
        }

        return to_route('notes.show', $fresh)->with('status', 'Note saved.');
    }

    public function togglePin(ToggleNotePinRequest $request, Note $note): RedirectResponse
    {
        $note = $this->owned($request, $note);
        /** @var User $user */
        $user = $request->user();
        $expectedVersion = (int) $request->validated('version');
        $pinned = ! $note->is_pinned;

        $updated = Note::query()->ownedBy($user)->whereKey($note->id)->where('lock_version', $expectedVersion)->update([
            'is_pinned' => $pinned,
            'lock_version' => $expectedVersion + 1,
            'updated_at' => now(),
        ]);

        if ($updated !== 1) {
            return $this->conflict($request);
        }

        return to_route('notes.show', $note)->with('status', $pinned ? 'Note pinned.' : 'Note unpinned.');
    }

    public function destroy(Request $request, Note $note): RedirectResponse
    {
        $note = $this->owned($request, $note);
        $note->delete();

        return to_route('notes.index')->with('status', 'Note deleted.');
    }

    private function notesView(Request $request, ?Note $selected = null): View
    {
        $query = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($query) > 120, 422, 'Search may not be longer than 120 characters.');
        /** @var User $user */
        $user = $request->user();
        $projectFilter = $this->projectFilter($request, $user);
        $projects = Project::query()->ownedBy($user)->orderBy('title')->get(['id', 'title']);
        $notes = $this->notesFor($user, $query, $projectFilter)->simplePaginate(50)->withQueryString();
        $safeHtml = $selected === null ? null : $this->sanitizer->sanitize($selected->html())['html'];

        return view('pages.notes.index', [
            ...$this->layout->for($request, 'notes.index'),
            'notes' => $notes,
            'selectedNote' => $selected,
            'selectedHtml' => $safeHtml,
            'search' => $query,
            'projects' => $projects,
            'projectFilter' => $projectFilter,
        ]);
    }

    /** @return Builder<Note> */
    private function notesFor(User $user, string $search, int|string|null $projectFilter): Builder
    {
        return Note::query()->ownedBy($user)->active()->with('tags:id,name')
            ->when($projectFilter === 'mine', fn (Builder $query) => $query->whereNull('project_id'))
            ->when(is_int($projectFilter), fn (Builder $query) => $query->where('project_id', $projectFilter))
            ->when($search !== '', function (Builder $query) use ($search, $user): void {
                $needle = '%'.$search.'%';
                $query->where(function (Builder $query) use ($needle, $user): void {
                    $query->whereLike('title', $needle)
                        ->orWhereLike('content_text', $needle)
                        ->orWhereHas('tags', fn (Builder $tags) => $tags->where('user_id', $user->id)
                            ->where('module', 'notes')->whereLike('name', $needle));
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

    private function owned(Request $request, Note $note): Note
    {
        abort_unless($note->user_id === $request->user()?->id, 404);
        Gate::authorize('view', $note);

        return $note;
    }

    private function conflict(Request $request): JsonResponse|RedirectResponse
    {
        $message = 'This note changed in another tab. Reload it before saving again.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 409);
        }

        return back()->withInput()->withErrors(['version' => $message]);
    }
}
