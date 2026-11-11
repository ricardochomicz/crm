<?php

namespace App\Rules;

use App\Enums\OrderType as LegacyOrderType;
use App\Models\OrderType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class ValidCommissionOrderType implements ValidationRule
{
    public function __construct(private readonly bool $activeOnly = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && LegacyOrderType::tryFrom($value) !== null) {
            return;
        }

        if (! is_string($value) || ! Str::isUuid($value)) {
            $fail('Selecione um tipo de pedido válido.');

            return;
        }

        $query = OrderType::query()->whereKey($value);

        if ($this->activeOnly) {
            $query->where('is_active', true);
        }

        if (! $query->exists()) {
            $fail($this->activeOnly
                ? 'Selecione um tipo de pedido válido e ativo.'
                : 'Selecione um tipo de pedido válido.');
        }
    }
}
