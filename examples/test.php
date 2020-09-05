<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Pex\Hydrator;
use Pex\Example\Person;

function getCsvData() {
    $keys = [];
    $data = [];

    $f = fopen(__DIR__ . '/people.csv', 'r');

    $isKeys = true;
    while (($values = fgetcsv($f)) !== false) {
        if ($isKeys) {
            $keys = $values;
            $isKeys = false;
        } else {
            $data[] = array_filter(array_combine($keys, $values), function ($value) {
                return $value !== '';
            });
        }
    }

    fclose($f);

    return $data;
}

$hydrator = new Hydrator();
$data = getCsvData();
$hydratedData = $hydrator->hydrateEntities(Person::class, $data);

echo 'Associative array before being hydrated into entities:' . PHP_EOL;
dump($data);
echo PHP_EOL . PHP_EOL;

echo 'Associative array before being hydrated into entities:' . PHP_EOL;
dump($hydratedData);
