@extends('layouts.app')
@section('title', 'Projects · Scrunote')

@section('content')
    <section class="projects-page" aria-labelledby="projects-heading">
        <header class="workspace-section-header projects-page-header">
            <div>
                <p class="workspace-eyebrow">Organization</p>
                <h1 id="projects-heading">Projects</h1>
                <p class="workspace-section-description">Group related Notes, Markdown Docs, and safe Vault metadata. Unassigned items stay in My Workspace.</p>
            </div>
        </header>

        @if (session('project_status'))
            <p class="projects-flash" role="status"><x-icon name="circle-check" /> {{ session('project_status') }}</p>
        @endif

        <div class="projects-index-layout">
            <section class="projects-list-section" aria-labelledby="projects-list-heading">
                <header class="projects-section-heading">
                    <div><h2 id="projects-list-heading">Your Projects</h2><p>{{ $projects->count() }} {{ Str::plural('project', $projects->count()) }} saved</p></div>
                </header>

                <div class="projects-grid">
                    <a class="project-card project-card-my-workspace" href="{{ route('notes.index', ['project' => 'mine']) }}">
                        <span class="project-card-icon"><x-icon name="home" /></span>
                        <span class="project-card-copy"><strong>My Workspace</strong><span>Personal and unassigned content</span></span>
                        <span class="project-card-counts"><span>{{ $myWorkspaceCounts['notes'] }} Notes</span><span>{{ $myWorkspaceCounts['documents'] }} Docs</span><span>{{ $myWorkspaceCounts['vaultEntries'] }} Vault</span></span>
                        <x-icon name="arrow-up-right" class="project-card-arrow" />
                    </a>
                    @forelse ($projects as $project)
                        <a class="project-card" href="{{ route('projects.show', $project) }}">
                            <span class="project-card-icon"><x-icon name="folder" /></span>
                            <span class="project-card-copy"><strong>{{ $project->title }}</strong><span>{{ filled($project->description) ? Str::limit($project->description, 95) : 'No description yet.' }}</span></span>
                            <span class="project-card-counts"><span>{{ $project->notes_count }} Notes</span><span>{{ $project->documents_count }} Docs</span><span>{{ $project->vault_entries_count }} Vault</span></span>
                            <x-icon name="arrow-up-right" class="project-card-arrow" />
                        </a>
                    @empty
                        <div class="projects-empty-card"><x-icon name="folder-plus" /><p>Create a Project when related work needs its own context. Your existing items remain safely in My Workspace.</p></div>
                    @endforelse
                </div>
            </section>

            <aside class="project-create-card" aria-labelledby="create-project-heading">
                <div><span class="project-create-icon"><x-icon name="folder-plus" /></span><h2 id="create-project-heading">New Project</h2><p>Create a private container for one focused body of work.</p></div>
                <form method="POST" action="{{ route('projects.store') }}">
                    @csrf
                    <label for="project-title">Project name</label>
                    <input id="project-title" type="text" name="title" value="{{ old('title') }}" maxlength="120" placeholder="Example: Client portal" autocomplete="off" required>
                    @error('title')<p class="project-field-error">{{ $message }}</p>@enderror
                    <label for="project-description">Description <span>Optional</span></label>
                    <textarea id="project-description" name="description" rows="4" maxlength="2000" placeholder="What belongs in this Project?">{{ old('description') }}</textarea>
                    @error('description')<p class="project-field-error">{{ $message }}</p>@enderror
                    <button type="submit" class="projects-primary-button"><x-icon name="plus" /> Create Project</button>
                </form>
            </aside>
        </div>
    </section>
@endsection
