<?php

namespace App\Tests\Command;

use App\Command\GenerateApiKeyCommand;
use App\Service\ApiKeyService;
use App\Entity\ApiKey;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\MockObject\MockObject;


/**
 * Class GenerateApiKeyCommandTest
 *
 * Tests for the GenerateApiKeyCommand.
 */
class GenerateApiKeyCommandTest extends WebTestCase
{
    private ApiKeyService|MockObject $apiKeyService;
    private GenerateApiKeyCommand $command;
    private CommandTester $commandTester;


    /**
     * Sets up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->apiKeyService = $this->createMock(ApiKeyService::class);
        $this->command = new GenerateApiKeyCommand($this->apiKeyService);

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * Tests successful execution of the command.
     *
     * @return void
     */
    public function testExecuteSuccessfully(): void
    {
        $apiKey = new ApiKey();
        $apiKey->setKeyValue('test_api_key_123');
        $apiKey->setCreatedAt(new DateTime());
        $apiKey->setIsActive(true);

        $this->apiKeyService
            ->expects($this->once())
            ->method('createApiKey')
            ->willReturn($apiKey);

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('API Key successfully generated!', $output);
        $this->assertStringContainsString('test_api_key_123', $output);
        $this->assertStringContainsString('Keep this key safe !', $output);
    }

    /**
     * Tests execution of the command when an exception is thrown.
     *
     * @return void
     */
    public function testExecuteWithException(): void
    {
        $this->apiKeyService
            ->expects($this->once())
            ->method('createApiKey')
            ->willThrowException(new Exception('Database error'));

        $this->commandTester->execute([]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Error generating API key: Database error', $output);
    }

    /**
     * Tests the command configuration.
     *
     * @return void
     */
    public function testCommandConfiguration(): void
    {
        $this->assertEquals('app:generate-api-key', $this->command->getName());
        $this->assertEquals('Generate a new API key', $this->command->getDescription());
    }
}
