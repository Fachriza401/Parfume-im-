<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia123';

    public function test_user_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@mail.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'phone' => '08123456789',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'budi@mail.com')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);

        $this->assertDatabaseHas('users', ['email' => 'budi@mail.com', 'role' => 'customer']);
    }

    public function test_register_never_returns_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@mail.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $response->assertStatus(201);
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('$2y$', $response->getContent());
    }

    public function test_password_is_hashed_in_database(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@mail.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $user = User::where('email', 'budi@mail.com')->firstOrFail();

        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'budi@mail.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@mail.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_weak_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@mail.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'budi@mail.com',
            'password' => Hash::make(self::PASSWORD),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@mail.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'budi@mail.com',
            'password' => Hash::make(self::PASSWORD),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@mail.com',
            'password' => 'PasswordSalah123',
        ])->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'tidakada@mail.com',
            'password' => self::PASSWORD,
        ])->assertStatus(401);
    }

    public function test_login_records_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@mail.com',
            'password' => Hash::make(self::PASSWORD),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@mail.com',
            'password' => self::PASSWORD,
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login',
            'module' => 'auth',
        ]);
    }

    public function test_authenticated_user_can_call_me(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test')->plainTextToken;

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)->postJson('/api/v1/auth/logout')->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The auth guard caches the resolved user for the lifetime of the
        // application instance, which a real HTTP request never has. Clearing it
        // makes this request behave like a fresh one.
        $this->app['auth']->forgetGuards();

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/profile', [
                'name' => 'Nama Baru',
                'phone' => '08999999999',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Nama Baru')
            ->assertJsonPath('data.user.phone', '08999999999');
    }

    public function test_user_can_update_profile_photo_path(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/profile', [
                'avatar_url' => '/images/uploads/customer-photo.jpg',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.user.avatar_url', '/images/uploads/customer-photo.jpg');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => '/images/uploads/customer-photo.jpg',
        ]);
    }

    public function test_authenticated_user_can_upload_an_image(): void
    {
        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->post('/api/v1/media/images', [
                'image' => UploadedFile::fake()->image('profile.jpg'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $imageUrl = $response->json('data.image_url');
        $this->assertMatchesRegularExpression(
            '~^/images/uploads/[0-9a-f-]+\.jpg$~i',
            $imageUrl
        );

        $imagePath = public_path(ltrim($imageUrl, '/'));
        $this->assertFileExists($imagePath);
        File::delete($imagePath);
    }

    public function test_update_profile_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/profile', [
                'password' => 'PasswordBaru123',
                'password_confirmation' => 'PasswordBaru123',
                'current_password' => 'PasswordSalah123',
            ])
            ->assertStatus(422);
    }

    public function test_forgot_password_returns_generic_response(): void
    {
        User::factory()->create(['email' => 'budi@mail.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'budi@mail.com'])
            ->assertStatus(200);

        // Unknown email must produce an identical, non-revealing response.
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'tidakada@mail.com'])
            ->assertStatus(200);
    }

    public function test_customer_cannot_access_admin_endpoint(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/admin/dashboard-stats')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_access_admin_endpoint(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/v1/admin/dashboard-stats')
            ->assertStatus(200);
    }

    public function test_guest_cannot_access_admin_endpoint(): void
    {
        $this->getJson('/api/v1/admin/dashboard-stats')->assertStatus(401);
    }

    public function test_factories_produce_expected_roles(): void
    {
        $this->assertSame('customer', User::factory()->create()->role);
        $this->assertSame('admin', User::factory()->admin()->create()->role);
        $this->assertInstanceOf(UserFactory::class, User::factory());
    }
}
