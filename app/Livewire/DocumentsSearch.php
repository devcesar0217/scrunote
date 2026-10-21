<?php

namespace App\Livewire;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentsSearch extends Component
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
        $this->dispatch('workspace-project-filter-updated', module: 'markdown', projectId: is_int($this->projectFilter) ? $this->projectFilter : null);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.documents-search', [
            'documents' => $this->documentsFor($user)->simplePaginate(50),
            'projects' => Project::query()->ownedBy($user)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    /** @return Builder<Document> */
    private function documentsFor(User $user): Builder
    {
        $search = $this->search;
        $projectFilter = $this->projectFilter;

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
