<?php

namespace App\Services\Sepidar;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Math\BigInteger;
use Ramsey\Uuid\Uuid;
use Random\RandomException;

class SepidarBaseService
{
    protected string $serial;
    protected string $key;
    protected int $integrationId;
    protected string $baseUrl;
    protected string $generationVersion;

    public function __construct()
    {
        $this->serial = env('SEPIDAR_SERIAL');
        $this->generationVersion = env('SEPIDAR_GEN_VER');
        $this->key = $this->serial . $this->serial;
        $this->integrationId = (int)substr($this->serial, 0, 4);
        $this->baseUrl = rtrim(env('SEPIDAR_API_URL'), '/');
    }

    /**
     * @return array
     * Checks the status of the Sepidar API.
     */
    public function checkStatus(): array
    {
        try {
            $response = Http::get($this->baseUrl . '/General/GenerationVersion');
            if (!$response->successful()) {
                return $this->sepidarError($response);
            }
            return [
                'success' => true,
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            //TelegramService::sendMessage("سرویس دهنده سپیدار از دسترس خارج است.");
            Log::error(
                'Sepidar Status Check Error',
                [
                    'message' => $e->getMessage(),
                ]
            );
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array
     * Generates headers for Sepidar API requests.
     * @throws RandomException
     */
    protected function generateHeaders(): array
    {
        $uuid = Uuid::uuid4()->toString();
        $uuidBytes = $this->guidToBytes($uuid);

        $pem = Storage::get('sepidar/sepidar_public_key.pem');
        if (!$pem) {
            $this->extractPublicKey();
            $pem = Storage::get('sepidar/sepidar_public_key.pem');
        }
        $publicKey = PublicKeyLoader::load($pem)
            ->withPadding(RSA::ENCRYPTION_PKCS1);

        $encrypted = $publicKey->encrypt($uuidBytes);
        $encArbitraryCode = base64_encode($encrypted);

        return [
            'GenerationVersion' => env('SEPIDAR_GEN_VER'),
            'IntegrationID' => $this->integrationId,
            'ArbitraryCode' => $uuid,
            'EncArbitraryCode' => $encArbitraryCode,
        ];
    }

    /**
     * @param string $guid
     * @return string
     * Converts a GUID string to a byte string.
     */
    protected function guidToBytes(string $guid): string
    {
        $hex = str_replace('-', '', $guid);
        return pack("H*", $hex);
    }

    /**
     * @return array
     * @throws RandomException
     * Extracts the public key from the Sepidar registration process.
     */
    protected function extractPublicKey(): array
    {
        if (!Storage::exists('sepidar/cypher.txt') || !Storage::exists('sepidar/iv.txt')) {
            $this->register();
        }

        $cypherBase64 = Storage::get('sepidar/cypher.txt');
        $ivBase64 = Storage::get('sepidar/iv.txt');

        $cypher = base64_decode($cypherBase64);
        $iv = base64_decode($ivBase64);

        $decrypted = openssl_decrypt($cypher, 'AES-128-CBC', $this->key, OPENSSL_RAW_DATA, $iv);

        if (!$decrypted) {
            return ['success' => false, 'message' => '❌ رمزگشایی ناموفق بود. احتمالاً key یا iv اشتباه است.'];
        }

        $xml = $decrypted;
        Storage::put('sepidar/sepidar_public_key.xml', $decrypted);

        $xmlObj = simplexml_load_string($xml);
        if (!$xmlObj || !isset($xmlObj->Modulus) || !isset($xmlObj->Exponent)) {
            return ['success' => false, 'message' => '❌ کلید عمومی در XML یافت نشد.'];
        }

        $modulus = base64_decode((string)$xmlObj->Modulus);
        $exponent = base64_decode((string)$xmlObj->Exponent);

        $rsa = RSA::loadPublicKey([
            'n' => new BigInteger($modulus, 256),
            'e' => new BigInteger($exponent, 256),
        ]);
        $pem = $rsa->toString('PKCS8');

        Storage::put('sepidar/sepidar_public_key.pem', $pem);

        return [
            'success' => true,
            'message' => '✅ کلید عمومی استخراج و ذخیره شد.',
            'pem_path' => 'storage/app/sepidar/sepidar_public_key.pem',
        ];
    }

    /**
     * @throws RandomException
     * Registers the device with the Sepidar API.
     */
    public function register(): array
    {
        [$cypherBase64, $ivBase64] = $this->encrypt($this->key, (string)$this->integrationId);

        $response = Http::post($this->baseUrl . '/Devices/Register/', [
            'Cypher' => $cypherBase64,
            'IV' => $ivBase64,
            'IntegrationID' => $this->integrationId,
        ]);

        if (!$response->successful()) {
            return $this->sepidarError($response);
        }

        $data = $response->json();

        Storage::put('sepidar/cypher.txt', $data['Cypher']);
        Storage::put('sepidar/iv.txt', $data['IV']);

        return [
            'success' => true,
            'message' => 'Device registered successfully',
        ];
    }

    /**
     * @throws RandomException
     * Encrypts the integration ID using AES-128-CBC.
     */
    protected function encrypt(string $key, string $integrationId): array
    {
        $iv = random_bytes(16);
        $cypher = openssl_encrypt($integrationId, 'AES-128-CBC', $this->key, OPENSSL_RAW_DATA, $iv);

        return [
            base64_encode($cypher),
            base64_encode($iv),
        ];
    }

    /**
     * @param $res
     * @return array
     */
    protected function sepidarError($res): array
    {
        Log::error(
            'Sepidar API Error',
            [
                'status' => $res->status(),
                'message' => $res->body(),
            ]
        );
        return [
            'success' => false,
            'status' => $res->status(),
            'message' => '❌ خطا در ارتباط با Sepidar',
            'error' => json_decode($res->body(), true),
        ];
    }
}
