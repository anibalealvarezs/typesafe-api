<?php

namespace Anibalealvarezs\TypeSafeApi;

use Anibalealvarezs\ApiSkeleton\Clients\BearerTokenClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class TypeSafeApi extends BearerTokenClient
{
    public const DEFAULT_MODEL = 'jev-latest';
    public const DEFAULT_BASE_URL = 'https://api.typesafe.ai/v1/';

    /**
     * @param string $apiKey The TypeSafe API key
     * @param string $baseUrl Base URL for TypeSafe API (defaults to https://api.typesafe.ai/v1/)
     * @param GuzzleClient|null $guzzleClient Optional Guzzle HTTP client (useful for mock testing)
     * @param LoggerInterface|null $logger Optional PSR-3 logger
     * @param bool $debugMode Enable debug logging / telemetry in client
     * @throws \Exception
     */
    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?GuzzleClient $guzzleClient = null,
        ?LoggerInterface $logger = null,
        bool $debugMode = false
    ) {
        parent::__construct(
            baseUrl: rtrim($baseUrl, '/') . '/',
            token: $apiKey,
            defaultHeaders: [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            guzzleClient: $guzzleClient,
            logger: $logger,
        );

        $this->setDebugMode($debugMode);
    }

    /**
     * Evaluates a state against a collection of typed questions using TypeSafe System One.
     *
     * @param array|string $state The input state, prompt, or structured context (e.g. query, asset context)
     * @param array $questions Key-value dictionary of question ID => question specifications (type, instructions, criteria, choices)
     * @param string|null $model Model identifier, defaults to "jev-latest"
     * @return array The decoded JSON response containing answers, tokens usage, and calibration metadata
     * @throws GuzzleException
     */
    public function evaluate(array|string $state, array $questions, ?string $model = null): array
    {
        $payload = [
            'model'     => $model ?: self::DEFAULT_MODEL,
            'state'     => $state,
            'questions' => $questions,
        ];

        $response = $this->performRequest(
            method: 'POST',
            endpoint: 'systemone',
            body: json_encode($payload)
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Evaluates a categorical question (type = "choice").
     * Note: TypeSafe System One requires criteria to be an associative dictionary
     * mapping each choice to its definition or description.
     *
     * @param string|array $state
     * @param string $questionId Unique key for the question (e.g. 'intent')
     * @param string $instructions Question instruction / prompt
     * @param array $choices List of valid category choices
     * @param array|null $criteria Optional associative array: ['choice_name' => 'description']
     * @param string|null $model Optional model override
     * @return array Full evaluation response
     * @throws GuzzleException
     */
    public function evaluateChoice(
        string|array $state,
        string $questionId,
        string $instructions,
        array $choices,
        ?array $criteria = null,
        ?string $model = null
    ): array {
        // If no criteria dict is explicitly supplied, provide fallback descriptions from choices
        if ($criteria === null) {
            $criteria = [];
            foreach ($choices as $choice) {
                $criteria[$choice] = "Represents {$choice}";
            }
        }

        $question = [
            'type'         => 'choice',
            'instructions' => $instructions,
            'choices'      => $choices,
            'criteria'     => $criteria,
        ];

        return $this->evaluate(
            state: $state,
            questions: [$questionId => $question],
            model: $model
        );
    }

    /**
     * Evaluates a binary judgment with calibrated confidence (type = "noul" / 0.0 to 1.0 probability).
     * Ideal for yes/no conditions, relevance checks, and brand detection.
     *
     * @param string|array $state
     * @param string $questionId
     * @param string $instructions
     * @param string|null $model
     * @return array
     * @throws GuzzleException
     */
    public function evaluateNoul(
        string|array $state,
        string $questionId,
        string $instructions,
        ?string $model = null
    ): array {
        $question = [
            'type'         => 'noul',
            'instructions' => $instructions,
        ];

        return $this->evaluate(
            state: $state,
            questions: [$questionId => $question],
            model: $model
        );
    }

    /**
     * Evaluates a calibrated scalar score question (type = "score").
     * Note: TypeSafe requires criteria to be an indexed list of boundary/level descriptions (e.g. [min, ..., max]).
     *
     * @param string|array $state
     * @param string $questionId
     * @param string $instructions
     * @param array $criteria Indexed list of level descriptions (e.g. ['Very Low', 'Low', 'Medium', 'High', 'Very High'])
     * @param string|null $model
     * @return array
     * @throws GuzzleException
     */
    public function evaluateScore(
        string|array $state,
        string $questionId,
        string $instructions,
        array $criteria,
        ?string $model = null
    ): array {
        $question = [
            'type'         => 'score',
            'instructions' => $instructions,
            'criteria'     => array_values($criteria),
        ];

        return $this->evaluate(
            state: $state,
            questions: [$questionId => $question],
            model: $model
        );
    }
}