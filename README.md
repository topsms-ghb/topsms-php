# topsms-php

Oficiální PHP klient pro [TopSMS.cz](https://www.topsms.cz) — českou SMS bránu s přímým routováním přes T-Mobile, O2 a Vodafone.

- Odeslání SMS jedním voláním, stav doručení druhým
- Zůstatek kreditu přes API
- Ověření podpisu webhooků
- Bez závislostí (jen `ext-curl` + `ext-json`), PHP ≥ 7.4

## Instalace

```bash
composer require topsms/topsms-php
```

## Rychlý start

```php
<?php
require 'vendor/autoload.php';

use TopSms\TopSmsClient;
use TopSms\TopSmsException;

// Client ID a Secret najdete v dashboardu: https://www.topsms.cz/dashboard/api
$client = new TopSmsClient('VAS_CLIENT_ID', 'VAS_SECRET');

try {
    $result = $client->send('+420601234567', 'Vase objednavka #1024 je na ceste.', 'MojeFirma');
    echo "Odesláno, ID: {$result['id']}, cena: {$result['price']} Kč\n";
} catch (TopSmsException $e) {
    echo "Chyba ({$e->getHttpCode()}): {$e->getMessage()}\n";
}
```

## Stav doručení

```php
$status = $client->status($result['id']);
echo $status['status']; // sent | delivered | failed | expired
```

Doručenka od operátora dorazí asynchronně (sekundy až minuty) — doporučujeme krátký polling, nebo si nastavte webhook (níže).

## Zůstatek kreditu

```php
$credit = $client->credit();
echo "Zůstatek: {$credit['credit']} Kč, cena SMS: {$credit['smsPrice']} Kč\n";
```

## Webhooky (push doručenky)

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

## Chybové kódy

| HTTP | Význam |
|------|--------|
| 400  | Neplatné číslo, chybějící pole, číslo na blacklistu |
| 401  | Neplatná autentizace |
| 402  | Nedostatečný kredit |
| 403  | Účet neaktivní, IP mimo whitelist, chybějící scope |
| 429  | Překročen rate limit |

Kompletní dokumentace: **https://www.topsms.cz/api-integrace/rest-api**

## Licence

MIT — viz [LICENSE](LICENSE).
