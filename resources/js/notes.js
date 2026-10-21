import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

// Browser enhancement for Notes. The server-rendered form remains the source of truth.
const AUTOSAVE_DELAY = 650;

function isSafeLink(value) {
    const href = value.trim();
    if (href === '') return false;
    if (href.toLowerCase().startsWith('mailto:')) return href.length > 'mailto:'.length;

    try {
        return ['http:', 'https:'].includes(new URL(href).protocol);
    } catch (_) {
        return false;
    }
}

function notesWorkspace({ selected }) {
    return {
        selected,
        hasJavaScript: true,
        listOpen: true,

        init() {
            this.listOpen = !selected || !window.matchMedia('(max-width: 767.98px)').matches;
        },

        toggleList() { this.listOpen = !this.listOpen; },
        openList() { this.listOpen = true; },
        closeListForSelection() {
            if (window.matchMedia('(max-width: 767.98px)').matches) this.listOpen = false;
        },

        handleShortcut(event) {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.repeat && event.shiftKey && event.key.toLowerCase() === 'n') {
                const newNote = this.$root.querySelector('form[action$="/notes"] button[type="submit"]');
                if (newNote) { event.preventDefault(); newNote.click(); }
            }
        },
    };
}

function noteEditor({ noteId, version, saveUrl, pinVersionId }) {
    // Keep the Tiptap instance outside Alpine's reactive proxy. ProseMirror
    // mutates its own state and must not be wrapped by another proxy.
    let tiptapEditor = null;

    return {
        noteId,
        version,
        saveUrl,
        pinVersionId,
        dirty: false,
        saving: false,
        wordCount: 0,
        toolbarVersion: 0,
        conflicted: false,
        statusMessage: 'Autosaves as you type.',
        timer: null,
        unloadHandler: null,

        init() {
            this.$refs.contentInput.disabled = false;
            this.$refs.contentInput.name = 'content_html';
            const initialHtml = this.$refs.editor.innerHTML;
            // Tiptap mounts its ProseMirror root inside the supplied element.
            // Remove the server-rendered seed DOM after capturing it so it is
            // not displayed alongside the hydrated editor content.
            this.$refs.editor.replaceChildren();

            tiptapEditor = new Editor({
                element: this.$refs.editor,
                content: initialHtml,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3] },
                        link: {
                            autolink: false,
                            linkOnPaste: false,
                            openOnClick: false,
                            HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
                        },
                    }),
                ],
                editorProps: {
                    attributes: {
                        class: 'notes-tiptap-content',
                        role: 'textbox',
                        'aria-label': 'Note content',
                        'aria-multiline': 'true',
                    },
                    handlePaste: (view, event) => {
                        const text = event.clipboardData?.getData('text/plain') || '';
                        if (text === '') return false;
                        event.preventDefault();
                        const { from, to } = view.state.selection;
                        view.dispatch(view.state.tr.insertText(text, from, to));

                        return true;
                    },
                },
                onUpdate: () => {
                    this.markDirty();
                    this.updateToolbar();
                },
                onSelectionUpdate: () => this.updateToolbar(),
            });

            this.serialize();
            this.updateWordCount();
            this.unloadHandler = event => {
                if (!this.dirty && !this.saving) return;
                event.preventDefault();
                event.returnValue = '';
            };
            window.addEventListener('beforeunload', this.unloadHandler);
        },

        destroy() {
            clearTimeout(this.timer);
            window.removeEventListener('beforeunload', this.unloadHandler);
            tiptapEditor?.destroy();
            tiptapEditor = null;
        },

        updateToolbar() {
            this.toolbarVersion += 1;
        },

        isActive(name, attributes = {}) {
            // Read the reactive counter so toolbar buttons update after selection changes.
            this.toolbarVersion;

            return tiptapEditor?.isActive(name, attributes) ?? false;
        },

        updateWordCount() {
            const text = tiptapEditor?.getText().trim() || '';
            this.wordCount = text ? text.split(/\s+/u).length : 0;
        },

        serialize() {
            this.$refs.contentInput.value = tiptapEditor?.getHTML() ?? this.$refs.editor.innerHTML;
            this.$refs.versionInput.value = this.version;
            const pinVersion = document.getElementById(this.pinVersionId);
            if (pinVersion) pinVersion.value = this.version;
        },

        markDirty() {
            if (this.conflicted) return;
            this.updateWordCount();
            this.dirty = true;
            this.statusMessage = 'Saving changes automatically…';
            this.saveSoon();
        },

        saveSoon(delay = AUTOSAVE_DELAY) {
            if (this.conflicted) return;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(), delay);
        },

        async saveNow() {
            await this.save();
        },

        async save() {
            if (!this.dirty || this.saving || this.conflicted) return;
            clearTimeout(this.timer);
            this.serialize();
            this.saving = true;
            this.dirty = false;
            this.statusMessage = 'Saving automatically…';
            const body = new FormData(this.$root);

            try {
                const response = await fetch(this.saveUrl, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json().catch(() => ({}));
                if (response.status === 409) {
                    this.conflicted = true;
                    this.dirty = true;
                    this.statusMessage = data.message || 'This note changed in another tab. Reload it before saving again.';

                    return;
                }
                if (!response.ok) {
                    this.dirty = true;
                    this.statusMessage = data.message || 'Could not save. Your changes are still in this tab.';

                    return;
                }
                this.version = data.note.version;
                this.serialize();
                if (this.$refs.tags) this.$refs.tags.value = data.note.tags.join(', ');
                if (this.$refs.project) this.$refs.project.value = data.note.project?.id || '';
                this.updateNotesList(data.note);
                this.statusMessage = 'Autosaved.';
            } catch (_) {
                this.dirty = true;
                this.statusMessage = 'Connection problem. Your changes are still in this tab.';
            } finally {
                this.saving = false;
                if (this.dirty && !this.conflicted) this.saveSoon();
            }
        },

        updateNotesList(note) {
            const item = Array.from(document.querySelectorAll('[data-note-id]'))
                .find(candidate => String(candidate.dataset.noteId) === String(note.id));
            if (!item) return;

            const title = item.querySelector('[data-note-list-title]');
            if (title) title.textContent = note.title;

            const tags = item.querySelector('[data-note-list-tags]');
            const preview = item.querySelector('[data-note-list-preview]');
            const tagText = (note.tags || []).map(tag => `#${tag}`).join(' ');
            if (tags) {
                tags.textContent = tagText;
                tags.hidden = tagText === '';
            }
            if (preview) preview.textContent = note.preview || 'Empty note';

            const updated = item.querySelector('[data-note-list-updated]');
            if (updated) {
                if (note.updated_at) updated.dateTime = note.updated_at;
                updated.textContent = 'Just now';
            }
        },

        saveShortcut(event) {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 's') {
                event.preventDefault();
                this.save();
            }
        },

        toggleFormat(format) {
            if (!tiptapEditor) return;

            if (format === 'bold') tiptapEditor.commands.toggleBold();
            if (format === 'italic') tiptapEditor.commands.toggleItalic();
            if (format === 'underline') tiptapEditor.commands.toggleUnderline();
            if (format === 'bulletList') tiptapEditor.commands.toggleBulletList();
            if (format === 'orderedList') tiptapEditor.commands.toggleOrderedList();
        },

        formatBlock(tag) {
            if (!tiptapEditor) return;

            if (tag === 'p') tiptapEditor.commands.setParagraph();
            if (tag === 'h2') tiptapEditor.commands.toggleHeading({ level: 2 });
            if (tag === 'h3') tiptapEditor.commands.toggleHeading({ level: 3 });
            if (tag === 'blockquote') tiptapEditor.commands.toggleBlockquote();
            if (tag === 'pre') tiptapEditor.commands.toggleCodeBlock();
        },

        addLink() {
            if (!tiptapEditor) return;
            const current = tiptapEditor.getAttributes('link').href || '';
            const href = window.prompt('Paste a full https://, http:// or mailto: link', current);
            if (href === null) return;
            if (href.trim() === '') {
                tiptapEditor.chain().focus().extendMarkRange('link').unsetLink().run();

                return;
            }
            if (!isSafeLink(href)) {
                this.statusMessage = 'Use an https://, http:// or mailto: link.';

                return;
            }
            tiptapEditor.chain().focus().extendMarkRange('link').setLink({ href: href.trim() }).run();
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('notesWorkspace', notesWorkspace);
    window.Alpine.data('noteEditor', noteEditor);
}, { once: true });

document.addEventListener('submit', event => {
    if (!event.target.matches('[data-note-delete]')) return;
    if (!window.confirm('Delete this note permanently? This cannot be undone.')) event.preventDefault();
});
