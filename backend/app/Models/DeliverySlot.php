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

    /** Creates the default windows for a branch for the coming days, skipping ones that already exist. */
    public static function generateFor(int $branchId, int $days, int $capacity = 10): int
    {
        $created = 0;
        for ($d = 0; $d < $days; $d++) {
            foreach (self::DEFAULT_WINDOWS as [$s, $e]) {
                $date = today()->addDays($d);
                $exists = static::where('branch_id', $branchId)->whereDate('date', $date)
                    ->where('starts_at', 'like', $s.'%')->exists();
                if (! $exists) {
                    static::create(['branch_id' => $branchId, 'date' => $date, 'starts_at' => $s.':00', 'ends_at' => $e.':00', 'capacity' => $capacity]);
                    $created++;
                }
            }
        }

        return $created;
    }

    protected $fillable = ['branch_id', 'date', 'starts_at', 'ends_at', 'capacity', 'booked'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isFull(): bool
    {
        return $this->booked >= $this->capacity;
    }
}
