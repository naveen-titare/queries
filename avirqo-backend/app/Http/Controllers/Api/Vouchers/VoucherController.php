<?php

namespace App\Http\Controllers\Api\Vouchers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Vouchers\ImportVoucherRequest;
use App\Models\VoucherInventory;
use App\Models\VoucherImportLog;
use App\Services\Vouchers\VoucherImportService;

/**
 * Avirqo-side voucher operations: import (fetch), inventory listing, and history.
 */
class VoucherController extends Controller
{
    public function __construct(private VoucherImportService $importer) {}

    /**
     * Feature 1/4: fetch (import) selected voucher(s) from Xoxoday.
     * Balance is checked inside the service; every attempt is logged.
     */
    public function import(ImportVoucherRequest $request)
    {
        $log = $this->importer->import(
            $request->validated(),
            $request->user()?->id
        );

        $status = $log->status === 'success' ? 200 : 422;
        return response()->json([
            'status'  => $log->status,
            'message' => $log->message,
            'log'     => $log,
        ], $status);
    }

    /**
     * Feature 6: inventory list grouped by brand, with denominations,
     * quantities, total value, and quantity shared with customers.
     */
    public function inventory(Request $request)
    {
        $rows = VoucherInventory::query()
            ->when($request->query('q'), fn ($q, $s) =>
                $q->where('brand_name', 'like', "%{$s}%"))
            ->orderBy('brand_name')
            ->orderBy('denomination')
            ->get();

        $brands = $rows->groupBy('brand_name')->map(function ($group, $brand) {
            $denoms = $group->map(fn ($r) => [
                'denomination'       => (float) $r->denomination,
                'currency_code'      => $r->currency_code,
                'quantity_available' => (int) $r->quantity_available,
                'quantity_imported'  => (int) $r->quantity_imported,
                'quantity_shared'    => (int) $r->quantity_shared,
                'line_value'         => (float) $r->denomination * (int) $r->quantity_available,
            ])->values();

            return [
                'brand_name'      => $brand,
                'image_url'       => $group->first()->image_url,
                'currency_code'   => $group->first()->currency_code,
                'denominations'   => $denoms,
                'total_value'     => (float) $denoms->sum('line_value'),
                'total_available' => (int) $denoms->sum('quantity_available'),
                'total_shared'    => (int) $group->sum('quantity_shared'),
            ];
        })->values();

        return response()->json([
            'data'        => $brands,
            'grand_total' => (float) $brands->sum('total_value'),
        ]);
    }

    /**
     * Feature 5: history of all fetch attempts with success/error and selection.
     */
    public function history(Request $request)
    {
        $logs = VoucherImportLog::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($logs);
    }
}
