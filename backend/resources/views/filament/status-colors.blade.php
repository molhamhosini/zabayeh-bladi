{{-- Row and card colours per order status (same palette in admin and branch portals). --}}
<style>
    .fi-ta-row.zb-st-pending > td { background: #fff7e0; }
    .fi-ta-row.zb-st-confirmed > td { background: #d9f7e6; }
    .fi-ta-row.zb-st-slaughtering > td { background: #e3eefc; }
    .fi-ta-row.zb-st-packing > td { background: #e9e5fb; }
    .fi-ta-row.zb-st-out_for_delivery > td { background: #dff4f7; }
    .fi-ta-row.zb-st-delivered > td { background: #eef2ee; color: #4b5d4f; }
    .fi-ta-row.zb-st-cancelled > td { background: #fde4e4; }
    .fi-ta-row.zb-st-returned > td { background: #fdebd8; }
    .fi-ta-row.zb-st-failed_delivery > td { background: #fbe0ea; }
    .fi-ta-row.zb-st:hover > td { filter: brightness(.97); }
    .dark .fi-ta-row.zb-st > td { background: transparent; }
    .zb-card { border-inline-start: 4px solid var(--zb-c, #ccc); }
    .zb-card-pending { --zb-c: #e0a100; } .zb-card-confirmed { --zb-c: #1f9d55; } .zb-card-slaughtering { --zb-c: #3b7dd8; }
    .zb-card-packing { --zb-c: #7c5cd6; } .zb-card-out_for_delivery { --zb-c: #1ba3b5; } .zb-card-delivered { --zb-c: #5f7a63; }
    .zb-card-cancelled { --zb-c: #d64545; } .zb-card-returned { --zb-c: #e07b1f; } .zb-card-failed_delivery { --zb-c: #c2185b; }
</style>
