<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up(): void {
  $defaults=['claro'=>['CB','CD','CE','CF'],'vivo'=>['Ouro','Platina']];
  foreach($defaults as $slug=>$codes){
   $operator=DB::table('operators')->where('slug',$slug)->first();
   if(!$operator)continue;
   foreach($codes as $i=>$code){
    $exists=DB::table('operator_classifications')->where('operator_id',$operator->id)->whereNull('tenant_id')->where('code',$code)->exists();
    if(!$exists)DB::table('operator_classifications')->insert(['operator_id'=>$operator->id,'tenant_id'=>null,'code'=>$code,'name'=>$code,'description'=>null,'is_active'=>true,'sort_order'=>$i,'created_at'=>now(),'updated_at'=>now()]);
   }
  }
 }
 public function down(): void {
  // Registros utilizados por clientes e regras são preservados.
 }
};
