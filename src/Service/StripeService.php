<?php

namespace App\Service;

use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    public function __construct(
        private string $secretKey,
        private string $webhookSecret
    ) {
        Stripe::setApiKey($this->secretKey);
    }

    public function createPaymentIntent(float $amount, string $currency = 'eur', array $metadata = []): PaymentIntent
    {
        return PaymentIntent::create([
            'amount'   => (int) round($amount * 100),
            'currency' => $currency,
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ]);
    }

    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
    }
}
