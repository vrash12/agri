<?php

namespace Tests\Feature;

use Illuminate\Cache\FileStore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;
use Tests\TestCase;

use function Laravel\Ai\agent;

class FrameworkUpgradeTest extends TestCase
{
    public function test_ai_sdk_can_prompt_with_a_fake_without_provider_access(): void
    {
        Http::preventStrayRequests();
        $agent = agent('Summarize synthetic totals only.');
        Ai::fakeAgent($agent::class, ['There are 12 sample farmers.'])->preventStrayPrompts();

        $response = $agent->prompt('Synthetic farmer count: 12.');

        $this->assertSame('There are 12 sample farmers.', $response->text);
        Ai::assertAgentWasPrompted($agent::class, 'Synthetic farmer count: 12.');
        Http::assertNothingSent();
    }

    public function test_ai_sdk_structured_output_uses_the_framework_schema_component(): void
    {
        Http::preventStrayRequests();
        $agent = agent('Read synthetic totals.', schema: fn (JsonSchema $schema) => [
            'farmer_count' => $schema->integer()->required(),
        ]);
        Ai::fakeAgent($agent::class, [['farmer_count' => 12]])->preventStrayPrompts();

        $response = $agent->prompt('Synthetic farmer count: 12.');

        $this->assertSame(12, $response['farmer_count']);
        Http::assertNothingSent();
    }

    public function test_redis_retains_the_laravel_9_cache_key_separator(): void
    {
        $prefix = config('cache.prefix');
        $expected = ! empty($prefix) ? $prefix.':' : '';

        // Resolving this store does not connect to Redis.
        $this->assertSame($expected, Cache::store('redis')->getStore()->getPrefix());
        $this->assertSame($expected, config('cache.stores.memcached.prefix'));
        $this->assertSame($expected, config('cache.stores.dynamodb.prefix'));
    }

    public function test_existing_file_cache_budget_counters_can_be_read_and_incremented(): void
    {
        $files = new Filesystem;
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'agrilgu-cache-upgrade-'.bin2hex(random_bytes(8));
        $store = new FileStore($files, $directory);
        $key = 'sentinel:requests:day:synthetic';
        $path = $store->path($key);

        try {
            $files->ensureDirectoryExists(dirname($path));
            // Laravel 9 file-cache payload: ten-digit expiry followed by PHP serialization.
            $files->put($path, (string) (time() + 3600).serialize(17));

            $this->assertSame(17, $store->get($key));
            $this->assertSame(18, $store->increment($key));
            $this->assertSame(18, $store->get($key));
        } finally {
            $files->deleteDirectory($directory);
        }
    }
}
