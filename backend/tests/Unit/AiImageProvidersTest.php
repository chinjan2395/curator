<?php

namespace Tests\Unit;

use App\Support\AiImageProviders;
use Tests\TestCase;

class AiImageProvidersTest extends TestCase
{
    public function test_selectable_ids_excludes_the_stub(): void
    {
        $ids = AiImageProviders::selectableIds();

        $this->assertContains(AiImageProviders::OPENAI, $ids);
        $this->assertContains(AiImageProviders::FLUX, $ids);
        $this->assertContains(AiImageProviders::GEMINI, $ids);
        $this->assertContains(AiImageProviders::GROK, $ids);
        $this->assertNotContains(AiImageProviders::STUB, $ids);
    }

    public function test_grok_is_byok_and_does_not_support_a_reference_image(): void
    {
        $spec = AiImageProviders::find(AiImageProviders::GROK);

        $this->assertTrue($spec['byok']);
        $this->assertFalse(AiImageProviders::supportsReference(AiImageProviders::GROK));

        $modelIds = array_column(AiImageProviders::models(AiImageProviders::GROK), 'id');
        $this->assertContains('grok-2-image-1212', $modelIds);
    }

    public function test_grok_contributes_no_fixed_sizes(): void
    {
        $this->assertSame([], AiImageProviders::sizes(AiImageProviders::GROK));

        // The union of every provider's sizes must still tolerate an empty contribution.
        $this->assertNotEmpty(AiImageProviders::allSizes());
    }

    public function test_grok_platform_key_reads_from_config(): void
    {
        config(['services.ai.image.grok.api_key' => 'test-key']);

        $this->assertSame('test-key', AiImageProviders::platformKey(AiImageProviders::GROK));
        $this->assertTrue(AiImageProviders::platformConfigured(AiImageProviders::GROK));

        config(['services.ai.image.grok.api_key' => null]);

        $this->assertNull(AiImageProviders::platformKey(AiImageProviders::GROK));
        $this->assertFalse(AiImageProviders::platformConfigured(AiImageProviders::GROK));
    }
}
