<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Note;
use App\Support\WorkspaceLayoutData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceLayoutData $layout) {}

    public function __invoke(Request $request): View
    {
        $data = $this->layout->for($request, $request->route()->getName());

        if ($data['page']['id'] !== 'overview') {
            return view('pages.section-unavailable', $data);
        }

        $user = $request->user();
        $recentNotes = Note::query()->ownedBy($user)->active()->select(['id', 'title', 'updated_at'])
            ->latest('updated_at')->limit(5)->get();

        return view('pages.dashboard', [
            ...$data,
            'notesCount' => Note::query()->ownedBy($user)->active()->count(),
            'documentsCount' => Document::query()->ownedBy($user)->active()->count(),
            'vaultEntriesCount' => $user->vault?->entries()->count() ?? 0,
            'recentNotes' => $recentNotes,
        ]);
    }
}
