<?php

namespace App\Http\Requests;

use App\DTOs\InventoryComparisonData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InventoryComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->role_name === 'Property Custodian';
    }

    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'accepted'],
            'metric' => ['required', 'string', 'in:stock_in_quantity,stock_out_quantity,request_count,request_quantity,assignment_count,assignment_quantity,transfer_count,transfer_quantity,maintenance_count,disposal_quantity'],
            'scope' => ['required', 'string', 'in:all,item'],
            'item_id' => ['nullable', 'integer', 'exists:inventory,item_id'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:64'],
            'month_one' => ['required', 'date_format:Y-m'],
            'month_two' => ['required', 'date_format:Y-m', 'different:month_one'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $quantityMetrics = [
                'stock_in_quantity', 'stock_out_quantity', 'request_quantity', 'assignment_quantity',
                'transfer_quantity', 'disposal_quantity',
            ];
            $metric = $this->input('metric');
            $unit = $this->input('unit');
            if (in_array($metric, $quantityMetrics, true) && $this->input('scope') === 'all'
                && (! is_string($unit) || trim($unit) === '')) {
                $validator->errors()->add('unit', 'Choose one inventory unit for a quantity comparison across all inventory.');
            }
            if ($unit !== null && ! in_array($metric, $quantityMetrics, true)) {
                $validator->errors()->add('unit', 'A unit can only be selected for a quantity comparison.');
            }
            if ($this->input('scope') === 'item' && ! $this->filled('item_id') && ! $this->filled('item_name')) {
                $validator->errors()->add('item_name', 'Choose an inventory item.');
            }
            if ($this->input('scope') !== 'item' && $this->filled('item_id')) {
                $validator->errors()->add('item_id', 'An inventory item can only be selected for item scope.');
            }

            foreach (['month_one', 'month_two'] as $field) {
                $value = $this->input($field);
                if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
                    continue;
                }

                if (CarbonImmutable::createFromFormat('!Y-m', $value)->greaterThanOrEqualTo(now()->startOfMonth())) {
                    $validator->errors()->add($field, 'Choose a completed calendar month.');
                }
            }
        });
    }

    public function comparisonData(): InventoryComparisonData
    {
        $validated = $this->validated();

        return new InventoryComparisonData(
            metric: $validated['metric'],
            scope: $validated['scope'],
            itemId: isset($validated['item_id']) ? (int) $validated['item_id'] : null,
            itemName: $validated['scope'] === 'item' && isset($validated['item_name']) ? trim($validated['item_name']) : null,
            unit: isset($validated['unit']) ? trim($validated['unit']) : null,
            monthOne: CarbonImmutable::createFromFormat('!Y-m', $validated['month_one']),
            monthTwo: CarbonImmutable::createFromFormat('!Y-m', $validated['month_two']),
        );
    }
}