# topsms-php

Official PHP client for the **TopSMS SMS gateway (topsms.eu / topsms.cz)**. Česká dokumentace je [níže](#česky).

## English

TopSMS is an SMS gateway with two sites that share one API:

- [topsms.eu](https://topsms.eu) – English, German, Italian, Spanish and Portuguese; accounts in EUR; for Germany, Austria and the EU.
- [www.topsms.cz](https://www.topsms.cz) – Czech and Slovak; accounts in CZK.

This library sends SMS, reads delivery status and the credit balance, and verifies webhook signatures. No dependencies (only `ext-curl` and `ext-json`), PHP 7.4 or newer. The API key decides the account; amounts are returned in the account currency (`currency` is `EUR` or `CZK`).

```bash
composer require topsms/topsms-php
```

```php
<?php
require 'vendor/autoload.php';

use TopSms\TopSmsClient;
use TopSms\TopSmsException;

// Client ID and Secret: TopSMS dashboard → API keys (a test key sends nothing and costs nothing)
$client = new TopSmsClient('YOUR_CLIENT_ID', 'YOUR_SECRET');

try {
    $result = $client->send('+4915112345678', 'Your order 1024 has shipped.');
    echo "Sent, ID {$result['id']}, price {$result['price']} {$result['currency']}\n";
    echo $client->status($result['id'])['status'], "\n"; // sent | delivered | failed | expired
} catch (TopSmsException $e) {
    echo "Error ({$e->getHttpCode()}): {$e->getMessage()}\n";
}
```

**Prices:** prepaid credit, no monthly fee, no setup fee, credit never expires. Current prices are on the price pages: [topsms.eu/pricing](https://topsms.eu/pricing) (EUR), [www.topsms.cz/cenik](https://www.topsms.cz/cenik) (CZK), machine-readable [topsms.eu/pricing.json](https://topsms.eu/pricing.json), per country [topsms.eu/countries](https://topsms.eu/countries).

**Docs:** [English](https://topsms.eu/docs) · [Deutsch](https://topsms.eu/de/docs) · [OpenAPI](https://topsms.eu/openapi.yaml). Also available: Verify (OTP) API, webhooks with delivery reports, and an MCP server for Claude, ChatGPT and Cursor ([topsms.eu/mcp](https://topsms.eu/mcp)).

## Česky

Oficiální PHP klient pro SMS bránu TopSMS: [www.topsms.cz](https://www.topsms.cz) (česky a slovensky, účty v Kč) a [topsms.eu](https://topsms.eu) (anglicky, německy, italsky, španělsky a portugalsky, účty v EUR, pro Německo, Rakousko a EU). Obě stránky používají stejné API; částky se vrací v měně účtu (pole `currency`: `CZK` nebo `EUR`).

Aktuální ceny: [www.topsms.cz/cenik](https://www.topsms.cz/cenik) (Kč) · [topsms.eu/pricing](https://topsms.eu/pricing) (EUR). Bez měsíčního poplatku, kredit nepropadá.

- Odeslání SMS jedním voláním, stav doručení druhým
- Zůstatek kreditu přes API
- Ověření podpisu webhooků
- Bez závislostí (jen `ext-curl` + `ext-json`), PHP ≥ 7.4

### Instalace

```bash
composer require topsms/topsms-php
```

### Rychlý start

```php
<?php
require 'vendor/autoload.php';

use TopSms\TopSmsClient;
use TopSms\TopSmsException;

// Client ID a Secret najdete v dashboardu: https://www.topsms.cz/dashboard/api
$client = new TopSmsClient('VAS_CLIENT_ID', 'VAS_SECRET');

try {
    $result = $client->send('+420601234567', 'Vase objednavka #1024 je na ceste.', 'MojeFirma');
    echo "Odesláno, ID: {$result['id']}, cena: {$result['price']} {$result['currency']}\n";
} catch (TopSmsException $e) {
    echo "Chyba ({$e->getHttpCode()}): {$e->getMessage()}\n";
}
```

### Stav doručení

```php
$status = $client->status($result['id']);
echo $status['status']; // sent | delivered | failed | expired
```

Doručenka od operátora dorazí asynchronně (sekundy až minuty) — doporučujeme krátký polling, nebo si nastavte webhook (níže).

### Zůstatek kreditu

```php
$credit = $client->credit();
// částky jsou v měně účtu: CZK (topsms.cz) nebo EUR (topsms.eu)
echo "Zůstatek: {$credit['credit']} {$credit['currency']}, cena SMS: {$credit['smsPrice']} {$credit['currency']}\n";
```

### Webhooky (push doručenky)

V [dashboardu](https://www.topsms.cz/dashboard/api) nastavte u API klíče webhook URL — při změně stavu zprávy pošleme HTTP POST s JSON payloadem a podpisem v hlavičce `X-TopSMS-Signature`:

```php
$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_TOPSMS_SIGNATURE'] ?? '';

if (!TopSmsClient::verifyWebhookSignature($raw, $signature, 'VAS_WEBHOOK_SECRET')) {
    http_response_code(401);
    exit;
}

$event = json_decode($raw, true);
// $event['status'] => delivered | failed | expired
```

### Chybové kódy

| HTTP | Význam |
|------|--------|
| 400  | Neplatné číslo, chybějící pole, číslo na blacklistu |
| 401  | Neplatná autentizace |
| 402  | Nedostatečný kredit |
| 403  | Účet neaktivní, IP mimo whitelist, chybějící scope |
| 429  | Překročen rate limit |

Kompletní dokumentace: **https://www.topsms.cz/api-integrace/rest-api** · English: **https://topsms.eu/docs** · Deutsch: **https://topsms.eu/de/docs** · OpenAPI: https://topsms.eu/openapi.yaml

## Licence

MIT — viz [LICENSE](LICENSE).
