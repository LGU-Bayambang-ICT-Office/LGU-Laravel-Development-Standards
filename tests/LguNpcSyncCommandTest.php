<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Tests;

use Illuminate\Support\Facades\File;

class LguNpcSyncCommandTest extends TestCase
{
    public function test_npc_sync_command_supports_fetch_only(): void
    {
        $this->artisan('lgu:npc-sync', ['--fetch-only' => true])
            ->expectsOutputToContain('National Privacy Commission (NPC) Issuance Synchronizer')
            ->expectsOutputToContain('Catalog updated')
            ->assertSuccessful();

        $assessmentPath = storage_path('app/lgu/npc-assessment.json');
        $this->assertTrue(File::exists($assessmentPath));

        $data = json_decode(File::get($assessmentPath), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data['issuances']);

        File::delete($assessmentPath);
    }
}
