<?php

namespace Tests\Unit;

use App\Support\BrandKitExpandedSettings;
use App\Support\PublishSettings;
use Tests\TestCase;

/**
 * The frontend capability matrix and the embed CSS-var registry describe the
 * backend's settings tree and the embed runtime. Nothing links them at compile
 * time, so these tests read the JS from disk and assert the three stay in step.
 *
 * The failure this catches is the one that produced the whole "this setting
 * does nothing" class of bug: a key added on one side and forgotten on another.
 */
class EmbedCapabilityParityTest extends TestCase
{
    /**
     * The backend container only mounts `backend/`, so docker-compose also
     * mounts the frontend read-only at `/frontend`. Outside the container the
     * sibling checkout is used.
     */
    private function readFrontend(string $relative): string
    {
        foreach (['/frontend/'.$relative, base_path('../frontend/'.$relative)] as $candidate) {
            if (is_readable($candidate)) {
                return (string) file_get_contents($candidate);
            }
        }

        $this->markTestSkipped("frontend sources not available ({$relative})");
    }

    /** @return list<string> capability dot-paths declared by the matrix */
    private function capabilityPaths(): array
    {
        $source = $this->readFrontend('src/constants/embedCapabilities.js');
        $body = substr(
            $source,
            (int) strpos($source, 'export const EMBED_CAPABILITIES'),
            (int) strpos($source, 'export const EMBED_TABS') - (int) strpos($source, 'export const EMBED_CAPABILITIES')
        );

        preg_match_all("/^\s+'?([a-z_]+(?:\.[a-z_]+)*)'?:\s*\{\s*tab:/m", $body, $m);
        $paths = array_values(array_unique($m[1]));
        $this->assertNotEmpty($paths, 'could not parse EMBED_CAPABILITIES');

        return $paths;
    }

    private function hasPath(array $tree, string $path): bool
    {
        $cursor = $tree;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }

        return true;
    }

    public function test_every_capability_path_exists_in_publish_settings(): void
    {
        $defaults = PublishSettings::defaults();

        foreach ($this->capabilityPaths() as $path) {
            $this->assertTrue(
                $this->hasPath($defaults, $path),
                "EMBED_CAPABILITIES declares '{$path}' but PublishSettings::defaults() has no such key."
            );
        }
    }

    public function test_every_capability_path_exists_in_brand_kit_settings(): void
    {
        $defaults = BrandKitExpandedSettings::defaults();

        foreach ($this->capabilityPaths() as $path) {
            // Brand kits store the widget palette under `feed_colors`.
            $kitPath = str_starts_with($path, 'colors.') ? 'feed_'.$path : $path;

            $this->assertTrue(
                $this->hasPath($defaults, $kitPath),
                "EMBED_CAPABILITIES declares '{$path}' but BrandKitExpandedSettings::defaults() has no '{$kitPath}'."
            );
        }
    }

    public function test_capability_layouts_are_all_real_feed_styles(): void
    {
        $source = $this->readFrontend('src/constants/embedCapabilities.js');
        preg_match('/export const EMBED_LAYOUTS = \[(.*?)\];/s', $source, $m);
        $this->assertNotEmpty($m, 'could not parse EMBED_LAYOUTS');

        preg_match_all("/'([a-z_]+)'/", $m[1], $found);
        $this->assertSame(PublishSettings::STYLES, $found[1]);
    }

    /**
     * Every CSS custom property the runtime writes has to be read by the
     * stylesheet, and vice versa — a var set but never read is a dead setting.
     */
    public function test_embed_css_vars_are_written_and_read(): void
    {
        $runtime = (string) file_get_contents(resource_path('embed/curator-embed.js'));
        $css = (string) file_get_contents(app_path('Http/Controllers/EmbedController.php'));

        preg_match_all("/setProperty\(\s*'(--crt-[a-z0-9-]+)'/", $runtime, $written);
        $writtenVars = array_values(array_unique($written[1]));
        $this->assertNotEmpty($writtenVars);

        preg_match_all('/var\((--crt-[a-z0-9-]+)/', $css, $read);
        $readVars = array_values(array_unique($read[1]));

        foreach ($writtenVars as $var) {
            $this->assertContains(
                $var,
                $readVars,
                "curator-embed.js writes {$var} but no CSS rule reads it — the setting has no effect."
            );
        }
    }
}
