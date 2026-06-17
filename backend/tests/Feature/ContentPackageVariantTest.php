<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\User;
use App\Services\AI\AiContentService;
use App\Services\AI\StubAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentPackageVariantTest extends TestCase
{
    use RefreshDatabase;

    private function makePackage(User $user): ContentPackage
    {
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Test campaign',
            'status' => 'draft',
            'product_info' => 'A great product',
        ]);

        return ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Original caption text',
            'status' => 'draft',
            'version' => 1,
        ]);
    }

    public function test_generates_variants_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $package = $this->makePackage($user);

        $response = $this->postJson("/api/content-packages/{$package->id}/variants");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(4, $data); // original + 3 variants

        // Original is stamped with index 0
        $original = collect($data)->firstWhere('variant_index', 0);
        $this->assertNotNull($original);
        $this->assertNotNull($original['variant_group_id']);

        // Variants have distinct indices 1, 2, 3
        foreach ([1, 2, 3] as $idx) {
            $variant = collect($data)->firstWhere('variant_index', $idx);
            $this->assertNotNull($variant, "Variant with index {$idx} is missing");
            $this->assertSame($original['variant_group_id'], $variant['variant_group_id']);
        }
    }

    public function test_cannot_generate_variants_for_already_grouped_package(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $package = $this->makePackage($user);

        // First generation is fine
        $this->postJson("/api/content-packages/{$package->id}/variants")->assertOk();

        // Re-fetching the original (now has variant_group_id) must be rejected
        $package->refresh();
        $this->postJson("/api/content-packages/{$package->id}/variants")->assertUnprocessable();
    }

    public function test_fetching_variant_group(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $package = $this->makePackage($user);

        $this->postJson("/api/content-packages/{$package->id}/variants")->assertOk();
        $package->refresh();

        $response = $this->getJson("/api/content-packages/{$package->id}/variants");
        $response->assertOk();

        $this->assertCount(4, $response->json('data'));
    }

    public function test_picking_winner_approves_winner_and_rejects_others(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $package = $this->makePackage($user);

        $this->postJson("/api/content-packages/{$package->id}/variants")->assertOk();

        // Get all siblings and pick the second variant as winner
        $group = ContentPackage::query()
            ->where('variant_group_id', $package->fresh()->variant_group_id)
            ->where('variant_index', 2)
            ->firstOrFail();

        $response = $this->postJson("/api/content-packages/{$group->id}/winner");
        $response->assertOk();

        $winner = $group->fresh();
        $this->assertTrue($winner->is_winner);
        $this->assertSame('approved', $winner->status);

        // All other siblings must be rejected
        ContentPackage::query()
            ->where('variant_group_id', $winner->variant_group_id)
            ->where('id', '!=', $winner->id)
            ->each(function (ContentPackage $sibling): void {
                $this->assertFalse($sibling->is_winner);
                $this->assertSame('rejected', $sibling->status);
            });
    }

    public function test_generate_variants_service_unit(): void
    {
        $user = User::factory()->create();
        $package = $this->makePackage($user);

        $service = new AiContentService(new StubAiProvider);
        $variants = $service->generateVariants($package, 3);

        $this->assertCount(4, $variants); // original + 3 generated

        $package->refresh();
        $this->assertNotNull($package->variant_group_id);
        $this->assertSame(0, $package->variant_index);
    }

    public function test_other_user_cannot_access_variants(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $package = $this->makePackage($owner);

        Sanctum::actingAs($other);

        $this->postJson("/api/content-packages/{$package->id}/variants")->assertForbidden();
        $this->getJson("/api/content-packages/{$package->id}/variants")->assertForbidden();
        $this->postJson("/api/content-packages/{$package->id}/winner")->assertForbidden();
    }
}
