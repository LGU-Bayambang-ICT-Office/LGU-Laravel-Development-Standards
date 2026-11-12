<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Commands;

use Illuminate\Console\Command;
use Lgu\GovSuite\Services\NpcCatalogService;

/**
 * National Privacy Commission (NPC) Statutory Issuance Synchronizer & Assessor.
 *
 * @author Ricky Bulalakaw
 */
class LguNpcSyncCommand extends Command
{
    protected $signature = 'lgu:npc-sync 
                            {--fetch-only : Update local catalog cache without interactive review prompts}
                            {--re-review : Prompt for all issuances, including previously reviewed ones}
                            {--url= : Custom URL to fetch NPC Advisories and Circulars}';

    protected $description = 'Synchronize National Privacy Commission (NPC) issuances and assess applicability to the application';

    public function handle(NpcCatalogService $service): int
    {
        $this->newLine();
        $this->info('🇵🇭  National Privacy Commission (NPC) Issuance Synchronizer');
        $this->line('    Targeting: Advisories, Circulars & Resolutions (https://privacy.gov.ph)');
        $this->newLine();

        $customUrl = $this->option('url') ? (string) $this->option('url') : null;
        $this->comment('🔄 Connecting to National Privacy Commission repository...');

        $result = $service->fetchCatalog($customUrl);
        if ($result['source'] === 'privacy.gov.ph') {
            $this->info('✅ ' . $result['message']);
        } else {
            $this->warn('⚠️  ' . $result['message']);
        }

        $issuances = $result['issuances'];
        $existingAssessment = $service->loadAssessment() ?? ['issuances' => []];
        $existingMap = [];

        foreach ($existingAssessment['issuances'] as $item) {
            if (isset($item['id'])) {
                $existingMap[$item['id']] = $item;
            }
        }

        if ($this->option('fetch-only')) {
            $merged = [];
            foreach ($issuances as $iss) {
                $id = $iss['id'];
                $status = $existingMap[$id]['status'] ?? 'UNREVIEWED';
                $merged[] = array_merge($iss, ['status' => $status]);
            }
            $service->saveAssessment($merged);
            $this->info('📦 Catalog updated (' . count($merged) . ' issuances cached). Run `php artisan lgu:npc-sync` without --fetch-only to review applicability.');

            return self::SUCCESS;
        }

        $isReReview = (bool) $this->option('re-review');
        $toReview = [];

        foreach ($issuances as $iss) {
            $id = $iss['id'];
            if ($isReReview || ! isset($existingMap[$id]['status']) || $existingMap[$id]['status'] === 'UNREVIEWED') {
                $toReview[] = $iss;
            }
        }

        $assessmentList = [];
        $applicableCount = 0;

        if (empty($toReview)) {
            $this->info('✨ All ' . count($issuances) . ' NPC issuances have already been assessed.');
            $this->line('   Use `php artisan lgu:npc-sync --re-review` if you want to re-evaluate existing determinations.');
            $assessmentList = $existingAssessment['issuances'];
        } else {
            $this->newLine();
            $this->comment('📋 Please review each NPC issuance below for relevance to your application:');
            $this->line('   (Answer Yes if your application collects, processes, shares, or retains personal data under this issuance)');
            $this->newLine();

            $updatedReview = [];

            foreach ($issuances as $iss) {
                $id = $iss['id'];

                if (! $isReReview && isset($existingMap[$id]['status']) && $existingMap[$id]['status'] !== 'UNREVIEWED') {
                    $updatedReview[] = $existingMap[$id];

                    continue;
                }

                $this->line('--------------------------------------------------------------------------------');
                $this->info("📑 [{$iss['number']}] {$iss['title']}");
                $this->line("   Category: {$iss['type']} | Year: {$iss['year']}");
                $this->line("   Summary:  {$iss['summary']}");
                $this->line("   Official: {$iss['url']}");
                $this->newLine();

                $choice = $this->choice(
                    'Is this issuance applicable to this application?',
                    ['Yes (Applicable)', 'No (Not Applicable)', 'Skip for now'],
                    0
                );

                $status = match ($choice) {
                    'Yes (Applicable)' => 'APPLICABLE',
                    'No (Not Applicable)' => 'NOT_APPLICABLE',
                    default => 'UNREVIEWED',
                };

                $reviewedItem = array_merge($iss, [
                    'status' => $status,
                    'assessed_at' => date('c'),
                ]);

                $updatedReview[] = $reviewedItem;
            }

            $service->saveAssessment($updatedReview);
            $assessmentList = $updatedReview;
        }

        $applicable = array_filter($assessmentList, fn ($i) => ($i['status'] ?? '') === 'APPLICABLE');
        $notApplicable = array_filter($assessmentList, fn ($i) => ($i['status'] ?? '') === 'NOT_APPLICABLE');
        $unreviewed = array_filter($assessmentList, fn ($i) => ($i['status'] ?? '') === 'UNREVIEWED');

        $this->newLine();
        $this->info('📊 NPC Applicability Inventory Summary:');
        $this->line('   • Applicable Issuances:     <info>' . count($applicable) . '</info>');
        $this->line('   • Non-Applicable Issuances: ' . count($notApplicable));
        $this->line('   • Unreviewed Issuances:     ' . (count($unreviewed) > 0 ? '<comment>' . count($unreviewed) . '</comment>' : '0'));
        $this->newLine();

        $this->comment('💾 Assessment persisted to: <info>storage/' . NpcCatalogService::ASSESSMENT_FILE . '</info>');
        $this->line('👉 Next step: Run `php artisan lgu:audit` to generate your statutory compliance audit report.');
        $this->newLine();

        return self::SUCCESS;
    }
}
