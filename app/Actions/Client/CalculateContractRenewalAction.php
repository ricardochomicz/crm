<?php

namespace App\Actions\Client;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CalculateContractRenewalAction
{
    /**
     * Calcula a previsão de renovação integral a partir das linhas ativadas.
     *
     * @param Collection<int, object> $items
     * @return array{average_activation_date: string, renewal_date: string, line_count: int}
     */
    public function execute(Collection $items, int $renewalMonths): array
    {
        if ($renewalMonths < 1) {
            throw ValidationException::withMessages([
                'renewal_months' => 'O prazo de renovação deve ser maior que zero.',
            ]);
        }

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'O contrato precisa possuir linhas para calcular a renovação.',
            ]);
        }

        $totalDays = 0;
        $totalLines = 0;

        foreach ($items as $item) {
            if ($item->status !== 'ACTIVE' || empty($item->activated_at)) {
                throw ValidationException::withMessages([
                    'items' => 'Todas as linhas do contrato devem estar ativas para calcular a renovação integral.',
                ]);
            }

            $quantity = (int) $item->quantity;

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'items' => 'A quantidade de cada item deve ser maior que zero.',
                ]);
            }

            $activation = CarbonImmutable::parse($item->activated_at)->startOfDay();
            $totalDays += $activation->getTimestamp() / 86400 * $quantity;
            $totalLines += $quantity;
        }

        $averageDays = (int) round($totalDays / $totalLines);
        $averageDate = CarbonImmutable::createFromTimestampUTC($averageDays * 86400);

        return [
            'average_activation_date' => $averageDate->toDateString(),
            'renewal_date' => $averageDate->addMonthsNoOverflow($renewalMonths)->toDateString(),
            'line_count' => $totalLines,
        ];
    }
}
