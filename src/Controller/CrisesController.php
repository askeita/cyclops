<?php

namespace App\Controller;

use App\Repository\CrisisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


/**
 * Class CrisesController
 */
#[Route('/api/crises', name: 'api_crises_')]
#[IsGranted('ROLE_API_USER')]
class CrisesController extends AbstractController
{
    private CrisisRepository $crisisRepository;


    /**
     * CrisesController constructor.
     *
     * @param CrisisRepository $crisisRepository
     */
    public function __construct(CrisisRepository $crisisRepository)
    {
        $this->crisisRepository = $crisisRepository;
    }

    /**
     * List all crises
     *
     * @return JsonResponse
     */
    #[Route('/', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        try {
            $crises = $this->crisisRepository->findAll();

            return new JsonResponse($crises);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error fetching crises',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API health check route
     *
     * @return JsonResponse
     */
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'healthy',
            'service' => 'Crisis Financial Data API',
            'version' => '1.0.0',
            'timestamp' => date('c'),
            'endpoints' => [
                'documentation' => '/api/docs',
                'swagger_ui' => '/api',
                'crises' => '/api/crises'
            ]
        ]);
    }

    /**
     * Get API statistics
     *
     * @return JsonResponse
     */
    #[Route('/stats', name: 'statistics', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        try {
            $allCrises = $this->crisisRepository->findAll();

            $stats = [
                'api' => [
                    'name' => 'Crisis Financial Data API',
                    'version' => '1.0.0',
                    'total_endpoints' => count($allCrises),
                    'available_routes' => [
                        'GET /api' => 'Liste des ressources disponibles',
                        'GET /api/crises' => 'Toutes les crises financières',
                        'GET /api/crises/{id}' => 'Crise spécifique',
                        'GET /api/docs' => 'Documentation API'
                    ]
                ],
                'data' => [
                    'estimated_crises_count' => '56+',
                    'time_period' => '1975-2023',
                    'coverage' => 'Crises financières et économiques mondiales'
                ],
                'authentication' => [
                    'required' => true,
                    'methods' => ['api_key query parameter', 'X-API-KEY header']
                ]
            ];

            return new JsonResponse($stats);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du calcul des statistiques',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search crises by type
     *
     * @param string $type
     * @return JsonResponse
     */
    #[Route('/search/by-type/{type}', name: 'search_by_type', methods: ['GET'])]
    public function searchByType(string $type): JsonResponse
    {
        try {
            $crises = $this->crisisRepository->findByType($type);

            return new JsonResponse([
                'type' => $type,
                'count' => count($crises),
                'data' => $crises
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error during search',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
