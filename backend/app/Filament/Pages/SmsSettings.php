<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Msg91SmsSender;
use App\Services\Sms;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/** Admin page for the SMS provider (MSG91) that sends login codes. */
class SmsSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?string $navigationLabel = 'رسائل SMS';

    protected static ?string $title = 'إعدادات رسائل SMS';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $msg91 = Sms::msg91();
        $this->form->fill([
            'active' => Sms::msg91Active() ? '1' : '0',
            'template_id' => $msg91['template_id'],
            'otp_variable' => $msg91['otp_variable'],
            'auth_key' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $hasKey = filled(Sms::msg91()['auth_key']);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Msg91')
                    ->description('يُستخدم لإرسال رمز الدخول للعملاء. عند إيقافه تُكتب الرموز في سجل الخادم فقط.')
                    ->schema([
                        Radio::make('active')
                            ->hiddenLabel()
                            ->options(['1' => 'مفعّل', '0' => 'غير مفعّل'])
                            ->inline()
                            ->required(),
                        TextInput::make('template_id')
                            ->label('Template Id')
                            ->placeholder('Template id')
                            ->required(fn ($get) => $get('active') === '1'),
                        TextInput::make('auth_key')
                            ->label('Auth Key')
                            ->password()
                            ->revealable()
                            ->placeholder($hasKey ? '•••••••• (محفوظ، اتركه فارغاً للإبقاء عليه)' : 'Auth key')
                            ->required(fn ($get) => $get('active') === '1' && ! $hasKey),
                        TextInput::make('otp_variable')
                            ->label('اسم متغيّر الرمز في القالب')
                            ->helperText('مثلاً إذا كان نص القالب "رمز التحقق: ##otp##" فالمتغيّر هو otp.')
                            ->default('otp')
                            ->required(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('تحديث')->submit('save'),
                        $this->testAction(),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put('sms.msg91.active', $data['active']);
        Setting::put('sms.msg91.template_id', $data['template_id']);
        Setting::put('sms.msg91.otp_variable', $data['otp_variable']);
        if (filled($data['auth_key'])) {
            Setting::put('sms.msg91.auth_key', $data['auth_key'], secret: true);
        }

        $this->mount();
        Notification::make()->title('تم حفظ الإعدادات')->success()->send();
    }

    protected function testAction(): Action
    {
        return Action::make('test')
            ->label('إرسال رسالة تجربة')
            ->color('gray')
            ->schema([
                TextInput::make('phone')->label('رقم الجوال')->tel()->placeholder('+9715XXXXXXXX')->required(),
            ])
            ->action(function (array $data): void {
                try {
                    (new Msg91SmsSender)->sendOtp($data['phone'], (string) random_int(100000, 999999));
                    Notification::make()->title('تم الإرسال، تحقق من الجوال')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('فشل الإرسال')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
