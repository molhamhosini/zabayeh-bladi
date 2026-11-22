<?php

namespace App\Filament\Resources\Branches\RelationManagers;

use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Accounts that can sign in to this branch's portal at /branch. */
class StaffRelationManager extends RelationManager
{
    protected static string $relationship = 'staff';

    protected static ?string $title = 'حسابات بوابة الفرع';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->required(),
            TextInput::make('email')->label('البريد الإلكتروني')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('الجوال')->tel()->unique(ignoreRecord: true),
            TextInput::make('password')->label('كلمة المرور')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))->minLength(8),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم'),
                TextColumn::make('email')->label('البريد'),
                TextColumn::make('phone')->label('الجوال'),
            ])
            ->headerActions([
                CreateAction::make()->label('إضافة حساب')->mutateDataUsing(fn (array $data) => $data + ['role' => User::ROLE_BRANCH_STAFF]),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
