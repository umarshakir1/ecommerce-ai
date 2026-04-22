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

    // ── JSON array fields (split by comma) ────────────────────────────────────
    private const COMMA_JSON = [
        'cross_reference', 'cross_reference_syn',
        'related_skus', 'crosssell_skus', 'upsell_skus', 'additional_images',
    ];

    // ── JSON array fields (split by pipe and deduplicated) ────────────────────
    private const PIPE_JSON = ['supplier', 'supplier_v2'];

    // ── Decimal fields ────────────────────────────────────────────────────────
    private const DECIMAL_FIELDS = ['price', 'rrp_value', 'selling_surcharge'];

    // ── Float fields ──────────────────────────────────────────────────────────
    private const FLOAT_FIELDS = ['weight_kg', 'package_width', 'package_depth', 'package_length'];

    // ── Integer fields ────────────────────────────────────────────────────────
    private const INT_FIELDS = ['qty', 'allow_backorders', 'website_id'];

    // ── Boolean flags ─────────────────────────────────────────────────────────
    private const BOOL_FIELDS = ['is_deleted', 'is_updated', 'is_new', 'is_images_updated'];

    // ── Date fields ───────────────────────────────────────────────────────────
    private const DATE_FIELDS = ['new_from_date', 'new_to_date'];

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
            if ($this->parseBool($data['is_deleted'] ?? null)) {
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

        // ── String fields (direct copy) ───────────────────────────────────────
        $stringFields = [
            'sku', 'url_key', 'commodity_code', 'name', 'description',
            'short_description', 'notes', 'synonym', 'conind', 'brand',
            'category', 'categories', 'product_groups', 'store_model',
            'sub_range', 'color', 'size', 'image',
        ];

        foreach ($stringFields as $field) {
            $row[$field] = $this->nullableString($data[$field] ?? null);
        }

        // ── Decimal fields ────────────────────────────────────────────────────
        foreach (self::DECIMAL_FIELDS as $field) {
            $val = $this->nullableString($data[$field] ?? null);
            $row[$field] = ($val !== null && is_numeric($val)) ? (float) $val : null;
        }

        // ── Float fields ──────────────────────────────────────────────────────
        foreach (self::FLOAT_FIELDS as $field) {
            $val = $this->nullableString($data[$field] ?? null);
            $row[$field] = ($val !== null && is_numeric($val)) ? (float) $val : null;
        }

        // ── Integer fields ────────────────────────────────────────────────────
        foreach (self::INT_FIELDS as $field) {
            $val = $this->nullableString($data[$field] ?? null);
            $row[$field] = ($val !== null && is_numeric($val)) ? (int) $val : null;
        }

        // ── Boolean flags ─────────────────────────────────────────────────────
        foreach (self::BOOL_FIELDS as $field) {
            $row[$field] = $this->parseBool($data[$field] ?? null) ? 1 : 0;
        }

        // ── in_stock (derive from qty / explicit field) ───────────────────────
        if (isset($data['in_stock'])) {
            $row['in_stock'] = $this->parseBool($data['in_stock']) ? 1 : 1;
        } else {
            $qty = $row['qty'] ?? null;
            $row['in_stock'] = ($qty === null || $qty > 0) ? 1 : 0;
        }

        // ── Date fields ───────────────────────────────────────────────────────
        foreach (self::DATE_FIELDS as $field) {
            $val = $this->nullableString($data[$field] ?? null);
            $row[$field] = $this->parseDate($val);
        }

        // ── Comma-split JSON arrays ───────────────────────────────────────────
        foreach (self::COMMA_JSON as $field) {
            $row[$field] = $this->splitToJson($data[$field] ?? null, ',');
        }

        // ── Pipe-split JSON arrays (deduplicated) ─────────────────────────────
        foreach (self::PIPE_JSON as $field) {
            $row[$field] = $this->splitToJson($data[$field] ?? null, '|', dedupe: true);
        }

        // ── additional_attributes: "key=value,key=value" → JSON object ────────
        $row['additional_attributes'] = $this->parseAdditionalAttributes(
            $data['additional_attributes'] ?? null
        );

        // ── Defaults for chat-compat fields not in CSV ─────────────────────────
        $row['popularity']       = isset($data['popularity']) && is_numeric($data['popularity'])
            ? (int) $data['popularity']
            : 0;
        $row['embedding']        = null;
        $row['tags']             = null;
        $row['available_sizes']  = null;
        $row['available_colors'] = null;

        return $row;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function nullableString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return trim($value);
    }

    private function parseBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function splitToJson(?string $value, string $delimiter, bool $dedupe = false): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $parts = array_map('trim', explode($delimiter, $value));
        $parts = array_filter($parts, fn ($p) => $p !== '');
        $parts = array_values($parts);

        if ($dedupe) {
            $parts = array_values(array_unique($parts));
        }

        return empty($parts) ? null : json_encode($parts);
    }

    private function parseAdditionalAttributes(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $result = [];

        foreach (explode(',', $value) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            $eqPos = strpos($pair, '=');
            if ($eqPos === false) {
                continue;
            }

            $key   = trim(substr($pair, 0, $eqPos));
            $val   = trim(substr($pair, $eqPos + 1));

            if ($key !== '') {
                $result[$key] = $val;
            }
        }

        return empty($result) ? null : json_encode($result);
    }
}
