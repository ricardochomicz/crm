<?php
namespace App\Http\Resources\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ProductPromotionResource extends JsonResource
{
 public function toArray(Request $request): array
 {
  return ['id'=>$this->id,'product_id'=>$this->product_id,'name'=>$this->name,'promotional_price'=>$this->promotional_price,'starts_at'=>$this->starts_at?->format('Y-m-d'),'ends_at'=>$this->ends_at?->format('Y-m-d'),'is_active'=>$this->is_active,'created_at'=>$this->created_at,'updated_at'=>$this->updated_at];
 }
}
