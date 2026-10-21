# Scrunote — one cumulative update ZIP

Use **`scrunote-update.zip`** only. This is the one cumulative overlay archive; it has been overwritten with the **Phase 8 restore correction**. Do not chain phase ZIPs.

This updated/new-files overlay contains **33 updated** and **103 new** files relative to the inspected foundation. It excludes credentials, `.env` files, database data, dependencies, build/cache output, and verification artifacts.

## Phase 8 correction — encrypted restore

This archive retains all approved Phase 2–8 work and corrects the failed same-account backup restore.

### Root cause fixed
Vault Server and Database port fields are submitted by normal browser forms as strings (for example, `"443"`). Laravel validates these values as integers but does not cast them. The original restore validator mistakenly rejected this valid, existing Vault metadata shape after a backup was successfully decrypted.

The corrected implementation:

- canonicalizes newly saved Vault `metadata.port` values to JSON integers;
- accepts canonical decimal port strings from already-stored/previously-exported Vault metadata and normalizes them to an integer during restore;
- preserves strict allowed-field/range validation (1–65535);
- additionally handles canonical decimal owner IDs while preserving same-account-only restore protection; cross-user backups remain rejected.

No user data or Vault secrets are decrypted, weakened, or exposed by this correction. The existing encrypted backup file can be retried after applying this ZIP; export a new backup only after confirming restore succeeds.

## Apply safely

1. Stop PHP/Vite and back up the project/database.
2. Extract this ZIP to a temporary directory.
3. Copy the **contents** of `files/` into the existing project root, preserving paths. Do **not** create a `files` directory inside the application.
4. Preserve `.env`, `.env.testing`, `APP_KEY`, credentials, and database data.
5. Remove only paths listed in `DELETE-FILES.txt`, if they exist; remove `docs` only if it becomes empty.

Run from the project root:

```powershell
composer install --no-interaction --prefer-dist
composer check-platform-reqs
php artisan optimize:clear
php artisan migrate --no-interaction
npm ci --ignore-scripts
npm run build
composer test -- --compact
```

Do **not** run `composer update`, `npm update`, `key:generate`, `migrate:fresh`, `db:wipe`, or rollback commands. Use a separate disposable MySQL database/account for test runs.

## Contents and verification

- `MANIFEST.csv` contains all **136** payload files, their Updated/New status, destinations, and SHA-256 digests.
- All payloads are under `files/`: `33` Updated and `103` New.
- `DELETE-FILES.txt` is the conservative historical cleanup list.
- Regression coverage adds a real Vault Server entry with a browser-style string port to the encrypted backup/restore round-trip and confirms restored metadata is integer-normalized. Vault feature coverage also verifies form-string port persistence.
- Validation passed: targeted Settings/Vault **17 tests / 207 assertions**; full MySQL suite **97 tests / 848 assertions**; Pint (**100 files**); PHP/JS syntax; Vite build; config/route/event/view caches; Settings routes; Composer validation/platform checks/audit; `git diff --check`; and archive source-byte manifest verification.
- The only non-blocking advisory remains Vite’s existing Markdown chunk-size warning.

Native Windows/PHP 8.3/MySQL/browser verification remains your responsibility. This correction remains within **Phase 8**; no next phase is approved by this archive.

After retrying the restore, reply with **“Approved — proceed to the next phase.”**, **“Issue found — help me fix the current phase.”**, or **“Request changes — revise the current phase.”**
