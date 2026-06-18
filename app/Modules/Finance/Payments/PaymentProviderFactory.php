<?php

namespace App\Modules\Finance\Payments;

/**
 * Resolves the active payment gateway from config('finance.provider'). Mirrors
 * AiProviderFactory: a live provider chosen without working credentials degrades
 * to the deterministic fake provider rather than breaking every payment.
 */
class PaymentProviderFactory
{
    public static function make(?string $provider = null): PaymentProviderInterface
    {
        $provider ??= config('finance.provider', 'fake');

        $instance = match ($provider) {
            'stripe' => new StripePaymentProvider(),
            'paypal' => new PayPalPaymentProvider(),
            'square' => new SquarePaymentProvider(),
            default => new FakePaymentProvider(),
        };

        if (! $instance->isAvailable()) {
            return new FakePaymentProvider();
        }

        return $instance;
    }

    public static function default(): PaymentProviderInterface
    {
        return static::make();
    }
}
