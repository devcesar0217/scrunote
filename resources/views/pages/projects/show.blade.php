@extends('layouts.app')
@section('title', $project->title.' · Projects · Scrunote')

@section('content')
    <section class="project-overview-page" aria-labelledby="project-heading">
        <a class="projects-back-link" href="{{ route('projects.index') }}"><x-icon name="arrow-left" /> All Projects</a>

        @if (session('project_status'))
            <p class="projects-flash" role="status"><x-icon name="circle-check" /> {{ session('project_status') }}</p>
        @endif

        <header class="project-overview-header">
            <div class="project-overview-title"><span class="project-overview-icon"><x-icon name="folder" /></span><div><p class="workspace-eyebrow">Project</p><h1 id="project-heading">{{ $project->title }}</h1>@if (filled($project->description))<p>{{ $project->description }}</p>@else <p class="project-muted">No description yet.</p>@endif</div></div>
            <div class="project-overview-actions">
                <details class="project-menu">
                    <summary aria-label="Project settings"><x-icon name="settings" /><span>Manage</span></summary>
                    <div class="project-menu-panel">
                        <form method="POST" action="{{ route('projects.update', $project) }}">
                            @csrf @method('PATCH')
                            <label for="edit-project-title">Project name</label>
                            <input id="edit-project-title" name="title" value="{{ old('title', $project->title) }}" maxlength="120" required>
                            @error('title')<p class="project-field-error">{{ $message }}</p>@enderror
                            <label for="edit-project-description">Description <span>Optional</span></label>
                            <textarea id="edit-project-description" name="description" rows="3" maxlength="2000">{{ old('description', $project->description) }}</textarea>
                            @error('description')<p class="project-field-error">{{ $message }}</p>@enderror
                            <button type="submit" class="projects-primary-button">Save changes</button>
                        </form>
                        <hr>
                        <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Delete this Project? Linked content will remain, but move to My Workspace.')">
                            @csrf @method('DELETE')
                            <label class="project-confirmation"><input type="checkbox" name="confirm_project_deletion" value="1" required> I understand linked content will be unassigned.</label>
                            @error('confirm_project_deletion')<p class="project-field-error">{{ $message }}</p>@enderror
                            <button type="submit" class="project-danger-button"><x-icon name="trash" /> Delete Project</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <section class="project-stats" aria-label="Project content totals">
            <div><x-icon name="file-text" /><strong>{{ $project->notes_count }}</strong><span>{{ Str::plural('Note', $project->notes_count) }}</span></div>
            <div><x-icon name="markdown" /><strong>{{ $project->documents_count }}</strong><span>{{ Str::plural('Markdown Doc', $project->documents_count) }}</span></div>
            <div><x-icon name="key-round" /><strong>{{ $project->vault_entries_count }}</strong><span>{{ Str::plural('Vault entry', $project->vault_entries_count) }}</span></div>
        </section>

        <div class="project-content-sections">
            <section class="project-content-section" aria-labelledby="project-notes-heading">
                <header><div><x-icon name="file-text" /><h2 id="project-notes-heading">Notes</h2></div><div><a href="{{ route('notes.index', ['project' => $project->id]) }}">Open <x-icon name="arrow-right" /></a></div></header>
                @forelse($recentNotes as $note)
                    <a class="project-content-row" href="{{ route('notes.show', ['note' => $note, 'project' => $project->id]) }}"><span><strong>{{ $note->title }}</strong><small>Updated {{ $note->updated_at->diffForHumans() }}</small></span><x-icon name="chevron-right" /></a>
                @empty
                    <p class="project-empty-inline">No Notes have been assigned here yet.</p>
                @endforelse
            </section>

            <section class="project-content-section" aria-labelledby="project-documents-heading">
                <header><div><x-icon name="markdown" /><h2 id="project-documents-heading">Markdown Docs</h2></div><div><a href="{{ route('markdown.index', ['project' => $project->id]) }}">Open <x-icon name="arrow-right" /></a></div></header>
                @forelse($recentDocuments as $document)
                    <a class="project-content-row" href="{{ route('markdown.show', ['document' => $document, 'project' => $project->id]) }}"><span><strong>{{ $document->title }}</strong><small>Updated {{ $document->updated_at->diffForHumans() }}</small></span><x-icon name="chevron-right" /></a>
                @empty
                    <p class="project-empty-inline">No Markdown Docs have been assigned here yet.</p>
                @endforelse
            </section>

            <section class="project-content-section" aria-labelledby="project-vault-heading">
                <header><div><x-icon name="key-round" /><h2 id="project-vault-heading">Vault</h2></div><div><a href="{{ route('vault.index', ['project' => $project->id]) }}">Open <x-icon name="arrow-right" /></a><a class="project-add-link" href="{{ route('vault.index', ['project' => $project->id, 'new' => 1]) }}"><x-icon name="plus" /> New</a></div></header>
                @forelse($vaultEntries as $entry)
                    <a class="project-content-row" href="{{ route('vault.show', ['vaultEntry' => $entry, 'project' => $project->id]) }}"><span><strong>{{ $entry->title }}</strong><small>{{ Str::headline($entry->type) }} · Updated {{ $entry->updated_at->diffForHumans() }}</small></span><x-icon name="chevron-right" /></a>
                @empty
                    <p class="project-empty-inline">No Vault entries have been assigned here yet.</p>
                @endforelse
            </section>
        </div>
    </section>
@endsection
