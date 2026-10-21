<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\VaultSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVaultIsUnlocked
{
    public function __construct(private readonly VaultSession $vaultSession) {}

    public function handle(Request $request, Closure $next): Response|JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // A successful sensitive request is Vault activity, including when JavaScript
        // is unavailable and therefore cannot send the browser heartbeat.
        if (! $this->vaultSession->renew($request, $user)) {
            $message = 'Unlock your Vault before accessing credential secrets.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 423);
            }

            return to_route('vault.index')->with('vault_status', $message);
        }

        return $next($request);
    }
}
