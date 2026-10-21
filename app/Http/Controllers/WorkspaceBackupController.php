<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\CreateWorkspaceBackupRequest;
use App\Http\Requests\Settings\RestoreWorkspaceBackupRequest;
use App\Models\User;
use App\Support\VaultSession;
use App\Support\WorkspaceBackup;
use App\Support\WorkspaceBackupException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class WorkspaceBackupController extends Controller
{
    public function __construct(
        private readonly WorkspaceBackup $backups,
        private readonly VaultSession $vaultSession,
    ) {}

    public function create(CreateWorkspaceBackupRequest $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $backup = $this->backups->create($user, $request->validated('backup_password'));
        } catch (WorkspaceBackupException $exception) {
            return back()->withErrors(['backup_password' => $exception->getMessage()]);
        }

        return response($backup, 200, [
            'Content-Type' => 'application/vnd.scrunote.workspace-backup+json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="scrunote-workspace-'.now()->format('Ymd-His').'.scrunote"',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function restore(RestoreWorkspaceBackupRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('backup_file');
        abort_if($file === null, 422);

        try {
            $contents = $file->get();
            $this->backups->restore($user, $contents, $request->validated('backup_password'));
        } catch (WorkspaceBackupException $exception) {
            return back()->withErrors(['restore_backup' => $exception->getMessage()]);
        }

        $this->vaultSession->lock($request);

        return to_route('settings.index')->with('settings_status', 'Your workspace was restored. Your Vault is locked until you enter its master password.');
    }
}
