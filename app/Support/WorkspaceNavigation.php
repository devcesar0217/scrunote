<?php

namespace App\Support;

class WorkspaceNavigation
{
    /** @return array{id: string, label: string, route: string, icon: string, group: string, description: string, phase: string}|null */
    public static function utilityPage(string $route): ?array
    {
        return match ($route) {
            'search.index' => [
                'id' => 'search',
                'label' => 'Global Search',
                'route' => 'search.index',
                'icon' => 'search',
                'group' => 'utility',
                'description' => 'Search your private Notes, Markdown Docs, and Vault metadata.',
                'phase' => 'Phase 7',
            ],
            default => null,
        };
    }

    /** Shell destinations only; these do not expose module records or actions. */
    public static function items(): array
    {
        return array_map(fn (array $item): array => [
            ...$item,
            'url' => route($item['route'], absolute: false),
        ], [
            [
                'id' => 'overview',
                'label' => 'Overview',
                'route' => 'dashboard',
                'icon' => 'home',
                'group' => 'workspace',
                'description' => 'Your personal developer workspace.',
                'phase' => 'Phase 3',
            ],
            [
                'id' => 'notes',
                'label' => 'Notes',
                'route' => 'notes.index',
                'icon' => 'file-description',
                'group' => 'modules',
                'description' => 'Capture ideas, code snippets and everyday notes.',
                'phase' => 'Phase 4',
                'unavailable_title' => 'Notes are coming next',
                'unavailable_description' => 'This is the Notes section of your shared workspace. Creating, editing, searching and saving notes will be implemented in Phase 4, after approval.',
            ],
            [
                'id' => 'markdown',
                'label' => 'Markdown Docs',
                'route' => 'markdown.index',
                'icon' => 'markdown',
                'group' => 'modules',
                'description' => 'Write and preview your technical documentation.',
                'phase' => 'Phase 5',
                'unavailable_title' => 'Markdown editing is not available yet',
                'unavailable_description' => 'The Markdown section is in place. Document editing, preview, import and saving belong to the separately approved Phase 5.',
            ],
            [
                'id' => 'vault',
                'label' => 'Secure Vault',
                'route' => 'vault.index',
                'icon' => 'lock',
                'group' => 'modules',
                'description' => 'A separate home for your account credentials.',
                'phase' => 'Phase 6',
                'unavailable_title' => 'The vault is not available yet',
                'unavailable_description' => 'This page establishes the vault navigation only. Credential storage, encryption, unlocking and reveal/copy controls require the Phase 6 security design and implementation.',
            ],
            [
                'id' => 'settings',
                'label' => 'Settings',
                'route' => 'settings.index',
                'icon' => 'settings',
                'group' => 'settings',
                'description' => 'Appearance, preferences and workspace utilities.',
                'phase' => 'Phase 8',
                'unavailable_title' => 'Preferences are not available yet',
                'unavailable_description' => 'Settings, appearance preferences, backup and restoration will be implemented in a separately approved Phase 8 subphase. No preference or backup is changed here.',
            ],
        ]);
    }
}
