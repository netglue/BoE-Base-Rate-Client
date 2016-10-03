# Bank of England Base Rate Data Client

This small library retrieves historical bank of england base rate data from the [BoE Website](http://www.bankofengland.co.uk/boeapps/iadb/index.asp?Travel=NIxRPx&From=Repo&C=13T&G0Xtop.x=1&G0Xtop.y=1), parses the returned XML into a data structure and provides some helpful methods to interrogate the data set.

The Rate Service instance accepts an array of rate data in it's constructor that should take the following form:

```php
[
    [
        'date' => // DateTime instance
        'rate' => // float
    ],
    // ... more elements
];
```

The Rate Service is an `SplPriorityQueue` instance, so `$rates->top()` will yield the most recent value because elements are inserted with the timestamp as the priority. Being a queue, iterating over it will remove elements, so clone before iterating if you want the data to stick around or use the `$queue->toArray()` method.


## Installation

```bash
$ composer require netglue/boe-rates
```

## Usage

### Client `BoeRateClient`

```php
use Netglue\Money\BoeRateClient;
$client = new BoeRateClient;
// Optionally set a different endpoint with…
// $client->setUrl('http://somewhere-else.com');
$rates = $client->get();
```

### Service `BoeRateService`

```php
use Netglue\Money\BoeRateService;
$service = new BoeRateService($rates);
$first = $service->firstDate();
$last  = $service->lastDate();
$mostRecentRate = $service->getRate();
$date = DateTime::createFromFormat('Y-m-d', '2000-06-01');
$otherRate = $service->getRate($date);

// Iterate over rates: most recent first…

$queue = clone $service;
$queue->setExtractFlags($queue::EXTR_DATA);
foreach ($queue as $data) {
    printf(
        "The BoE base rate changed to %0.2f on %s\n",
        $data['rate'],
        $date['date']->format('l jS F Y')
    );
}

// Serialize to JSON for caching. Date format is optional and defaults to 'c'

$dateFormat = 'd/m/Y';
$json = $service->toJson($dateFormat);

// Initialize service from JSON string
$service = BoeRateService::jsonFactory($json, $dateFormat);
```

## About

[Netglue makes web based stuff in Devon, England](https://netglue.uk). We hope this is useful to you and we’d appreciate feedback either way :)
