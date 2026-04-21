<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AIService;
use Illuminate\Console\Command;

/**
 * GenerateEmbeddings
 *
 * Artisan command: php artisan products:embed
 *
 * Generates and stores OpenRouter/OpenAI text embeddings for all products
 * that do not yet have an embedding (or all products with --force).
 *
 * Options:
 *   --force     Re-generate embeddings even if they already exist
 *   --id=X      Only generate for a single product by ID
 */
class GenerateEmbeddings extends Command
{
    protected $signature = 'products:embed
        {--force    : Re-generate even if embedding already exists}
        {--id=      : Only process a specific product ID}';

    protected $description = 'Generate and store text embeddings for products using OpenRouter API';

    public function __construct(private readonly AIService $aiService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = $this->option('force');
        $id    = $this->option('id');

        // Build query
        $query = Product::query();

        if ($id) {
            $query->where('id', (int) $id);
        } elseif (!$force) {
            // Only process products without embeddings
            $query->whereNull('embedding');
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->info('No products require embedding generation.');
            return Command::SUCCESS;
        }

        $total   = $products->count();
        $success = 0;
        $failed  = 0;

        $this->info("Generating embeddings for {$total} product(s)...");

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($products as $product) {
            // Build the text corpus: name + description + tags
            $text = $product->getEmbeddingText();

            // Call OpenRouter embedding API
            $embedding = $this->aiService->generateEmbedding($text);

            if ($embedding === null) {
                $this->newLine();
                $this->warn("  Failed to generate embedding for product [{$product->id}] {$product->name}");
                $failed++;
            } else {
                // Persist the embedding as a JSON string
                $product->update(['embedding' => json_encode($embedding)]);
                $success++;
            }

            $progressBar->advance();

            // Brief pause to respect API rate limits (500 ms)
            usleep(500_000);
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total processed', $total],
                ['Successful',      $success],
                ['Failed',          $failed],
            ]
        );

        if ($failed > 0) {
            $this->warn('Some embeddings failed. Run with --force to retry.');
            return Command::FAILURE;
        }

        $this->info('All embeddings generated successfully!');
        return Command::SUCCESS;
    }
}
