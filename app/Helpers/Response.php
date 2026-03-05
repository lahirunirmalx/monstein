<?php
/**
 * Standardized API response builder
 * 
 * Consistent JSON responses. No guessing, no surprises.
 * Every response follows the same structure.
 * 
 * Usage:
 *   return Response::success($data);
 *   return Response::error('Not found', 404);
 *   return Response::paginated($items, $total, $page, $perPage);
 * 
 * @package Monstein\Helpers
 */
namespace Monstein\Helpers;

use Monstein\Base\HttpCode;

class Response
{
    /**
     * Success response
     * 
     * @param mixed       $data
     * @param string|null $message
     * @param int         $status
     * @return array
     */
    public static function success($data = null, $message = null, $status = HttpCode::HTTP_OK)
    {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return ['body' => $response, 'status' => $status];
    }

    /**
     * Error response
     * 
     * @param string|array $errors
     * @param int          $status
     * @param array        $extra
     * @return array
     */
    public static function error($errors, $status = HttpCode::HTTP_BAD_REQUEST, array $extra = [])
    {
        $response = array_merge([
            'success' => false,
            'errors' => $errors,
        ], $extra);

        return ['body' => $response, 'status' => $status];
    }

    /**
     * Created response (201)
     * 
     * @param mixed       $data
     * @param string|null $message
     * @return array
     */
    public static function created($data = null, $message = 'Resource created')
    {
        return self::success($data, $message, HttpCode::HTTP_CREATED);
    }

    /**
     * No content response (204)
     * 
     * @return array
     */
    public static function noContent()
    {
        return ['body' => null, 'status' => HttpCode::HTTP_NO_CONTENT];
    }

    /**
     * Not found response (404)
     * 
     * @param string $message
     * @return array
     */
    public static function notFound($message = 'Resource not found')
    {
        return self::error($message, HttpCode::HTTP_NOT_FOUND);
    }

    /**
     * Unauthorized response (401)
     * 
     * @param string $message
     * @return array
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        return self::error($message, HttpCode::HTTP_UNAUTHORIZED);
    }

    /**
     * Forbidden response (403)
     * 
     * @param string $message
     * @return array
     */
    public static function forbidden($message = 'Forbidden')
    {
        return self::error($message, HttpCode::HTTP_FORBIDDEN);
    }

    /**
     * Validation error response (422)
     * 
     * @param array $errors
     * @return array
     */
    public static function validationError(array $errors)
    {
        return self::error($errors, HttpCode::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Rate limit exceeded response (429)
     * 
     * @param int    $retryAfter Seconds until rate limit resets
     * @param string $message
     * @return array
     */
    public static function rateLimited($retryAfter = 60, $message = 'Too many requests')
    {
        return self::error($message, HttpCode::HTTP_TOO_MANY_REQUESTS, [
            'retry_after' => $retryAfter,
        ]);
    }

    /**
     * Server error response (500)
     * 
     * @param string $message
     * @return array
     */
    public static function serverError($message = 'Internal server error')
    {
        return self::error($message, HttpCode::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Paginated list response
     * 
     * @param array $items
     * @param int   $total
     * @param int   $page
     * @param int   $perPage
     * @return array
     */
    public static function paginated(array $items, $total, $page = 1, $perPage = 20)
    {
        $lastPage = (int) ceil($total / $perPage);

        return self::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Collection response (simple list)
     * 
     * @param array $items
     * @param int   $count
     * @return array
     */
    public static function collection(array $items, $count = null)
    {
        return self::success([
            'items' => $items,
            'count' => $count !== null ? $count : count($items),
        ]);
    }

    /**
     * Apply response to Slim response object
     * 
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param array                               $data     From success/error methods
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function apply($response, array $data)
    {
        if ($data['body'] === null) {
            return $response->withStatus($data['status']);
        }

        return $response->withJson($data['body'], $data['status']);
    }
}
