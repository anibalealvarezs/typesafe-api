<?php

namespace Tests\Unit;

use Anibalealvarezs\TypeSafeApi\TypeSafeApi;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TypeSafeApiTest extends TestCase
{
    protected string $apiKey = 'ts-test-api-key-12345';
    protected string $baseUrl = 'https://api.typesafe.ai/v1/';

    protected function createMockedClient(array $responses = [], ?MockHandler $mock = null): TypeSafeApi
    {
        if ($mock === null) {
            $mock = new MockHandler($responses);
        }
        $handler = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handler]);

        return new TypeSafeApi(
            apiKey: $this->apiKey,
            baseUrl: $this->baseUrl,
            guzzleClient: $guzzle
        );
    }

    public function testConstructorSetsBaseUrlAndAuth(): void
    {
        $client = new TypeSafeApi($this->apiKey);

        $this->assertEquals($this->baseUrl, $client->getBaseUrl());
        $this->assertEquals($this->apiKey, $client->getToken());
        $this->assertFalse($client->getDebugMode());
    }

    public function testConstructorThrowsExceptionWhenTokenIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token is required for Bearer Token authentication');

        new TypeSafeApi('');
    }

    public function testEvaluateCallsSystemOneEndpointWithCorrectPayload(): void
    {
        $mockResponse = [
            'model' => 'jev-latest',
            'answers' => [
                'intent' => [
                    'type' => 'choice',
                    'choice' => 'informational',
                    'confidence' => 0.96
                ],
                'brand_relation' => [
                    'type' => 'choice',
                    'choice' => 'generic',
                    'confidence' => 0.99
                ]
            ],
            'usage' => [
                'input_tokens' => 84,
                'output_tokens' => 12
            ]
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mockResponse))
        ]);

        $client = $this->createMockedClient(mock: $mock);

        $state = [
            'query' => 'how to clean leather jacket',
            'business' => 'Leather care and repair products shop'
        ];

        $questions = [
            'intent' => [
                'type' => 'choice',
                'instructions' => 'Determine search intent',
                'choices' => ['informational', 'commercial', 'transactional', 'navigational'],
                'criteria' => [
                    'informational' => 'Seeking instructions or answers',
                    'commercial' => 'Researching options',
                    'transactional' => 'Ready to purchase',
                    'navigational' => 'Looking for specific site'
                ]
            ]
        ];

        $result = $client->evaluate(state: $state, questions: $questions);

        $this->assertEquals($mockResponse, $result);

        $lastRequest = $mock->getLastRequest();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertEquals('https://api.typesafe.ai/v1/systemone', (string) $lastRequest->getUri());
        $this->assertEquals('Bearer ' . $this->apiKey, $lastRequest->getHeaderLine('Authorization'));
        $this->assertEquals('application/json', $lastRequest->getHeaderLine('Content-Type'));

        $sentBody = json_decode((string) $lastRequest->getBody(), true);
        $this->assertEquals('jev-latest', $sentBody['model']);
        $this->assertEquals($state, $sentBody['state']);
        $this->assertEquals($questions, $sentBody['questions']);
    }

    public function testEvaluateAllowsCustomModel(): void
    {
        $mockResponse = [
            'model' => 'jev-preview',
            'answers' => [
                'is_relevant' => [
                    'type' => 'noul',
                    'noul' => 0.91
                ]
            ]
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mockResponse))
        ]);

        $client = $this->createMockedClient(mock: $mock);

        $result = $client->evaluate(
            state: 'buy winter jackets online',
            questions: [
                'is_relevant' => [
                    'type' => 'noul',
                    'instructions' => 'Is this query commercially relevant?'
                ]
            ],
            model: 'jev-preview'
        );

        $this->assertEquals($mockResponse, $result);

        $sentBody = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertEquals('jev-preview', $sentBody['model']);
        $this->assertEquals('buy winter jackets online', $sentBody['state']);
    }

    public function testEvaluateChoiceHelperWithCustomCriteria(): void
    {
        $mockResponse = [
            'model' => 'jev-latest',
            'answers' => [
                'intent' => [
                    'type' => 'choice',
                    'choice' => 'transactional',
                    'confidence' => 0.98
                ]
            ]
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mockResponse))
        ]);

        $client = $this->createMockedClient(mock: $mock);

        $criteria = [
            'informational' => 'Learning',
            'transactional' => 'Buying'
        ];

        $result = $client->evaluateChoice(
            state: 'buy shoes online fast delivery',
            questionId: 'intent',
            instructions: 'Classify search intent',
            choices: ['informational', 'transactional'],
            criteria: $criteria
        );

        $this->assertEquals($mockResponse, $result);

        $sentBody = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertArrayHasKey('intent', $sentBody['questions']);
        $q = $sentBody['questions']['intent'];
        $this->assertEquals('choice', $q['type']);
        $this->assertEquals(['informational', 'transactional'], $q['choices']);
        $this->assertEquals($criteria, $q['criteria']);
    }

    public function testEvaluateChoiceHelperGeneratesDefaultCriteriaIfNull(): void
    {
        $mockResponse = ['model' => 'jev-latest', 'answers' => []];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mockResponse))
        ]);

        $client = $this->createMockedClient(mock: $mock);

        $client->evaluateChoice(
            state: 'nike running sneakers',
            questionId: 'brand',
            instructions: 'Classify brand',
            choices: ['branded', 'generic']
        );

        $sentBody = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $q = $sentBody['questions']['brand'];
        $this->assertArrayHasKey('criteria', $q);
        $this->assertEquals('Represents branded', $q['criteria']['branded']);
        $this->assertEquals('Represents generic', $q['criteria']['generic']);
    }

    public function testEvaluateNoulHelper(): void
    {
        $mockResponse = [
            'model' => 'jev-latest',
            'answers' => [
                'is_branded' => [
                    'type' => 'noul',
                    'noul' => 0.97
                ]
            ]
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mockResponse))
        ]);

        $client = $this->createMockedClient(mock: $mock);

        $result = $client->evaluateNoul(
            state: 'running shoes review',
            questionId: 'is_branded',
            instructions: 'Does this query mention Nike or Adidas?'
        );

        $this->assertEquals($mockResponse, $result);

        $sentBody = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertArrayHasKey('is_branded', $sentBody['questions']);
        $q = $sentBody['questions']['is_branded'];
        $this->assertEquals('noul', $q['type']);
        $this->assertEquals('Does this query mention Nike or Adidas?', $q['instructions']);
    }

    public function testEvaluateScoreHelper(): void
    {
        $mockResponse = [
            'model' => 'jev-latest',
            'answers' => [
                'urgency' => [
                    'type' => 'score',
                    'score' => 4.2,
                    'confidence' => 0.85
                ]
            ]
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mockResponse))
        ]);

        $client = $this->createMockedClient(mock: $mock);

        $criteria = ['Low', 'Medium', 'High', 'Critical'];

        $result = $client->evaluateScore(
            state: 'emergency plumber near me now',
            questionId: 'urgency',
            instructions: 'Rate the urgency level',
            criteria: $criteria
        );

        $this->assertEquals($mockResponse, $result);

        $sentBody = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertArrayHasKey('urgency', $sentBody['questions']);
        $q = $sentBody['questions']['urgency'];
        $this->assertEquals('score', $q['type']);
        $this->assertEquals($criteria, $q['criteria']);
    }
}