<?php

namespace App\Filament\Pages;

use App\Support\InvoiceCompany;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Seller details printed on every tax invoice. */
class InvoiceSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?string $navigationLabel = 'الفاتورة الضريبية';

    protected static ?string $title = 'إعدادات الفاتورة الضريبية';

    protected static ?int $navigationSort = 91;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $this->form->fill(InvoiceCompany::get());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('بيانات المنشأة')
                ->description('تظهر في رأس كل فاتورة ضريبية. الرقم الضريبي (TRN) مطلوب في الفاتورة الضريبية في الإمارات.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name_ar')->label('الاسم التجاري بالعربية')->required(),
                        TextInput::make('name_en')->label('الاسم التجاري بالإنجليزية'),
                        TextInput::make('legal_name_ar')->label('الاسم القانوني (كما في الرخصة) بالعربية')->required(),
                        TextInput::make('legal_name_en')->label('الاسم القانوني بالإنجليزية'),
                        TextInput::make('trn')->label('الرقم الضريبي (TRN)')->placeholder('100XXXXXXXXXXXX')
                            ->rule('regex:/^\d{15}$/')->validationMessages(['regex' => 'الرقم الضريبي 15 رقماً.']),
                        TextInput::make('phone')->label('الهاتف')->tel(),
                        TextInput::make('address_ar')->label('العنوان بالعربية'),
                        TextInput::make('address_en')->label('العنوان بالإنجليزية'),
                        TextInput::make('email')->label('البريد الإلكتروني')->email(),
                        TextInput::make('website')->label('الموقع'),
                    ]),
                    TextInput::make('footer_note')->label('عبارة أسفل الفاتورة'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('حفظ')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        InvoiceCompany::put($this->form->getState());
        Notification::make()->title('تم حفظ بيانات الفاتورة')->success()->send();
    }
}
