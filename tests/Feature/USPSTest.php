<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParcelTrap\Contracts\Factory;
use ParcelTrap\DTOs\TrackingDetails;
use ParcelTrap\Enums\Status;
use ParcelTrap\Exceptions\ApiAuthenticationFailedException;
use ParcelTrap\ParcelTrap;
use ParcelTrap\USPS\USPS;

it('can add the USPS driver to ParcelTrap', function () {
    /** @var ParcelTrap $client */
    $client = $this->app->make(Factory::class);

    $client->extend('usps_other', fn () => new USPS(
        apiKey: 'abcdefg',
        sourceId: 'test',
    ));

    expect($client)->driver(USPS::IDENTIFIER)->toBeInstanceOf(USPS::class)
        ->and($client)->driver('usps_other')->toBeInstanceOf(USPS::class);
});

it('can retrieve the USPS driver from ParcelTrap', function () {
    expect($this->app->make(Factory::class)->driver(USPS::IDENTIFIER))->toBeInstanceOf(USPS::class);
});

it('can call `find` on the USPS driver', function () {
    $httpMockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TrackResponse>
<TrackInfo ID="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
<Class>Priority Mail Same Day</Class>
<ClassOfMailCode>MT</ClassOfMailCode>
<DestinationCity>ARLINGTON</DestinationCity>
<DestinationState>VA</DestinationState>
<DestinationZip>22203</DestinationZip>
<EmailEnabled>true</EmailEnabled>
<KahalaIndicator>false</KahalaIndicator>
<MailTypeCode>DM</MailTypeCode>
<MPDATE>2022-07-08 07:48:49.000000</MPDATE>
<MPSUFFIX>999999999</MPSUFFIX>
<OriginCity>PHILADELPHIA</OriginCity>
<OriginState>PA</OriginState>
<OriginZip>19153</OriginZip>
<PodEnabled>false</PodEnabled>
<TPodEnabled>false</TPodEnabled>
<RestoreEnabled>false</RestoreEnabled>
<RramEnabled>false</RramEnabled>
<RreEnabled>false</RreEnabled>
<Service>Adult Signature Restricted Delivery</Service>
<ServiceTypeCode>836</ServiceTypeCode>
<Status>USPS in possession of item</Status>
<StatusCategory>Accepted</StatusCategory>
<StatusSummary>USPS is now in possession of your item as of 9:00 am on July 6, 2022 in PHILADELPHIA, PA 19153.</StatusSummary>
<TABLECODE>T</TABLECODE>
<TrackSummary>
<EventTime>9:00 am</EventTime>
<EventDate>July 6, 2022</EventDate>
<Event>USPS in possession of item</Event>
<EventCity>PHILADELPHIA</EventCity>
<EventState>PA</EventState>
<EventZIPCode>19153</EventZIPCode>
<EventCountry/>
<FirmName/>
<Name/>
<AuthorizedAgent>false</AuthorizedAgent>
<EventCode>03</EventCode>
<GMT>13:00:00</GMT>
<GMTOffset>-04:00</GMTOffset>
</TrackSummary>
</TrackInfo>
</TrackResponse>
XML
        ),
    ]);

    $handlerStack = HandlerStack::create($httpMockHandler);

    $httpClient = new Client([
        'handler' => $handlerStack,
    ]);

    $this->app->make(Factory::class)->extend(USPS::IDENTIFIER, fn () => new USPS(
        apiKey: 'abcdefg',
        sourceId: 'test',
        client: $httpClient,
    ));

    expect($this->app->make(Factory::class)->driver('usps')->find('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'))
        ->toBeInstanceOf(TrackingDetails::class)
        ->identifier->toBe('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX')
        ->status->toBe(Status::In_Transit)
        ->status->description()->toBe('In Transit')
        ->summary->toBe('USPS is now in possession of your item as of 9:00 am on July 6, 2022 in
PHILADELPHIA, PA 19153.')
        ->estimatedDelivery->toEqual(null)
        ->raw->toBeArray()->not->toBeEmpty();
});

it('can handle auth failure when calling `find` on the USPS driver', function () {
    $httpMockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Error>
    <Number>80040B1A</Number>
    <Description>API Authorization failure. User 123ABC456 is not authorized to use API TrackV2.</Description>
    <Source>USPSCOM::DoAuth</Source>
</Error>
XML
        ),
    ]);

    $handlerStack = HandlerStack::create($httpMockHandler);

    $httpClient = new Client([
        'handler' => $handlerStack,
    ]);

    $this->app->make(Factory::class)->extend(USPS::IDENTIFIER, fn () => new USPS(
        apiKey: '123ABC456',
        sourceId: 'test',
        client: $httpClient,
    ));

    $this->app->make(Factory::class)->driver('usps')->find('ABCDEFG12345');
})->throws(ApiAuthenticationFailedException::class, 'The API authentication failed for the USPS driver');

it('can handle errors when calling `find` on the USPS driver', function () {
    $httpMockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TrackResponse>
<TrackInfo ID="XXXXXXXXXXXXXXXXXX">
<Error>
<Number>-2147219283</Number>
<Description>A status update is not yet available on your Priority Mail Express<SUP>&reg;</SUP> package. It will be available when the shipper provides an update or the package is delivered to USPS. Check back soon. Sign up for Informed Delivery<SUP>&reg;</SUP> to receive notifications for packages addressed to you.</Description>
<HelpFile/>
<HelpContext/>
</Error>
</TrackInfo>
</TrackResponse>
XML
        ),
    ]);

    $handlerStack = HandlerStack::create($httpMockHandler);

    $httpClient = new Client([
        'handler' => $handlerStack,
    ]);

    $this->app->make(Factory::class)->extend(USPS::IDENTIFIER, fn () => new USPS(
        apiKey: 'abcdefg',
        sourceId: 'test',
        client: $httpClient,
    ));

    $this->app->make(Factory::class)->driver('usps')->find('ABCDEFG12345');
})->throws(RuntimeException::class, 'A status update is not yet available on your Priority Mail Express package. It will be available when the shipper provides an update or the package is delivered to USPS. Check back soon. Sign up for Informed Delivery to receive notifications for packages addressed to you.');
