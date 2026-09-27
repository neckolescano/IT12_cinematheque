<?php

namespace App\Services\PayMongo;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the two PayMongo endpoints this app uses:
 *   POST /checkout_sessions        create a hosted checkout page
 *   GET  /checkout_sessions/{id}   ask PayMongo what actually happened
 *
 * Authentication is HTTP Basic with the secret key as username and no password.
 * The key is read from config/services.php → .env; nothing is hard-coded.
 */
class PayMongoClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.paymongo.secret_key'));
    }

    /**
     * @param  array<string, mixed>  $attributes  data.attributes of the request
     * @return array<string, mixed>  the "data" object of PayMongo's response
     *
     * @throws PayMongoException
     */
    public function createCheckoutSession(array $attributes): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post('checkout_sessions', [
            'data' => ['attributes' => $attributes],
        ]));
    }

    /**
     * @return array<string, mixed>  the "data" object of PayMongo's response
     *
     * @throws PayMongoException
     */
    public function retrieveCheckoutSession(string $id): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('checkout_sessions/'.rawurlencode($id)));
    }

    /** @param  callable(PendingRequest): \Illuminate\Http\Client\Response  $call */
    private function send(callable $call): array
    {
        if (! $this->isConfigured()) {
            throw new PayMongoException('PayMongo is not configured. Set PAYMONGO_SECRET_KEY in .env.');
        }

        $http = Http::baseUrl(rtrim(config('services.paymongo.base_url'), '/').'/')
            ->withBasicAuth(config('services.paymongo.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(15);

        try {
            $response = $call($http)->throw();
        } catch (RequestException $e) {
            $detail = $e->response?->json('errors.0.detail') ?? $e->getMessage();
            throw new PayMongoException('PayMongo request failed: '.$detail, previous: $e);
        } catch (\Throwable $e) {
            throw new PayMongoException('Could not reach PayMongo: '.$e->getMessage(), previous: $e);
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new PayMongoException('Unexpected response from PayMongo.');
        }

        return $data;
    }
}
