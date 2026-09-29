<?php

test('guests cannot create orders', function () {
    $this->postJson('/api/v1/orders', [
        'product_id' => 1,
        'msisdn' => '082233456777',
    ])->assertStatus(401);
});

test('guests cannot view order status', function () {
    $this->getJson('/api/v1/orders/RJW-20260101-0001')->assertStatus(401);
});

test('guests cannot view order history', function () {
    $this->getJson('/api/v1/orders/history')->assertStatus(401);
});

test('the product catalog is publicly accessible', function () {
    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('success', true);
});
