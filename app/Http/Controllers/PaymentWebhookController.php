<?php

namespace App\Http\Controllers;

use App\Payments\Exceptions\GatewayNotConfigured;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Payments;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Gateways answer here. A 2xx tells the provider to stop retrying, so a
 * replay or an event we do not care about still gets one; only a bad
 * signature is refused.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $driver, Payments $payments): Response
    {
        try {
            $gateway = $payments->gateway($driver);
        } catch (GatewayNotConfigured) {
            abort(404);
        }

        try {
            $outcome = $gateway->handleWebhook($request);
        } catch (InvalidWebhookSignature) {
            return response('', 400);
        }

        if ($outcome !== null) {
            $payments->applyWebhook($gateway, $outcome);
        }

        return response()->noContent();
    }
}
