<?php

namespace App\Tests\Security;

use App\Security\ApiLoginEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


/**
 * Class ApiLoginEntryPointTest
 *
 * Tests for ApiLoginEntryPoint
 */
class ApiLoginEntryPointTest extends TestCase
{
    /**
     * Test that start() method returns a 401 JSON response
     */
    public function testStartReturns401Json(): void
    {
        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $entryPoint = new ApiLoginEntryPoint($urlGen);

        $request = Request::create('/api/crises');
        $response = $entryPoint->start($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Authentication required', $data['message'] ?? null);
        $this->assertSame('Unauthorized', $data['error'] ?? null);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }
}

