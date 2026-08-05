<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end checks on the two files a customer's site actually loads.
 * The stylesheet is a heredoc and the script is a string concatenation, so a
 * broken edit shows up here rather than on someone's live page.
 */
class EmbedAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(array $settings = []): Workspace
    {
        return Workspace::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Embed WS',
            'public_key' => 'embed-assets-key',
            'publish_settings' => $settings,
        ]);
    }

    public function test_stylesheet_drives_theme_and_showcase_sizing_from_vars(): void
    {
        $css = $this->get('/api/embed/'.$this->workspace()->public_key.'.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->getContent();

        // The theme owns the surface, not just the cards.
        $this->assertStringContainsString('background:var(--crt-surface,transparent)', $css);
        $this->assertStringContainsString('var(--crt-showcase-shell-bg,var(--crt-surface,#0a0a0a))', $css);

        // Showcase thumbnails resolve through the shared media vars instead of
        // hard-coded 3/4 + cover, which is what made the controls inert.
        $this->assertStringContainsString('aspect-ratio:var(--crt-media-aspect,3/4)', $css);
        $this->assertStringContainsString('object-fit:var(--crt-media-fit,cover)', $css);
        $this->assertStringNotContainsString('aspect-ratio:3/4', $css);

        // Gap and radius are no longer frozen for showcase or waterfall.
        $this->assertStringContainsString('gap:var(--crt-gap,14px)', $css);
        $this->assertStringContainsString('margin-bottom:var(--crt-gap,12px)', $css);
        $this->assertStringContainsString('column-count:var(--crt-columns,3)', $css);
    }

    public function test_script_bootstraps_saved_settings_and_never_enables_preview_mode(): void
    {
        $workspace = $this->workspace([
            'feed_style' => 'showcase_carousel',
            'feed' => ['showcase_card_width' => 320],
            'widget' => ['theme' => 'dark'],
        ]);

        $js = $this->get('/api/embed/'.$workspace->public_key.'.js')->assertOk()->getContent();

        $this->assertStringContainsString('var CRT_PUBLIC_KEY = "embed-assets-key"', $js);
        $this->assertStringContainsString('"showcase_card_width":320', $js);
        $this->assertStringContainsString('"theme":"dark"', $js);

        // A production snippet must never accept pushed settings; only the
        // Publish preview host declares CRT_PREVIEW.
        $this->assertStringNotContainsString('var CRT_PREVIEW =', $js);
        $this->assertStringContainsString("typeof CRT_PREVIEW !== 'undefined'", $js);
    }

    public function test_unknown_public_key_is_not_served(): void
    {
        $this->get('/api/embed/no-such-key.css')->assertNotFound();
        $this->get('/api/embed/no-such-key.js')->assertNotFound();
    }
}
