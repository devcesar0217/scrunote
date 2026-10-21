<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\DestroyProjectRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\WorkspaceLayoutData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private readonly WorkspaceLayoutData $layout) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('pages.projects.index', [
            ...$this->layout->for($request, 'projects.index'),
            'projects' => $this->projectsFor($user)->get(),
            'myWorkspaceCounts' => [
                'notes' => $user->notes()->active()->whereNull('project_id')->count(),
                'documents' => $user->documents()->active()->whereNull('project_id')->count(),
                'vaultEntries' => $user->vault?->entries()->whereNull('project_id')->count() ?? 0,
            ],
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project = $user->projects()->create($request->validated());

        return to_route('projects.show', $project)->with('project_status', 'Project created.');
    }

    public function show(Request $request, Project $project): View
    {
        /** @var User $user */
        $user = $request->user();
        $project = $this->owned($user, $project);
        $project->loadCount([
            'notes' => fn ($query) => $query->active(),
            'documents' => fn ($query) => $query->active(),
            'vaultEntries',
        ]);

        return view('pages.projects.show', [
            ...$this->layout->for($request, 'projects.show'),
            'project' => $project,
            'recentNotes' => $project->notes()->active()->select(['id', 'project_id', 'title', 'content_text', 'updated_at'])->latest('updated_at')->limit(6)->get(),
            'recentDocuments' => $project->documents()->active()->select(['id', 'project_id', 'title', 'content_text', 'updated_at'])->latest('updated_at')->limit(6)->get(),
            // This query intentionally omits sensitive_payload. Project context
            // is safe metadata and remains visible while the Vault is locked.
            'vaultEntries' => $project->vaultEntries()->select(['id', 'vault_id', 'project_id', 'type', 'title', 'url', 'username', 'metadata', 'updated_at'])
                ->with('tags:id,name')->latest('updated_at')->limit(6)->get(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project = $this->owned($user, $project);
        $project->update($request->validated());

        return to_route('projects.show', $project)->with('project_status', 'Project details saved.');
    }

    public function destroy(DestroyProjectRequest $request, Project $project): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->owned($user, $project);

        DB::transaction(function () use ($user, $project): void {
            /** @var Project $owned */
            $owned = Project::query()->ownedBy($user)->lockForUpdate()->findOrFail($project->id);
            $owned->notes()->update(['project_id' => null]);
            $owned->documents()->update(['project_id' => null]);
            $owned->vaultEntries()->update(['project_id' => null]);
            $owned->delete();
        }, 3);

        return to_route('projects.index')->with('project_status', 'Project deleted. Its items remain in My Workspace.');
    }

    /** @return Builder<Project> */
    private function projectsFor(User $user): Builder
    {
        return Project::query()->ownedBy($user)->withCount([
            'notes' => fn ($query) => $query->active(),
            'documents' => fn ($query) => $query->active(),
            'vaultEntries',
        ])->latest('updated_at')->orderByDesc('id');
    }

    private function owned(User $user, Project $project): Project
    {
        $owned = Project::query()->ownedBy($user)->whereKey($project->id)->firstOrFail();
        Gate::authorize('view', $owned);

        return $owned;
    }
}
