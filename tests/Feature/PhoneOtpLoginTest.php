<?php

namespace Tests\Feature;

use App\Filament\Auth\Pages\PhoneLogin;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Services\WhatsAppGateway;
use Filament\Facades\Filament;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneOtpLoginTest extends TestCase
{
    private RecordingLoginGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.whatsapp_gateway.url' => 'https://whatsapp.example.test',
            'services.whatsapp_gateway.token' => 'test-token',
            'services.whatsapp_otp.ttl_minutes' => 5,
        ]);
        DB::purge('sqlite');
        Cache::flush();
        $this->withoutVite();

        $settingsMigration = require database_path('migrations/2022_12_14_083707_create_settings_table.php');
        $settingsMigration->up();
        require_once database_path('migrations/2024_11_16_101110_pwa_settings.php');
        (new \PWASettings)->up();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        $migration = require database_path('migrations/2026_09_24_000000_add_whatsapp_login_number_to_users_table.php');
        $migration->up();

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'guard_name' => 'web']);

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('business_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('job_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });

        $this->gateway = new RecordingLoginGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    public function test_registered_user_can_login_and_session_is_regenerated(): void
    {
        $user = $this->registeredUser();
        $component = $this->sendOtp();
        $oldSessionId = Session::getId();

        $component->set('data.otp', $this->code())->call('verify')
            ->assertHasNoFormErrors()->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, Session::getId());
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(1, User::count());
        $this->assertSame('6281234567890', $this->gateway->messages[0]['phone']);
    }

    public function test_admin_edit_preserves_the_existing_hidden_login_credential(): void
    {
        $user = $this->registeredUser();
        $this->actingAs($user);
        Gate::before(fn (): bool => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertSet('data.whatsapp_login_number', '6281234567890')
            ->fillForm(['name' => 'Budi Updated'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('Budi Updated', $user->fresh()->name);
        $this->assertSame('6281234567890', $user->fresh()->whatsapp_login_number);
    }

    public function test_admin_cannot_assign_an_equivalent_login_number_already_used_by_another_user(): void
    {
        $admin = $this->registeredUser();
        $other = User::create(['name' => 'Other']);
        $this->actingAs($admin);
        Gate::before(fn (): bool => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
            ->fillForm(['whatsapp_login_number' => '0812-3456-7890'])
            ->call('save')->assertHasFormErrors(['whatsapp_login_number' => 'unique']);

        $this->assertNull($other->fresh()->whatsapp_login_number);
    }

    public function test_non_admin_user_editor_cannot_read_or_change_the_login_credential(): void
    {
        $user = $this->registeredUser();
        DB::table('roles')->where('id', 1)->update(['name' => 'operator']);
        $this->actingAs($user);
        Gate::before(fn (): bool => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertFormFieldIsHidden('whatsapp_login_number')
            ->assertSet('data.whatsapp_login_number', null)
            ->set('data.whatsapp_login_number', '6289999999999')
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('6281234567890', $user->fresh()->whatsapp_login_number);
    }

    public function test_unknown_number_is_only_rejected_after_otp_without_registration(): void
    {
        $component = $this->sendOtp();
        $this->assertSame(0, User::count());

        $component->set('data.otp', $this->code())->call('verify')
            ->assertHasFormErrors(['phone'])->assertSet('awaitingOtp', false);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_notification_contact_alone_cannot_authenticate_even_with_a_role(): void
    {
        $user = $this->registeredUser();
        $user->update(['whatsapp_number' => '081234567890', 'whatsapp_login_number' => null]);

        $this->sendOtp()->set('data.otp', $this->code())->call('verify')
            ->assertHasFormErrors(['phone']);

        $this->assertGuest();
    }

    public function test_registered_number_without_panel_access_is_rejected(): void
    {
        $this->registeredUser(withRole: false);

        $this->sendOtp()->set('data.otp', $this->code())->call('verify')
            ->assertHasFormErrors(['phone']);

        $this->assertGuest();
    }

    public function test_invalid_otp_does_not_login_or_consume_challenge(): void
    {
        $user = $this->registeredUser();
        $component = $this->sendOtp();

        $component->set('data.otp', '000000')->call('verify')
            ->assertHasFormErrors(['otp'])->assertSet('awaitingOtp', true);
        $this->assertGuest();

        $component->set('data.otp', $this->code())->call('verify')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $this->registeredUser();
        $component = $this->sendOtp();
        $this->travel(6)->minutes();

        $component->set('data.otp', $this->code())->call('verify')->assertHasFormErrors(['otp']);

        $this->assertGuest();
    }

    public function test_another_session_cannot_verify_a_challenge(): void
    {
        $this->registeredUser();
        $component = $this->sendOtp();
        Session::migrate(true);

        $component->set('data.otp', $this->code())->call('verify')->assertHasFormErrors(['otp']);

        $this->assertGuest();
    }

    public function test_changing_phone_revokes_the_old_challenge_and_resend_gets_a_new_one(): void
    {
        $this->registeredUser();
        $component = $this->sendOtp();
        $challengeId = $component->get('otpChallengeId');

        $component->call('changePhone')->assertSet('awaitingOtp', false)
            ->assertSet('otpChallengeId', null)->assertSet('data.otp', null);
        $this->assertNull(Cache::get('shelf:phone-otp:login:challenge:'.hash('sha256', $challengeId)));

        $component->fillForm(['phone' => '081234567890'])->call('send')->assertHasNoFormErrors();
        $this->assertNotSame($challengeId, $component->get('otpChallengeId'));
        $this->assertCount(2, $this->gateway->messages);
    }

    public function test_tampering_with_phone_cannot_login_as_another_account(): void
    {
        $this->registeredUser();
        $component = $this->sendOtp();

        $component->set('data.phone', '6289999999999')->set('data.otp', $this->code())
            ->call('verify')->assertHasFormErrors(['phone']);

        $this->assertGuest();
    }

    public function test_removing_the_login_number_while_waiting_for_otp_blocks_login(): void
    {
        $user = $this->registeredUser();
        $component = $this->sendOtp();
        $user->update(['whatsapp_login_number' => null]);

        $component->set('data.otp', $this->code())->call('verify')->assertHasFormErrors(['phone']);

        $this->assertGuest();
    }

    public function test_delivery_failure_stays_on_phone_step(): void
    {
        $this->gateway->deliver = false;

        Livewire::test(PhoneLogin::class)->fillForm(['phone' => '081234567890'])
            ->call('send')->assertHasFormErrors(['phone'])->assertSet('awaitingOtp', false)
            ->assertSet('otpChallengeId', null);
        $this->assertGuest();
    }

    public function test_missing_gateway_hides_login_option_and_blocks_send(): void
    {
        config(['services.whatsapp_gateway.token' => '']);
        $this->get('/admin/login')->assertOk()->assertDontSee('Atau masuk dengan');

        Livewire::test(PhoneLogin::class)->set('data.phone', '081234567890')
            ->call('send')->assertHasFormErrors(['phone'])->assertSet('awaitingOtp', false);
        $this->assertCount(0, $this->gateway->messages);
    }

    public function test_login_page_links_to_the_matching_mekaya_phone_page(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Username or Email')
            ->assertSee('Atau masuk dengan')->assertSee(route('phone-login'), false);
        $this->get('/phone-login')->assertOk()->assertSee('Masuk dengan Nomor HP')
            ->assertSee('Kembali ke halaman masuk')->assertSee('Kirim OTP WhatsApp');
    }

    public function test_phone_login_works_through_real_livewire_http_updates(): void
    {
        $html = $this->get('/phone-login')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);

        $this->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['data.phone' => '081234567890'],
                'calls' => [['path' => '', 'method' => 'send', 'params' => []]],
            ]],
        ])->assertOk()->assertSee('Verifikasi OTP');

        $this->assertCount(1, $this->gateway->messages);
    }

    public function test_session_and_csrf_middleware_are_applied_once(): void
    {
        $route = app('router')->getRoutes()->getByName('phone-login');
        $middleware = app('router')->gatherRouteMiddleware($route);

        foreach ([EncryptCookies::class, StartSession::class, VerifyCsrfToken::class] as $class) {
            $this->assertSame(1, collect($middleware)->filter(
                fn (string $candidate): bool => is_a($candidate, $class, true),
            )->count());
        }
    }

    private function registeredUser(bool $withRole = true): User
    {
        $user = User::create(['name' => 'Budi', 'whatsapp_login_number' => '+62 812-3456-7890']);
        if ($withRole) {
            DB::table('model_has_roles')->insert([
                'role_id' => 1, 'model_type' => User::class, 'model_id' => $user->id,
            ]);
        }

        return $user;
    }

    private function sendOtp()
    {
        return Livewire::test(PhoneLogin::class)->fillForm(['phone' => '0812-3456-7890'])
            ->call('send')->assertHasNoFormErrors()->assertSet('awaitingOtp', true)
            ->assertSet('data.phone', '6281234567890')->assertSeeHtml('wire:submit="verify"');
    }

    private function code(): string
    {
        preg_match('/\*([0-9]{6})\*/', $this->gateway->messages[0]['message'], $matches);

        return $matches[1];
    }
}

class RecordingLoginGateway extends WhatsAppGateway
{
    public array $messages = [];

    public bool $deliver = true;

    public function send($phoneNumber, string $message): bool
    {
        $this->messages[] = ['phone' => $phoneNumber, 'message' => $message];

        return $this->deliver;
    }
}
