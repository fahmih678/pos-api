<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test(
    'kasir atau admin dapat login dengan kredensial yang valid dan menerima bearer token',
    function () {
        User::factory()->cashier()->create([
            'email' => 'kasir@pos.test',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'kasir@pos.test',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'role_label',
                        'is_active',
                    ],
                ],
            ])
            ->assertJsonPath('data.user.role', UserRole::CASHIER->value)
            ->assertJsonPath('data.user.role_label', 'Cashier');
    }
);

test('login gagal jika password salah', function () {
    User::factory()->create([
        'email' => 'admin@pos.test',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'admin@pos.test',
        'password' => 'password-salah',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('user yang dinonaktifkan tidak diizinkan login', function () {
    User::factory()->inactive()->create([
        'email' => 'kasir-keluar@pos.test',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'kasir-keluar@pos.test',
        'password' => 'password123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('pengguna terautentikasi dapat melihat profil diri melalui /api/me', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

test('pengguna yang belum login ditolak saat mengakses /api/me', function () {
    $response = $this->getJson('/api/me');
    $response->assertUnauthorized();
});
test('pengguna dapat logout dan token yang digunakan akan dihapus', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pos-terminal')->plainTextToken;
    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/logout');
    $response->assertOk()
        ->assertJson(['message' => 'Logout berhasil.']);
    // Pastikan token sudah terhapus dari database
    expect($user->tokens()->count())->toBe(0);
});
