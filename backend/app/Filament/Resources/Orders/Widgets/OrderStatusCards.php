<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** One card per order status, in workflow order. Counts follow the branch and delivery-date filters; clicking a card filters the list. */
class OrderStatusCards extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = '30s';

    protected int|array|null $columns = ['default' => 2, 'md' => 3, 'xl' => 5];

    protected function getTablePage(): string
    {
        return ListOrders::class;
    }

    protected function getStats(): array
    {
        $filters = $this->tableFilters ?? [];
        $query = OrderResource::getEloquentQuery()
            ->when($filters['branch_id']['value'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
        $query = OrderResource::applyDeliveryDate($query, $filters['delivery_date'] ?? []);
        $counts = $query->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $stats = [];
        foreach (OrderResource::STATUSES as $status => $label) {
            $params = ['filters' => array_filter([
                'status' => ['values' => [$status]],
                'delivery_date' => $filters['delivery_date'] ?? null,
                'branch_id' => $filters['branch_id'] ?? null,
            ])];
            $stats[] = Stat::make($label, (int) ($counts[$status] ?? 0))
                ->icon(OrderResource::STATUS_ICONS[$status])
                ->color(OrderResource::STATUS_COLORS[$status])
                ->extraAttributes(['class' => 'zb-card zb-card-'.$status])
                ->url(OrderResource::getUrl('index', $params));
        }

        return $stats;
    }
}
