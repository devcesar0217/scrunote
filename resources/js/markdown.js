import DOMPurify from 'dompurify';
import MarkdownIt from 'markdown-it';
import taskLists from 'markdown-it-task-lists';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { markdown as markdownLanguage } from '@codemirror/lang-markdown';
import { bracketMatching, defaultHighlightStyle, foldGutter, indentOnInput, syntaxHighlighting } from '@codemirror/language';
import { EditorState } from '@codemirror/state';
import { drawSelection, EditorView, highlightActiveLine, highlightActiveLineGutter, keymap, lineNumbers } from '@codemirror/view';

const AUTOSAVE_DELAY = 650;
const PREVIEW_SANITIZE_OPTIONS = {
    ALLOWED_TAGS: ['a', 'article', 'blockquote', 'br', 'code', 'del', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'input', 'label', 'li', 'ol', 'p', 'pre', 's', 'span', 'strong', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'ul'],
    ALLOWED_ATTR: ['checked', 'class', 'disabled', 'for', 'href', 'id', 'rel', 'target', 'title', 'type'],
    ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto):)/i,
    FORBID_TAGS: ['form', 'iframe', 'img', 'math', 'object', 'script', 'style', 'svg'],
    ALLOW_DATA_ATTR: false,
};

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

function escapeHtml(value) {
    return value.replace(/[&<>'"]/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;',
    }[character]));
}

const markdownRenderer = new MarkdownIt({ html: false, linkify: false, breaks: false, typographer: false })
    .use(taskLists, { label: true, labelAfter: true });

markdownRenderer.validateLink = isSafeLink;
const defaultLinkOpen = markdownRenderer.renderer.rules.link_open
    ?? ((tokens, index, options, _environment, self) => self.renderToken(tokens, index, options));
markdownRenderer.renderer.rules.link_open = (tokens, index, options, environment, self) => {
    tokens[index].attrSet('target', '_blank');
    tokens[index].attrSet('rel', 'noopener noreferrer');

    return defaultLinkOpen(tokens, index, options, environment, self);
};
markdownRenderer.renderer.rules.image = (tokens, index) => {
    const alt = tokens[index].content.trim() || 'Image';

    return `<span class="markdown-image-omitted">Image omitted: ${escapeHtml(alt)}</span>`;
};

const scrunoteEditorTheme = EditorView.theme({
    '&': { height: '100%', color: 'var(--sn-soft)', backgroundColor: 'var(--sn-field)' },
    '&.cm-focused': { outline: 'none' },
    '.cm-scroller': { overflow: 'auto', fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace' },
    '.cm-content': { minHeight: '100%', padding: '18px 0', caretColor: '#059669', fontSize: '12px', lineHeight: '1.7' },
    '.cm-line': { padding: '0 16px' },
    '.cm-gutters': { minHeight: '100%', border: '0', backgroundColor: 'var(--sn-field-alt)', color: 'var(--sn-faint)' },
    '.cm-gutterElement': { padding: '0 8px 0 10px' },
    '.cm-activeLine': { backgroundColor: 'color-mix(in srgb, var(--sn-raised) 75%, transparent)' },
    '.cm-activeLineGutter': { backgroundColor: 'var(--sn-raised)' },
    '.cm-selectionBackground, &.cm-focused .cm-selectionBackground': { backgroundColor: '#10b98138' },
    '.cm-cursor, .cm-dropCursor': { borderLeftColor: '#059669' },
    '.cm-foldPlaceholder': { border: '0', backgroundColor: 'var(--sn-raised)', color: 'var(--sn-muted)' },
}, { dark: true });

function markdownWorkspace({ selected }) {
    return {
        selected,
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
                const newDocument = this.$root.querySelector('form[action$="/markdown"] button[type="submit"]');
                if (newDocument) {
                    event.preventDefault();
                    newDocument.click();
                }
            }
        },
    };
}

function documentEditor({ documentId, version, saveUrl, pinVersionId }) {
    // CodeMirror owns mutable editor state, so it must remain outside Alpine's proxy.
    let editor = null;
    let sourceScroller = null;
    let previewScroller = null;
    let sourceScrollHandler = null;
    let previewScrollHandler = null;
    let scrollSyncLocked = false;
    let scrollSyncFrame = null;

    return {
        documentId,
        version,
        saveUrl,
        pinVersionId,
        dirty: false,
        saving: false,
        wordCount: 0,
        conflicted: false,
        mode: 'split',
        statusMessage: 'Autosaves as you type.',
        timer: null,
        unloadHandler: null,

        init() {
            this.$refs.contentInput.disabled = false;
            this.$refs.contentInput.name = 'markdown';
            const initialMarkdown = this.$refs.editor.textContent;
            this.$refs.editor.replaceChildren();

            editor = new EditorView({
                state: EditorState.create({
                    doc: initialMarkdown,
                    extensions: [
                        lineNumbers(),
                        highlightActiveLineGutter(),
                        history(),
                        foldGutter(),
                        drawSelection(),
                        indentOnInput(),
                        bracketMatching(),
                        syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
                        markdownLanguage(),
                        EditorView.lineWrapping,
                        scrunoteEditorTheme,
                        keymap.of([indentWithTab, ...defaultKeymap, ...historyKeymap]),
                        EditorView.updateListener.of(update => {
                            if (update.docChanged) this.markDirty();
                        }),
                    ],
                }),
                parent: this.$refs.editor,
            });

            this.serialize();
            this.updateWordCount();
            this.renderPreview();
            this.enableScrollSync();
            this.unloadHandler = event => {
                if (!this.dirty && !this.saving) return;
                event.preventDefault();
                event.returnValue = '';
            };
            window.addEventListener('beforeunload', this.unloadHandler);
        },

        destroy() {
            clearTimeout(this.timer);
            cancelAnimationFrame(scrollSyncFrame);
            sourceScroller?.removeEventListener('scroll', sourceScrollHandler);
            previewScroller?.removeEventListener('scroll', previewScrollHandler);
            window.removeEventListener('beforeunload', this.unloadHandler);
            editor?.destroy();
            editor = null;
            sourceScroller = null;
            previewScroller = null;
            sourceScrollHandler = null;
            previewScrollHandler = null;
        },

        markdown() {
            return editor?.state.doc.toString() ?? this.$refs.contentInput.value;
        },

        updateWordCount() {
            const text = this.markdown().trim();
            this.wordCount = text ? text.split(/\s+/u).length : 0;
        },

        serialize() {
            this.$refs.contentInput.value = this.markdown();
            this.$refs.versionInput.value = this.version;
            const pinVersion = document.getElementById(this.pinVersionId);
            if (pinVersion) pinVersion.value = this.version;
        },

        renderPreview() {
            const rendered = markdownRenderer.render(this.markdown());
            this.$refs.preview.innerHTML = DOMPurify.sanitize(rendered, PREVIEW_SANITIZE_OPTIONS);

            // Keep task-list controls non-interactive. DOMPurify intentionally applies
            // extra safeguards to form controls, so restore only these fixed,
            // renderer-owned properties after sanitization rather than trusting input
            // attributes from Markdown.
            this.$refs.preview.querySelectorAll('input.task-list-item-checkbox').forEach(checkbox => {
                checkbox.type = 'checkbox';
                checkbox.disabled = true;
                checkbox.setAttribute('aria-label', checkbox.checked ? 'Completed task' : 'Incomplete task');
            });
            this.$refs.preview.querySelectorAll('a').forEach(anchor => {
                const href = anchor.getAttribute('href') ?? '';
                if (!isSafeLink(href)) {
                    anchor.removeAttribute('href');
                    anchor.removeAttribute('target');
                    anchor.removeAttribute('rel');

                    return;
                }

                anchor.target = '_blank';
                anchor.rel = 'noopener noreferrer';
            });
            if (sourceScroller && previewScroller) this.syncScrollPosition(sourceScroller, previewScroller);
        },

        enableScrollSync() {
            sourceScroller = this.$refs.editor.querySelector('.cm-scroller');
            previewScroller = this.$refs.preview;
            if (!sourceScroller || !previewScroller) return;

            sourceScrollHandler = () => this.syncScrollPosition(sourceScroller, previewScroller);
            previewScrollHandler = () => this.syncScrollPosition(previewScroller, sourceScroller);
            sourceScroller.addEventListener('scroll', sourceScrollHandler, { passive: true });
            previewScroller.addEventListener('scroll', previewScrollHandler, { passive: true });
        },

        syncScrollPosition(from, to) {
            if (!from || !to || this.mode !== 'split' || scrollSyncLocked) return;

            const fromRange = Math.max(0, from.scrollHeight - from.clientHeight);
            const toRange = Math.max(0, to.scrollHeight - to.clientHeight);
            const progress = fromRange === 0 ? 0 : from.scrollTop / fromRange;

            scrollSyncLocked = true;
            to.scrollTop = Math.max(0, Math.min(toRange, progress * toRange));
            cancelAnimationFrame(scrollSyncFrame);
            scrollSyncFrame = requestAnimationFrame(() => { scrollSyncLocked = false; });
        },

        selectMode(mode) {
            this.mode = mode;
            if (mode === 'split') this.$nextTick(() => this.syncScrollPosition(sourceScroller, previewScroller));
        },


        markDirty() {
            if (this.conflicted) return;
            this.updateWordCount();
            this.renderPreview();
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
                    this.statusMessage = data.message || 'This document changed in another tab. Reload it before saving again.';

                    return;
                }
                if (!response.ok) {
                    this.dirty = true;
                    this.statusMessage = data.message || 'Could not save. Your changes are still in this tab.';

                    return;
                }
                this.version = data.document.version;
                this.serialize();
                if (this.$refs.tags) this.$refs.tags.value = data.document.tags.join(', ');
                if (this.$refs.project) this.$refs.project.value = data.document.project?.id || '';
                this.updateDocumentsList(data.document);
                this.statusMessage = 'Autosaved.';
            } catch (_) {
                this.dirty = true;
                this.statusMessage = 'Connection problem. Your changes are still in this tab.';
            } finally {
                this.saving = false;
                if (this.dirty && !this.conflicted) this.saveSoon();
            }
        },

        updateDocumentsList(documentData) {
            const item = Array.from(document.querySelectorAll('[data-document-id]'))
                .find(candidate => String(candidate.dataset.documentId) === String(documentData.id));
            if (!item) return;

            const title = item.querySelector('[data-document-list-title]');
            if (title) title.textContent = documentData.title;

            const tags = item.querySelector('[data-document-list-tags]');
            const preview = item.querySelector('[data-document-list-preview]');
            const tagText = (documentData.tags || []).map(tag => `#${tag}`).join(' ');
            if (tags) {
                tags.textContent = tagText;
                tags.hidden = tagText === '';
            }
            if (preview) preview.textContent = documentData.preview || 'Empty document';

            const updated = item.querySelector('[data-document-list-updated]');
            if (updated) {
                if (documentData.updated_at) updated.dateTime = documentData.updated_at;
                updated.textContent = 'Just now';
            }
        },

        saveShortcut(event) {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 's') {
                event.preventDefault();
                this.save();
            }
        },
    };
}

function registerMarkdownAlpineData() {
    window.Alpine.data('markdownWorkspace', markdownWorkspace);
    window.Alpine.data('documentEditor', documentEditor);
}

// This file is loaded only on Markdown pages as its own Vite entry. Register
// immediately when Alpine is already available, while retaining the normal
// event path for the first page load.
if (window.Alpine) registerMarkdownAlpineData();
else document.addEventListener('alpine:init', registerMarkdownAlpineData, { once: true });

document.addEventListener('submit', event => {
    if (!event.target.matches('[data-document-delete]')) return;
    if (!window.confirm('Delete this document permanently? This cannot be undone.')) event.preventDefault();
});
