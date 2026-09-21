# TypeSafe API PHP SDK

Standalone, production-grade PHP SDK for interacting with the **TypeSafe AI (JEV System One)** API, providing calibrated judgment, confidence scoring, and semantic query classification.

Built on top of `anibalealvarezs/api-client-skeleton`.

## Features

- **System One Endpoint**: Seamless integration with TypeSafe System One (`POST /systemone`).
- **All Question Types**:
  - `choice`: Categorical evaluation with calibrated confidence and probabilities (requires choice-to-definition mapping dictionary).
  - `noul`: Binary judgment probability ($0.0$ to $1.0$), ideal for boolean questions, intent flags, or brand identification.
  - `score`: Scalar calibrated ratings against discrete criteria boundaries.
- **Bearer Token Authentication**: Automatic `Authorization: Bearer <API_KEY>` handling with rate limit detection and backoff retry.
- **Standalone Package**: Can be consumed by any modern PHP 8.3+ project as an independent Composer dependency.
- **Mockable & Testable**: Full unit test coverage with Guzzle `MockHandler` and live integration test suite.

## Installation

```bash
composer require anibalealvarezs/typesafe-api
```

## Configuration

Copy `config/config.yaml.example` to `config/config.yaml` or set the `TYPESAFE_API_KEY` environment variable:

```yaml
typesafe_api_key: "your-typesafe-api-key"
typesafe_base_url: "https://api.typesafe.ai/v1/"
typesafe_model: "jev-latest"
```

## Basic Usage

### 1. Categorical Evaluation (`evaluateChoice`)

```php
use Anibalealvarezs\TypeSafeApi\TypeSafeApi;

$client = new TypeSafeApi(apiKey: 'your-api-key');

$result = $client->evaluateChoice(
    state: 'buy blue suede shoes online',
    questionId: 'intent',
    instructions: 'Determine search intent',
    choices: ['informational', 'commercial', 'transactional', 'navigational'],
    criteria: [
        'informational' => 'Seeking answers or tutorial',
        'commercial'    => 'Comparing products or options',
        'transactional' => 'Ready to purchase directly',
        'navigational'  => 'Seeking a specific domain or website'
    ]
);

// Response:
// $result['answers']['intent']['choice'] => 'transactional'
// $result['answers']['intent']['confidence'] => 1.0
// $result['answers']['intent']['probabilities'] => ['transactional' => 1.0, ...]
```

### 2. Binary / Calibrated Probability (`evaluateNoul`)

```php
$result = $client->evaluateNoul(
    state: 'free download pdf cheat sheet',
    questionId: 'is_free',
    instructions: 'Does this query seek free or gratis content?'
);

// Response:
// $result['answers']['is_free']['noul'] => 0.95 (95% confidence)
```

### 3. Scalar Rating (`evaluateScore`)

```php
$result = $client->evaluateScore(
    state: 'emergency plumber burst pipe leaking water now',
    questionId: 'urgency',
    instructions: 'Rate the urgency level',
    criteria: ['Low / Routine', 'Medium', 'High', 'Critical Emergency']
);

// Response:
// $result['answers']['urgency']['score'] => 3.65
// $result['answers']['urgency']['confidence'] => 0.82
```

### 4. Advanced Multi-Question Evaluation (`evaluate`)

```php
$result = $client->evaluate(
    state: [
        'query' => 'how to care for leather jacket',
        'asset' => 'leathercareproducts.com'
    ],
    questions: [
        'intent' => [
            'type' => 'choice',
            'instructions' => 'Determine user search intent',
            'choices' => ['informational', 'commercial', 'transactional', 'navigational'],
            'criteria' => [
                'informational' => 'Seeking tips or guidance',
                'commercial' => 'Researching options to buy',
                'transactional' => 'Ready to buy immediately',
                'navigational' => 'Looking for specific site'
            ]
        ],
        'brand_relation' => [
            'type' => 'choice',
            'instructions' => 'Determine relation to asset',
            'choices' => ['branded', 'generic', 'competitor'],
            'criteria' => [
                'branded' => 'Explicitly mentions brand',
                'generic' => 'Generic product or question',
                'competitor' => 'Mentions rival brand'
            ]
        ]
    ]
);
```

## Running Tests

```bash
# Run unit tests (offline, mocked HTTP)
composer test-unit

# Run live integration tests (against TypeSafe API)
composer test-integration

# Run entire test suite
composer test
```