<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCpfCnpj implements ValidationRule
{
    public function __construct(private readonly string $personType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $document = (string) $value;
        $valid = $this->personType === 'PF'
            ? $this->validCpf($document)
            : ($this->personType === 'PJ' && $this->validCnpj($document));

        if (! $valid) {
            $fail('Informe um CPF ou CNPJ válido.');
        }
    }

    private function validCpf(string $cpf): bool
    {
        if (! preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($length = 9; $length <= 10; $length++) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cpf[$i] * ($length + 1 - $i);
            }
            $digit = ($sum * 10) % 11;
            if ($digit === 10) {
                $digit = 0;
            }
            if ((int) $cpf[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function validCnpj(string $cnpj): bool
    {
        if (! preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;
            foreach ($weights as $index => $weight) {
                $sum += (int) $cnpj[$index] * $weight;
            }
            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int) $cnpj[count($weights)] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
