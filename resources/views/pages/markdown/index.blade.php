@extends('layouts.app')
@section('title', ($selectedDocument ? $selectedDocument->display_title.' · ' : '').'Markdown Docs · Scrunote')

@section('content')
    <section class="markdown-workspace" x-data="markdownWorkspace({
        selected: {{ Illuminate\Support\Js::from($selectedDocument?->id) }},
        projectId: {{ Illuminate\Support\Js::from(is_int($projectFilter) ? $projectFilter : null) }},
    })" @keydown.window="handleShortcut($event)"
        @workspace-project-filter-updated.window="if ($event.detail.module === 'markdown') projectId = $event.detail.projectId">
        <header class="markdown-page-header">
            <div>
                <h1>Markdown Docs</h1>
                <p>Write, preview, and organize developer documentation in one private workspace.</p>
            </div>
            <div class="markdown-page-actions">
                <button type="button" class="workspace-secondary-button markdown-list-toggle" @click="toggleList()" :aria-expanded="listOpen" aria-controls="markdown-list-panel">
                    <x-icon name="{{ $selectedDocument ? 'layout-sidebar-left-collapse' : 'list' }}" />
                    <span x-text="listOpen ? 'Hide list' : 'Show list'">Show list</span>
                </button>
                <form method="POST" action="{{ route('markdown.store') }}">
                    @csrf
                    <input type="hidden" name="project_id" value="{{ is_int($projectFilter) ? $projectFilter : '' }}" :value="projectId ?? ''" :disabled="projectId === null">
                    <button type="submit" class="markdown-primary-button"><x-icon name="plus" /> New document</button>
                </form>
            </div>
        </header>

        @if (session('status'))
            <p class="markdown-flash" role="status"><x-icon name="circle-check" /> {{ session('status') }}</p>
        @endif

        <div class="markdown-layout" :class="{ 'markdown-list-is-hidden': !listOpen }">
            <aside id="markdown-list-panel" class="markdown-list-panel" aria-label="Your Markdown documents" :aria-hidden="!listOpen" :inert="!listOpen">
                <livewire:documents-search :selected-id="$selectedDocument?->id" :initial-search="$search" :initial-project-filter="$projectFilter" />
            </aside>

            <section class="markdown-editor-panel" aria-label="Markdown document editor">
                @if ($selectedDocument)
                    @include('pages.markdown.editor')
                @else
                    <div class="markdown-empty-state">
                        <span class="markdown-empty-icon"><x-icon name="markdown" /></span>
                        <h2>Choose a document or start fresh</h2>
                        <p>Create a private Markdown document with an editor and safe live preview.</p>
                        <form method="POST" action="{{ route('markdown.store') }}">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ is_int($projectFilter) ? $projectFilter : '' }}" :value="projectId ?? ''" :disabled="projectId === null">
                            <button type="submit" class="workspace-secondary-button"><x-icon name="plus" /> Create your first document</button>
                        </form>
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
