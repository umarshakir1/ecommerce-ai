<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoProductSeeder extends Seeder
{
    private const CSV_PATH    = __DIR__ . '/data/eCommerceMaster.csv';
    private const CHUNK_SIZE  = 500;
    private const MAX_ROWS    = 10000;
    private const CLIENT_ID   = '00000000-0000-0000-0000-000000000001';

    // ── Core columns that map directly from CSV ───────────────────────────────
    private const CORE_STRING = ['sku', 'url_key', 'name', 'description', 'short_description'];
    private const CORE_DECIMAL = ['price', 'rrp_value'];
    private const CORE_DATE   = ['new_from_date', 'new_to_date'];

    public function run(): void
    {
        // ── Ensure demo user exists (always — even without CSV) ───────────
        $apiKey = env('DEMO_API_KEY', 'ShopAIDemoKey2026xK9mN2pQr8vXjL5wA3hE9tYcF6bDsG4iZ0uV1nMoR7qTkP2');

        $user = User::firstOrCreate(
            ['email' => 'demo@shopai.test'],
            [
                'name'      => 'ShopAI Demo',
                'password'  => Hash::make('demo-password-not-for-login'),
                'client_id' => self::CLIENT_ID,
                'api_key'   => $apiKey,
                'is_active' => true,
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $user->update(['api_key' => $apiKey, 'client_id' => self::CLIENT_ID, 'is_active' => true]);
        }

        $this->command->info("Demo user ready  →  API key: {$apiKey}");

        if (! file_exists(self::CSV_PATH)) {
            $this->command->warn('CSV not found: ' . self::CSV_PATH);
            $this->command->warn('Chat is ready. Place eCommerceMaster.csv in database/seeders/data/ and re-run to import products.');
            return;
        }

        // ── Wipe existing demo products ───────────────────────────────────
        DB::table('products')->where('client_id', self::CLIENT_ID)->delete();
        $this->command->info('Cleared existing demo products.');

        // ── Open CSV ──────────────────────────────────────────────────────────
        $handle = fopen(self::CSV_PATH, 'r');
        if ($handle === false) {
            $this->command->error('Cannot open CSV file.');
            return;
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            $this->command->error('CSV appears to be empty.');
            fclose($handle);
            return;
        }

        // Normalise headers: trim whitespace + lowercase
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        $this->command->info('CSV headers found: ' . count($headers));
        $this->command->info('Starting import — max ' . self::MAX_ROWS . ' rows, chunk size ' . self::CHUNK_SIZE . ' ...');

        $chunk       = [];
        $imported    = 0;
        $skipped     = 0;
        $rowNumber   = 0;
        $now         = now()->toDateTimeString();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            }

            $data = array_combine($headers, $row);

            // ── Skip deleted rows ─────────────────────────────────────────────
            if ($this->bool($data['is_deleted'] ?? null)) {
                $skipped++;
                continue;
            }

            if ($imported >= self::MAX_ROWS) {
                break;
            }

            $rowNumber++;

            $chunk[] = $this->buildRow($data, $now);

            // ── Insert chunk ──────────────────────────────────────────────────
            if (count($chunk) >= self::CHUNK_SIZE) {
                DB::table('products')->insert($chunk);
                $imported += count($chunk);
                $chunk     = [];

                if ($imported % self::CHUNK_SIZE === 0) {
                    $this->command->info("  → Inserted {$imported} rows...");
                }
            }
        }

        // ── Insert remaining rows ─────────────────────────────────────────────
        if (! empty($chunk)) {
            DB::table('products')->insert($chunk);
            $imported += count($chunk);
        }

        fclose($handle);

        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->command->info("  Imported : {$imported} products");
        $this->command->info("  Skipped  : {$skipped} deleted rows");
        $this->command->info("  Client ID: " . self::CLIENT_ID);
        $this->command->info("  API Key  : {$apiKey}");
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    // ── Build a single DB row from CSV data ───────────────────────────────────

    private function buildRow(array $data, string $now): array
    {
        $row = [
            'client_id'  => self::CLIENT_ID,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // ── Core string fields ────────────────────────────────────────────────
        foreach (self::CORE_STRING as $field) {
            $row[$field] = $this->str($data[$field] ?? null);
        }

        // ── Core decimal fields ───────────────────────────────────────────────
        foreach (self::CORE_DECIMAL as $field) {
            $v = $this->str($data[$field] ?? null);
            $row[$field] = ($v !== null && is_numeric($v)) ? (float) $v : null;
        }

        // ── Core integer: qty ─────────────────────────────────────────────────
        $v = $this->str($data['qty'] ?? null);
        $row['qty'] = ($v !== null && is_numeric($v)) ? (int) $v : 0;

        // ── Core float: weight_kg ─────────────────────────────────────────────
        $v = $this->str($data['weight_kg'] ?? null);
        $row['weight_kg'] = ($v !== null && is_numeric($v)) ? (float) $v : null;

        // ── Core media ────────────────────────────────────────────────────────
        $row['base_image']       = $this->str($data['base_image'] ?? $data['image'] ?? null);
        $row['thumbnail_image']  = $this->str($data['thumbnail_image'] ?? $data['small_image'] ?? null);

        // ── Core boolean flags ────────────────────────────────────────────────
        $row['is_deleted'] = $this->bool($data['is_deleted'] ?? null) ? 1 : 0;
        $row['is_new']     = $this->bool($data['is_new'] ?? null) ? 1 : 0;

        // ── Core dates ────────────────────────────────────────────────────────
        foreach (self::CORE_DATE as $field) {
            $row[$field] = $this->parseDate($this->str($data[$field] ?? null));
        }

        // ── cross_reference: merge cross_reference + cross_reference_syn ──────
        $crMerged = array_values(array_unique(array_filter(array_merge(
            $this->splitArr($data['cross_reference']     ?? null, ','),
            $this->splitArr($data['cross_reference_syn'] ?? null, ',')
        ))));
        $row['cross_reference'] = empty($crMerged) ? null : json_encode($crMerged);

        // ── suppliers: merge supplier + supplier_v2 (pipe-delimited) ──────────
        $supMerged = array_values(array_unique(array_filter(array_merge(
            $this->splitArr($data['supplier']    ?? null, '|'),
            $this->splitArr($data['supplier_v2'] ?? null, '|')
        ))));
        $row['suppliers'] = empty($supMerged) ? null : json_encode($supMerged);

        // ── categories: categories (comma) + product_groups + store_model + sub_range
        $catParts = array_merge(
            $this->splitArr($data['categories'] ?? null, ','),
            array_filter([
                $this->str($data['product_groups'] ?? null),
                $this->str($data['store_model']    ?? null),
                $this->str($data['sub_range']      ?? null),
            ])
        );
        $catMerged = array_values(array_unique(array_filter($catParts)));
        $row['categories'] = empty($catMerged) ? null : json_encode($catMerged);

        // ── attributes: everything platform/product-specific ──────────────────
        $attrs = [];

        // Scalar attribute fields
        $scalarAttrs = [
            'brand', 'color', 'size', 'category', 'commodity_code',
            'attribute_set_code', 'product_groups', 'store_model', 'sub_range',
            'conind', 'synonym', 'notes', 'superto', 'dd', 'brake_chamber_filter',
        ];
        foreach ($scalarAttrs as $field) {
            $v = $this->str($data[$field] ?? null);
            if ($v !== null) {
                $attrs[$field] = $v;
            }
        }

        // Numeric attribute fields
        $numericAttrs = [
            'selling_surcharge' => 'float',
            'package_width'     => 'float',
            'package_depth'     => 'float',
            'package_length'    => 'float',
            'allow_backorders'  => 'int',
            'website_id'        => 'int',
        ];
        foreach ($numericAttrs as $field => $type) {
            $v = $this->str($data[$field] ?? null);
            if ($v !== null && is_numeric($v)) {
                $attrs[$field] = $type === 'int' ? (int) $v : (float) $v;
            }
        }

        // Boolean attribute fields
        foreach (['is_updated', 'is_images_updated'] as $field) {
            if (isset($data[$field]) && $this->str($data[$field]) !== null) {
                $attrs[$field] = $this->bool($data[$field]) ? 1 : 0;
            }
        }

        // Array attribute fields (comma-split)
        foreach (['related_skus', 'crosssell_skus', 'upsell_skus', 'additional_images'] as $field) {
            $arr = $this->splitArr($data[$field] ?? null, ',');
            if (! empty($arr)) {
                $attrs[$field] = $arr;
            }
        }

        // Merge parsed additional_attributes key=value pairs into attrs
        $parsed = $this->parseAdditionalAttributes($data['additional_attributes'] ?? null);
        if (! empty($parsed)) {
            $attrs = array_merge($parsed, $attrs); // CSV attrs override parsed so explicit wins
        }

        $row['attributes'] = empty($attrs) ? null : json_encode($attrs);

        // ── AI / RAG defaults ─────────────────────────────────────────────────
        $v = $data['popularity'] ?? null;
        $row['popularity'] = ($v !== null && is_numeric($v)) ? (int) $v : 0;
        $row['embedding']  = null;

        return $row;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function str(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return trim($value);
    }

    private function bool(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function splitArr(?string $value, string $delimiter): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode($delimiter, $value))));
    }

    private function parseAdditionalAttributes(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }
        $result = [];
        foreach (explode(',', $value) as $pair) {
            $pair  = trim($pair);
            $eqPos = strpos($pair, '=');
            if ($eqPos === false || $eqPos === 0) {
                continue;
            }
            $key = trim(substr($pair, 0, $eqPos));
            $val = trim(substr($pair, $eqPos + 1));
            if ($key !== '') {
                $result[$key] = $val !== '' ? $val : null;
            }
        }
        return $result;
    }
}
