<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_tidak_bisa_simpan_token(): void
    {
        $this->postJson(route('fcm-token.store'), ['token' => str_repeat('a', 30)])
            ->assertUnauthorized();
    }

    public function test_peserta_bisa_simpan_token_fcm(): void
    {
        $user = User::factory()->peserta()->create();

        $token = str_repeat('x', 40).'_fcm_token_peserta';

        $this->actingAs($user)->postJson(route('fcm-token.store'), [
            'token' => $token,
            'device_type' => 'web',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $user->id,
            'token' => $token,
            'device_type' => 'web',
        ]);
    }

    public function test_token_bersifat_unik_dan_pindah_kepemilikan(): void
    {
        $userA = User::factory()->peserta()->create();
        $userB = User::factory()->pengawas()->create();
        $token = str_repeat('t', 40).'_shared';

        $this->actingAs($userA)->postJson(route('fcm-token.store'), ['token' => $token])->assertOk();
        $this->assertDatabaseHas('user_fcm_tokens', ['user_id' => $userA->id, 'token' => $token]);

        // Device yang sama login sebagai user lain → kepemilikan pindah
        $this->actingAs($userB)->postJson(route('fcm-token.store'), ['token' => $token, 'device_type' => 'android'])->assertOk();

        $this->assertDatabaseHas('user_fcm_tokens', ['user_id' => $userB->id, 'token' => $token]);
        $this->assertDatabaseMissing('user_fcm_tokens', ['user_id' => $userA->id, 'token' => $token]);
        $this->assertSame(1, UserFcmToken::where('token', $token)->count());
    }

    public function test_validasi_token_wajib_dan_device_type(): void
    {
        $user = User::factory()->peserta()->create();

        $this->actingAs($user)->postJson(route('fcm-token.store'), [])->assertStatus(422)->assertJsonValidationErrors(['token']);
        $this->actingAs($user)->postJson(route('fcm-token.store'), ['token' => 'pendek'])->assertStatus(422);
        $this->actingAs($user)->postJson(route('fcm-token.store'), ['token' => str_repeat('a', 30), 'device_type' => 'tv'])->assertStatus(422);
    }

    public function test_update_idempotent_tidak_duplikat(): void
    {
        $user = User::factory()->peserta()->create();
        $token = str_repeat('u', 40).'_idempotent';

        $this->actingAs($user)->postJson(route('fcm-token.store'), ['token' => $token, 'device_type' => 'web'])->assertOk();
        $this->actingAs($user)->postJson(route('fcm-token.store'), ['token' => $token, 'device_type' => 'ios'])->assertOk();

        $this->assertSame(1, UserFcmToken::where('token', $token)->count());
        $this->assertDatabaseHas('user_fcm_tokens', ['token' => $token, 'device_type' => 'ios']);
    }

    public function test_hapus_token_milik_sendiri(): void
    {
        $user = User::factory()->peserta()->create();
        $token = UserFcmToken::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson(route('fcm-token.destroy'), ['token' => $token->token])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('user_fcm_tokens', ['token' => $token->token]);
    }

    public function test_tidak_bisa_hapus_token_milik_orang_lain(): void
    {
        $owner = User::factory()->peserta()->create();
        $other = User::factory()->peserta()->create();
        $token = UserFcmToken::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)->deleteJson(route('fcm-token.destroy'), ['token' => $token->token])->assertOk();

        // Token tetap ada karena where user_id membatasi
        $this->assertDatabaseHas('user_fcm_tokens', ['token' => $token->token, 'user_id' => $owner->id]);
    }

    public function test_guest_tidak_bisa_hapus_token(): void
    {
        $this->deleteJson(route('fcm-token.destroy'), ['token' => 'whatever-token-value-1234567890'])
            ->assertUnauthorized();
    }
}
