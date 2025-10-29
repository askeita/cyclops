<?php

namespace App\Tests\Repository;

use App\Repository\CrisisRepository;
use App\Entity\Crisis;
use Aws\Command;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


/**
 * Class CrisisRepositoryTest
 *
 * Tests for the CrisisRepository class.
 */
class CrisisRepositoryTest extends WebTestCase
{
    private DynamoDbClient|MockObject $dynamoDbClient;
    private CrisisRepository $repository;


    /**
     * Sets up the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Create a partial mock that allows us to override specific methods
        $this->dynamoDbClient = $this->getMockBuilder(DynamoDbClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $this->repository = new CrisisRepository($this->dynamoDbClient);
    }

    /**
     * Tests the findAll method for successful data retrieval.
     *
     * @return void
     */
    public function testFindAllSuccess(): void
    {
        $mockResult = [
            'Items' => [
                [
                    'id' => ['S' => '1'],
                    'name' => ['S' => 'Financial Crisis 2008'],
                    'category' => ['S' => 'Financial'],
                    'origin' => ['S' => 'United States'],
                    'start_date' => ['S' => '2007-07-01'],
                    'end_date' => ['S' => '2009-07-01'],
                    'duration' => ['N' => '730'],
                    'causes' => ['L' => [
                        ['S' => 'Subprime mortgages'],
                        ['S' => 'Deregulation']
                    ]]
                ]
            ]
        ];

        $this->dynamoDbClient
            ->expects($this->once())
            ->method('__call')
            ->with('scan', [['TableName' => 'Crises']])
            ->willReturn($mockResult);

        $result = $this->repository->findAll();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Crisis::class, $result[0]);
        $this->assertEquals('1', $result[0]->getId());
        $this->assertEquals('Financial Crisis 2008', $result[0]->getName());
    }

    /**
     * Tests the findAll method when a DynamoDbException is thrown.
     *
     * @return void
     */
    public function testFindAllWithDynamoDbException(): void
    {
        $this->dynamoDbClient
            ->expects($this->once())
            ->method('__call')
            ->with('scan')
            ->willThrowException(new DynamoDbException('Connection failed',
                $this->createMock(Command::class)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error when fetching data: Connection failed');

        $this->repository->findAll();
    }

    /**
     * Tests the findOne method for successful data retrieval.
     *
     * @return void
     */
    public function testFindOneSuccess(): void
    {
        $mockResult = [
            'Item' => [
                'id' => ['S' => '1'],
                'name' => ['S' => 'Financial Crisis 2008'],
                'category' => ['S' => 'Financial'],
                'origin' => ['S' => 'United States']
            ]
        ];

        $this->dynamoDbClient
            ->expects($this->once())
            ->method('__call')
            ->with('getItem', [[
                'TableName' => 'Crises',
                'Key' => ['id' => ['S' => '1']]
            ]])
            ->willReturn($mockResult);

        $result = $this->repository->findOne('1');

        $this->assertInstanceOf(Crisis::class, $result);
        $this->assertEquals('1', $result->getId());
        $this->assertEquals('Financial Crisis 2008', $result->getName());
    }

    /**
     * Tests the findByName method for successful data retrieval.
     *
     * @return void
     */
    public function testFindByName(): void
    {
        $mockResult = [
            'Items' => [
                [
                    'id' => ['S' => 'crisis_1'],
                    'name' => ['S' => 'COVID Crisis'],
                    'category' => ['S' => 'Health']
                ]
            ]
        ];

        $this->dynamoDbClient
            ->expects($this->once())
            ->method('__call')
            ->with('scan')
            ->willReturn($mockResult);

        $crises = $this->repository->findByName('covid');

        $this->assertIsArray($crises);
        $this->assertCount(1, $crises);
    }

    /**
     * Tests the findOne method when no item is found.
     *
     * @return void
     */
    public function testFindOneNotFound(): void
    {
        $this->dynamoDbClient
            ->expects($this->once())
            ->method('__call')
            ->with('getItem')
            ->willReturn([]);

        $result = $this->repository->findOne('999');

        $this->assertNull($result);
    }

    /**
     * Tests the internal entity mapping logic.
     *
     * @return void
     * @throws Exception
     */
    public function testEntityMappingLogic(): void
    {
        // Test the internal mapping logic without DynamoDB dependency
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('mapDynamoItemToCrisis');

        $item = [
            'id' => ['S' => '1'],
            'name' => ['S' => 'Test Crisis'],
            'category' => ['S' => 'Financial'],
            'causes' => ['L' => [
                ['S' => 'Cause 1'],
                ['S' => 'Cause 2']
            ]]
        ];

        try {
            $crisis = $method->invoke($this->repository, $item);
        } catch (ReflectionException $e) {
            throw new Exception('Failed to access method', 0, $e);
        }

        $this->assertInstanceOf(Crisis::class, $crisis);
        $this->assertEquals('1', $crisis->getId());
        $this->assertEquals('Test Crisis', $crisis->getName());
        $this->assertEquals('Financial', $crisis->getCategory());
        $this->assertEquals(['Cause 1', 'Cause 2'], $crisis->getCauses());
    }

    /**
     * Tests the date pattern matching logic.
     *
     * @return void
     * @throws Exception
     */
    public function testDatePatternMatching(): void
    {
        // Test the date pattern matching logic
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('matchDatePattern');

        try {
            // Test YYYY format
            $this->assertTrue($method->invoke($this->repository, '2008-01-01', '2008'));
            $this->assertFalse($method->invoke($this->repository, '2009-01-01', '2008'));

            // Test YYYY-MM format
            $this->assertTrue($method->invoke($this->repository, '2008-01-01', '2008-01'));
            $this->assertFalse($method->invoke($this->repository, '2008-02-01', '2008-01'));

            // Test YYYY-MM-DD format
            $this->assertTrue($method->invoke($this->repository, '2008-01-01', '2008-01-01'));
            $this->assertFalse($method->invoke($this->repository, '2008-01-02', '2008-01-01'));
        } catch (ReflectionException $e) {
            throw new Exception('Failed to access method', 0, $e);
        }

    }

    /**
     * Tests the findByType method for successful data retrieval.
     *
     * @return void
     */
    public function testFindByTypeSuccess(): void
    {
        // Tests the findByType method
        $mockResult = [
            'Items' => [
                [
                    'id' => ['S' => '1'],
                    'name' => ['S' => 'Financial Crisis'],
                    'category' => ['S' => 'Financial'],
                    'type' => ['S' => 'Financial']
                ]
            ]
        ];

        $this->dynamoDbClient
            ->expects($this->once())
            ->method('__call')
            ->with('scan')
            ->willReturn($mockResult);

        // Test with type 'Financial'
        $result = $this->repository->findByName('Financial');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }
}
