<?php

namespace App\Command;

use App\Service\ApiKeyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * Command to generate a new API key
 */
#[AsCommand(
    name: 'app:generate-api-key',
    description: 'Generate a new API key',
)]
class GenerateApiKeyCommand extends Command
{
    private ApiKeyService $apiKeyService;

    /**
     * Constructor
     *
     * @param ApiKeyService $apiKeyService
     */
    public function __construct(ApiKeyService $apiKeyService)
    {
        parent::__construct();
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Configures the command arguments and options
     */
    protected function configure(): void {}

    /**
     * Executes the command to generate a new API key
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $apiKey = $this->apiKeyService->createApiKey();

            $io->success('API Key successfully generated!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['API key', $apiKey->getKeyValue()],
                    ['Created at', $apiKey->getCreatedAt()->format('Y-m-d H:i:s')],
                    ['Active', $apiKey->isActive() ? 'Yes' : 'No'],
                ]
            );

            $io->note([
                'Keep this key safe !',
                'Use it in your requests with the X-API-KEY header.',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error generating API key: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
