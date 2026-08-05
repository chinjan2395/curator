<?php

namespace Tests\Unit;

use App\Support\PublishSettings;
use Tests\TestCase;

class PublishSettingsTest extends TestCase
{
    public function test_normalizes_the_showcase_sizing_keys(): void
    {
        $out = PublishSettings::validateAndNormalize([
            'feed' => ['showcase_card_width' => 9999],
            'colors' => ['showcase_shell_bg' => ['enabled' => 1, 'color' => '#ABC']],
            'widget' => ['columns' => 4],
        ]);

        $this->assertSame(640, $out['feed']['showcase_card_width']);
        $this->assertTrue($out['colors']['showcase_shell_bg']['enabled']);
        $this->assertSame('#aabbcc', $out['colors']['showcase_shell_bg']['color']);
        $this->assertSame(4, $out['widget']['columns']);
    }

    public function test_showcase_shell_follows_the_theme_by_default(): void
    {
        $defaults = PublishSettings::defaults();

        $this->assertFalse($defaults['colors']['showcase_shell_bg']['enabled']);
    }

    public function test_invalid_showcase_values_fall_back_to_defaults(): void
    {
        $out = PublishSettings::validateAndNormalize([
            'feed' => ['showcase_card_width' => 10],
            'colors' => ['showcase_shell_bg' => ['color' => 'not-a-colour']],
            'widget' => ['columns' => 7],
        ]);

        $this->assertSame(180, $out['feed']['showcase_card_width']);
        $this->assertSame('#0a0a0a', $out['colors']['showcase_shell_bg']['color']);
        $this->assertSame(3, $out['widget']['columns']);
    }

    /**
     * Option visibility is a presentation concern owned by the frontend
     * capability matrix. If the backend stripped keys that the current layout
     * ignores, previewing showcase and saving would silently destroy a user's
     * grid tuning — and every brand-kit-applied workspace on a different layout
     * would report permanent phantom drift.
     */
    public function test_keeps_settings_that_the_active_layout_does_not_use(): void
    {
        $tuned = PublishSettings::validateAndNormalize([
            'feed_style' => 'grid',
            'feed' => ['post_min_width' => 420],
            'post' => ['source_row_layout' => 'inline', 'show_likes' => true],
            'widget' => ['platform_filters' => ['youtube']],
        ]);

        $switched = PublishSettings::validateAndNormalize(
            array_merge($tuned, ['feed_style' => 'showcase_carousel'])
        );

        $this->assertSame(420, $switched['feed']['post_min_width']);
        $this->assertSame('inline', $switched['post']['source_row_layout']);
        $this->assertTrue($switched['post']['show_likes']);
        $this->assertSame(['youtube'], $switched['widget']['platform_filters']);

        $back = PublishSettings::validateAndNormalize(
            array_merge($switched, ['feed_style' => 'grid'])
        );

        $this->assertSame($tuned, $back);
    }

    public function test_theme_accepts_auto(): void
    {
        $out = PublishSettings::validateAndNormalize(['widget' => ['theme' => 'auto']]);

        $this->assertSame('auto', $out['widget']['theme']);
    }
}
