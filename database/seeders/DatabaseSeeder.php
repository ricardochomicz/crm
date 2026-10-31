<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call(SubscriptionPlanSeeder::class);

        $name = config('platform.admin.name');
        $email = config('platform.admin.email');
        $password = config('platform.admin.password');

        if (blank($name) && blank($email) && blank($password)) {
            $this->command?->info(
                'Credenciais administrativas não configuradas. Nenhuma conta criada.',
            );

            return;
        }

        $credentials = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ])->validate();

        $existingUser = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($existingUser !== null) {
            $this->command?->warn(
                "O usuário {$existingUser->id} já existe. Senha e permissões foram preservadas.",
            );

            return;
        }

        $user = new User();
        $user->name = $credentials['name'];
        $user->email = $credentials['email'];
        $user->password = $credentials['password'];
        $user->is_platform_admin = true;
        $user->save();

        $this->command?->info(
            "Administrador criado com ID {$user->id}.",
        );
    }
}
