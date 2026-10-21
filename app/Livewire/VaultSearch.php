<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\User;
use App\Models\VaultEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class VaultSearch extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'project', history: true, except: '')]
    public string|int|null $projectFilter = null;

    public ?int $selectedId = null;

    public function mount(?int $selectedId = null, string $initialSearch = '', string|int|null $initialProjectFilter = null): void
    {
        $this->selectedId = $selectedId;
        $this->search = $this->normalizeSearch($initialSearch);
        $this->projectFilter = $this->normalizeProjectFilter($initialProjectFilter);
    }

    public function updatedSearch(): void
    {
        $this->search = $this->normalizeSearch($this->search);
        $this->resetPage();
    }

    public function updatedProjectFilter(): void
    {
        $this->projectFilter = $this->normalizeProjectFilter($this->projectFilter);
        $this->resetPage();
        $this->dispatch('workspace-project-filter-updated', module: 'vault', projectId: is_int($this->projectFilter) ? $this->projectFilter : null);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $vault = $user->vault;

        return view('livewire.vault-search', [
            'vault' => $vault,
            'entries' => $vault === null ? collect() : $this->entriesFor($vault->id, $user)->simplePaginate(50),
            'projects' => Project::query()->ownedBy($user)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    /** @return Builder<VaultEntry> */
    private function entriesFor(int $vaultId, User $user): Builder
    {
        $search = $this->search;
        $projectFilter = $this->projectFilter;

        return VaultEntry::query()->where('vault_id', $vaultId)
            ->with(['tags' => fn ($tags) => $tags->select(['tags.id', 'tags.name'])->where('user_id', $user->id)->where('module', 'vault')])
            // sensitive_payload intentionally remains outside this query.
            ->when($projectFilter === 'mine', fn (Builder $query) => $query->whereNull('project_id'))
            ->when(is_int($projectFilter), fn (Builder $query) => $query->where('project_id', $projectFilter))
            ->when($search !== '', function (Builder $query) use ($search, $user): void {
                $needle = '%'.$search.'%';
                $query->where(function (Builder $query) use ($needle, $user): void {
                    $query->whereLike('title', $needle)
                        ->orWhereLike('url', $needle)
                        ->orWhereLike('username', $needle)
                        ->orWhereLike('metadata', $needle)
                        ->orWhereHas('tags', fn (Builder $tags) => $tags->where('user_id', $user->id)->where('module', 'vault')->whereLike('name', $needle));
                });
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->orderByDesc('id');
    }

    private function normalizeSearch(string $value): string
    {
        return Str::limit(trim($value), 120, '');
    }

    private function normalizeProjectFilter(string|int|null $filter): string|int|null
    {
        if ($filter === null || $filter === '') {
            return null;
        }
        if ($filter === 'mine') {
            return 'mine';
        }
        if ((! is_int($filter) && (! is_string($filter) || ! ctype_digit($filter))) || auth()->user() === null) {
            return null;
        }

        /** @var User $user */
        $user = auth()->user();
        $project = Project::query()->ownedBy($user)->find($filter);

        return $project?->id;
    }
}
