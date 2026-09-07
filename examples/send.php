<?php
// Odeslani SMS + kontrola doruceni.
// Spusteni: php examples/send.php

require __DIR__ . '/../vendor/autoload.php';

use TopSms\TopSmsClient;
use TopSms\TopSmsException;

$client = new TopSmsClient(
    getenv('TOPSMS_CLIENT_ID') ?: 'VAS_CLIENT_ID',
    getenv('TOPSMS_SECRET') ?: 'VAS_SECRET'
);

try {
    $credit = $client->credit();
    echo "Zůstatek: {$credit['credit']} Kč\n";

    $result = $client->send('+420601234567', 'Testovaci zprava z topsms-php.');
    echo "Odesláno: {$result['id']} ({$result['smsCount']} segment, {$result['price']} Kč)\n";

    sleep(5);
    $status = $client->status($result['id']);
    echo "Stav: {$status['status']}\n";
} catch (TopSmsException $e) {
    fwrite(STDERR, "Chyba ({$e->getHttpCode()}): {$e->getMessage()}\n");
    exit(1);
}
