<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Msg91SmsSender;
use App\Services\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Msg91Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'shop.sms.driver' => 'msg91',
            'shop.sms.msg91.auth_key' => 'test-auth-key',
            'shop.sms.msg91.template_id' => 'tmpl_123',
            'shop.sms.msg91.api' => 'flow',
        ]);
    }

    public function test_otp_request_sends_code_through_msg91_template(): void
    {
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success', 'message' => 'ok'])]);

        $this->assertInstanceOf(Msg91SmsSender::class, app(SmsSender::class));
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '050 123 4567'])->assertOk();

        Http::assertSent(function (Request $request) {
            $recipient = $request['recipients'][0];

            return $request->url() === 'https://control.msg91.com/api/v5/flow'
                && $request->hasHeader('authkey', 'test-auth-key')
                && $request['template_id'] === 'tmpl_123'
                && $recipient['mobiles'] === '971501234567'
                && preg_match('/^\d{6}$/', $recipient['otp']);
        });
    }

    public function test_admin_settings_override_env_and_key_is_encrypted(): void
    {
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success'])]);
        config(['shop.sms.driver' => 'log']);
        Setting::put('sms.msg91.active', '1');
        Setting::put('sms.msg91.template_id', 'tmpl_admin');
        Setting::put('sms.msg91.auth_key', 'admin-key', secret: true);

        $this->assertNotSame('admin-key', Setting::where('key', 'sms.msg91.auth_key')->value('value'));
        app(SmsSender::class)->sendOtp('+971501234567', '123456');

        Http::assertSent(fn (Request $r) => $r->hasHeader('authkey', 'admin-key') && $r['template_id'] === 'tmpl_admin');
    }

    public function test_settings_page_is_for_admins_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'branch_staff']);

        $this->actingAs($admin)->get('/admin/sms-settings')->assertOk()->assertSee('Template Id');
        $this->actingAs($staff)->get('/admin/sms-settings')->assertForbidden();
    }

    public function test_otp_api_is_the_default_and_sends_the_code(): void
    {
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success', 'request_id' => 'abc'])]);
        config(['shop.sms.msg91.api' => 'otp']);

        app(SmsSender::class)->sendOtp('+971501234567', '482913');

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://control.msg91.com/api/v5/otp?')
            && $r->hasHeader('authkey', 'test-auth-key')
            && str_contains($r->url(), 'template_id=tmpl_123') && str_contains($r->url(), 'mobile=971501234567')
            && str_contains($r->url(), 'otp=482913'));
    }

    public function test_msg91_error_is_reported(): void
    {
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'error', 'message' => 'Invalid template'])]);

        $this->expectException(\RuntimeException::class);
        app(SmsSender::class)->sendOtp('+971501234567', '123456');
    }
}
