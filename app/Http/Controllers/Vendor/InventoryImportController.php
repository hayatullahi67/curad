<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Medication;
use App\Models\Pharmacy;
use App\Services\CsvInventoryMapper;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoryImportController extends Controller
{
    /**
     * How long an uploaded-but-unconfirmed CSV is kept around before it's
     * treated as abandoned and swept up on the next preview call.
     */
    protected const STAGING_TTL_MINUTES = 60;

    protected const PREVIEW_ROW_LIMIT = 5;

    protected const SNIFF_ROW_LIMIT = 25;

    public function __construct(protected CsvInventoryMapper $mapper)
    {
    }

    /**
     * Step 1: accept the raw CSV, auto-detect the best column-to-field
     * mapping (exact/fuzzy header match, falling back to sniffing the actual
     * values), and hand back a preview + the mapping for the vendor to
     * confirm or correct before anything touches the database.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $user = Auth::user();
        $this->cleanupStaleUploads($user->id);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json(['error' => 'Could not read the uploaded file.'], 422);
        }

        $header = fgetcsv($handle);
        if (empty($header)) {
            fclose($handle);

            return response()->json(['error' => 'The file appears to be empty.'], 422);
        }

        $sampleRows = [];
        while (count($sampleRows) < self::SNIFF_ROW_LIMIT && ($row = fgetcsv($handle)) !== false) {
            if (! $this->isBlankRow($row)) {
                $sampleRows[] = $row;
            }
        }
        fclose($handle);

        if (empty($sampleRows)) {
            return response()->json(['error' => 'No data rows found below the header.'], 422);
        }

        $mapping = $this->mapper->map($header, $sampleRows);

        $token = (string) Str::uuid();
        $stagingPath = $this->stagingPath($user->id, $token);
        Storage::disk('local')->putFileAs(
            dirname($stagingPath),
            $file,
            basename($stagingPath)
        );

        $columns = [];
        $columnCount = max(count($header), ...array_map('count', $sampleRows));
        for ($i = 0; $i < $columnCount; $i++) {
            $label = trim((string) ($header[$i] ?? ''));
            $columns[] = ['index' => $i, 'label' => $label !== '' ? $label : 'Column ' . ($i + 1)];
        }

        $previewRows = array_slice($sampleRows, 0, self::PREVIEW_ROW_LIMIT);

        return response()->json([
            'token' => $token,
            'columns' => $columns,
            'fields' => CsvInventoryMapper::FIELDS,
            'mapping' => $mapping['fields'],
            'missing_required' => $this->mapper->requiredFieldsMissing($mapping['fields']),
            'preview_rows' => $previewRows,
            'row_count' => count($sampleRows),
        ]);
    }

    /**
     * Step 2: re-open the staged file and run the actual import using the
     * (possibly hand-corrected) column mapping the vendor confirmed.
     */
    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|uuid',
            'mapping' => 'required|array',
            'mapping.*' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();
        $pharmacy = Pharmacy::current();

        if (! $pharmacy) {
            return response()->json(['error' => 'Pharmacy record not found.'], 422);
        }

        $mapping = array_intersect_key($validated['mapping'], array_flip(CsvInventoryMapper::FIELDS));
        $mapping += array_fill_keys(CsvInventoryMapper::FIELDS, null);
        $missing = array_values(array_diff(
            CsvInventoryMapper::REQUIRED_FIELDS,
            array_keys(array_filter($mapping, fn ($v) => $v !== null))
        ));

        if (! empty($missing)) {
            return response()->json(['error' => 'Missing required column mapping for: ' . implode(', ', $missing)], 422);
        }

        $stagingPath = $this->stagingPath($user->id, $validated['token']);
        if (! Storage::disk('local')->exists($stagingPath)) {
            return response()->json(['error' => 'This upload has expired. Please choose the file again.'], 422);
        }

        $handle = fopen(Storage::disk('local')->path($stagingPath), 'r');
        fgetcsv($handle); // header row already accounted for in the mapping

        $importedCount = 0;
        $skippedRows = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isBlankRow($row)) {
                    $skippedRows++;
                    continue;
                }

                $data = $this->extractRow($row, $mapping);
                $name = trim((string) ($data['name'] ?? ''));

                if ($name === '') {
                    $skippedRows++;
                    continue;
                }

                $medicationData = [
                    'pharmacy_id' => $pharmacy->id,
                    'name' => $name,
                    'category' => $this->cleanValue($data['category'] ?? 'General') ?: 'General',
                    'dosage' => $this->cleanValue($data['dosage'] ?? null),
                    'price' => $this->parsePrice($data['price'] ?? null),
                    'stock_level' => $this->parseInteger($data['stock_level'] ?? null, 0),
                    'low_stock_threshold' => $this->parseInteger($data['low_stock_threshold'] ?? null, 10),
                    'expiry_date' => $this->parseDate($data['expiry_date'] ?? null),
                    'description' => $this->cleanValue($data['description'] ?? null),
                    // An import carries a selling price, so the product is for sale,
                    // including one that so far only existed as received stock.
                    'is_listed' => true,
                ];

                $search = ['pharmacy_id' => $pharmacy->id, 'name' => $name];
                if (! empty($medicationData['dosage'])) {
                    $search['dosage'] = $medicationData['dosage'];
                }

                $medication = Medication::firstOrNew($search);
                $stockBefore = $medication->exists ? (int) $medication->stock_level : 0;
                $medication->fill($medicationData);
                $medication->save();
                $importedCount++;

                // Keep Stock History complete: an import sets stock too.
                $change = (int) $medication->stock_level - $stockBefore;
                if ($change !== 0) {
                    InventoryMovement::create([
                        'pharmacy_id' => $pharmacy->id,
                        'medication_id' => $medication->id,
                        'user_id' => $user->id,
                        'type' => 'import',
                        'quantity_change' => $change,
                        'quantity_after' => $medication->stock_level,
                        'reason' => 'Stock set by CSV import',
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);

            return response()->json(['error' => 'Critical Error during import: ' . $e->getMessage()], 422);
        }

        fclose($handle);
        Storage::disk('local')->delete($stagingPath);

        if ($importedCount > 0) {
            Audit::log('product.imported', "Imported {$importedCount} product(s) from a CSV file", $pharmacy->id);
        }

        $message = $importedCount > 0
            ? "Import successful! {$importedCount} item(s) registered."
            : 'No inventory items were imported.';

        if ($skippedRows > 0) {
            $message .= " {$skippedRows} blank row(s) were skipped.";
        }

        return response()->json(['success' => $message, 'imported' => $importedCount, 'skipped' => $skippedRows]);
    }

    /**
     * @param  array<string, int|null>  $mapping  field => column index
     */
    protected function extractRow(array $row, array $mapping): array
    {
        $data = [];

        foreach ($mapping as $field => $index) {
            $data[$field] = $index !== null ? ($row[$index] ?? null) : null;
        }

        return $data;
    }

    protected function stagingPath(int $userId, string $token): string
    {
        return "private/inventory-imports/{$userId}/{$token}.csv";
    }

    protected function cleanupStaleUploads(int $userId): void
    {
        $dir = "private/inventory-imports/{$userId}";
        $disk = Storage::disk('local');
        $cutoff = now()->subMinutes(self::STAGING_TTL_MINUTES)->getTimestamp();

        foreach ($disk->files($dir) as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        }
    }

    protected function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    protected function cleanValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function parsePrice($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) str_replace([',', ' ', '₦', '$'], '', (string) $value);
    }

    protected function parseInteger($value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return max(0, (int) $value);
    }

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
