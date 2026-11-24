<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            'invoice' => Action::make('invoice')
                ->label('طباعة الفاتورة')->icon('heroicon-o-printer')->color('gray')
                ->url(fn (Order $record) => route('orders.invoice', [$record, 'print' => 1]))
                ->openUrlInNewTab(),
            'receipt' => Action::make('receipt')
                ->label('إيصال 80مم')->icon('heroicon-o-receipt-percent')->color('gray')
                ->url(fn (Order $record) => route('orders.invoice', [$record, 'size' => '80mm', 'print' => 1]))
                ->openUrlInNewTab(),
        ];
        foreach (Order::TRANSITIONS as $to) {
            foreach ($to as $status) {
                $actions[$status] ??= Action::make("to_$status")
                    ->label(OrderResource::STATUS_ACTIONS[$status])
                    ->color($status === 'cancelled' ? 'danger' : 'primary')
                    ->visible(fn (Order $record) => $record->canMoveTo($status))
                    ->requiresConfirmation($status === 'cancelled')
                    ->schema($status === 'cancelled' ? [TextInput::make('note')->label('سبب الإلغاء')->required()] : [])
                    ->action(function (Order $record, array $data) use ($status) {
                        $record->moveTo($status, auth()->user(), $data['note'] ?? null);
                        if ($status === 'cancelled') {
                            $record->slot?->decrement('booked');
                        }
                        Notification::make()->title('تم تحديث حالة الطلب')->success()->send();
                    });
            }
        }

        $actions['driver'] = Action::make('assign_driver')
            ->label('تعيين سائق')->color('gray')
            ->visible(fn (Order $record) => ! in_array($record->status, ['delivered', 'cancelled'], true))
            ->schema([Select::make('driver_id')->label('السائق')->required()
                ->options(fn () => User::where('role', User::ROLE_DRIVER)->pluck('name', 'id'))])
            ->fillForm(fn (Order $record) => ['driver_id' => $record->driver_id])
            ->action(fn (Order $record, array $data) => $record->update(['driver_id' => $data['driver_id']]));

        $actions['cod_paid'] = Action::make('mark_cod_paid')
            ->label('تم استلام المبلغ نقداً')->color('success')->requiresConfirmation()
            ->visible(fn (Order $record) => $record->payment_method === 'cod' && $record->payment_status !== 'paid' && $record->status !== 'cancelled')
            ->action(fn (Order $record) => $record->update(['payment_status' => 'paid']));

        return array_values($actions);
    }
}
