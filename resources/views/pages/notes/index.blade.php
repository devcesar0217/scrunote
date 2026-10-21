@extends('layouts.app')
@section('title', ($selectedNote ? $selectedNote->display_title.' · ' : '').'Notes · Scrunote')

@section('content')
    <section class="notes-workspace" x-data="notesWorkspace({
        selected: {{ Illuminate\Support\Js::from($selectedNote?->id) }},
        projectId: {{ Illuminate\Support\Js::from(is_int($projectFilter) ? $projectFilter : null) }},
    })" @keydown.window="handleShortcut($event)" @notes-created.window="openList()"
        @workspace-project-filter-updated.window="if ($event.detail.module === 'notes') projectId = $event.detail.projectId">
        <header class="notes-page-header">
            <div>
                <h1>Notes</h1>
                <p>Capture and organize rich-text notes in your signed-in workspace.</p>
            </div>
            <div class="notes-page-actions">
                <button type="button" class="workspace-secondary-button notes-list-toggle" @click="toggleList()" :aria-expanded="listOpen" aria-controls="notes-list-panel">
                    <x-icon name="{{ $selectedNote ? 'layout-sidebar-left-collapse' : 'list' }}" />
                    <span x-text="listOpen ? 'Hide list' : 'Show list'">Show list</span>
                </button>
                <form method="POST" action="{{ route('notes.store') }}">
                    @csrf
                    <input type="hidden" name="project_id" value="{{ is_int($projectFilter) ? $projectFilter : '' }}" :value="projectId ?? ''" :disabled="projectId === null">
                    <button type="submit" class="notes-primary-button"><x-icon name="plus" /> New note</button>
                </form>
            </div>
        </header>

        @if (session('status'))
            <p class="notes-flash" role="status"><x-icon name="circle-check" /> {{ session('status') }}</p>
        @endif

        <div class="notes-layout" :class="{ 'notes-list-is-hidden': !listOpen }">
            <aside id="notes-list-panel" class="notes-list-panel" aria-label="Your notes" :aria-hidden="!listOpen" :inert="!listOpen">
                <livewire:notes-search :selected-id="$selectedNote?->id" :initial-search="$search" :initial-project-filter="$projectFilter" />
            </aside>

            <section class="notes-editor-panel" aria-label="Note editor">
                @if ($selectedNote)
                    @include('pages.notes.editor')
                @else
                    <div class="notes-empty-state">
                        <span class="notes-empty-icon"><x-icon name="notes" /></span>
                        <h2>Choose a note or start fresh</h2>
                        <p>Create a private rich-text note, then search it by title, text or tags.</p>
                        <form method="POST" action="{{ route('notes.store') }}">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ is_int($projectFilter) ? $projectFilter : '' }}" :value="projectId ?? ''" :disabled="projectId === null">
                            <button type="submit" class="workspace-secondary-button"><x-icon name="plus" /> Create your first note</button>
                        </form>
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
