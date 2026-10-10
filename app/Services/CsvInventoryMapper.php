<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Figures out which CSV column belongs to which inventory field, even when
 * headers are missing, renamed, reordered, or don't match any known alias.
 *
 * Two passes:
 *  1. Header text match — exact alias match, then fuzzy match (typos/synonyms).
 *  2. Content sniffing — for any field still unmapped, scores every remaining
 *     column against a type detector (currency-like, integer-like, date-like,
 *     dosage-like, low-cardinality-like, longest-text-like) using the actual
 *     sample values, and assigns the best-scoring free column.
 *
 * Every mapped field carries a confidence (0-1) and a source, so the caller
 * can surface a preview and let a human confirm anything below a threshold
 * rather than trusting a guess blindly.
 */
class CsvInventoryMapper
{
    public const FIELDS = [
        'name', 'category', 'dosage', 'price',
        'stock_level', 'low_stock_threshold', 'expiry_date', 'description',
    ];

    /**
     * Fields that must resolve to a real column before an import can run.
     * "category" and the rest have sane defaults (see the controller), so
     * only the fields where a missing mapping would produce useless or
     * silently-wrong data (a blank name, a zeroed price/stock) are required.
     */
    public const REQUIRED_FIELDS = ['name', 'price', 'stock_level'];

    protected const ALIASES = [
        'name' => [
            'name', 'product', 'item', 'productname', 'product_name', 'itemname',
            'medication', 'medicationname', 'drugname', 'drug', 'title', 'itemdescription',
            'productitle', 'medicine', 'medicinename',
        ],
        'category' => [
            'category', 'type', 'class', 'group', 'productcategory', 'itemtype',
            'drugclass', 'department', 'section',
        ],
        'dosage' => [
            'dosage', 'strength', 'dose', 'concentration', 'potency',
        ],
        'price' => [
            'price', 'unitprice', 'cost', 'sellingprice', 'amount', 'unitcost',
            'rate', 'value', 'sellprice', 'retailprice', 'priceper unit', 'costprice',
        ],
        'stock_level' => [
            'stocklevel', 'stock', 'quantity', 'qty', 'availablestock', 'unitsinstock',
            'inventory', 'onhand', 'quantityavailable', 'balance', 'countinstock',
        ],
        'low_stock_threshold' => [
            'lowstockthreshold', 'threshold', 'alertlevel', 'reorderlevel', 'reorderpoint',
            'minstock', 'minimumstock', 'lowstockalert', 'safetystock',
        ],
        'expiry_date' => [
            'expirydate', 'expiry', 'expdate', 'expirationdate', 'expires', 'bestbefore',
            'useby',
        ],
        'description' => [
            'description', 'details', 'notes', 'remarks', 'info', 'summary',
        ],
    ];

    /**
     * @param  array<int, string>  $header  raw header row (already parsed with fgetcsv)
     * @param  array<int, array<int, string>>  $sampleRows  a handful of raw data rows for content sniffing
     * @return array{field: array<string, array{column: int|null, header: string|null, confidence: float, source: string}>, unmapped_columns: int[]}
     */
    public function map(array $header, array $sampleRows): array
    {
        $columnCount = max(count($header), $this->widestRow($sampleRows));
        $normalizedHeaders = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $normalizedHeaders[$i] = $this->normalize($header[$i] ?? '');
        }

        $result = [];
        $claimedColumns = [];

        // Pass 1a: exact alias match.
        foreach (self::FIELDS as $field) {
            $result[$field] = ['column' => null, 'header' => null, 'confidence' => 0.0, 'source' => 'none'];

            foreach ($normalizedHeaders as $index => $normalized) {
                if ($normalized === '' || in_array($index, $claimedColumns, true)) {
                    continue;
                }

                if (in_array($normalized, self::ALIASES[$field], true)) {
                    $result[$field] = [
                        'column' => $index,
                        'header' => $header[$index] ?? null,
                        'confidence' => 1.0,
                        'source' => 'exact_header',
                    ];
                    $claimedColumns[] = $index;
                    break;
                }
            }
        }

        // Pass 1b: fuzzy header match for fields still unmapped.
        foreach (self::FIELDS as $field) {
            if ($result[$field]['column'] !== null) {
                continue;
            }

            $best = null;
            $bestScore = 0.0;

            foreach ($normalizedHeaders as $index => $normalized) {
                if ($normalized === '' || in_array($index, $claimedColumns, true)) {
                    continue;
                }

                foreach (self::ALIASES[$field] as $alias) {
                    $score = $this->similarity($normalized, $alias);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = $index;
                    }
                }
            }

            if ($best !== null && $bestScore >= 0.72) {
                $result[$field] = [
                    'column' => $best,
                    'header' => $header[$best] ?? null,
                    'confidence' => round(0.55 + ($bestScore - 0.72) * 1.2, 2),
                    'source' => 'fuzzy_header',
                ];
                $claimedColumns[] = $best;
            }
        }

        // Pass 2: content sniffing for anything still unmapped, using free columns only.
        $freeColumns = array_values(array_diff(range(0, $columnCount - 1), $claimedColumns));

        if (! empty($freeColumns)) {
            $columnStats = [];
            foreach ($freeColumns as $index) {
                $columnStats[$index] = $this->scoreColumn($this->columnValues($sampleRows, $index));
            }

            // Assign remaining fields to the free column that scores highest for that
            // field's type, highest-confidence field first so strong signals claim
            // their column before weaker ones fall back to whatever is left.
            $stillUnmapped = array_filter(self::FIELDS, fn ($f) => $result[$f]['column'] === null);

            $candidates = [];
            foreach ($stillUnmapped as $field) {
                foreach ($freeColumns as $index) {
                    if (in_array($index, $claimedColumns, true)) {
                        continue;
                    }
                    $score = $columnStats[$index][$field] ?? 0.0;
                    if ($score > 0) {
                        $candidates[] = [$field, $index, $score];
                    }
                }
            }

            usort($candidates, fn ($a, $b) => $b[2] <=> $a[2]);

            foreach ($candidates as [$field, $index, $score]) {
                if ($result[$field]['column'] !== null || in_array($index, $claimedColumns, true)) {
                    continue;
                }

                $result[$field] = [
                    'column' => $index,
                    'header' => $header[$index] ?? null,
                    'confidence' => round(min($score, 0.85), 2), // content sniffing never beats a header match's ceiling
                    'source' => 'content_sniffed',
                ];
                $claimedColumns[] = $index;
            }
        }

        // Last resort: if "name" is still empty, claim the first unclaimed column —
        // every inventory row needs *something* to identify it by.
        if ($result['name']['column'] === null) {
            $fallback = null;
            foreach (range(0, $columnCount - 1) as $index) {
                if (! in_array($index, $claimedColumns, true)) {
                    $fallback = $index;
                    break;
                }
            }
            if ($fallback !== null) {
                $result['name'] = [
                    'column' => $fallback,
                    'header' => $header[$fallback] ?? null,
                    'confidence' => 0.3,
                    'source' => 'fallback_first_column',
                ];
                $claimedColumns[] = $fallback;
            }
        }

        $unmappedColumns = array_values(array_diff(range(0, $columnCount - 1), $claimedColumns));

        return ['fields' => $result, 'unmapped_columns' => $unmappedColumns];
    }

    public function requiredFieldsMissing(array $mappedFields): array
    {
        return array_values(array_filter(
            self::REQUIRED_FIELDS,
            fn ($field) => ($mappedFields[$field]['column'] ?? null) === null
        ));
    }

    protected function widestRow(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, count($row));
        }

        return $max;
    }

    protected function columnValues(array $rows, int $index): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$index] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    protected function normalize(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    /**
     * Similarity ratio in [0, 1] between two normalized strings, combining
     * character-level similarity with a substring-containment bonus so
     * "producttitle" still matches "title" strongly.
     */
    protected function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);
        $ratio = $percent / 100;

        if (str_contains($a, $b) || str_contains($b, $a)) {
            $ratio = max($ratio, 0.8);
        }

        return $ratio;
    }

    /**
     * @param  array<int, string>  $values  sample non-blank values from one column
     * @return array<string, float>  field => confidence this column is that field
     */
    protected function scoreColumn(array $values): array
    {
        $scores = array_fill_keys(self::FIELDS, 0.0);

        if (empty($values)) {
            return $scores;
        }

        $count = count($values);
        $numericCount = 0;
        $integerCount = 0;
        $decimalCount = 0;
        $dateCount = 0;
        $dosageCount = 0;
        $lengths = [];

        foreach ($values as $value) {
            $clean = str_replace([',', ' ', '₦', '$', '#'], '', $value);
            $lengths[] = mb_strlen($value);

            if (is_numeric($clean)) {
                $numericCount++;
                if (str_contains($clean, '.')) {
                    $decimalCount++;
                } else {
                    $integerCount++;
                }
            }

            if (preg_match('/^\d+(\.\d+)?\s?(mg|ml|mcg|g|iu|%|mg\/ml)$/i', $value)) {
                $dosageCount++;
            }

            if (! is_numeric($clean) && $this->looksLikeDate($value)) {
                $dateCount++;
            }
        }

        $numericRatio = $numericCount / $count;
        $dateRatio = $dateCount / $count;
        $dosageRatio = $dosageCount / $count;
        $avgLength = array_sum($lengths) / $count;
        $uniqueRatio = count(array_unique(array_map('mb_strtolower', $values))) / $count;

        // price: mostly numeric, with a meaningful share of decimals
        if ($numericRatio >= 0.9) {
            $scores['price'] = round(0.5 + 0.4 * ($decimalCount / max($count, 1)), 2);
        }

        // stock_level / low_stock_threshold: mostly whole numbers
        if ($numericRatio >= 0.9 && $decimalCount === 0) {
            $scores['stock_level'] = 0.65;
            $scores['low_stock_threshold'] = 0.55;
        }

        // expiry_date: parses as a date and isn't purely numeric
        if ($dateRatio >= 0.7) {
            $scores['expiry_date'] = round(0.5 + 0.4 * $dateRatio, 2);
        }

        // dosage: matches "500mg" / "10ml" style pattern
        if ($dosageRatio >= 0.5) {
            $scores['dosage'] = round(0.5 + 0.4 * $dosageRatio, 2);
        }

        // category: short, low-cardinality, non-numeric text repeated across rows
        if ($numericRatio < 0.2 && $uniqueRatio <= 0.6 && $avgLength <= 20) {
            $scores['category'] = round(0.75 - ($uniqueRatio * 0.3), 2);
        }

        // description: longest free text, high cardinality
        if ($numericRatio < 0.2 && $avgLength >= 18) {
            $scores['description'] = round(min(0.5 + ($avgLength / 200), 0.8), 2);
        }

        // name: non-numeric, fairly unique, short-to-medium length — the weakest
        // signal, deliberately capped low so real signals (price/date/etc.) win
        // the column first and name only gets it when nothing else fits.
        if ($numericRatio < 0.2 && $uniqueRatio >= 0.5) {
            $scores['name'] = 0.4;
        }

        return $scores;
    }

    protected function looksLikeDate(string $value): bool
    {
        if (! preg_match('/\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}/', $value)
            && ! preg_match('/[a-z]{3,9}\s+\d{1,2},?\s+\d{2,4}/i', $value)) {
            return false;
        }

        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
