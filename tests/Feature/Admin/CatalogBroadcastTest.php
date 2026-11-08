<?php

namespace Tests\Feature\Admin;

use App\Events\CatalogUpdated;
use App\Models\Product;
use App\Models\PromoBanner;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CatalogBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_updated_event_broadcasts_on_public_catalog_channel(): void
    {
        $event = new CatalogUpdated('product_saved');

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('catalog', $channels[0]->name);
        $this->assertSame('CatalogUpdated', $event->broadcastAs());
    }

    public function test_updating_product_dispatches_catalog_updated_event(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'sell_price' => 25000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Event::fake([CatalogUpdated::class]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'sell_price' => 27000,
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        Event::assertDispatched(CatalogUpdated::class);
    }

    public function test_saving_or_deleting_voucher_and_banner_dispatches_catalog_updated_event(): void
    {
        $product = Product::factory()->create();

        Event::fake([CatalogUpdated::class]);

        $voucher = Voucher::factory()->create(['product_id' => $product->id]);
        Event::assertDispatched(CatalogUpdated::class, fn (CatalogUpdated $e) => $e->reason === 'voucher_saved');

        $banner = PromoBanner::factory()->create();
        Event::assertDispatched(CatalogUpdated::class, fn (CatalogUpdated $e) => $e->reason === 'banner_saved');

        $voucher->delete();
        Event::assertDispatched(CatalogUpdated::class, fn (CatalogUpdated $e) => $e->reason === 'voucher_deleted');

        $banner->delete();
        Event::assertDispatched(CatalogUpdated::class, fn (CatalogUpdated $e) => $e->reason === 'banner_deleted');
    }
}
