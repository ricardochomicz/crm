<?php
namespace Tests\Feature;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\OperatorFactory;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
class ProductFilteringTest extends TestCase
{
 use LazilyRefreshDatabase;
 private function company(): Tenant
 {
  $tenant = Tenant::factory()->create();
  $user = User::factory()->create();
  $tenant->users()->attach($user, ['role'=>'OWNER','status'=>'ACTIVE']);
  Subscription::factory()->for($tenant)->trialing()->create();
  $this->actingAs($user);
  return $tenant;
 }
 public function test_filters_inactive_products_and_paginates_without_leaking_other_tenants(): void
 {
  $tenant = $this->company();
  $operator = OperatorFactory::new()->create();
  ProductFactory::new()->count(2)->create(['tenant_id'=>$tenant->id,'operator_id'=>$operator->id,'name'=>'Controle','is_active'=>false]);
  ProductFactory::new()->create(['tenant_id'=>$tenant->id,'operator_id'=>$operator->id,'name'=>'Controle','is_active'=>true]);
  ProductFactory::new()->create(['operator_id'=>$operator->id,'name'=>'Controle','is_active'=>false]);

  $this->getJson("/api/v1/tenants/{$tenant->id}/products?search=Controle&is_active=0&per_page=1&page=2")
   ->assertOk()->assertJsonCount(1,'data')->assertJsonPath('meta.total',2)->assertJsonPath('meta.current_page',2)
   ->assertJsonPath('data.0.tenant_id',$tenant->id)->assertJsonPath('data.0.is_active',false);
 }
 public function test_rejects_invalid_filters(): void
 {
  $tenant = $this->company();
  $this->getJson("/api/v1/tenants/{$tenant->id}/products?category=INVALID&per_page=101")
   ->assertUnprocessable()->assertInvalid(['category','per_page']);
 }
 public function test_product_create_and_update_return_resources(): void
 {
  $tenant = $this->company();
  $operator = OperatorFactory::new()->create();
  $tenant->operators()->attach($operator->id);
  $payload = ['operator_id'=>$operator->id,'name'=>'Controle','category'=>'CONTROL','price'=>'39.90','is_active'=>true];

  $response = $this->postJson("/api/v1/tenants/{$tenant->id}/products", $payload)
   ->assertCreated()->assertJsonPath('data.name','Controle')->assertJsonPath('data.price','39.90');
  $id = $response->json('data.id');
  $this->assertDatabaseHas('products',['id'=>$id,'tenant_id'=>$tenant->id,'name'=>'Controle']);
  $this->patchJson("/api/v1/tenants/{$tenant->id}/products/{$id}", [...$payload,'name'=>'Controle atualizado'])
   ->assertOk()->assertJsonPath('data.name','Controle atualizado');
  $this->assertDatabaseHas('products',['id'=>$id,'name'=>'Controle atualizado']);
 }
 public function test_cannot_update_product_from_another_tenant(): void
 {
  $tenant = $this->company();
  $operator = OperatorFactory::new()->create();
  $tenant->operators()->attach($operator->id);
  $product = ProductFactory::new()->create(['operator_id'=>$operator->id]);
  $this->patchJson("/api/v1/tenants/{$tenant->id}/products/{$product->id}", ['operator_id'=>$operator->id,'name'=>'Changed','category'=>'CONTROL','price'=>'10.00'])
   ->assertNotFound();
  $this->assertDatabaseHas('products',['id'=>$product->id,'name'=>$product->name]);
 }
 public function test_guests_cannot_list_products(): void
 {
  $tenant = Tenant::factory()->create();
  $this->getJson("/api/v1/tenants/{$tenant->id}/products")->assertUnauthorized();
 }
}
