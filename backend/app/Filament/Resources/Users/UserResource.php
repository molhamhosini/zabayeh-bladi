<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    public const ROLES = ['customer' => 'عميل', 'admin' => 'مدير', 'branch_staff' => 'موظف فرع', 'driver' => 'سائق'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('الاسم')->required(),
            TextInput::make('phone')->label('الجوال')->tel()->unique(ignoreRecord: true),
            TextInput::make('email')->label('البريد الإلكتروني')->email()->unique(ignoreRecord: true)
                ->required(fn ($get) => in_array($get('role'), ['admin', 'branch_staff'], true)),
            Select::make('role')->label('الدور')->options(self::ROLES)->required()->default('customer')->live(),
            Select::make('branch_id')->label('الفرع')->relationship('branch', 'name_ar')
                ->visible(fn ($get) => $get('role') === 'branch_staff')->required(fn ($get) => $get('role') === 'branch_staff'),
            TextInput::make('password')->label('كلمة المرور')->password()->revealable()->minLength(8)
                ->dehydrated(fn ($state) => filled($state))->helperText('اتركها فارغة لعدم التغيير. العملاء يدخلون برمز SMS.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable(),
                TextColumn::make('phone')->label('الجوال')->searchable(),
                TextColumn::make('email')->label('البريد')->searchable()->toggleable(),
                TextColumn::make('role')->label('الدور')->badge()->formatStateUsing(fn ($state) => self::ROLES[$state] ?? $state),
                TextColumn::make('branch.name_ar')->label('الفرع')->placeholder('-'),
                TextColumn::make('orders_count')->label('الطلبات')->counts('orders'),
                TextColumn::make('created_at')->label('التسجيل')->date('Y-m-d')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('role')->label('الدور')->options(self::ROLES)])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
