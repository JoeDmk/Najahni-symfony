<?php

namespace App\Tests\Service\Investment;

use App\Entity\InvestmentOpportunity;
use App\Entity\Projet;
use App\Service\Investment\MLPredictionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MLPredictionServiceTest extends TestCase
{
    public function testReturnsPredictionPayloadWhenServiceRespondsSuccessfully(): void
    {
        $capturedRequest = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedRequest): MockResponse {
            $capturedRequest = [
                'method' => $method,
                'url' => $url,
                'options' => $options,
            ];

            return new MockResponse(json_encode([
                'success_probability' => 0.8123,
                'confidence' => 'high',
                'model_trained_on' => '2026-04-01T12:00:00+00:00',
                'synthetic_data' => false,
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['content-type: application/json'],
            ]);
        });

        $service = new MLPredictionService($httpClient, 'http://127.0.0.1:5001');
        $prediction = $service->predictSuccessProbability($this->createOpportunity(), 52.4, 3);
        $requestPayload = json_decode($capturedRequest['options']['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($prediction);
        self::assertSame(0.8123, $prediction['success_probability']);
        self::assertSame('high', $prediction['confidence']);
        self::assertFalse($prediction['synthetic_data']);
        self::assertSame('POST', $capturedRequest['method']);
        self::assertSame('http://127.0.0.1:5001/predict', $capturedRequest['url']);
        self::assertSame(3.0, $capturedRequest['options']['timeout']);
        self::assertSame(1, $requestPayload['sector']);
        self::assertSame(15000.0, $requestPayload['amount']);
        self::assertSame(90, $requestPayload['duration_days']);
        self::assertSame(52.4, $requestPayload['economic_score']);
        self::assertSame(3, $requestPayload['offer_count']);
    }

    public function testReturnsNullWhenPredictionServiceFails(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('service unavailable', ['http_code' => 503]),
        ]);

        $service = new MLPredictionService($httpClient, 'http://127.0.0.1:5001');

        self::assertNull($service->predictSuccessProbability($this->createOpportunity(), 47.0, 1));
    }

    private function createOpportunity(): InvestmentOpportunity
    {
        $project = (new Projet())
            ->setSecteur('Technologie');

        return (new InvestmentOpportunity())
            ->setProject($project)
            ->setTargetAmount('15000');
    }
}