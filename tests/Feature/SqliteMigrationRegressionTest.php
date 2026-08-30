<?php

use Illuminate\Support\Facades\Schema;

test('the inventory tracking migration can run on sqlite and create the required unique indexes', function () {
    $this->artisan('migrate:fresh')->assertSuccessful();

    expect(Schema::hasTable('inventory'))->toBeTrue()
        ->and(Schema::hasIndex('inventory', ['inventory_item_no']))->toBeTrue()
        ->and(Schema::hasIndex('inventory', ['qr_code']))->toBeTrue();
});
