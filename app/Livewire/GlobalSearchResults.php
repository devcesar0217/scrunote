<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\GlobalSearch;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class GlobalSearchResults extends Component
{
    #[Url(as: 'q', history: true, except: '')]
    public string $query = '';

    public function mount(string $initialQuery = ''): void
    {
        $this->query = $this->normalizeQuery($initialQuery);
    }

    public function updatedQuery(): void
    {
        $this->query = $this->normalizeQuery($this->query);
    }

    public function render(GlobalSearch $search): View
    {
        /** @var User $user */
        $user = auth()->user();
        $results = $search->search($user, $this->query);

        return view('livewire.global-search-results', [
            'minimumQueryLength' => GlobalSearch::MINIMUM_QUERY_LENGTH,
            'results' => $results['results'],
            'hasMore' => $results['has_more'],
        ]);
    }

    private function normalizeQuery(string $value): string
    {
        return Str::limit(trim($value), 120, '');
    }
}
