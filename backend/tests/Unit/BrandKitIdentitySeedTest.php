<?php

namespace Tests\Unit;

use App\Support\BrandKitExpandedSettings;
use App\Support\PublishSettings;
use Tests\TestCase;

/**
 * Brand identity and Embed appearance describe the same brand, so a new Master
 * kit seeds one from the other. The mapping exists twice — here and in the
 * editor's "Apply to embed appearance" button — so it is asserted, not trusted.
 */
class BrandKitIdentitySeedTest extends TestCase
{
    public function test_identity_palette_seeds_every_mapped_embed_colour(): void
    {
        $seeded = BrandKitExpandedSettings::feedColorsFromIdentity([
            'primary' => '#ff0000',
            'secondary' => '#00ff00',
            'accent' => '#0000ff',
            'background' => '#111111',
            'text' => '#222222',
        ]);

        $this->assertSame('#222222', $seeded['post_text']);
        $this->assertSame('#111111', $seeded['post_bg']['color']);
        $this->assertTrue($seeded['post_bg']['enabled'], 'the background colour is invisible unless it is switched on');
        $this->assertSame('#ff0000', $seeded['post_link']);
        $this->assertSame('#0000ff', $seeded['post_button']);
        $this->assertSame('#00ff00', $seeded['post_icon']);
        $this->assertSame('#00ff00', $seeded['post_date']);
        $this->assertSame('#00ff00', $seeded['header_text']);
        $this->assertSame('#222222', $seeded['footer_text']);

        // Borders deliberately stay neutral rather than being derived.
        $this->assertSame(
            PublishSettings::defaults()['colors']['post_border'],
            $seeded['post_border'],
        );
    }

    public function test_seed_survives_normalization_and_targets_real_keys(): void
    {
        $identity = BrandKitExpandedSettings::defaults()['colors'];
        $seeded = BrandKitExpandedSettings::feedColorsFromIdentity($identity);

        $normalized = BrandKitExpandedSettings::validateAndNormalize(['feed_colors' => $seeded]);

        foreach ($seeded as $key => $value) {
            $this->assertArrayHasKey(
                $key,
                $normalized['feed_colors'],
                "seeded '{$key}' is not a real feed_colors key"
            );
            $this->assertSame($value, $normalized['feed_colors'][$key]);
        }
    }

    public function test_garbage_identity_colours_fall_back_to_defaults(): void
    {
        $seeded = BrandKitExpandedSettings::feedColorsFromIdentity(['text' => 'not-a-colour']);
        $identityDefaults = BrandKitExpandedSettings::defaults()['colors'];

        $this->assertSame($identityDefaults['text'], $seeded['post_text']);
        $this->assertSame($identityDefaults['background'], $seeded['post_bg']['color']);
    }

    /**
     * The frontend re-sync button carries its own copy of the mapping. A drift
     * between the two would make "Apply to embed appearance" produce a
     * different result from creating the kit.
     */
    public function test_frontend_mapping_matches_the_backend(): void
    {
        $source = null;
        foreach ([
            '/frontend/src/constants/brandIdentityColors.js',
            base_path('../frontend/src/constants/brandIdentityColors.js'),
        ] as $candidate) {
            if (is_readable($candidate)) {
                $source = (string) file_get_contents($candidate);
                break;
            }
        }

        if ($source === null) {
            $this->markTestSkipped('frontend sources not available');
        }

        preg_match('/IDENTITY_TO_EMBED_COLOR = \{(.*?)\};/s', $source, $m);
        $this->assertNotEmpty($m, 'could not parse IDENTITY_TO_EMBED_COLOR');

        preg_match_all("/'?([a-z_.]+)'?:\s*'([a-z_]+)'/", $m[1], $pairs, PREG_SET_ORDER);
        $frontend = [];
        foreach ($pairs as $pair) {
            $frontend[$pair[1]] = $pair[2];
        }

        $this->assertSame(BrandKitExpandedSettings::IDENTITY_COLOR_MAP, $frontend);
    }
}
