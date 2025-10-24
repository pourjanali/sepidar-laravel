<?php

namespace App\Services\Sepidar;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Random\RandomException;
use Illuminate\Support\Facades\Log;

class SepidarAuthService extends SepidarBaseService
{
    /**
     * @throws RandomException
     * @throws ConnectionException
     * Logs in to the Sepidar API and retrieves an authentication token.
     */
    public function login(): array
    {
        $response = Http::withHeaders($this->generateHeaders())
            ->post($this->baseUrl . '/users/login', [
                'UserName' => env('SEPIDAR_USERNAME'),
                'PasswordHash' => md5(env('SEPIDAR_PASSWORD')),
            ]);

        if (!$response->successful()) {
            return $this->sepidarError($response);
        }

        $data = $response->json();
        Cache::put('sepidar_token', $data['Token'] ?? null);

        return [
            'success' => true,
            'message' => 'Device registered successfully',
            'data' => $response->json()
        ];
    }

    /**
     * @return array
     * @throws ConnectionException
     * @throws RandomException
     */
    public function getAuthenticatedHeaders(): array
    {
        $token = Cache::get('sepidar_token');
        if (!$token) {
            $this->login();
        }

        return array_merge(
            $this->generateHeaders(),
            ['Authorization' => 'Bearer ' . Cache::get('sepidar_token')]
        );
    }

}
