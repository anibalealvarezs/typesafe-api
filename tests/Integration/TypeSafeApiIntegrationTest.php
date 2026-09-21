<?php

namespace Tests\Integration;

use Anibalealvarezs\TypeSafeApi\TypeSafeApi;
use PHPUnit\Framework\TestCase;

class TypeSafeApiIntegrationTest extends TestCase
{
    protected ?TypeSafeApi $client = null;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = app_config('typesafe_api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->markTestSkipped('TypeSafe API key not configured. Skipping live integration test.');
        }

        $this->client = new TypeSafeApi(
            apiKey: $apiKey,
            baseUrl: app_config('typesafe_base_url', 'https://api.typesafe.ai/v1/')
        );
    }

    public function testLiveEvaluateChoice(): void
    {
        $response = $this->client->evaluateChoice(
            state: 'buy blue suede shoes online',
            questionId: 'intent',
            instructions: 'Determine search intent',
            choices: ['informational', 'commercial', 'transactional', 'navigational'],
            criteria: [
                'informational' => 'Seeking answers or guide',
                'commercial'    => 'Comparing products or options',
                'transactional' => 'Ready to purchase directly',
                'navigational'  => 'Seeking a specific domain or website'
            ]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('answers', $response);
        $this->assertArrayHasKey('intent', $response['answers']);
        $this->assertEquals('choice', $response['answers']['intent']['type']);
        $this->assertEquals('transactional', $response['answers']['intent']['choice']);
        $this->assertGreaterThan(0.8, $response['answers']['intent']['confidence']);
        $this->assertArrayHasKey('usage', $response);
    }

    public function testLiveEvaluateNoul(): void
    {
        $response = $this->client->evaluateNoul(
            state: 'free download pdf cheat sheet',
            questionId: 'is_free',
            instructions: 'Does this query seek free or gratis content?'
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('answers', $response);
        $this->assertArrayHasKey('is_free', $response['answers']);
        $this->assertEquals('noul', $response['answers']['is_free']['type']);
        $this->assertGreaterThan(0.7, $response['answers']['is_free']['noul']);
    }

    public function testLiveEvaluateScore(): void
    {
        $response = $this->client->evaluateScore(
            state: 'best luxury sports cars 2026',
            questionId: 'luxury_level',
            instructions: 'Rate the implied luxury/budget tier of the vehicle request',
            criteria: ['Budget / Economy', 'Standard / Commuter', 'Premium', 'Ultra Luxury / Supercar']
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('answers', $response);
        $this->assertArrayHasKey('luxury_level', $response['answers']);
        $this->assertEquals('score', $response['answers']['luxury_level']['type']);
        $this->assertIsFloat($response['answers']['luxury_level']['score']);
    }
}