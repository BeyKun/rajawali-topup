<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VoucherCheckRequest;
use App\Http\Requests\Admin\VoucherRequest;
use App\Http\Requests\Admin\VoucherUpdateRequest;
use App\Jobs\ValidateVoucherBatchJob;
use App\Models\Voucher;
use App\Services\TelkomselVoucherService;
use App\Services\VoucherInventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class VoucherController extends Controller
{
    public function __construct(
        private readonly TelkomselVoucherService $telkomsel,
        private readonly VoucherInventoryService $inventory,
    ) {}

    /**
     * Display a filtered, paginated list of vouchers plus per-status counts.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
        ];

        $isSuperAdmin = (bool) $request->user()?->isSuperAdmin();
        $cities = $isSuperAdmin
            ? \App\Models\City::query()
                ->with('province:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (\App\Models\City $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'province_name' => $c->province?->name,
                ])
                ->all()
            : [];

        $vouchers = Voucher::query()
            ->with(['product:id,name,sell_price,hpp_price', 'cities:id'])
            ->forCity($request->user()?->isKabupatenAdmin() ? $request->user()->city_id : null)
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('serial_number', 'like', "%{$search}%")
                        ->orWhere('redeemed_msisdn', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Voucher $voucher): array => [
                'id' => $voucher->id,
                'serial_number' => $voucher->serial_number,
                'product_name' => $voucher->product?->name,
                'sell_price' => $voucher->product !== null ? (float) $voucher->product->sell_price : null,
                'hpp_price' => $voucher->product !== null ? (float) $voucher->product->hpp_price : null,
                'margin_percentage' => ($voucher->product !== null && (float) $voucher->product->hpp_price > 0)
                    ? (float) round((((float) $voucher->product->sell_price - (float) $voucher->product->hpp_price) / (float) $voucher->product->hpp_price) * 100, 2)
                    : 0.0,
                'city_ids' => $voucher->cities->pluck('id')->all() ?: ($voucher->city_id ? [$voucher->city_id] : []),
                'status' => $voucher->status->value,
                'region' => $voucher->region,
                'expired_date' => $voucher->expired_date,
                'redeemed_msisdn' => $voucher->redeemed_msisdn,
                'created_at' => $voucher->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/vouchers/Index', [
            'vouchers' => $vouchers,
            'filters' => $filters,
            'statusCounts' => $this->statusCounts($request->user()?->isKabupatenAdmin() ? $request->user()->city_id : null),
            'is_super_admin' => $isSuperAdmin,
            'cities' => $cities,
        ]);
    }

    /**
     * Show the single voucher input form.
     */
    public function create(Request $request): Response
    {
        $isSuperAdmin = (bool) $request->user()?->isSuperAdmin();
        $cities = $isSuperAdmin
            ? \App\Models\City::query()
                ->with('province:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (\App\Models\City $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'province_name' => $c->province?->name,
                ])
                ->all()
            : [];

        return Inertia::render('admin/vouchers/Create', [
            'is_super_admin' => $isSuperAdmin,
            'cities' => $cities,
        ]);
    }

    /**
     * Validate a serial number against Telkomsel and return the preview.
     *
     * This endpoint never persists anything; it is only used by the AJAX
     * "Cek Validitas" button before the operator submits the form.
     */
    public function check(VoucherCheckRequest $request): JsonResponse
    {
        $result = $this->telkomsel->checkVoucher($request->string('serial_number')->toString());

        return response()->json([
            'name' => $result['name'],
            'description' => $result['description'],
            'validity' => $result['validity'],
            'expired_date' => $result['expired_date'],
            'region' => $result['region'],
            'status_code' => $result['status_code'],
            'is_valid' => $result['is_valid'],
            'status_message' => $result['status_message'],
        ]);
    }

    /**
     * Validate and store a single voucher into the inventory.
     */
    public function store(VoucherRequest $request): RedirectResponse
    {
        $cityIds = $request->user()?->isSuperAdmin()
            ? (array) $request->input('city_ids', [])
            : ($request->user()?->city_id ? [$request->user()->city_id] : []);

        $basePrice = (float) $request->float('sell_price');
        $marginPercentage = $request->filled('margin_percentage')
            ? (float) $request->float('margin_percentage')
            : 0.0;

        $finalSellPrice = $marginPercentage > 0
            ? (float) round($basePrice * (1 + ($marginPercentage / 100)))
            : $basePrice;

        try {
            $voucher = $this->inventory->addVoucher(
                $request->string('serial_number')->toString(),
                $request->string('hrn')->toString(),
                $finalSellPrice,
                $request->user()?->id,
                $cityIds,
                hppPrice: $basePrice,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Voucher {$voucher->serial_number} berhasil ditambahkan ke inventori.",
        ]);

        return to_route('admin.vouchers.index');
    }

    /**
     * Update an existing voucher.
     */
    public function update(VoucherUpdateRequest $request, Voucher $voucher): RedirectResponse
    {
        if ($request->user()?->isKabupatenAdmin()) {
            $userCityId = $request->user()->city_id;
            $hasAccess = $voucher->city_id === $userCityId || $voucher->cities()->where('cities.id', $userCityId)->exists();
            abort_unless($hasAccess, 403, 'Anda tidak berhak mengubah voucher di luar wilayah Anda.');
        }

        $basePrice = (float) $request->float('sell_price');
        $marginPercentage = $request->filled('margin_percentage')
            ? (float) $request->float('margin_percentage')
            : 0.0;

        $finalSellPrice = $marginPercentage > 0
            ? (float) round($basePrice * (1 + ($marginPercentage / 100)))
            : $basePrice;

        $voucher->serial_number = $request->string('serial_number')->toString();
        if ($request->filled('hrn')) {
            $voucher->hrn = $request->string('hrn')->toString();
        }
        $voucher->status = VoucherStatus::from($request->string('status')->toString());

        if ($request->user()?->isSuperAdmin()) {
            $cityIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('city_ids', [])))));
            $voucher->cities()->sync($cityIds);
            $voucher->city_id = count($cityIds) === 1 ? $cityIds[0] : null;
        }

        $voucher->save();

        if ($voucher->product !== null) {
            $voucher->product->update([
                'sell_price' => $finalSellPrice,
                'hpp_price' => $basePrice,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Voucher {$voucher->serial_number} berhasil diperbarui.",
        ]);

        return to_route('admin.vouchers.index');
    }

    /**
     * Delete a voucher, but only while it is still available.
     */
    public function destroy(Voucher $voucher): RedirectResponse
    {
        try {
            $this->inventory->deleteAvailableVoucher($voucher);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voucher berhasil dihapus.']);

        return to_route('admin.vouchers.index');
    }

    /**
     * Show the bulk import form.
     */
    public function bulk(Request $request): Response
    {
        $isSuperAdmin = (bool) $request->user()?->isSuperAdmin();
        $cities = $isSuperAdmin
            ? \App\Models\City::query()
                ->with('province:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (\App\Models\City $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'province_name' => $c->province?->name,
                ])
                ->all()
            : [];

        return Inertia::render('admin/vouchers/Bulk', [
            'is_super_admin' => $isSuperAdmin,
            'cities' => $cities,
        ]);
    }

    /**
     * Parse an uploaded CSV and dispatch a background validation job.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $rules = [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'margin_percentage' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($request->user()?->isSuperAdmin()) {
            $rules['city_ids'] = ['nullable', 'array'];
            $rules['city_ids.*'] = ['integer', 'exists:cities,id'];
        }

        $validated = $request->validate($rules);

        $cityIds = $request->user()?->isSuperAdmin()
            ? (array) ($validated['city_ids'] ?? [])
            : ($request->user()?->city_id ? [$request->user()->city_id] : []);

        $marginPercentage = $request->filled('margin_percentage')
            ? (float) $request->float('margin_percentage')
            : null;

        try {
            $rows = $this->parseCsv($request->file('file')->getRealPath());
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => 'Gagal membaca file: '.$exception->getMessage(),
            ]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'File tidak memuat baris data yang valid.',
            ]);
        }

        Storage::disk('local')->put(
            'voucher-imports/'.now()->format('YmdHis').'-'.uniqid().'.csv',
            $request->file('file')->getContent() ?? '',
        );

        ValidateVoucherBatchJob::dispatch($rows, $request->user()?->id, $cityIds, $marginPercentage);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Import %d baris diproses di background. Cek daftar voucher untuk hasilnya.', count($rows)),
        ]);

        return to_route('admin.vouchers.index');
    }

    /**
     * Per-status voucher counters for the index summary.
     *
     * @return array<string, int>
     */
    private function statusCounts(?int $cityId = null): array
    {
        $raw = Voucher::query()
            ->forCity($cityId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];

        foreach (VoucherStatus::cases() as $status) {
            $counts[$status->value] = (int) $raw->get($status->value, 0);
        }

        return $counts;
    }

    /**
     * Parse a CSV file into normalized voucher rows.
     *
     * The parser tolerates comma, semicolon or tab delimiters and skips an
     * optional header row whose first cell is not a serial number.
     *
     * @return array<int, array{serial_number: string, hrn: string, sell_price: float}>
     */
    private function parseCsv(string|false $path): array
    {
        if ($path === false || ! is_readable($path)) {
            throw new \RuntimeException('Berkas tidak dapat dibaca.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Berkas tidak dapat dibuka.');
        }

        $rows = [];
        $isHeader = true;
        $delimiter = $this->detectDelimiter($path);

        while (($line = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }

            [$serial, $hrn, $sellPrice] = array_pad($line, 3, null);

            $serial = trim((string) $serial);
            $hrn = trim((string) $hrn);
            $sellPrice = preg_replace('/[^0-9.]/', '', trim((string) $sellPrice)) ?? '';

            if ($isHeader) {
                $isHeader = false;

                if (! ctype_digit($serial)) {
                    continue;
                }
            }

            if ($serial === '' || $hrn === '' || $sellPrice === '' || ! is_numeric($sellPrice)) {
                continue;
            }

            $rows[] = [
                'serial_number' => $serial,
                'hrn' => $hrn,
                'sell_price' => (float) $sellPrice,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Best-effort delimiter detection for a CSV file (tab, semicolon, comma).
     */
    private function detectDelimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 4096);

        $candidates = ["\t" => substr_count($sample, "\t"), ';' => substr_count($sample, ';'), ',' => substr_count($sample, ',')];

        arsort($candidates);

        $delimiter = (string) array_key_first($candidates);

        return $delimiter === '' ? ',' : $delimiter;
    }
}
