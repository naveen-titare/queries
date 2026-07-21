<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SendVoucherOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    private const REPORT_DATA_START_DATE = '2026-07-01';

    public function summary(Request $request)
    {
        $ordersPeriod = $this->normalizePeriod($request->query('orders_period', 'week'));
        $vouchersPeriod = $this->normalizePeriod($request->query('vouchers_period', 'week'));
        $customersPeriod = $this->normalizePeriod($request->query('customers_period', 'week'));
        $brandsPeriod = $this->normalizePeriod($request->query('brands_period', 'week'));

        return response()->json([
            'financial_year' => $this->financialYearMeta(),
            'orders' => $this->buildBundle($ordersPeriod, 'orders'),
            'vouchers' => $this->buildBundle($vouchersPeriod, 'vouchers'),
            'customers' => $this->buildBundle($customersPeriod, 'customers'),
            'brands' => $this->buildBundle($brandsPeriod, 'brands'),
        ]);
    }

    private function buildBundle(string $period, string $focus): array
    {
        [$start, $end, $bucketMode] = $this->resolveWindow($period);

        $orders = SendVoucherOrder::query()
            ->with([
                'customer:id,company_name',
                'items.product:id,brand,name',
            ])
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('sent_at')
            ->get();

        $buckets = $this->makeBuckets($start, $end, $bucketMode);
        $customerTotals = [];
        $brandTotals = [];
        $summary = [
            'order_value' => 0.0,
            'discount' => 0.0,
            'service_charge' => 0.0,
            'voucher_amount' => 0.0,
        ];

        foreach ($orders as $order) {
            $bucketIndex = $this->locateBucketIndex($buckets, $order->sent_at);
            if ($bucketIndex === null) {
                continue;
            }

            $grossVoucherAmount = 0.0;
            $productDiscount = 0.0;
            $productServiceCharge = 0.0;
            $brandSubtotals = [];

            foreach ($order->items as $item) {
                $grossVoucherAmount += (float) ($item->gross_total ?? 0);
                $adjustment = (float) ($item->discount_amount ?? 0);

                if ($adjustment >= 0) {
                    $productDiscount += $adjustment;
                } else {
                    $productServiceCharge += abs($adjustment);
                }

                $brandName = $item->product?->brand ?: $item->product?->name ?: 'Unbranded';
                $brandSubtotals[$brandName] = ($brandSubtotals[$brandName] ?? 0) + (float) ($item->total_value ?? 0);
            }

            $invoiceAdjustment = (float) ($order->invoice_discount_amount ?? 0);
            if ($invoiceAdjustment >= 0) {
                $productDiscount += $invoiceAdjustment;
            } else {
                $productServiceCharge += abs($invoiceAdjustment);
            }

            if (! empty($brandSubtotals) && $invoiceAdjustment !== 0.0) {
                $brandSubtotalSum = array_sum($brandSubtotals);
                if ($brandSubtotalSum > 0) {
                    foreach ($brandSubtotals as $brandName => $brandSubtotal) {
                        $brandSubtotals[$brandName] = $brandSubtotal - (($brandSubtotal / $brandSubtotalSum) * $invoiceAdjustment);
                    }
                }
            }

            $bucket = &$buckets[$bucketIndex];
            $bucket['order_value'] += (float) $order->total_amount;
            $bucket['discount'] += $productDiscount;
            $bucket['service_charge'] += $productServiceCharge;
            $bucket['voucher_amount'] += $grossVoucherAmount;

            $summary['order_value'] += (float) $order->total_amount;
            $summary['discount'] += $productDiscount;
            $summary['service_charge'] += $productServiceCharge;
            $summary['voucher_amount'] += $grossVoucherAmount;

            $customerId = (int) $order->customer_id;
            if (! isset($customerTotals[$customerId])) {
                $customerTotals[$customerId] = [
                    'id' => $customerId,
                    'company_name' => $order->customer?->company_name ?? 'Unknown customer',
                    'order_value' => 0.0,
                    'orders_count' => 0,
                ];
            }
            $customerTotals[$customerId]['order_value'] += (float) $order->total_amount;
            $customerTotals[$customerId]['orders_count'] += 1;

            foreach ($brandSubtotals as $brandName => $brandValue) {
                if (! isset($brandTotals[$brandName])) {
                    $brandTotals[$brandName] = [
                        'brand_name' => $brandName,
                        'order_value' => 0.0,
                        'orders_count' => 0,
                    ];
                }
                $brandTotals[$brandName]['order_value'] += (float) $brandValue;
                $brandTotals[$brandName]['orders_count'] += 1;
            }
        }

        $topCustomers = collect($customerTotals)
            ->sortByDesc('order_value')
            ->take(5)
            ->values()
            ->map(fn (array $row, int $index) => [
                'rank' => $index + 1,
                'id' => $row['id'],
                'company_name' => $row['company_name'],
                'order_value' => round($row['order_value'], 2),
                'orders_count' => $row['orders_count'],
            ])
            ->all();

        $topBrands = collect($brandTotals)
            ->sortByDesc('order_value')
            ->take(5)
            ->values()
            ->map(fn (array $row, int $index) => [
                'rank' => $index + 1,
                'brand_name' => $row['brand_name'],
                'order_value' => round($row['order_value'], 2),
                'orders_count' => $row['orders_count'],
            ])
            ->all();

        return [
            'period' => $period,
            'focus' => $focus,
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $this->rangeLabel($start, $end),
            ],
            'summary' => array_map(fn ($value) => round($value, 2), $summary),
            'timeline' => collect($buckets)->map(function (array $bucket) {
                return [
                    'label' => $bucket['label'],
                    'start' => $bucket['start']->toDateString(),
                    'end' => $bucket['end']->toDateString(),
                    'order_value' => round($bucket['order_value'], 2),
                    'discount' => round($bucket['discount'], 2),
                    'service_charge' => round($bucket['service_charge'], 2),
                    'voucher_amount' => round($bucket['voucher_amount'], 2),
                ];
            })->values()->all(),
            'top_customers' => $topCustomers,
            'top_brands' => $topBrands,
        ];
    }

    private function normalizePeriod(string $period): string
    {
        return in_array($period, ['week', 'bi-weekly', 'monthly', 'quarterly', 'half-yearly', 'yearly'], true)
            ? $period
            : 'week';
    }

    private function financialYearMeta(): array
    {
        $today = now();
        $fyStartYear = $today->month >= 4 ? $today->year : $today->year - 1;
        $start = Carbon::create($fyStartYear, 4, 1, 0, 0, 0, $today->timezone);
        $end = Carbon::create($fyStartYear + 1, 3, 31, 23, 59, 59, $today->timezone);

        return [
            'label' => sprintf('FY %d-%02d', $start->year, $end->year % 100),
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    private function resolveWindow(string $period): array
    {
        $today = now()->endOfDay();
        $fy = $this->financialYearMeta();
        $fyStart = Carbon::parse($fy['start'])->startOfDay();
        $dataStart = Carbon::parse(self::REPORT_DATA_START_DATE, $today->timezone)->startOfDay();
        $reportStart = $fyStart->greaterThan($dataStart) ? $fyStart->copy() : $dataStart->copy();

        [$start, $bucketMode] = match ($period) {
            'bi-weekly' => [$today->copy()->subDays(13)->startOfDay(), 'day'],
            'monthly' => [$reportStart->copy()->startOfMonth(), 'fy-month'],
            'quarterly' => [$reportStart->copy(), 'fy-quarter'],
            'half-yearly' => [$reportStart->copy(), 'fy-half'],
            'yearly' => [$dataStart->copy(), 'fy-year'],
            default => [$today->copy()->subDays(6)->startOfDay(), 'day'],
        };

        if ($start->lt($reportStart) && in_array($bucketMode, ['day'], true)) {
            $start = $reportStart->copy();
        }

        return [$start->copy()->startOfDay(), $today->copy(), $bucketMode];
    }

    private function makeBuckets(Carbon $start, Carbon $end, string $bucketMode): array
    {
        if (str_starts_with($bucketMode, 'fy-')) {
            return $this->makeFinancialYearBuckets($start, $end, $bucketMode);
        }

        $buckets = [];
        $cursor = match ($bucketMode) {
            'week' => $start->copy()->startOfDay(),
            'month' => $start->copy()->startOfMonth()->startOfDay(),
            default => $start->copy()->startOfDay(),
        };

        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy();
            $bucketEnd = match ($bucketMode) {
                'day' => $cursor->copy()->endOfDay(),
                'week' => $cursor->copy()->addDays(6)->endOfDay(),
                'month' => $cursor->copy()->endOfMonth()->endOfDay(),
            };

            if ($bucketEnd->gt($end)) {
                $bucketEnd = $end->copy();
            }

            $buckets[] = [
                'start' => $bucketStart,
                'end' => $bucketEnd,
                'label' => $this->bucketLabel($bucketStart, $bucketEnd, $bucketMode),
                'order_value' => 0.0,
                'discount' => 0.0,
                'service_charge' => 0.0,
                'voucher_amount' => 0.0,
            ];

            $cursor = match ($bucketMode) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow()->startOfMonth(),
            };
        }

        return $buckets;
    }

    private function bucketLabel(Carbon $start, Carbon $end, string $bucketMode): string
    {
        return match ($bucketMode) {
            'day' => $start->format('d M'),
            'week' => $start->format('d M') . ' - ' . $end->format('d M'),
            'month' => $start->format('M Y'),
            'fy-month' => $start->format('M Y'),
            'fy-quarter', 'fy-half' => $start->format('M') . ' - ' . $end->format('M'),
            'fy-year' => (string) $end->year,
            default => $start->format('d M'),
        };
    }

    private function makeFinancialYearBuckets(Carbon $start, Carbon $end, string $bucketMode): array
    {
        $buckets = [];

        if ($bucketMode === 'fy-month') {
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $bucketStart = $cursor->copy();
                $bucketEnd = $cursor->copy()->endOfMonth()->endOfDay();
                $this->appendBucket($buckets, $bucketStart, $bucketEnd, $start, $end, $bucketMode);
                $cursor->addMonthNoOverflow()->startOfMonth();
            }

            return $buckets;
        }

        if ($bucketMode === 'fy-quarter') {
            foreach ($this->financialYearQuarterRanges($end) as [$bucketStart, $bucketEnd]) {
                $this->appendBucket($buckets, $bucketStart, $bucketEnd, $start, $end, $bucketMode);
            }

            return $buckets;
        }

        if ($bucketMode === 'fy-half') {
            foreach ($this->financialYearHalfRanges($end) as [$bucketStart, $bucketEnd]) {
                $this->appendBucket($buckets, $bucketStart, $bucketEnd, $start, $end, $bucketMode);
            }

            return $buckets;
        }

        $cursor = $this->financialYearStartFor($start);
        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy();
            $bucketEnd = $cursor->copy()->addYear()->subDay()->endOfDay();
            $this->appendBucket($buckets, $bucketStart, $bucketEnd, $start, $end, $bucketMode);
            $cursor->addYear();
        }

        return $buckets;
    }

    private function appendBucket(array &$buckets, Carbon $bucketStart, Carbon $bucketEnd, Carbon $reportStart, Carbon $reportEnd, string $bucketMode): void
    {
        if ($bucketEnd->lt($reportStart) || $bucketStart->gt($reportEnd)) {
            return;
        }

        $queryStart = $bucketStart->greaterThan($reportStart) ? $bucketStart->copy() : $reportStart->copy();
        $queryEnd = $bucketEnd->lessThan($reportEnd) ? $bucketEnd->copy() : $reportEnd->copy();

        $buckets[] = [
            'start' => $queryStart,
            'end' => $queryEnd,
            'label' => $this->bucketLabel($bucketStart, $bucketEnd, $bucketMode),
            'order_value' => 0.0,
            'discount' => 0.0,
            'service_charge' => 0.0,
            'voucher_amount' => 0.0,
        ];
    }

    private function financialYearStartFor(Carbon $date): Carbon
    {
        $year = $date->month >= 4 ? $date->year : $date->year - 1;
        return Carbon::create($year, 4, 1, 0, 0, 0, $date->timezone);
    }

    private function financialYearQuarterRanges(Carbon $date): array
    {
        $fyStartYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return [
            [Carbon::create($fyStartYear, 4, 1, 0, 0, 0, $date->timezone), Carbon::create($fyStartYear, 6, 30, 23, 59, 59, $date->timezone)],
            [Carbon::create($fyStartYear, 7, 1, 0, 0, 0, $date->timezone), Carbon::create($fyStartYear, 9, 30, 23, 59, 59, $date->timezone)],
            [Carbon::create($fyStartYear, 10, 1, 0, 0, 0, $date->timezone), Carbon::create($fyStartYear, 12, 31, 23, 59, 59, $date->timezone)],
            [Carbon::create($fyStartYear + 1, 1, 1, 0, 0, 0, $date->timezone), Carbon::create($fyStartYear + 1, 3, 31, 23, 59, 59, $date->timezone)],
        ];
    }

    private function financialYearHalfRanges(Carbon $date): array
    {
        $fyStartYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return [
            [Carbon::create($fyStartYear, 4, 1, 0, 0, 0, $date->timezone), Carbon::create($fyStartYear, 9, 30, 23, 59, 59, $date->timezone)],
            [Carbon::create($fyStartYear, 10, 1, 0, 0, 0, $date->timezone), Carbon::create($fyStartYear + 1, 3, 31, 23, 59, 59, $date->timezone)],
        ];
    }

    private function locateBucketIndex(array $buckets, Carbon $date): ?int
    {
        foreach ($buckets as $index => $bucket) {
            if ($date->betweenIncluded($bucket['start'], $bucket['end'])) {
                return $index;
            }
        }

        return null;
    }

    private function rangeLabel(Carbon $start, Carbon $end): string
    {
        return $start->format('d M Y') . ' - ' . $end->format('d M Y');
    }
}
