<?php

use App\Models\TelkomselApiLog;
use App\Services\TelkomselVoucherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['telkomsel.mock' => true]);
});

test('check voucher returns a valid normalized response in mock mode', function () {
    $result = app(TelkomselVoucherService::class)->checkVoucher('300338120354');

    expect($result['is_valid'])->toBeTrue()
        ->and($result['status_code'])->toBe(1)
        ->and($result['status_message'])->toBe('Tersedia')
        ->and($result['serial_number'])->toBe('300338120354')
        ->and($result['name'])->not->toBeEmpty()
        ->and($result['validity'])->toBe('5')
        ->and($result['expired_date'])->not->toBeEmpty()
        ->and($result['region'])->not->toBeEmpty();
});

test('redeem voucher returns a successful normalized response in mock mode', function () {
    $result = app(TelkomselVoucherService::class)->redeemVoucher('71125613431848001', '082233456777');

    expect($result['success'])->toBeTrue()
        ->and($result['code'])->toBe('00');
});

test('mock mode never performs a real http request', function () {
    Http::fake();

    $service = app(TelkomselVoucherService::class);
    $service->checkVoucher('300338120354');
    $service->redeemVoucher('71125613431848001', '082233456777');

    Http::assertNothingSent();
});

test('format msisdn converts numbers to 62 format', function (string $input, string $expected) {
    expect(app(TelkomselVoucherService::class)->formatMsisdnTo62($input))->toBe($expected);
})->with([
    ['082233456777', '6282233456777'],
    ['6282233456777', '6282233456777'],
    ['82233456777', '6282233456777'],
    ['0822-3345-6777', '6282233456777'],
    [' 0822 3345 6777 ', '6282233456777'],
]);

test('check voucher writes an audit log record', function () {
    app(TelkomselVoucherService::class)->checkVoucher('300338120354');

    $log = TelkomselApiLog::query()->where('endpoint', 'check')->sole();

    expect($log->response_code)->toBe(200)
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($log->request_payload['serialNumber'])->toBe('300338120354');
});

test('redeem voucher masks the hrn in the audit log', function () {
    $hrn = '71125613431848001';

    app(TelkomselVoucherService::class)->redeemVoucher($hrn, '082233456777');

    $log = TelkomselApiLog::query()->where('endpoint', 'redeem')->sole();

    expect($log->request_payload['hrn'])->toBe('****8001')
        ->and($log->request_payload['msisdn'])->toBe('6282233456777');

    $stored = json_encode($log->request_payload).json_encode($log->response_payload);

    expect($stored)->not->toContain($hrn);
});

test('check voucher parses an already used voucher response', function () {
    config(['telkomsel.mock' => false]);

    Http::fake([
        '*/check' => Http::response([
            'statusCode' => 3,
            'statusMessage' => 'Sudah Digunakan',
            'serialNumber' => '300338120354',
        ]),
    ]);

    $result = app(TelkomselVoucherService::class)->checkVoucher('300338120354');

    expect($result['is_valid'])->toBeFalse()
        ->and($result['status_code'])->toBe(3)
        ->and($result['status_message'])->toBe('Sudah Digunakan');

    Http::assertSent(fn ($request) => $request->url() === config('telkomsel.base_url').'/check');
});

test('check voucher parses an invalid voucher response', function () {
    config(['telkomsel.mock' => false]);

    Http::fake([
        '*/check' => Http::response([
            'statusCode' => -1,
            'statusMessage' => 'Voucher Invalid',
            'serialNumber' => '',
        ]),
    ]);

    $result = app(TelkomselVoucherService::class)->checkVoucher('100338120354');

    expect($result['is_valid'])->toBeFalse()
        ->and($result['status_code'])->toBe(-1);
});

test('redeem voucher parses an already used code', function () {
    config(['telkomsel.mock' => false]);

    Http::fake([
        '*/redeem' => Http::response([
            'status' => true,
            'message' => 400,
            'data' => [
                'code' => '15',
                'description' => 'VoucherAlreadyUsed',
            ],
        ], 400),
    ]);

    $result = app(TelkomselVoucherService::class)->redeemVoucher('71125613431848001', '082233456777');

    expect($result['success'])->toBeFalse()
        ->and($result['code'])->toBe('15')
        ->and($result['description'])->toBe('VoucherAlreadyUsed');
});

test('check voucher normalizes a connection failure instead of throwing', function () {
    config(['telkomsel.mock' => false]);

    Http::fake([
        '*/check' => fn () => throw new ConnectionException('Connection timed out'),
    ]);

    $result = app(TelkomselVoucherService::class)->checkVoucher('300338120354');

    expect($result['is_valid'])->toBeFalse()
        ->and($result['status_code'])->toBe(-1)
        ->and($result['status_message'])->toContain('Connection');

    expect(TelkomselApiLog::query()->where('endpoint', 'check')->count())->toBe(1);
});

test('redeem voucher normalizes a connection failure instead of throwing', function () {
    config(['telkomsel.mock' => false]);

    Http::fake([
        '*/redeem' => fn () => throw new ConnectionException('Connection timed out'),
    ]);

    $result = app(TelkomselVoucherService::class)->redeemVoucher('71125613431848001', '082233456777');

    expect($result['success'])->toBeFalse()
        ->and($result['code'])->toBe('ERROR');

    expect(TelkomselApiLog::query()->where('endpoint', 'redeem')->count())->toBe(1);
});
