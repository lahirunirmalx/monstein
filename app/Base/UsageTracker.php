<?php

namespace Monstein\Base;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * API Usage Tracker
 * 
 * Tracks endpoint usage statistics including:
 * - Request count per endpoint
 * - Response times
 * - Status codes
 * - User/IP information
 * - Timestamps
 * 
 * Storage options:
 * - database: Store in usage_logs table (recommended for production)
 * - file: Store in JSON files (good for development)
 * - memory: In-memory only (for testing)
 * 
 * Supports PHP 7.4 and 8.x
 */
class UsageTracker
{
    /** @var string Storage driver: 'database', 'file', 'memory' */
    private $driver;

    /** @var string Storage path for file driver */
    private $storagePath;

    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    /** @var array In-memory storage */
    private static $memoryStore = [];

    /** @var array Default configuration */
    private static $defaults = [
        'driver' => 'database',
        'storage_path' => null,
        'batch_size' => 100,      // Batch insert size for database
        'retention_days' => 90,   // How long to keep logs
        'track_ip' => true,
        'track_user' => true,
        'track_user_agent' => false,
        'track_request_body' => false,  // Warning: may contain sensitive data
    ];

    /** @var array Pending logs for batch insert */
    private static $pendingLogs = [];

    /**
     * @param array $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->driver = $options['driver'] ?? $_ENV['USAGE_TRACKER_DRIVER'] ?? self::$defaults['driver'];
        $this->storagePath = $options['storage_path'] ?? $this->getDefaultStoragePath();
        $this->logger = $options['logger'] ?? null;

        // Ensure storage directory exists for file driver
        if ($this->driver === 'file' && !is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Get default storage path
     * 
     * @return string
     */
    private function getDefaultStoragePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/usage';
    }

    /**
     * Track an API request
     * 
     * @param array $data Request data to track
     * @return bool
     */
    public function track(array $data): bool
    {
        $record = [
            'endpoint' => $data['endpoint'] ?? '',
            'method' => $data['method'] ?? 'GET',
            'status_code' => $data['status_code'] ?? 200,
            'response_time_ms' => $data['response_time_ms'] ?? 0,
            'user_id' => $data['user_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'request_size' => $data['request_size'] ?? 0,
            'response_size' => $data['response_size'] ?? 0,
            'route_name' => $data['route_name'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        switch ($this->driver) {
            case 'database':
                return $this->trackToDatabase($record);
            case 'file':
                return $this->trackToFile($record);
            case 'memory':
                return $this->trackToMemory($record);
            default:
                return false;
        }
    }

    /**
     * Track to database
     * 
     * @param array $record
     * @return bool
     */
    private function trackToDatabase(array $record): bool
    {
        try {
            DB::table('usage_logs')->insert($record);
            return true;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to track usage to database', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Track to file (daily rotation)
     * 
     * @param array $record
     * @return bool
     */
    private function trackToFile(array $record): bool
    {
        $filename = $this->storagePath . '/usage_' . date('Y-m-d') . '.jsonl';
        
        $line = json_encode($record) . "\n";
        
        if (@file_put_contents($filename, $line, FILE_APPEND | LOCK_EX) === false) {
            $this->log('error', 'Failed to track usage to file', ['file' => $filename]);
            return false;
        }
        
        return true;
    }

    /**
     * Track to memory
     * 
     * @param array $record
     * @return bool
     */
    private function trackToMemory(array $record): bool
    {
        self::$memoryStore[] = $record;
        return true;
    }

    /**
     * Get usage statistics for an endpoint
     * 
     * @param string $endpoint Endpoint path
     * @param string $period 'day', 'week', 'month', 'all'
     * @return array
     */
    public function getStats(string $endpoint = '', string $period = 'day'): array
    {
        $startDate = $this->getStartDate($period);

        switch ($this->driver) {
            case 'database':
                return $this->getStatsFromDatabase($endpoint, $startDate);
            case 'file':
                return $this->getStatsFromFile($endpoint, $startDate);
            case 'memory':
                return $this->getStatsFromMemory($endpoint, $startDate);
            default:
                return [];
        }
    }

    /**
     * Get statistics from database
     * 
     * @param string $endpoint
     * @param string $startDate
     * @return array
     */
    private function getStatsFromDatabase(string $endpoint, string $startDate): array
    {
        try {
            $query = DB::table('usage_logs')
                ->where('created_at', '>=', $startDate);

            if (!empty($endpoint)) {
                $query->where('endpoint', $endpoint);
            }

            $total = $query->count();
            
            $byEndpoint = DB::table('usage_logs')
                ->select('endpoint', 'method')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('AVG(response_time_ms) as avg_response_time')
                ->selectRaw('MIN(response_time_ms) as min_response_time')
                ->selectRaw('MAX(response_time_ms) as max_response_time')
                ->selectRaw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count')
                ->where('created_at', '>=', $startDate)
                ->when(!empty($endpoint), function ($q) use ($endpoint) {
                    return $q->where('endpoint', $endpoint);
                })
                ->groupBy('endpoint', 'method')
                ->orderByDesc('count')
                ->get()
                ->toArray();

            $byStatusCode = DB::table('usage_logs')
                ->select('status_code')
                ->selectRaw('COUNT(*) as count')
                ->where('created_at', '>=', $startDate)
                ->when(!empty($endpoint), function ($q) use ($endpoint) {
                    return $q->where('endpoint', $endpoint);
                })
                ->groupBy('status_code')
                ->orderBy('status_code')
                ->get()
                ->toArray();

            $byHour = DB::table('usage_logs')
                ->selectRaw('HOUR(created_at) as hour')
                ->selectRaw('COUNT(*) as count')
                ->where('created_at', '>=', $startDate)
                ->when(!empty($endpoint), function ($q) use ($endpoint) {
                    return $q->where('endpoint', $endpoint);
                })
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->toArray();

            return [
                'total_requests' => $total,
                'period_start' => $startDate,
                'by_endpoint' => $byEndpoint,
                'by_status_code' => $byStatusCode,
                'by_hour' => $byHour,
            ];
        } catch (\Exception $e) {
            $this->log('error', 'Failed to get stats from database', ['error' => $e->getMessage()]);
            return ['error' => 'Failed to retrieve statistics'];
        }
    }

    /**
     * Get statistics from file
     * 
     * @param string $endpoint
     * @param string $startDate
     * @return array
     */
    private function getStatsFromFile(string $endpoint, string $startDate): array
    {
        $stats = [
            'total_requests' => 0,
            'period_start' => $startDate,
            'by_endpoint' => [],
            'by_status_code' => [],
            'response_times' => [],
        ];

        $files = glob($this->storagePath . '/usage_*.jsonl');
        $startTimestamp = strtotime($startDate);

        foreach ($files as $file) {
            // Check if file is within date range
            preg_match('/usage_(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $matches);
            if (isset($matches[1]) && strtotime($matches[1]) < $startTimestamp) {
                continue;
            }

            $handle = fopen($file, 'r');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $record = json_decode(trim($line), true);
                if ($record === null) {
                    continue;
                }

                if (!empty($endpoint) && $record['endpoint'] !== $endpoint) {
                    continue;
                }

                $stats['total_requests']++;
                
                $key = $record['endpoint'] . ':' . $record['method'];
                if (!isset($stats['by_endpoint'][$key])) {
                    $stats['by_endpoint'][$key] = [
                        'endpoint' => $record['endpoint'],
                        'method' => $record['method'],
                        'count' => 0,
                        'total_time' => 0,
                        'error_count' => 0,
                    ];
                }
                $stats['by_endpoint'][$key]['count']++;
                $stats['by_endpoint'][$key]['total_time'] += $record['response_time_ms'];
                if ($record['status_code'] >= 400) {
                    $stats['by_endpoint'][$key]['error_count']++;
                }

                $statusCode = (string) $record['status_code'];
                $stats['by_status_code'][$statusCode] = ($stats['by_status_code'][$statusCode] ?? 0) + 1;
            }

            fclose($handle);
        }

        // Calculate averages
        foreach ($stats['by_endpoint'] as &$ep) {
            $ep['avg_response_time'] = $ep['count'] > 0 ? $ep['total_time'] / $ep['count'] : 0;
            unset($ep['total_time']);
        }

        $stats['by_endpoint'] = array_values($stats['by_endpoint']);
        
        return $stats;
    }

    /**
     * Get statistics from memory
     * 
     * @param string $endpoint
     * @param string $startDate
     * @return array
     */
    private function getStatsFromMemory(string $endpoint, string $startDate): array
    {
        $filtered = array_filter(self::$memoryStore, function ($record) use ($endpoint, $startDate) {
            if (!empty($endpoint) && $record['endpoint'] !== $endpoint) {
                return false;
            }
            return $record['created_at'] >= $startDate;
        });

        return [
            'total_requests' => count($filtered),
            'period_start' => $startDate,
            'records' => array_values($filtered),
        ];
    }

    /**
     * Get start date for period
     * 
     * @param string $period
     * @return string
     */
    private function getStartDate(string $period): string
    {
        switch ($period) {
            case 'hour':
                return date('Y-m-d H:i:s', strtotime('-1 hour'));
            case 'day':
                return date('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'all':
                return '1970-01-01 00:00:00';
            default:
                return date('Y-m-d 00:00:00');
        }
    }

    /**
     * Get top endpoints by request count
     * 
     * @param int $limit
     * @param string $period
     * @return array
     */
    public function getTopEndpoints(int $limit = 10, string $period = 'day'): array
    {
        $stats = $this->getStats('', $period);
        
        if (!isset($stats['by_endpoint'])) {
            return [];
        }

        // Convert objects to arrays if needed
        $endpoints = array_map(function ($item) {
            return is_object($item) ? (array) $item : $item;
        }, $stats['by_endpoint']);

        usort($endpoints, function ($a, $b) {
            return ($b['count'] ?? 0) - ($a['count'] ?? 0);
        });

        return array_slice($endpoints, 0, $limit);
    }

    /**
     * Get slowest endpoints
     * 
     * @param int $limit
     * @param string $period
     * @return array
     */
    public function getSlowestEndpoints(int $limit = 10, string $period = 'day'): array
    {
        $stats = $this->getStats('', $period);
        
        if (!isset($stats['by_endpoint'])) {
            return [];
        }

        // Convert objects to arrays if needed
        $endpoints = array_map(function ($item) {
            return is_object($item) ? (array) $item : $item;
        }, $stats['by_endpoint']);

        usort($endpoints, function ($a, $b) {
            return ($b['avg_response_time'] ?? 0) <=> ($a['avg_response_time'] ?? 0);
        });

        return array_slice($endpoints, 0, $limit);
    }

    /**
     * Get error rate by endpoint
     * 
     * @param string $period
     * @return array
     */
    public function getErrorRates(string $period = 'day'): array
    {
        $stats = $this->getStats('', $period);
        
        if (!isset($stats['by_endpoint'])) {
            return [];
        }

        $errorRates = [];
        foreach ($stats['by_endpoint'] as $ep) {
            // Convert object to array if needed
            $ep = is_object($ep) ? (array) $ep : $ep;
            
            $count = (int) ($ep['count'] ?? 0);
            $errorCount = (int) ($ep['error_count'] ?? 0);
            $errorRate = $count > 0 ? ($errorCount / $count) * 100 : 0;
            
            $errorRates[] = [
                'endpoint' => $ep['endpoint'] ?? '',
                'method' => $ep['method'] ?? '',
                'total_requests' => $count,
                'error_count' => $errorCount,
                'error_rate' => round($errorRate, 2),
            ];
        }

        usort($errorRates, function ($a, $b) {
            return $b['error_rate'] <=> $a['error_rate'];
        });

        return $errorRates;
    }

    /**
     * Cleanup old logs
     * 
     * @param int $days Retention period in days
     * @return int Number of records deleted
     */
    public function cleanup(int $days = 90): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $deleted = 0;

        switch ($this->driver) {
            case 'database':
                try {
                    $deleted = DB::table('usage_logs')
                        ->where('created_at', '<', $cutoffDate)
                        ->delete();
                } catch (\Exception $e) {
                    $this->log('error', 'Failed to cleanup database logs', ['error' => $e->getMessage()]);
                }
                break;

            case 'file':
                $files = glob($this->storagePath . '/usage_*.jsonl');
                $cutoffTimestamp = strtotime("-{$days} days");
                
                foreach ($files as $file) {
                    preg_match('/usage_(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $matches);
                    if (isset($matches[1]) && strtotime($matches[1]) < $cutoffTimestamp) {
                        if (unlink($file)) {
                            $deleted++;
                        }
                    }
                }
                break;
        }

        return $deleted;
    }

    /**
     * Clear all memory storage (for testing)
     */
    public static function clearMemory(): void
    {
        self::$memoryStore = [];
    }

    /**
     * Get memory storage (for testing)
     * 
     * @return array
     */
    public static function getMemoryStore(): array
    {
        return self::$memoryStore;
    }

    /**
     * Log message
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level('[UsageTracker] ' . $message, $context);
        }
    }
}
