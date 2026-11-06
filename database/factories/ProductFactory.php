<?php
namespace Database\Factories;
use App\Enums\ProductCategory;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<Product> */
class ProductFactory extends Factory
{
 protected $model = Product::class;
 public function definition(): array
 {
  return ['tenant_id'=>Tenant::factory(),'operator_id'=>OperatorFactory::new(),'name'=>fake()->words(3,true),'category'=>ProductCategory::Control,'price'=>'100.00','is_active'=>true];
 }
}
