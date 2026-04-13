<?php

namespace App\Controller;

use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    public function __construct(private StripeService $stripeService, private MailerInterface $mailer)
    {
    }

    #[Route('/payment/intent', name: 'payment_intent', methods: ['POST'])]
    public function createIntent(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getEmail')) {
            return new JsonResponse(['error' => 'Authentication required or email not available'], 401);
        }

        $email = $user->getEmail();

        $paymentIntent = $this->stripeService->createPaymentIntent(
            amount: 20.00,
            currency: 'eur',
            metadata: [
                'user_email' => $email,
            ]
        );

        return new JsonResponse([
            'client_secret'     => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
            'amount'            => 20.00,
            'currency'          => 'eur',
        ], 200);
    }

    #[Route('/payment/webhook', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        if (!$sigHeader) {
            return new Response('Missing Stripe-Signature header', 400);
        }

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $sigHeader);
        } catch (\Exception $e) {
            return new Response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $userEmail = $paymentIntent->metadata->user_email ?? null;
                if ($userEmail) {
                    $email = (new Email())
                        ->from($_ENV['MAILER_FROM'] ?? 'no-reply@example.com')
                        ->to($userEmail)
                        ->subject('Gràcies per la teva compra')
                        ->text(sprintf('Gràcies per la compra. Referència: %s', $paymentIntent->id));

                    try {
                        $this->mailer->send($email);
                    } catch (\Throwable $ex) {
                        error_log('Error sending thank-you email: ' . $ex->getMessage());
                    }
                }
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                error_log('Payment failed: ' . $paymentIntent->id);
                break;
        }

        return new Response('', 200);
    }
}
