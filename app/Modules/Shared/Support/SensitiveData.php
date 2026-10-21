<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

final class SensitiveData
{
    public static function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $name = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
            if (str_contains($name, 'secret') || str_contains($name, 'password') || str_ends_with($name, 'token')
                || in_array($name, ['card', 'creditcard', 'number', 'ccnumber', 'cardnumber', 'pan', 'cvv', 'cvc', 'password', 'passwordconfirmation',
                'secret', 'apikey', 'authorization', 'accesstoken', 'token', 'credentials', 'paymentdetails'], true)) {
                continue;
            }
            if (is_object($value)) {
                $value = (array) $value;
            }
            $clean[$key] = is_array($value) ? self::sanitize($value) : $value;
        }
        return $clean;
    }
}
