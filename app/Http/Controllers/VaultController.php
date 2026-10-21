<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultEntry;
use App\Support\VaultSession;
use App\Support\WorkspaceLayoutData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VaultController extends Controller
{
    public function __construct(
        private readonly WorkspaceLayoutData $layout,
        private readonly VaultSession $vaultSession,
    ) {}

    public function index(Request $request): View
    {
        return $this->vaultView($request);
    }

    public function show(Request $request, VaultEntry $vaultEntry): View
    {
        /** @var User $user */
        $user = $request->user();
        $vault = $user->vault;
        abort_if($vault === null, 404);

        $selected = $this->entriesFor($vault, '', null)
            ->whereKey($vaultEntry->id)
            ->firstOrFail();

        return $this->vaultView($request, $selected);
    }

    private function vaultView(Request $request, ?VaultEntry $selected = null): View
    {
        $query = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($query) > 120, 422, 'Search may not be longer than 120 characters.');
        /** @var User $user */
        $user = $request->user();
        $vault = $user->vault;
        $projectFilter = $this->projectFilter($request, $user);
        $projects = Project::query()->ownedBy($user)->orderBy('title')->get(['id', 'title']);
        $entries = $vault === null
            ? collect()
            : $this->entriesFor($vault, $query, $projectFilter)->simplePaginate(50)->withQueryString();

        return view('pages.vault.index', [
            ...$this->layout->for($request, 'vault.index'),
            'vault' => $vault,
            'entries' => $entries,
            'selectedEntry' => $selected,
            'search' => $query,
            'projects' => $projects,
            'projectFilter' => $projectFilter,
            'vaultUnlocked' => $vault !== null && $this->vaultSession->isUnlocked($request, $user),
            'idleTimeoutMinutes' => (int) config('vault.idle_timeout_minutes', 5),
            'createRequested' => $selected === null && ($request->boolean('new') || $request->boolean('create')),
            'editRequested' => $selected !== null && $request->boolean('edit'),
        ]);
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

    /** @return Builder<VaultEntry> */
    private function entriesFor(Vault $vault, string $search, int|string|null $projectFilter): Builder
    {
        return VaultEntry::query()->forVault($vault)->with('tags:id,name')
            ->when($projectFilter === 'mine', fn (Builder $query) => $query->whereNull('project_id'))
            ->when(is_int($projectFilter), fn (Builder $query) => $query->where('project_id', $projectFilter))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $needle = '%'.$search.'%';
                $query->where(function (Builder $query) use ($needle): void {
                    $query->whereLike('title', $needle)
                        ->orWhereLike('url', $needle)
                        ->orWhereLike('username', $needle)
                        // metadata is intentionally non-secret JSON only; do not
                        // add the encrypted sensitive_payload to this projection.
                        ->orWhereLike('metadata', $needle)
                        ->orWhereHas('tags', fn (Builder $tags) => $tags->where('module', 'vault')->whereLike('name', $needle));
                });
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->orderByDesc('id');
    }
}
