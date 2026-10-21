<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceLayoutData
{
    /** @return array{page: array, navigation: array, modules: array, identity: array} */
    public function for(Request $request, string $routeName): array
    {
        $navigation = WorkspaceNavigation::items();
        $page = collect($navigation)->firstWhere('route', $routeName)
            ?? ($routeName === 'projects.show' ? collect($navigation)->firstWhere('route', 'projects.index') : null)
            ?? WorkspaceNavigation::utilityPage($routeName);
        abort_unless($page, 404);

        /** @var User $user */
        $user = $request->user();
        $firstName = trim($user->first_name);
        $displayName = $firstName !== '' ? $firstName : $user->username;

        return [
            'page' => $page,
            'navigation' => $navigation,
            'modules' => array_values(array_filter($navigation, fn (array $item): bool => $item['group'] === 'modules')),
            'identity' => [
                'name' => $user->name,
                'first_name' => $displayName,
                'initial' => Str::upper(Str::substr($displayName, 0, 1)),
                'username' => $user->username,
                'email' => $user->email,
            ],
        ];
    }
}
