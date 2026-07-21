<?php
namespace App\Http\Controllers\Api\Campaigns;

use App\Mail\CampaignOtpMail;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SendVoucherProduct;
use App\Models\VoucherCampaign;
use App\Models\VoucherCampaignProduct;
use App\Models\BillingOtpApprover;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class VoucherCampaignController extends Controller
{
    public function index()
    {
        return response()->json(VoucherCampaign::withCount('customers')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:voucher_campaigns,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'required_otp_confirmation' => ['sometimes', 'boolean'],
        ]);

        return response()->json(VoucherCampaign::create($data), 201);
    }

    public function update(VoucherCampaign $campaign, Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:voucher_campaigns,name,' . $campaign->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'required_otp_confirmation' => ['sometimes', 'boolean'],
        ]);

        if (! $campaign->required_otp_confirmation) {
            $campaign->update($data);
            return response()->json($campaign->fresh()->loadCount('customers'));
        }

        return $this->queueCampaignOtp(
            $request,
            $campaign,
            'update',
            $data,
            'Campaign settings',
            $this->campaignChangesForUpdate($campaign, $data)
        );
    }

    public function products(VoucherCampaign $campaign)
    {
        $settings = $campaign->products()->get()->keyBy('product_id');

        return response()->json([
            'data' => SendVoucherProduct::where('is_active', true)
                ->where('is_blacklisted', false)
                ->orderBy('brand')
                ->get()
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'currency_code' => $product->currency_code,
                    'global_margin_percentage' => (float) $product->global_margin_percentage,
                    'discount_percentage' => (float) ($settings->get($product->id)?->discount_percentage ?? 0),
                    'is_blacklisted' => $settings->get($product->id)?->is_blacklisted ?? false,
                ])->values(),
        ]);
    }

    public function saveProducts(VoucherCampaign $campaign, Request $request)
    {
        $data = $request->validate([
            'products' => ['present', 'array'],
            'products.*.product_id' => ['required', 'integer', 'distinct', 'exists:send_voucher_products,id'],
            'products.*.discount_percentage' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'products.*.is_blacklisted' => ['sometimes', 'boolean'],
        ]);

        $globalBlacklistedIds = SendVoucherProduct::whereIn('id', collect($data['products'])->pluck('product_id'))
            ->where('is_blacklisted', true)
            ->pluck('id')
            ->all();

        if (! empty($globalBlacklistedIds)) {
            return response()->json([
                'message' => 'Globally blacklisted products cannot be used in campaign rules.',
                'product_ids' => $globalBlacklistedIds,
            ], 422);
        }

        if ($campaign->required_otp_confirmation) {
            $changes = $this->campaignProductChanges($campaign, $data['products']);
            return $this->queueCampaignOtp(
                $request,
                $campaign,
                'save_products',
                ['products' => $data['products']],
                'Campaign product rules',
                $changes
            );
        }

        DB::transaction(function () use ($campaign, $data) {
            $ids = collect($data['products'])->pluck('product_id')->all();
            $query = $campaign->products();
            $ids ? $query->whereNotIn('product_id', $ids)->delete() : $query->delete();
            foreach ($data['products'] as $product) {
                VoucherCampaignProduct::updateOrCreate(
                    ['campaign_id' => $campaign->id, 'product_id' => $product['product_id']],
                    [
                        'discount_percentage' => $product['discount_percentage'] ?? 0,
                        'is_blacklisted' => $product['is_blacklisted'] ?? false,
                    ]
                );
            }
        });

        return response()->json(['message' => 'Campaign rules saved.']);
    }

    public function customers(VoucherCampaign $campaign)
    {
        return response()->json($campaign->customers()->orderBy('company_name')->get());
    }

    public function saveCustomers(VoucherCampaign $campaign, Request $request)
    {
        $data = $request->validate([
            'customer_ids' => ['present', 'array'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
        ]);

        if ($campaign->required_otp_confirmation) {
            return $this->queueCampaignOtp(
                $request,
                $campaign,
                'save_customers',
                ['customer_ids' => $data['customer_ids']],
                'Campaign customer mapping',
                $this->campaignCustomerChanges($campaign, $data['customer_ids'])
            );
        }

        DB::transaction(function () use ($campaign, $data) {
            DB::table('voucher_campaign_customers')->where('campaign_id', $campaign->id)->delete();
            $campaign->customers()->sync($data['customer_ids']);
        });

        return response()->json(['message' => 'Customers assigned to campaign.']);
    }

    public function verifyCampaignOtp(VoucherCampaign $campaign, Request $request)
    {
        $data = $request->validate([
            'request_id' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $cacheKey = $this->campaignOtpCacheKey($campaign->id, $request->user()->id, $data['request_id']);
        $pending = Cache::get($cacheKey);

        if (! $pending) {
            return response()->json(['message' => 'OTP request expired or not found. Please save the campaign again.'], 422);
        }

        if (($pending['otp'] ?? null) !== $data['otp']) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        $expiresAt = data_get($pending, 'expires_at');
        if ($expiresAt && now()->greaterThan(Carbon::parse($expiresAt))) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'OTP expired. Please save the campaign again.'], 422);
        }

        DB::transaction(function () use ($campaign, $pending) {
            $action = $pending['action'] ?? '';
            $payload = $pending['payload'] ?? [];

            if ($action === 'update') {
                $campaign->update($payload);
                return;
            }

            if ($action === 'save_products') {
                $ids = collect($payload['products'] ?? [])->pluck('product_id')->all();
                $query = $campaign->products();
                $ids ? $query->whereNotIn('product_id', $ids)->delete() : $query->delete();
                foreach ($payload['products'] ?? [] as $product) {
                    VoucherCampaignProduct::updateOrCreate(
                        ['campaign_id' => $campaign->id, 'product_id' => $product['product_id']],
                        [
                            'discount_percentage' => $product['discount_percentage'] ?? 0,
                            'is_blacklisted' => $product['is_blacklisted'] ?? false,
                        ]
                    );
                }
                return;
            }

            if ($action === 'save_customers') {
                DB::table('voucher_campaign_customers')->where('campaign_id', $campaign->id)->delete();
                $campaign->customers()->sync($payload['customer_ids'] ?? []);
            }
        });

        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Campaign changes saved successfully.',
            'campaign' => $campaign->fresh()->loadCount('customers'),
        ]);
    }

    public function resendCampaignOtp(VoucherCampaign $campaign, Request $request)
    {
        $data = $request->validate([
            'request_id' => ['required', 'string'],
        ]);

        $cacheKey = $this->campaignOtpCacheKey($campaign->id, $request->user()->id, $data['request_id']);
        $pending = Cache::get($cacheKey);

        if (! $pending) {
            return response()->json(['message' => 'OTP request expired or not found. Please save the campaign again.'], 422);
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);
        $pending['otp'] = $otp;
        $pending['expires_at'] = $expiresAt->toIso8601String();
        Cache::put($cacheKey, $pending, $expiresAt);

        $this->sendCampaignOtpMail(
            $pending['recipients'] ?? $this->approverEmails('campaign_changes'),
            $campaign,
            $otp,
            $pending['changes'] ?? [],
            $pending['requested_by'] ?? ($request->user()->name ?: $request->user()->email),
            $expiresAt
        );

        return response()->json([
            'message' => 'OTP resent successfully. Please verify within 10 minutes.',
            'request_id' => $data['request_id'],
            'expires_at' => $expiresAt->toIso8601String(),
            'recipients' => $pending['recipients'] ?? [],
        ]);
    }

    public function customerCampaign(Customer $customer)
    {
        return response()->json($customer->belongsToMany(VoucherCampaign::class, 'voucher_campaign_customers', 'customer_id', 'campaign_id')->where('is_active', true)->first());
    }

    private function queueCampaignOtp(Request $request, VoucherCampaign $campaign, string $action, array $payload, string $contextLabel, array $changes)
    {
        $requestId = (string) Str::uuid();
        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);
        $recipients = $this->approverEmails('campaign_changes');
        $requestedBy = $request->user()->name ?: $request->user()->email;

        $cachePayload = [
            'request_id' => $requestId,
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'action' => $action,
            'payload' => $payload,
            'changes' => $changes,
            'requested_by' => $requestedBy,
            'otp' => $otp,
            'recipients' => $recipients,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put($this->campaignOtpCacheKey($campaign->id, $request->user()->id, $requestId), $cachePayload, $expiresAt);

        try {
            $this->sendCampaignOtpMail($recipients, $campaign, $otp, $changes, $requestedBy, $contextLabel, $expiresAt);
        } catch (\Throwable $e) {
            Cache::forget($this->campaignOtpCacheKey($campaign->id, $request->user()->id, $requestId));
            throw $e;
        }

        return response()->json([
            'message' => 'OTP sent to approved admin emails. Please verify within 10 minutes to save campaign changes.',
            'requires_otp' => true,
            'request_id' => $requestId,
            'expires_at' => $expiresAt->toIso8601String(),
            'recipients' => $recipients,
        ]);
    }

    private function sendCampaignOtpMail(array $recipients, VoucherCampaign $campaign, string $otp, array $changes, string $requestedBy, string $contextLabel, \DateTimeInterface $expiresAt): void
    {
        Mail::to($recipients)->send(new CampaignOtpMail(
            campaign: $campaign,
            otp: $otp,
            changes: $changes,
            requestedBy: $requestedBy,
            contextLabel: $contextLabel,
            expiresAt: $expiresAt
        ));
    }

    private function approverEmails(string $groupKey): array
    {
        $group = BillingOtpApprover::where('group_key', $groupKey)->where('is_active', true)->first();
        return array_values(array_unique(array_filter($group?->emails ?: ['naveentitare52@gmail.com', 'ptitare@gmail.com'])));
    }

    private function campaignOtpCacheKey(int $campaignId, int $userId, string $requestId): string
    {
        return "voucher-campaign:otp:{$campaignId}:{$userId}:{$requestId}";
    }

    private function campaignChangesForUpdate(VoucherCampaign $campaign, array $data): array
    {
        $fields = [
            'name' => 'Campaign Name',
            'description' => 'Description',
            'is_active' => 'Status',
            'required_otp_confirmation' => 'Required OTP confirmation',
        ];

        $changes = [];
        foreach ($fields as $key => $label) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $oldValue = $campaign->{$key};
            $newValue = $data[$key];

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[] = [
                'label' => $label,
                'old' => is_bool($oldValue) ? ($oldValue ? 'TRUE' : 'FALSE') : (string) ($oldValue ?? ''),
                'new' => is_bool($newValue) ? ($newValue ? 'TRUE' : 'FALSE') : (string) ($newValue ?? ''),
            ];
        }

        return $changes;
    }

    private function campaignProductChanges(VoucherCampaign $campaign, array $products): array
    {
        $current = $campaign->products()->get()->keyBy('product_id');

        return collect($products)
            ->map(function (array $product) use ($current) {
                $existing = $current->get($product['product_id']);
                $newDiscount = (float) ($product['discount_percentage'] ?? 0);
                $newBlacklist = (bool) ($product['is_blacklisted'] ?? false);

                if ($existing && (float) $existing->discount_percentage === $newDiscount && (bool) $existing->is_blacklisted === $newBlacklist) {
                    return null;
                }

                return [
                    'brand' => $existing?->product?->brand ?? '',
                    'name' => $existing?->product?->name ?? SendVoucherProduct::find($product['product_id'])?->name,
                    'old_discount_percentage' => (float) ($existing?->discount_percentage ?? 0),
                    'new_discount_percentage' => $newDiscount,
                    'old_is_blacklisted' => (bool) ($existing?->is_blacklisted ?? false),
                    'new_is_blacklisted' => $newBlacklist,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function campaignCustomerChanges(VoucherCampaign $campaign, array $customerIds): array
    {
        $currentIds = $campaign->customers()->pluck('customers.id')->all();
        if (collect($currentIds)->sort()->values()->all() === collect($customerIds)->sort()->values()->all()) {
            return [];
        }

        return [
            [
                'label' => 'Mapped customers',
                'old' => count($currentIds),
                'new' => count($customerIds),
            ],
        ];
    }
}
