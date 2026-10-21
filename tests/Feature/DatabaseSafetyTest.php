<?php

namespace Tests\Feature;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

trait DestructiveSetupSentinel
{
    public bool $unsafeSetupReached = false;

    protected function setUpDestructiveSetupSentinel(): void
    {
        $this->unsafeSetupReached = true;
        throw new LogicException('The database safety guard allowed trait setup.');
    }
}

class DatabaseSafetyTest extends TestCase
{
    public static function unsafeConfigurations(): array
    {
        return [
            'application database name' => ['mysql', 'scrunote', null],
            'non-MySQL connection' => ['sqlite', 'scrunote_test', null],
            'URL can override the database name' => ['mysql', 'scrunote_test', 'mysql://127.0.0.1/scrunote'],
        ];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_database_settings_are_rejected_before_trait_setup(string $connection, string $database, ?string $url): void
    {
        config([
            'database.default' => $connection,
            "database.connections.{$connection}.database" => $database,
            "database.connections.{$connection}.url" => $url,
        ]);
        $probe = new class('test_sentinel') extends TestCase
        {
            use DestructiveSetupSentinel;

            public function test_sentinel(): void {}

            public function runSafetyGuard(): void
            {
                $this->setUpTraits();
            }
        };

        try {
            $probe->runSafetyGuard();
            $this->fail('The unsafe database configuration was not rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No migrations have been started', $exception->getMessage());
            $this->assertFalse($probe->unsafeSetupReached);
        }
    }
}
