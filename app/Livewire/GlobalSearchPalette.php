<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\GlobalSearch;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class GlobalSearchPalette extends Component
{
    public string $query = '';

    #[On('reset-global-search')]
    public function resetSearch(): void
    {
        $this->query = '';
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

        return view('livewire.global-search-palette', [
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
