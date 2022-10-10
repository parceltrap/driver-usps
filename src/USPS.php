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

        /** @var array $json */
        $json = json_decode((string) json_encode(
            simplexml_load_string($this->getCleanedBody($response->getBody()->getContents()))
        ), true, 512, JSON_THROW_ON_ERROR);

        if (
            isset($json['Description']) &&
            str_starts_with((string) $json['Description'], 'API Authorization failure')
        ) {
            throw new ApiAuthenticationFailedException($this);
        }

        assert(isset($json['TrackInfo']), 'The tracking information is missing from the response');

        if (isset($json['TrackInfo']['Error']['Description'])) {
            throw new RuntimeException((string) $json['TrackInfo']['Error']['Description']);
        }

        $json = $json['TrackInfo'];

        assert(isset($json['@attributes']['ID']), 'The identifier is missing from the response');
        assert(isset($json['Status']), 'The status is missing from the response');
        assert(isset($json['StatusSummary']), 'The status summary is missing from the response');

        return new TrackingDetails(
            identifier: $json['@attributes']['ID'],
            status: $this->mapStatus($json['Status'] ?? 'unknown'),
            summary: $json['StatusSummary'] ?? null,
            estimatedDelivery: isset($json['PredictedDeliveryDate']) ? new DateTimeImmutable($json['PredictedDeliveryDate']) : null,
            events: $xmlRequest['TrackSummary'] ?? [],
            raw: $json,
        );
    }

    private function mapStatus(string $status): Status
    {
        return match ($status) {
            'Delivered' => Status::Delivered,
            'USPS in possession of item' => Status::In_Transit,
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
            '&reg;',
        ], [
            '',
        ], $contents);
    }
}
