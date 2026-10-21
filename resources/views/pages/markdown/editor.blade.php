@php
    $tagNames = $selectedDocument->tags->pluck('name')->implode(', ');
    $documentVersion = max(0, (int) ($selectedDocument->lock_version ?? 0));
@endphp

<form id="markdown-editor-form" class="markdown-editor-form" method="POST" action="{{ route('markdown.update', $selectedDocument) }}"
    x-data="documentEditor({
        documentId: {{ Illuminate\Support\Js::from($selectedDocument->id) }},
        version: {{ Illuminate\Support\Js::from($documentVersion) }},
        saveUrl: {{ Illuminate\Support\Js::from(route('markdown.update', $selectedDocument)) }},
        pinVersionId: 'document-pin-version',
    })"
    @submit.prevent="saveNow()" @keydown.window="saveShortcut($event)">
    @csrf
    @method('PATCH')
    <input type="hidden" name="version" value="{{ old('version', $documentVersion) }}" x-ref="versionInput">
    <textarea class="markdown-serialized-input" disabled x-ref="contentInput"></textarea>

    <div class="markdown-editor-head">
        <label class="sr-only" for="document-title">Document title</label>
        <input id="document-title" class="markdown-title-input" type="text" name="title" value="{{ old('title', $selectedDocument->title) }}" maxlength="160"
            placeholder="Untitled document" autocomplete="off" x-ref="title" @input="markDirty()">
        <a href="{{ route('markdown.export', $selectedDocument) }}" class="workspace-secondary-button markdown-export-button" title="Download Markdown source">
            <x-icon name="download" /> Export
        </a>
        <button type="submit" form="document-pin-form" class="workspace-secondary-button markdown-pin-button" aria-label="{{ $selectedDocument->is_pinned ? 'Unpin document' : 'Pin document' }}">
            <x-icon name="pin{{ $selectedDocument->is_pinned ? '-filled' : '' }}" />
            {{ $selectedDocument->is_pinned ? 'Pinned' : 'Pin' }}
        </button>
    </div>

    <div class="markdown-tag-row">
        <x-icon name="tags" />
        <label class="sr-only" for="document-tags">Tags, separated by commas</label>
        <input id="document-tags" type="text" name="tags" value="{{ old('tags', $tagNames) }}" maxlength="650"
            placeholder="Add tags, separated by commas" autocomplete="off" x-ref="tags" @input="markDirty()">
        <span>Up to 12 tags</span>
    </div>

    @if ($errors->any())
        <div class="markdown-form-errors" role="alert" tabindex="-1" id="markdown-editor-errors">
            <x-icon name="alert-circle" />
            <div><strong>Could not save this document.</strong><p>{{ $errors->first() }}</p></div>
        </div>
    @endif

    <div class="markdown-editor-toolbar" role="toolbar" aria-label="Markdown editor mode">
        <span class="markdown-toolbar-label">View</span>
        <button type="button" @click="selectMode('edit')" :aria-pressed="mode === 'edit'" :class="{ 'is-active': mode === 'edit' }"><x-icon name="pencil" /> Editor</button>
        <button type="button" @click="selectMode('split')" :aria-pressed="mode === 'split'" :class="{ 'is-active': mode === 'split' }"><x-icon name="columns-2" /> Split</button>
        <button type="button" @click="selectMode('preview')" :aria-pressed="mode === 'preview'" :class="{ 'is-active': mode === 'preview' }"><x-icon name="eye" /> Preview</button>
        <span class="markdown-toolbar-note">Images are not rendered in this workspace.</span>
    </div>

    <div class="markdown-workbench" :data-mode="mode">
        <section class="markdown-source-panel" aria-label="Markdown source">
            <header><span>Markdown</span><span>CodeMirror 6</span></header>
            <div class="markdown-codemirror" x-ref="editor" x-cloak>{{ old('markdown', $selectedDocument->markdown) }}</div>
            <noscript>
                <label class="markdown-plain-label" for="document-markdown-plain">Markdown source</label>
                <textarea id="document-markdown-plain" class="markdown-plain-fallback" name="markdown" rows="18">{{ old('markdown', $selectedDocument->markdown) }}</textarea>
            </noscript>
        </section>
        <section class="markdown-preview-panel" aria-label="Markdown preview">
            <header><span>Preview</span><span>Safe rendered output</span></header>
            <article class="markdown-preview" x-ref="preview" aria-live="polite"></article>
        </section>
    </div>

    <footer class="markdown-editor-footer">
        <p id="document-save-status" role="status" aria-live="polite" x-text="statusMessage">Save changes with the button below when JavaScript is unavailable.</p>
        <p><span x-text="wordCount"></span> words · Last updated {{ $selectedDocument->updated_at->diffForHumans() }}</p>
    </footer>
    <noscript><button type="submit" class="markdown-noscript-save-button">Save document</button></noscript>
</form>

<form id="document-pin-form" method="POST" action="{{ route('markdown.pin', $selectedDocument) }}">
    @csrf
    @method('PATCH')
    <input id="document-pin-version" type="hidden" name="version" value="{{ $documentVersion }}">
</form>
<form id="document-delete-form" method="POST" action="{{ route('markdown.destroy', $selectedDocument) }}" data-document-delete>
    @csrf
    @method('DELETE')
    <button type="submit" class="markdown-delete-button"><x-icon name="trash" /> Delete document</button>
</form>
