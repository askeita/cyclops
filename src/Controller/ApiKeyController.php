<?php

namespace App\Controller;

use App\Service\ApiKeyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;


/**
 * ApiKeyController
 */
#[Route('/api', name: 'api_')]
class ApiKeyController extends AbstractController
{
    private ApiKeyService $apiKeyService;
    private CsrfTokenManagerInterface $csrfTokenManager;

    /**
     * Constructor
     *
     * @param ApiKeyService $apiKeyService
     * @param CsrfTokenManagerInterface $csrfTokenManager
     */
    public function __construct(ApiKeyService $apiKeyService, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->apiKeyService = $apiKeyService;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Create a new API key
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api-keys', name: 'create_api_key', methods: ['POST'])]
    public function createApiKey(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $apiKey = $this->apiKeyService->createApiKey($data['name']);

        return new JsonResponse([
            'message' => 'API Key successfully created',
            'api_key' => $apiKey->getKeyValue(),
            'created_at' => $apiKey->getCreatedAt()->format('Y-m-d H:i:s'),
        ], 201);
    }

    /**
     * List all API keys
     *
     * @return JsonResponse
     */
    #[Route('/api-keys', name: 'list_api_keys', methods: ['GET'])]
    public function listApiKeys(): JsonResponse
    {
        $apiKeys = $this->apiKeyService->getAllApiKeys();

        $data = array_map(function($apiKey) {
            return [
                'id' => $apiKey->getId(),
                'key_value' => substr($apiKey->getKeyValue(), 0, 8) . '...',
                'is_active' => $apiKey->isActive(),
                'created_at' => $apiKey->getCreatedAt()->format('Y-m-d H:i:s'),
                'last_used_at' => $apiKey->getLastUsedAt()?->format('Y-m-d H:i:s'),
                'usage_count' => $apiKey->getUsageCount()
            ];
        }, $apiKeys);

        return new JsonResponse($data);
    }

    /**
     * Deactivate an API key
     *
     * @param string $keyValue
     * @return JsonResponse
     */
    #[Route('/api-keys/{keyValue}/deactivate', name: 'deactivate_api_key', methods: ['DELETE'])]
    public function deactivateApiKey(string $keyValue): JsonResponse
    {
        $success = $this->apiKeyService->deactivateApiKey($keyValue);

        if (!$success) {
            return new JsonResponse([
                'error' => 'API Key not found'
            ], 404);
        }

        return new JsonResponse([
            'message' => 'API Key successfully deactivated'
        ]);
    }
}
