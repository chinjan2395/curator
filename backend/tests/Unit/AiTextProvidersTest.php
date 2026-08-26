<?php

namespace Tests\Unit;

use App\Support\AiTextProviders;
use Tests\TestCase;

class AiTextProvidersTest extends TestCase
{
    public function test_models_returns_the_curated_list_for_a_provider(): void
    {
        $models = AiTextProviders::models(AiTextProviders::GROQ);

        $this->assertNotEmpty($models);
        $ids = array_column($models, 'id');
        $this->assertContains('openai/gpt-oss-120b', $ids);
        $this->assertNotContains('llama-3.3-70b-versatile', $ids);
    }

    public function test_models_is_empty_for_the_stub(): void
    {
        $this->assertSame([], AiTextProviders::models(AiTextProviders::STUB));
    }

    public function test_model_ids_mirrors_models(): void
    {
        $ids = AiTextProviders::modelIds(AiTextProviders::OLLAMA);

        $this->assertSame(['llama3.2'], $ids);
    }

    public function test_all_model_ids_combines_every_provider(): void
    {
        $all = AiTextProviders::allModelIds();

        $this->assertContains('openai/gpt-oss-120b', $all);
        $this->assertContains('llama3.2', $all);
    }

    public function test_selectable_ids_excludes_the_stub(): void
    {
        $ids = AiTextProviders::selectableIds();

        $this->assertContains(AiTextProviders::GROQ, $ids);
        $this->assertContains(AiTextProviders::OLLAMA, $ids);
        $this->assertNotContains(AiTextProviders::STUB, $ids);
    }

    public function test_ids_includes_the_stub(): void
    {
        $this->assertContains(AiTextProviders::STUB, AiTextProviders::ids());
    }

    public function test_exists_and_find(): void
    {
        $this->assertTrue(AiTextProviders::exists('groq'));
        $this->assertTrue(AiTextProviders::exists('GROQ'));
        $this->assertFalse(AiTextProviders::exists('midjourney'));
        $this->assertNull(AiTextProviders::find(null));
    }

    public function test_ollama_has_no_byok_and_no_platform_key(): void
    {
        $spec = AiTextProviders::find(AiTextProviders::OLLAMA);

        $this->assertFalse($spec['byok']);
        $this->assertNull(AiTextProviders::platformKey(AiTextProviders::OLLAMA));
        $this->assertTrue(AiTextProviders::platformConfigured(AiTextProviders::OLLAMA));
    }

    public function test_groq_platform_key_reads_from_config(): void
    {
        config(['services.ai.groq.api_key' => 'test-key']);

        $this->assertSame('test-key', AiTextProviders::platformKey(AiTextProviders::GROQ));
        $this->assertTrue(AiTextProviders::platformConfigured(AiTextProviders::GROQ));

        config(['services.ai.groq.api_key' => null]);

        $this->assertNull(AiTextProviders::platformKey(AiTextProviders::GROQ));
        $this->assertFalse(AiTextProviders::platformConfigured(AiTextProviders::GROQ));
    }

    public function test_stub_is_always_configured(): void
    {
        $this->assertTrue(AiTextProviders::platformConfigured(AiTextProviders::STUB));
    }

    public function test_grok_is_selectable_and_byok(): void
    {
        $ids = AiTextProviders::selectableIds();

        $this->assertContains(AiTextProviders::GROK, $ids);

        $spec = AiTextProviders::find(AiTextProviders::GROK);
        $this->assertTrue($spec['byok']);

        $modelIds = array_column(AiTextProviders::models(AiTextProviders::GROK), 'id');
        $this->assertContains('grok-4-0709', $modelIds);
    }

    public function test_grok_platform_key_reads_from_config(): void
    {
        config(['services.ai.grok.api_key' => 'test-key']);

        $this->assertSame('test-key', AiTextProviders::platformKey(AiTextProviders::GROK));
        $this->assertTrue(AiTextProviders::platformConfigured(AiTextProviders::GROK));

        config(['services.ai.grok.api_key' => null]);

        $this->assertNull(AiTextProviders::platformKey(AiTextProviders::GROK));
        $this->assertFalse(AiTextProviders::platformConfigured(AiTextProviders::GROK));
    }
}
