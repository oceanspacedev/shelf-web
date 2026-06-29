<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetLocation;
use App\Models\Brand;
use App\Models\BusinessEntity;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brandsAndModels = [
            'FURNITURE' => [
                'Ikea' => ['MEJA', 'KURSI', 'LEMARI'],
                'Ashley' => ['SOFA', 'RAK-RAKAN', 'PAPAN TULIS'],
            ],
            'PERKAKAS' => [
                'Bosch' => ['OBENG', 'TOOLBOX'],
                'Makita' => ['TALANG AC', 'TANGGA'],
            ],
            'BARANG ELEKTRONIK' => [
                'Asus' => ['LAPTOP', 'HANDPHONE'],
                'Samsung' => ['KULKAS', 'MICROWAVE'],
                'Sony' => ['KAMERA', 'TV'],
            ],
            'ACCESSORIES' => [
                'HP' => ['MOUSE', 'KEYBOARD'],
                'Logitech' => ['MIC WIRELESS', 'POINTER PRESENTASI'],
            ],
        ];

        $category = $this->faker->randomElement(array_keys($brandsAndModels));
        $brand = $this->faker->randomElement(array_keys($brandsAndModels[$category]));
        $model = $this->faker->randomElement($brandsAndModels[$category][$brand]);

        $condition = $this->faker->randomElement(AssetCondition::cases());
        $nbhStatus = ! $condition->isIncident()
            ? NbhStatus::None
            : $this->faker->randomElement([NbhStatus::Pending, NbhStatus::Resolved]);

        return [
            'purchase_date' => $this->faker->date(),
            'business_entity_id' => BusinessEntity::inRandomOrder()->first()->id ?? BusinessEntity::factory(),
            'name' => "{$brand} {$model}",
            'category_id' => Category::inRandomOrder()->first()->id ?? Category::factory(),
            'brand_id' => Brand::inRandomOrder()->first()->id ?? Brand::factory(),
            'type' => $category,
            'serial_number' => strtoupper($this->faker->unique()->bothify('??#####')),
            'imei1' => $category === 'BARANG ELEKTRONIK' ? $this->faker->unique()->numerify('###############') : null,
            'imei2' => $category === 'BARANG ELEKTRONIK' ? $this->faker->unique()->numerify('###############') : null,
            'item_price' => $this->faker->numberBetween(1000, 10000),
            'asset_location_id' => AssetLocation::inRandomOrder()->first()->id ?? AssetLocation::factory(),
            'condition_status' => $condition->value,
            'nbh_status' => $nbhStatus->value,
            'nbh_reported_at' => $condition->isIncident()
                ? $this->faker->date()
                : null,
            'audit_document_path' => null,
            'nbh_document_path' => $nbhStatus === NbhStatus::Resolved ? 'nbh/sample.pdf' : null,
            'nbh_notes' => $nbhStatus === NbhStatus::Resolved ? $this->faker->sentence() : null,
            'sold_at' => $condition === AssetCondition::Sold ? $this->faker->date() : null,
            'sold_to' => $condition === AssetCondition::Sold ? $this->faker->company() : null,
            'sold_price' => $condition === AssetCondition::Sold ? $this->faker->numberBetween(1000, 10000) : null,
            'sale_document_path' => $condition === AssetCondition::Sold ? 'asset-sales/sample.pdf' : null,
            'sale_notes' => $condition === AssetCondition::Sold ? $this->faker->sentence() : null,
            'is_available' => $condition === AssetCondition::Available,
        ];
    }
}
