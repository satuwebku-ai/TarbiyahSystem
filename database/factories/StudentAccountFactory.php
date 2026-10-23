<?php

namespace Database\Factories;

use App\Models\StudentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Akun login pelajar (tabel student_accounts). */
class StudentAccountFactory extends Factory
{
    protected $model = StudentAccount::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nis' => fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }
}
