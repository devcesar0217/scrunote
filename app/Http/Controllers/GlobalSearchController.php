<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\GlobalSearch;
use App\Support\WorkspaceLayoutData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function __construct(
        private readonly WorkspaceLayoutData $layout,
        private readonly GlobalSearch $search,
    ) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        $rawQuery = $request->query('q', '');
        abort_unless(is_string($rawQuery), 422, 'Search query must be a string.');
        $query = trim($rawQuery);
        abort_if(mb_strlen($query) > 120, 422, 'Search may not be longer than 120 characters.');

        /** @var User $user */
        $user = $request->user();
        $search = $this->search->search($user, $query);
        $payload = [
            'query' => $query,
            'minimum_query_length' => GlobalSearch::MINIMUM_QUERY_LENGTH,
            'results' => $search['results'],
            'has_more' => $search['has_more'],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 200, ['Cache-Control' => 'no-store, private']);
        }

        return view('pages.search.index', [
            ...$this->layout->for($request, 'search.index'),
            ...$payload,
        ]);
    }
}
