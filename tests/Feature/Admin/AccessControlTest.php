<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to login from admin routes', function () {
    $this->get('/admin/dashboard')->assertRedirect(route('login'));
    $this->get('/admin/products')->assertRedirect(route('login'));
    $this->get('/admin/vouchers')->assertRedirect(route('login'));
    $this->get('/admin/orders')->assertRedirect(route('login'));
});

test('customers are forbidden from admin routes', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($customer)->get('/admin/vouchers')->assertForbidden();
    $this->actingAs($customer)->get('/admin/orders')->assertForbidden();
});

test('super admins can access admin routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    $this->actingAs($admin)->get('/admin/vouchers')->assertOk();
    $this->actingAs($admin)->get('/admin/orders')->assertOk();
});

test('operators can access admin routes', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)->get('/admin/dashboard')->assertOk();
    $this->actingAs($operator)->get('/admin/vouchers')->assertOk();
    $this->actingAs($operator)->get('/admin/orders')->assertOk();
});

test('the removed category routes no longer exist', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/categories')->assertNotFound();
});

test('admins landing on dashboard are redirected to the admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertRedirect('/admin/dashboard');
});
