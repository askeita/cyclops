<?php

namespace App\Repository;

use ApiPlatform\Metadata\Get;
use App\Entity\Crisis;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Metadata\Operation;
use Aws\Result;


/**
 * Repository to manage Crisis entities stored in DynamoDB.
 */
class CrisisRepository implements ProviderInterface
{
    private DynamoDbClient $dynamoDbClient;
    private string $tableName = 'Crises';


    /**
     * CrisisRepository constructor.
     *
     * @param DynamoDbClient $dynamoDbClient
     */
    public function __construct(DynamoDbClient $dynamoDbClient)
    {
        $this->dynamoDbClient = $dynamoDbClient;
    }

    /**
     * Provides data for API Platform operations.
     *
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $uriTemplate = $operation->getUriTemplate();

        if ($operation instanceof Get &&
            isset($uriVariables['id']) &&
            str_contains($uriTemplate, '/crises/{id}')
        ) {
            return $this->findOne($uriVariables['id']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-name/')) {
            return $this->findByName($uriVariables['name']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-category/')) {
            return $this->findByCategory($uriVariables['category']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-origin/')) {
            return $this->findByOrigin($uriVariables['origin']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-causes/')) {
            return $this->findByCause($uriVariables['cause']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-start-date/')) {
            return $this->findByStartDate((int)$uriVariables['date']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-end-date/')) {
            return $this->findByEndDate($uriVariables['date']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-duration/')) {
            return $this->findByDuration((int)$uriVariables['months']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-aggravating-circumstances/')) {
            return $this->findByAggravatingCircumstances($uriVariables['circumstance']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-geographical-extension/')) {
            return $this->findByGeographicalExtension($uriVariables['region']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-prices-evolution/')) {
            return $this->findByPricesEvolution($uriVariables['pricesEvolution']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-consequences/')) {
            return $this->findByConsequence($uriVariables['consequence']);
        }

        if (str_contains($uriTemplate, '/crises/search/by-resolutions/')) {
            return $this->findByResolution($uriVariables['resolution']);
        }

        return $this->findAll();
    }

    /**
     * Fetches all crises from the DynamoDB table.
     *
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findAll(): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
            ]);

            $crises = [];
            foreach ($result['Items'] as $item) {
                $crises[] = $this->mapDynamoItemToCrisis($item);
            }

            // Ascending order by ID
            usort($crises, fn(Crisis $a, Crisis $b) => $a->getId() <=> $b->getId());

            return $crises;
        } catch (DynamoDbException $e) {
            if ($e->getAwsErrorCode() === 'UnrecognizedClientException') {
                throw new \RuntimeException(
                    'AWS credentials are invalid or expired. Please check your AWS configuration.'
                );
            }

            throw new \RuntimeException('Error when fetching data: ' . $e->getMessage());
        }
    }

    /**
     * Fetches a single crisis by its ID.
     *
     * @param string $id
     * @return Crisis|null
     * @throws \RuntimeException
     */
    public function findOne(string $id): ?Crisis
    {
        try {
            $result = $this->dynamoDbClient->getItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'id' => ['S' => $id]
                ]
            ]);

            if (!isset($result['Item'])) {
                return null;
            }

            return $this->mapDynamoItemToCrisis($result['Item']);
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error when retrieving the crisis: ' . $e->getMessage());
        }
    }

    /**
     * Finds a crisis by its name.
     *
     * @param string $name
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByName(string $name): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(#name)',
                'ExpressionAttributeNames' => [
                    '#name' => 'name'
                ]
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                // Check for names stored as String (S)
                if (isset($item['name']['S'])) {
                    if (stripos($item['name']['S'], $name) !== false) {
                        $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                        break;
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error when retrieving the crisis by name: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their category.
     *
     * @param string $category
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByCategory(string $category): array
    {
        try {
            $category = ucfirst($category);
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => '#category = :category',
                'ExpressionAttributeNames' => [
                    '#category' => 'category'
                ],
                'ExpressionAttributeValues' => [
                    ':category' => ['S' => $category]
                ]
            ]);

            return $this->mapResultsToCrises($result);
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by category: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their type (alias for category).
     *
     * @param string $type
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByType(string $type): array
    {
        try {
            $type = ucfirst($type);
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => '#category = :category',
                'ExpressionAttributeNames' => [
                    '#category' => 'category'
                ],
                'ExpressionAttributeValues' => [
                    ':category' => ['S' => $type]
                ]
            ]);

            return $this->mapResultsToCrises($result);
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by type: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their origin.
     *
     * @param string $origin
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByOrigin(string $origin): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => '#origin = :origin',
                'ExpressionAttributeNames' => [
                    '#origin' => 'origin'
                ],
                'ExpressionAttributeValues' => [
                    ':origin' => ['S' => $origin]
                ]
            ]);

            return $this->mapResultsToCrises($result);
        } catch (DynamoDbException $e) {
            throw new \RuntimeException("Error searching by origin: " . $e->getMessage());
        }
    }

    /**
     * Finds crises by a specific cause.
     *
     * @param string $cause
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByCause(string $cause): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(causes)'
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                // Check for causes stored as List (L)
                if (isset($item['causes']['L'])) {
                    foreach ($item['causes']['L'] as $causeItem) {
                        if (isset($causeItem['S']) &&
                            stripos($causeItem['S'], $cause) !== false) {
                            $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                            break;
                        }
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by cause: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their start date.
     *
     * @param int $date
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByStartDate(int $date): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(start_date)',
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                if (isset($item['start_date']['S'])) {
                    $itemStartDate = $item['start_date']['S'];

                    // Support for different date formats
                    if ($this->matchDatePattern($itemStartDate, $date)) {
                        $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by start date: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their start date.
     *
     * @param string $date
     * @return Crisis[]
     */
    public function findByEndDate(string $date): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(end_date)',
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                if (isset($item['end_date']['S'])) {
                    $itemEndDate = $item['end_date']['S'];

                    // Support for different date formats
                    if ($this->matchDatePattern($itemEndDate, $date)) {
                        $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by end date: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by a specific cause.
     *
     * @param int $duration
     * @return Crisis[]
     */
    public function findByDuration(int $duration): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(#duration) OR attribute_exists(durationInMonths)',
                'ExpressionAttributeNames' => [
                    '#duration' => 'duration'
                ]
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                // Check for duration stored as Number (N)
                if (isset($item['duration']['N'])) {
                    if ((int)$item['duration']['N'] === $duration) {
                        $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by duration: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their aggravating circumstances.
     *
     * @param string $aggCircumstance
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByAggravatingCircumstances(string $aggCircumstance): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(aggravating_circumstances)',
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                // Check for aggravating circumstances stored as List (L)
                if (isset($item['aggravating_circumstances']['L'])) {
                    foreach ($item['aggravating_circumstances']['L'] as $circumstance) {
                        if (isset($circumstance['S']) &&
                            str_contains(strtolower($circumstance['S']), strtolower($aggCircumstance))) {
                            $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                            break;
                        }
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by aggravating circumstances: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their geographical extension (region).
     *
     * @param string $region
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByGeographicalExtension(string $region): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(geographical_extension)',
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                // Check for geographical extension stored as String (S)
                if (isset($item['geographical_extension']['S'])) {
                    if (stripos($item['geographical_extension']['S'], $region) !== false) {
                        $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                        break;
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by geographical extension: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their start date.
     *
     * @param string $pricesEvolution
     * @return Crisis[]
     */
    public function findByPricesEvolution(string $pricesEvolution): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(prices_evolution)',
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                if (isset($item['prices_evolution']['L'])) {
                    foreach ($item['prices_evolution']['L'] as $evolutionItem) {
                        if (isset($evolutionItem['S'])
                            && str_contains(strtolower($evolutionItem['S']), strtolower($pricesEvolution))) {
                            $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                            break;
                        }
                    }
                }
            }
            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by prices evolution: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their consequences.
     *
     * @param string $consequences
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByConsequence(string $consequences): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(direct_consequences) OR attribute_exists(indirect_consequences)'
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                // Check direct consequences first in List (L)
                if (isset($item['direct_consequences']['L'])) {
                    foreach ($item['direct_consequences']['L'] as $consequenceItem) {
                        if (isset($consequenceItem['S']) &&
                            stripos($consequenceItem['S'], $consequences) !== false) {
                            $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                            break;
                        }
                    }
                }

                // Check indirect consequences in List (L)
                if (isset($item['indirect_consequences']['L'])) {
                    foreach ($item['indirect_consequences']['L'] as $consequenceItem) {
                        if (isset($consequenceItem['S']) &&
                            stripos($consequenceItem['S'], $consequences) !== false) {
                            $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                            break;
                        }
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by consequences: ' . $e->getMessage());
        }
    }

    /**
     * Finds crises by their resolutions.
     *
     * @param string $resolution
     * @return Crisis[]
     * @throws \RuntimeException
     */
    public function findByResolution(string $resolution): array
    {
        try {
            $result = $this->dynamoDbClient->scan([
                'TableName' => $this->tableName,
                'FilterExpression' => 'attribute_exists(resolutions)',
            ]);

            $filteredCrises = [];
            foreach ($result['Items'] as $item) {
                // Check for resolutions stored as List (L)
                if (isset($item['resolutions']['L'])) {
                    foreach ($item['resolutions']['L'] as $resolutionItem) {
                        if (isset($resolutionItem['S']) && stripos($resolutionItem['S'], $resolution) !== false) {
                            $filteredCrises[] = $this->mapDynamoItemToCrisis($item);
                            break;
                        }
                    }
                }
            }

            return $filteredCrises;
        } catch (DynamoDbException $e) {
            throw new \RuntimeException('Error searching by resolutions: ' . $e->getMessage());
        }
    }

    /**
     * Maps DynamoDB scan results to an array of Crisis entities.
     *
     * @param array|Result $result
     * @return Crisis[]
     */
    private function mapResultsToCrises(array|Result $result): array
    {
        $crises = [];
        foreach ($result['Items'] as $item) {
            $crises[] = $this->mapDynamoItemToCrisis($item);
        }

        return $crises;
    }

    /**
     * Maps a DynamoDB item to a Crisis entity.
     *
     * @param array $item
     * @return Crisis
     */
    private function mapDynamoItemToCrisis(array $item): Crisis
    {
        $crisis = new Crisis();

        $crisis->setId($item['id']['S'] ?? null);
        $crisis->setName($item['name']['S'] ?? null);
        $crisis->setCategory($item['category']['S'] ?? null);
        $crisis->setOrigin($item['origin']['S'] ?? null);
        $crisis->setStartDate($item['start_date']['S'] ?? null);
        $crisis->setEndDate($item['end_date']['S'] ?? null);
        $crisis->setDurationInMonths($item['duration']['N'] ?? null);

        // Causes
        if (isset($item['causes']['L'])) {
            $causes = [];
            foreach ($item['causes']['L'] as $cause) {
                $causes[] = $cause['S'];
            }
            $crisis->setCauses($causes);
        }

        // Aggravating circumstances
        if (isset($item['aggravating_circumstances']['L'])) {
            $circumstances = [];
            foreach ($item['aggravating_circumstances']['L'] as $circumstance) {
                $circumstances[] = $circumstance['S'];
            }
            $crisis->setAggravatingCircumstances($circumstances);
        }

        // Geographical extension
        $crisis->setGeographicalExtension($item['geographical_extension']['S'] ?? null);

        // Prices evolution
        if (isset($item['prices_evolution']['L'])) {
            $pricesEvolution = [];
            foreach ($item['prices_evolution']['L'] as $evolution) {
                $pricesEvolution[] = $evolution['S'];
            }
            $crisis->setPricesEvolution($pricesEvolution);
        }

        // Direct consequences
        if (isset($item['direct_consequences']['L'])) {
            $consequences = [];
            foreach ($item['direct_consequences']['L'] as $consequence) {
                $consequences[] = $consequence['S'];
            }
            $crisis->setDirectConsequences($consequences);
        }

        // Indirect consequences
        if (isset($item['indirect_consequences']['L'])) {
            $consequences = [];
            foreach ($item['indirect_consequences']['L'] as $consequence) {
                $consequences[] = $consequence['S'];
            }
            $crisis->setIndirectConsequences($consequences);
        }

        // Resolutions
        if (isset($item['resolutions']['L'])) {
            $resolutions = [];
            foreach ($item['resolutions']['L'] as $resolution) {
                $resolutions[] = $resolution['S'];
            }
            $crisis->setResolutions($resolutions);
        }

        // References
        if (isset($item['references']['L'])) {
            $references = [];
            foreach ($item['references']['L'] as $reference) {
                $references[] = $reference['S'];
            }
            $crisis->setReferences($references);
        }

        return $crisis;
    }

    /**
     * Matches item date against search date with support for various formats.
     *
     * @param string $itemDate      item date from DynamoDB
     * @param string $searchDate    search date input
     * @return bool true if matches, false otherwise
     */
    private function matchDatePattern(string $itemDate, string $searchDate): bool
    {
        // YYYY format
        if (preg_match('/^\d{4}$/', $searchDate)) {
            return str_starts_with($itemDate, $searchDate);
        }

        // YYYY-MM format
        if (preg_match('/^\d{4}-\d{2}$/', $searchDate)) {
            return str_starts_with($itemDate, $searchDate);
        }

        // YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $searchDate)) {
            return $itemDate === $searchDate;
        }

        return false;
    }
}
