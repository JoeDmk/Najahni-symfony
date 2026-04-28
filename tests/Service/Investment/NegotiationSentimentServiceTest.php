<?php

namespace App\Tests\Service\Investment;

use App\Entity\InvestmentContract;
use App\Entity\InvestmentContractMessage;
use App\Entity\User;
use App\Service\Investment\NegotiationSentimentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NegotiationSentimentServiceTest extends TestCase
{
    public function testBuildsPositiveSentimentFromInferenceResponse(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([[ 
                ['label' => '1 star', 'score' => 0.01],
                ['label' => '2 stars', 'score' => 0.04],
                ['label' => '3 stars', 'score' => 0.10],
                ['label' => '4 stars', 'score' => 0.30],
                ['label' => '5 stars', 'score' => 0.55],
            ]], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['content-type: application/json'],
            ]),
        ]);

        $service = new NegotiationSentimentService($httpClient, new ArrayAdapter(), new NullLogger(), 'hf_test_token');
        $sentiment = $service->analyze($this->createContract(), $this->createMessages());

        self::assertSame('positive', $sentiment['label']);
        self::assertGreaterThan(40, $sentiment['score']);
        self::assertSame(2, $sentiment['messageCount']);
        self::assertSame(2, $sentiment['latestMessageId']);
        self::assertSame('hf', $sentiment['source']);
    }

    public function testFallsBackToNeutralWhenInferenceFails(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('error', ['http_code' => 503]),
        ]);

        $service = new NegotiationSentimentService($httpClient, new ArrayAdapter(), new NullLogger(), 'hf_test_token');
        $sentiment = $service->analyze($this->createContract(), $this->createMessages());

        self::assertSame('neutral', $sentiment['label']);
        self::assertSame(0, $sentiment['score']);
        self::assertSame('fallback', $sentiment['source']);
    }

    /** @return list<InvestmentContractMessage> */
    private function createMessages(): array
    {
        $investor = $this->createUser(10);
        $entrepreneur = $this->createUser(11);

        $first = (new InvestmentContractMessage())
            ->setSender($investor)
            ->setBody('Les derniers retours sont bons et je veux avancer rapidement.');
        $this->forceId($first, 1);

        $second = (new InvestmentContractMessage())
            ->setSender($entrepreneur)
            ->setBody('Parfait, les termes me conviennent et nous pouvons finaliser.');
        $this->forceId($second, 2);

        return [$first, $second];
    }

    private function createContract(): InvestmentContract
    {
        $contract = (new InvestmentContract())
            ->setInvestor($this->createUser(10))
            ->setEntrepreneur($this->createUser(11));

        $this->forceId($contract, 33);

        return $contract;
    }

    private function createUser(int $id): User
    {
        $user = new User();
        $this->forceId($user, $id);

        return $user;
    }

    private function forceId(object $entity, int $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }
}