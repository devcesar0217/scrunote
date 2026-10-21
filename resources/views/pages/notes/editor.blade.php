@php
    $tagNames = $selectedNote->tags->pluck('name')->implode(', ');
    // Existing Notes made before the lock migration can briefly hydrate without
    // the attribute until `php artisan migrate` has been run. Always emit a
    // valid JavaScript integer so Alpine does not fail to initialise.
    $noteVersion = max(0, (int) ($selectedNote->lock_version ?? 0));
@endphp

<form id="note-editor-form" class="notes-editor-form" method="POST" action="{{ route('notes.update', $selectedNote) }}"
    x-data="noteEditor({
        noteId: {{ Illuminate\Support\Js::from($selectedNote->id) }},
        version: {{ Illuminate\Support\Js::from($noteVersion) }},
        saveUrl: {{ Illuminate\Support\Js::from(route('notes.update', $selectedNote)) }},
        pinVersionId: 'note-pin-version',
    })"
    @submit.prevent="saveNow()" @keydown.window="saveShortcut($event)">
    @csrf
    @method('PATCH')
    <input type="hidden" name="version" value="{{ old('version', $noteVersion) }}" x-ref="versionInput">
    <input type="hidden" disabled x-ref="contentInput">

    <div class="notes-editor-head">
        <label class="sr-only" for="note-title">Note title</label>
        <input id="note-title" class="notes-title-input" type="text" name="title" value="{{ old('title', $selectedNote->title) }}" maxlength="160"
            placeholder="Untitled note" autocomplete="off" x-ref="title" @input="markDirty()">
        <button type="submit" form="note-pin-form" class="workspace-secondary-button notes-pin-button" aria-label="{{ $selectedNote->is_pinned ? 'Unpin note' : 'Pin note' }}">
            <x-icon name="pin{{ $selectedNote->is_pinned ? '-filled' : '' }}" />
            {{ $selectedNote->is_pinned ? 'Pinned' : 'Pin' }}
        </button>
    </div>

    <div class="notes-project-row">
        <x-icon name="folder" />
        <label for="note-project">Project</label>
        <select id="note-project" name="project_id" x-ref="project" @change="markDirty()">
            <option value="">My Workspace</option>
            @foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id', $selectedNote->project_id) === (string) $project->id)>{{ $project->title }}</option>@endforeach
        </select>
    </div>

    <div class="notes-tag-row">
        <x-icon name="tags" />
        <label class="sr-only" for="note-tags">Tags, separated by commas</label>
        <input id="note-tags" type="text" name="tags" value="{{ old('tags', $tagNames) }}" maxlength="650"
            placeholder="Add tags, separated by commas" autocomplete="off" x-ref="tags" @input="markDirty()">
        <span>Up to 12 tags</span>
    </div>

    @if ($errors->any())
        <div class="notes-form-errors" role="alert" tabindex="-1" id="notes-editor-errors">
            <x-icon name="alert-circle" />
            <div><strong>Could not save this note.</strong><p>{{ $errors->first() }}</p></div>
        </div>
    @endif

    <div class="notes-editor-toolbar" role="toolbar" aria-label="Rich-text formatting">
        <label class="sr-only" for="note-block-style">Text style</label>
        <select id="note-block-style" @change="formatBlock($event.target.value)" x-ref="blockStyle">
            <option value="p">Body</option>
            <option value="h2">Heading</option>
            <option value="h3">Subheading</option>
            <option value="blockquote">Quote</option>
            <option value="pre">Code block</option>
        </select>
        <span class="notes-toolbar-divider" aria-hidden="true"></span>
        <button type="button" @mousedown.prevent @click="toggleFormat('bold')" title="Bold (Ctrl/Cmd+B)" aria-label="Bold" :aria-pressed="isActive('bold')" :class="{ 'is-active': isActive('bold') }"><x-icon name="bold" /></button>
        <button type="button" @mousedown.prevent @click="toggleFormat('italic')" title="Italic (Ctrl/Cmd+I)" aria-label="Italic" :aria-pressed="isActive('italic')" :class="{ 'is-active': isActive('italic') }"><x-icon name="italic" /></button>
        <button type="button" @mousedown.prevent @click="toggleFormat('underline')" title="Underline (Ctrl/Cmd+U)" aria-label="Underline" :aria-pressed="isActive('underline')" :class="{ 'is-active': isActive('underline') }"><x-icon name="underline" /></button>
        <span class="notes-toolbar-divider" aria-hidden="true"></span>
        <button type="button" @mousedown.prevent @click="toggleFormat('bulletList')" title="Bulleted list" aria-label="Bulleted list" :aria-pressed="isActive('bulletList')" :class="{ 'is-active': isActive('bulletList') }"><x-icon name="list" /></button>
        <button type="button" @mousedown.prevent @click="toggleFormat('orderedList')" title="Numbered list" aria-label="Numbered list" :aria-pressed="isActive('orderedList')" :class="{ 'is-active': isActive('orderedList') }"><x-icon name="list-numbers" /></button>
        <button type="button" @mousedown.prevent @click="addLink()" title="Add or remove link" aria-label="Add or remove link" :aria-pressed="isActive('link')" :class="{ 'is-active': isActive('link') }"><x-icon name="link" /></button>
        <button type="button" @mousedown.prevent @click="formatBlock('pre')" title="Code block" aria-label="Code block" :aria-pressed="isActive('codeBlock')" :class="{ 'is-active': isActive('codeBlock') }"><x-icon name="code" /></button>
    </div>

    <div class="notes-editor-wrap">
        <div class="notes-rich-editor" x-ref="editor" x-cloak>{!! $selectedHtml !!}</div>
        <noscript>
            <label class="notes-plain-label" for="note-content-plain">Note content (plain text without JavaScript)</label>
            <textarea id="note-content-plain" class="notes-plain-fallback" name="content_html" rows="16">{{ old('content_html', $selectedNote->content_text) }}</textarea>
            <input type="hidden" name="content_plain" value="1">
        </noscript>
    </div>

    <footer class="notes-editor-footer">
        <p id="note-save-status" role="status" aria-live="polite" x-text="statusMessage">Save changes with the button below when JavaScript is unavailable.</p>
        <p><span x-text="wordCount"></span> words · Last updated {{ $selectedNote->updated_at->diffForHumans() }}</p>
    </footer>
    <noscript><button type="submit" class="notes-noscript-save-button">Save note</button></noscript>
</form>

<form id="note-pin-form" method="POST" action="{{ route('notes.pin', $selectedNote) }}">
    @csrf
    @method('PATCH')
    <input id="note-pin-version" type="hidden" name="version" value="{{ $noteVersion }}">
</form>
<form id="note-delete-form" method="POST" action="{{ route('notes.destroy', $selectedNote) }}" data-note-delete>
    @csrf
    @method('DELETE')
    <button type="submit" class="notes-delete-button"><x-icon name="trash" /> Delete note</button>
</form>
