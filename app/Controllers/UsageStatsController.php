<?php

namespace Monstein\Controllers;

use Monstein\Base\BaseController;
use Monstein\Base\UsageTracker;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

/**
 * Usage Statistics Controller
 * 
 * Provides API endpoints to view usage statistics.
 * Only accessible to authenticated users.
 * 
 * Endpoints:
 *   GET /usage/stats          - Get overall statistics
 *   GET /usage/top            - Get top endpoints
 *   GET /usage/slow           - Get slowest endpoints
 *   GET /usage/errors         - Get error rates
 */
class UsageStatsController extends BaseController
{
    /** @var UsageTracker */
    private $tracker;

    public function __construct($depLogger, $depDB, $depValidator)
    {
        parent::__construct($depLogger, $depDB, $depValidator);
        $this->tracker = new UsageTracker(['logger' => $depLogger]);
    }

    public function validateGetRequest()
    {
        return [
            'period' => [
                'rules' => V::optional(V::in(['hour', 'day', 'week', 'month', 'all'])),
                'message' => 'Period must be: hour, day, week, month, or all'
            ],
            'endpoint' => [
                'rules' => V::optional(V::stringType()),
                'message' => 'Invalid endpoint filter'
            ],
            'limit' => [
                'rules' => V::optional(V::intVal()->positive()->max(100)),
                'message' => 'Limit must be between 1 and 100'
            ],
        ];
    }

    public function validatePostRequest()
    {
        return [];
    }

    public function validatePatchRequest()
    {
        return [];
    }

    public function validatePutRequest()
    {
        return [];
    }

    public function validateDeleteRequest()
    {
        return [];
    }

    /**
     * GET /usage/stats - Get usage statistics
     */
    public function doGet(Request $request, Response $response, $args, $cleanValues)
    {
        $period = $cleanValues['period'] ?? 'day';
        $endpoint = $cleanValues['endpoint'] ?? '';

        $stats = $this->tracker->getStats($endpoint, $period);

        return $response->withJson([
            'success' => true,
            'data' => $stats,
        ], 200);
    }

    /**
     * GET /usage/top - Get top endpoints by request count
     */
    public function topEndpoints(Request $request, Response $response, $args)
    {
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int) $params['limit'] : 10;
        $period = $params['period'] ?? 'day';

        $top = $this->tracker->getTopEndpoints($limit, $period);

        return $response->withJson([
            'success' => true,
            'data' => $top,
        ], 200);
    }

    /**
     * GET /usage/slow - Get slowest endpoints
     */
    public function slowEndpoints(Request $request, Response $response, $args)
    {
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int) $params['limit'] : 10;
        $period = $params['period'] ?? 'day';

        $slow = $this->tracker->getSlowestEndpoints($limit, $period);

        return $response->withJson([
            'success' => true,
            'data' => $slow,
        ], 200);
    }

    /**
     * GET /usage/errors - Get error rates
     */
    public function errorRates(Request $request, Response $response, $args)
    {
        $params = $request->getQueryParams();
        $period = $params['period'] ?? 'day';

        $errors = $this->tracker->getErrorRates($period);

        return $response->withJson([
            'success' => true,
            'data' => $errors,
        ], 200);
    }

    public function doPost(Request $request, Response $response, $args, $cleanValues)
    {
        return $this->handleUnsupportedMethod($request, $response);
    }

    public function doPut(Request $request, Response $response, $args, $cleanValues)
    {
        return $this->handleUnsupportedMethod($request, $response);
    }

    public function doPatch(Request $request, Response $response, $args, $cleanValues)
    {
        return $this->handleUnsupportedMethod($request, $response);
    }

    public function doDelete(Request $request, Response $response, $args, $cleanValues)
    {
        return $this->handleUnsupportedMethod($request, $response);
    }
}
