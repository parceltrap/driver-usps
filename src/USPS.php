<?php

declare(strict_types=1);

namespace ParcelTrap\USPS;

use DateTimeImmutable;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use ParcelTrap\Contracts\Driver;
use ParcelTrap\DTOs\TrackingDetails;
use ParcelTrap\Enums\Status;
use ParcelTrap\Exceptions\ApiAuthenticationFailedException;
use RuntimeException;
use SimpleXMLElement;

class USPS implements Driver
{
    public const IDENTIFIER = 'usps';

    public const BASE_URI = 'https://secure.shippingapis.com';

    private ClientInterface $client;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $sourceId,
        ?ClientInterface $client = null
    ) {
        $this->client = $client ?? GuzzleFactory::make(['base_uri' => self::BASE_URI]);
    }

    public function find(string $identifier, array $parameters = []): TrackingDetails
    {
        $xmlRequest = new SimpleXMLElement('<TrackFieldRequest></TrackFieldRequest>');
        $xmlRequest->addAttribute('USERID', $this->apiKey);
        $xmlRequest->addChild('SourceId')->addAttribute('ID', $identifier);
        $xmlRequest->addChild('TrackID', $this->sourceId);
        $xmlRequest->addChild('Revision', '1');

        $response = $this->client->request('POST', '/ShippingAPI.dll', [
            RequestOptions::HEADERS => $this->getHeaders(),
            RequestOptions::FORM_PARAMS => [
                'API' => 'TrackV2',
                'XML' => (string) $xmlRequest,
            ],
        ]);

        $xml = simplexml_load_string($this->getCleanedBody($response->getBody()->getContents()));

        if (isset($xml->Description) && str_starts_with((string) $xml->Description, 'API Authorization failure')) {
            throw new ApiAuthenticationFailedException($this);
        }

        if (isset($xml->TrackInfo->Error->Description)) {
            throw new RuntimeException((string) $xml->TrackInfo->Error->Description);
        }

        return new TrackingDetails(
            identifier: $identifier,
            status: $this->mapStatus($xml->Status ?? 'unknown'), // TODO
            summary: (string) $xml->StatusSummary,
            estimatedDelivery: new DateTimeImmutable($xml->Delivery ?? 'now'),
            events: (array) $xmlRequest->TrackSummary,
            raw: (array) $xml,
        );
    }

    private function mapStatus(string $status): Status
    {
        return match ($status) {
            'transit' => Status::In_Transit,
            default => Status::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function getHeaders(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/xml',
        ], $headers);
    }

    private function getCleanedBody(string $contents): string
    {
        return str_replace([
            '&reg;'
        ], [
            '',
        ], $contents);
    }
}
