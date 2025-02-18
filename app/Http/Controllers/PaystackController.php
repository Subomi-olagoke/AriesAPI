<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaystackController extends Controller
{
    private $secretKey;
    private $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
    }

    public function initiateSubscription(Request $request)
    {
        $validated = $request->validate([
            'plan_type' => 'required|string|in:monthly,yearly'
        ]);

        $amount = $validated['plan_type'] === 'monthly' ? 5000 : 50000; // ₦50 or ₦500

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/transaction/initialize', [
            'email' => auth()->user()->email,
            'amount' => $amount * 100, // Amount in kobo
            'callback_url' => route('paystack.callback'),
            'metadata' => [
                'plan_type' => $validated['plan_type'],
                'user_id' => auth()->id()
            ]
        ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Payment initialization failed'], 500);
        }

        return response()->json($response->json());
    }

    public function handleWebhook(Request $request)
    {
        if (!hash_equals(
            hash_hmac('sha512', $request->getContent(), $this->secretKey),
            $request->header('x-paystack-signature')
        )) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.success') {
            $metadata = $data['metadata'];
            $duration = $metadata['plan_type'] === 'monthly' ? 30 : 365;

            Subscription::create([
                'user_id' => $metadata['user_id'],
                'paystack_reference' => $data['reference'],
                'plan_type' => $metadata['plan_type'],
                'starts_at' => now(),
                'expires_at' => now()->addDays($duration),
                'is_active' => true
            ]);
        }

        return response()->json(['message' => 'Webhook handled']);
    }

    public function verifyPayment($reference)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get($this->baseUrl . "/transaction/verify/{$reference}");

        if (!$response->successful()) {
            return response()->json(['message' => 'Payment verification failed'], 500);
        }

        return response()->json($response->json());
    }
}
