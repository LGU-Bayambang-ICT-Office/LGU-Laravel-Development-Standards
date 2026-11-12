<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * National Privacy Commission (NPC) Issuance Catalog & Applicability Service.
 *
 * Fetches circulars, advisories, and resolutions from privacy.gov.ph with
 * automatic fallback to a bundled baseline seed catalog. Manages the developer's
 * local applicability inventory stored at storage/app/lgu/npc-assessment.json.
 *
 * @author Ricky Bulalakaw
 */
class NpcCatalogService
{
    public const DEFAULT_NPC_URL = 'https://privacy.gov.ph/advisories-circulars/';

    public const ASSESSMENT_FILE = 'app/lgu/npc-assessment.json';

    /**
     * Get the absolute path to the local NPC assessment file.
     */
    public function getAssessmentPath(): string
    {
        return storage_path(self::ASSESSMENT_FILE);
    }

    /**
     * Load the bundled offline seed catalog.
     */
    public function getSeedCatalog(): array
    {
        $path = __DIR__ . '/../../resources/data/npc-catalog-seed.json';
        if (File::exists($path)) {
            $data = json_decode(File::get($path), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Fetch issuances from privacy.gov.ph or fall back to the seed catalog.
     *
     * @return array{success: bool, source: string, issuances: array, message: string}
     */
    public function fetchCatalog(?string $customUrl = null): array
    {
        $url = $customUrl ?: self::DEFAULT_NPC_URL;
        $seedCatalog = $this->getSeedCatalog();

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) LguGovSuite/1.2.0 (Philippine Local Government Standards)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url);

            if ($response->successful()) {
                $html = $response->body();
                $parsed = $this->parseHtmlIssuances($html);

                if (! empty($parsed)) {
                    $merged = $this->mergeWithSeedCatalog($parsed, $seedCatalog);

                    return [
                        'success' => true,
                        'source' => 'privacy.gov.ph',
                        'issuances' => $merged,
                        'message' => 'Successfully synchronized ' . count($merged) . ' issuances from privacy.gov.ph.',
                    ];
                }
            }

            return [
                'success' => true,
                'source' => 'seed_fallback',
                'issuances' => $seedCatalog,
                'message' => 'Online portal did not return parseable circulars. Using bundled statutory seed catalog (' . count($seedCatalog) . ' issuances).',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'source' => 'seed_fallback',
                'issuances' => $seedCatalog,
                'message' => 'Could not reach privacy.gov.ph (' . $e->getMessage() . '). Using bundled statutory seed catalog (' . count($seedCatalog) . ' issuances).',
            ];
        }
    }

    /**
     * Load existing assessment if present.
     */
    public function loadAssessment(): ?array
    {
        $path = $this->getAssessmentPath();
        if (File::exists($path)) {
            $data = json_decode(File::get($path), true);
            if (is_array($data) && isset($data['issuances'])) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Save assessment to storage/app/lgu/npc-assessment.json.
     */
    public function saveAssessment(array $assessment): void
    {
        $path = $this->getAssessmentPath();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $payload = [
            'updated_at' => date('c'),
            'total_issuances' => count($assessment),
            'applicable_count' => count(array_filter($assessment, fn ($i) => ($i['status'] ?? '') === 'APPLICABLE')),
            'issuances' => $assessment,
        ];

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Filter only applicable issuances from assessment.
     */
    public function getApplicableIssuances(): array
    {
        $assessment = $this->loadAssessment();
        if (! $assessment || empty($assessment['issuances'])) {
            return [];
        }

        return array_values(array_filter(
            $assessment['issuances'],
            fn ($i) => ($i['status'] ?? '') === 'APPLICABLE'
        ));
    }

    /**
     * Extract issuances from raw HTML.
     */
    protected function parseHtmlIssuances(string $html): array
    {
        $issuances = [];

        // Match PDF links or links containing circular / advisory on privacy.gov.ph
        $pattern = '/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is';
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            $counter = 1;
            foreach ($matches as $match) {
                $link = trim($match[1]);
                $titleText = trim(preg_replace('/\s+/', ' ', strip_tags($match[2])));

                if (strlen($titleText) < 5 || stripos($titleText, 'next') !== false || stripos($titleText, 'previous') !== false) {
                    continue;
                }

                $isPdf = stripos($link, '.pdf') !== false;
                $hasKeyword = stripos($link, 'circular') !== false || stripos($link, 'advisory') !== false || stripos($titleText, 'circular') !== false || stripos($titleText, 'advisory') !== false;

                if (! $isPdf && ! $hasKeyword) {
                    continue;
                }

                if (! str_starts_with($link, 'http')) {
                    $link = 'https://privacy.gov.ph' . (str_starts_with($link, '/') ? '' : '/') . $link;
                }

                $type = 'Circular';
                if (stripos($link, 'advisory') !== false || stripos($titleText, 'Advisory') !== false) {
                    $type = 'Advisory';
                } elseif (stripos($link, 'resolution') !== false || stripos($titleText, 'Resolution') !== false) {
                    $type = 'Resolution';
                }

                $issuances[] = [
                    'id' => 'NPC-EXT-' . sprintf('%03d', $counter++),
                    'type' => $type,
                    'number' => $titleText,
                    'year' => (int) date('Y'),
                    'title' => $titleText,
                    'summary' => 'Official issuance published on privacy.gov.ph: ' . $titleText,
                    'url' => $link,
                ];
            }
        }

        return $issuances;
    }

    /**
     * Merge scraped items with seed catalog preventing duplication.
     */
    protected function mergeWithSeedCatalog(array $scraped, array $seed): array
    {
        $merged = $seed;
        $existingUrls = array_column($seed, 'url');

        foreach ($scraped as $item) {
            if (! in_array($item['url'], $existingUrls, true)) {
                $merged[] = $item;
                $existingUrls[] = $item['url'];
            }
        }

        return $merged;
    }
}
