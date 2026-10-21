# Scrunote web application

## Current implementation

Scrunote is a Laravel web application with protected login/registration and a responsive workspace shell. Implemented modules are **persistent, owner-scoped Notes**, **Markdown Docs**, and **Secure Vault**.

Authenticated users can create, select, search, edit, pin, and delete their own Notes. Notes are stored in MySQL with personal tags, safe limited rich text, a plain-text search projection, and optimistic `lock_version` concurrency protection.

The Notes browser enhancement uses **Tiptap 3** with headings, bold, italic, underline, lists, blockquotes, code blocks, and limited safe links. Title, tag, and content changes autosave after a brief pause; Ctrl/Cmd+S triggers an immediate autosave. Server-side sanitization is the security authority, and the server-rendered forms remain the no-JavaScript baseline.

Markdown Docs provide owner-scoped Markdown source storage, title/tag/content search, pin/delete, CodeMirror 6 syntax-highlighted editing, split/editor/preview modes, autosave with optimistic locking, safe live preview, and `.md` export. Desktop split mode presents one visible editor scrollbar and proportionally synchronizes source and preview scrolling in either direction. The preview disables raw HTML, accepts only `http`, `https`, and `mailto` links, and intentionally does not render images or support uploads/attachments in this phase.

Secure Vault provides typed Login, Server, Database, API Credential, and Secure Note entries. Entries have searchable non-secret metadata and tags; the credential value and encrypted notes are never included in list/search projections or ordinary page markup. The browser UI includes explicit reveal/re-hide, copy controls, a browser-CSPRNG password generator, responsive Vault views, and create/edit/delete/pin workflows.

## Runtime and safety notes

- Notes, Markdown, and Vault routes require the existing authenticated active session. Every record/tag lookup is owner-scoped; another user’s record URL returns 404.
- State changes use existing CSRF/session protection, server-side validation, unlock rate limiting, and optimistic concurrency checks where applicable.
- Rich text is restricted to safe semantic markup and `http`, `https`, or `mailto` links. Executable markup, embeds, event attributes, and unsafe URI schemes are removed.
- Stale Notes, Markdown, and Vault writes are rejected and do not overwrite newer data.
- Markdown preview uses a restrictive client-side sanitizer after parsing; raw HTML, executable markup, embeds, unsafe URI schemes, and rendered images are excluded. Markdown source remains exportable as entered.
- Secure Vault uses a separate master password. Argon2id derives a wrapping key; Sodium encrypts a random per-user data key and all sensitive entry payloads. Master passwords and decrypted secret values are not persisted in plaintext.
- An unlocked Vault uses a server-checked five-minute inactivity expiry and browser inactivity lock. Sensitive Vault operations return HTTP 423 for JSON callers while locked. Successful sensitive requests renew that expiry for no-JavaScript form use as well.
- Forgotten Vault master passwords cannot recover existing encrypted entries. This web application does **not** claim operating-system key-store protection.
- Vault custom fields, Wi-Fi/Network entries, imports, sharing, health analysis, folders/projects, and all export formats are intentionally out of scope for this phase.
- Editable fields intentionally have no visual focus ring, per the requested UI revision. Keyboard interaction remains available.

## Local update and verification

Use the single cumulative `scrunote-update.zip` delivery. It is an updated/new-files overlay for an existing project, not a full repository. Preserve `.env`, `.env.testing`, `APP_KEY`, MySQL credentials, and existing data.

From the project root after applying the package:

```powershell
composer install --no-interaction --prefer-dist
composer check-platform-reqs
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --no-interaction
npm ci --ignore-scripts
npm run build
php artisan view:cache
php artisan route:list --except-vendor --path=notes
php artisan route:list --except-vendor --path=markdown
php artisan route:list --except-vendor --path=vault
```

Do not use `composer update`, `npm update`, `composer run setup`, `key:generate`, `migrate:fresh`, `db:wipe`, or rollback commands to apply an update.

Run the test suite only against a separate disposable MySQL test database/account:

```powershell
composer test -- --compact
```

Run the current PHPUnit suite and browser checks after applying each cumulative update. Native Windows/PHP 8.3/MySQL/browser verification remains your responsibility.

After local review, reply with one of:

- **Approved — proceed to the next phase.**
- **Issue found — help me fix the current phase.**
- **Request changes — revise the current phase.**
