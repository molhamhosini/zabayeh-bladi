<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverySlot extends Model
{
    /** Default delivery windows: 9-12, 12-3, 3-6. */
    public const DEFAULT_WINDOWS = [['09:00', '12:00'], ['12:00', '15:00'], ['15:00', '18:00']];

    /** Arabic label such as "9 صباحاً - 12 ظهراً". */
    public function label(): string
    {
        $fmt = function (string $t): string {
            $h = (int) substr($t, 0, 2);
            $m = (int) substr($t, 3, 2);
            $period = $h < 12 ? 'صباحاً' : ($h < 15 ? 'ظهراً' : ($h < 18 ? 'عصراً' : 'مساءً'));
            $h12 = $h % 12 ?: 12;

            return $h12.($m ? ':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '').' '.$period;
        };

        return 'من '.$fmt($this->starts_at).' إلى '.$fmt($this->ends_at);
    }

    public function labelEn(): string
    {
        $fmt = fn (string $t) => date('g:i A', strtotime($t)) === date('g:00 A', strtotime($t)) ? date('g A', strtotime($t)) : date('g:i A', strtotime($t));

        return $fmt($this->starts_at).' - '.$fmt($this->ends_at);
    }

    /**
     * Keeps a branch's upcoming slots in line with the delivery windows of each emirate it delivers to:
     * creates missing slots for the coming $days, and removes future unbooked slots whose window was
     * changed or switched off. Booked slots are never touched. Returns the number of slots created.
     */
    public static function generateFor(int $branchId, int $days, ?int $capacity = null): int
    {
        $branch = Branch::with('areas:id,emirate_id')->findOrFail($branchId);
        $emirateIds = $branch->areas->pluck('emirate_id')->push($branch->emirate_id)->filter()->unique();
        $windows = DeliveryWindow::whereIn('emirate_id', $emirateIds)->where('active', true)->get()->groupBy('emirate_id');

        $created = 0;
        foreach ($emirateIds as $emirateId) {
            $ranges = ($windows[$emirateId] ?? collect())
                ->map(fn (DeliveryWindow $w) => [substr($w->starts_at, 0, 5), substr($w->ends_at, 0, 5), $w->capacity]);

            $upcoming = static::where('branch_id', $branchId)->where('emirate_id', $emirateId)
                ->whereDate('date', '>=', today())->get();
            foreach ($upcoming as $slot) {
                $matches = $ranges->contains(fn ($r) => $r[0] === substr($slot->starts_at, 0, 5) && $r[1] === substr($slot->ends_at, 0, 5));
                if (! $matches && $slot->booked === 0) {
                    $slot->delete();
                }
            }

            for ($d = 0; $d < $days; $d++) {
                $date = today()->addDays($d);
                foreach ($ranges as [$s, $e, $cap]) {
                    $exists = $upcoming->contains(fn ($slot) => $slot->date->isSameDay($date) && substr($slot->starts_at, 0, 5) === $s);
                    if (! $exists) {
                        static::create(['branch_id' => $branchId, 'emirate_id' => $emirateId, 'date' => $date,
                            'starts_at' => $s.':00', 'ends_at' => $e.':00', 'capacity' => $capacity ?? $cap]);
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    protected $fillable = ['branch_id', 'emirate_id', 'date', 'starts_at', 'ends_at', 'capacity', 'booked'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function emirate(): BelongsTo
    {
        return $this->belongsTo(Emirate::class);
    }

    public function isFull(): bool
    {
        return $this->booked >= $this->capacity;
    }
}
