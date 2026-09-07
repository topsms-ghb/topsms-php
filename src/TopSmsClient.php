<?php

declare(strict_types=1);

namespace TopSms;

/**
 * Oficiální PHP klient pro TopSMS.cz REST API.
 *
 * Dokumentace: https://www.topsms.cz/api-integrace/rest-api
 *
 * ```php
 * $client = new \TopSms\TopSmsClient('CLIENT_ID', 'SECRET');
 * $result = $client->send('+420601234567', 'Vase objednavka je na ceste.', 'MojeFirma');
 * echo $result['id']; // interní ID pro dotaz na stav
 * ```
 */
class TopSmsClient
{
    public const VERSION = '1.0.0';

    /** @var string */
    private $clientId;

    /** @var string */
    private $secret;

    /** @var string */
    private $baseUrl;

    /** @var int */
    private $timeoutSeconds;

    public function __construct(
        string $clientId,
        string $secret,
        string $baseUrl = 'https://www.topsms.cz',
        int $timeoutSeconds = 15
    ) {
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Odešle SMS. Kredit se odečte okamžitě, cena je v odpovědi.
     *
     * @param string      $to   Číslo v mezinárodním formátu (+420601234567)
     * @param string      $text Text zprávy (GSM-7: 160 znaků / Unicode: 70 znaků na segment)
     * @param string|null $from Jméno odesílatele (schválené Sender ID), výchozí "TopSMS"
     *
     * @return array{ok:bool,id:string,externalId:?string,to:string,from:string,smsCount:int,price:float,status:string}
     *
     * @throws TopSmsException při chybě (402 = nedostatečný kredit, 400 = neplatné číslo…)
     */
    public function send(string $to, string $text, ?string $from = null): array
    {
        $payload = ['to' => $to, 'text' => $text];
        if ($from !== null) {
            $payload['from'] = $from;
        }

        return $this->request('POST', '/api/sms/send', $payload);
    }

    /**
     * Stav doručení zprávy (polling). Přijímá interní `id` i `externalId`.
     *
     * @return array{id:string,externalId:?string,to:string,from:string,text:string,status:string,price:float,carrier:?string,createdAt:string,deliveredAt:?string,failedAt:?string,errorMessage:?string}
     *
     * @throws TopSmsException 404 = zpráva nenalezena
     */
    public function status(string $id): array
    {
        return $this->request('GET', '/api/sms/status/' . rawurlencode($id));
    }

    /**
     * Aktuální zůstatek kreditu v Kč a cena za SMS dle tarifu.
     *
     * @return array{ok:bool,credit:float,currency:string,smsPrice:float}
     */
    public function credit(): array
    {
        return $this->request('GET', '/api/credit');
    }

    /**
     * Ověření podpisu příchozího webhooku (hlavička X-TopSMS-Signature).
     *
     * ```php
     * $raw = file_get_contents('php://input');
     * $sig = $_SERVER['HTTP_X_TOPSMS_SIGNATURE'] ?? '';
     * if (!TopSmsClient::verifyWebhookSignature($raw, $sig, $webhookSecret)) {
     *     http_response_code(401);
     *     exit;
     * }
     * ```
     */
    public static function verifyWebhookSignature(string $rawBody, string $signatureHeader, string $webhookSecret): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * @param array<string,mixed>|null $payload
     *
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new TopSmsException('Nepodařilo se inicializovat cURL.');
        }

        $headers = [
            'Authorization: Bearer ' . $this->clientId . ':' . $this->secret,
            'Accept: application/json',
            'User-Agent: topsms-php/' . self::VERSION,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                curl_close($ch);
                throw new TopSmsException('Payload nelze serializovat do JSON.');
            }
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new TopSmsException('Síťová chyba: ' . $error, 0);
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            throw new TopSmsException('Neplatná odpověď serveru (HTTP ' . $httpCode . ').', $httpCode);
        }

        if ($httpCode >= 400) {
            $message = isset($data['error']) ? (string) $data['error'] : 'HTTP ' . $httpCode;
            throw new TopSmsException($message, $httpCode, $data);
        }

        return $data;
    }
}
