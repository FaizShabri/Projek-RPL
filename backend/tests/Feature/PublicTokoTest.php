<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Toko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\MarketplaceSeeder;

class PublicTokoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(MarketplaceSeeder::class);
    }
    public function test_can_list_approved_tokos(): void
    {
        $response = $this->getJson('/api/tokos');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'current_page',
            'data',
            'total',
            'per_page',
        ]);
    }

    public function test_can_search_toko_by_name(): void
    {
        $response = $this->getJson('/api/tokos?search=Tebet');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertStringContainsStringIgnoringCase('Tebet', $data[0]['nama']);
    }

    public function test_can_filter_toko_by_radius_and_calculate_distance(): void
    {
        $response = $this->getJson('/api/tokos?latitude=-6.236&longitude=106.852&radius=5');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertNotNull($item['longitude']);
            $this->assertNotNull($item['distance']);
            $this->assertLessThanOrEqual(5.0, $item['distance']);
        }
    }

    public function test_can_filter_toko_by_rating(): void
    {
        $response = $this->getJson('/api/tokos?min_rating=4.5');
        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            if ($item['reviews_avg_rating'] !== null) {
                $this->assertGreaterThanOrEqual(4.5, (float) $item['reviews_avg_rating']);
            }
        }
    }

    public function test_can_view_toko_detail_with_tarifs_and_reviews(): void
    {
        $toko = Toko::approved()->first();
        $this->assertNotNull($toko);

        $response = $this->getJson('/api/tokos/' . $toko->id . '?latitude=-6.236&longitude=106.852');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'toko' => [
                'id',
                'nama',
                'alamat',
                'tarifs',
                'owner',
            ],
            'rating_summary' => [
                'average',
                'total',
                'breakdown',
            ],
            'reviews',
        ]);
    }
}