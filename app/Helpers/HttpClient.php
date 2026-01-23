<?php
/**
 * Simple HTTP client for external API calls
 * 
 * cURL wrapper. No Guzzle dependency, no magic.
 * Direct, predictable, easy to debug.
 * 
 * Usage:
 *   $http = new HttpClient();
 *   $response = $http->get('https://api.example.com/data');
 *   $response = $http->post('https://api.example.com/data', ['key' => 'value']);
 * 
 * @package Monstein\Helpers
 */
namespace Monstein\Helpers;

class HttpClient
{
    /** @var array */
    private $defaultHeaders = [];

    /** @var int */
    private $timeout = 30;

    /** @var bool */
    private $verifySSL = true;

    /** @var string|null */
    private $userAgent = null;

    /** @var array Last response info */
    private $lastInfo = [];

    /** @var string|null Last error */
    private $lastError = null;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if (isset($options['timeout'])) {
            $this->timeout = (int) $options['timeout'];
        }
        if (isset($options['verify_ssl'])) {
            $this->verifySSL = (bool) $options['verify_ssl'];
        }
        if (isset($options['headers'])) {
            $this->defaultHeaders = (array) $options['headers'];
        }
        if (isset($options['user_agent'])) {
            $this->userAgent = $options['user_agent'];
        } else {
            $this->userAgent = 'Monstein-HttpClient/1.0';
        }
    }

    /**
     * GET request
     * 
     * @param string $url
     * @param array  $query
     * @param array  $headers
     * @return array
     */
    public function get($url, array $query = [], array $headers = [])
    {
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $this->request('GET', $url, null, $headers);
    }

    /**
     * POST request
     * 
     * @param string $url
     * @param mixed  $data
     * @param array  $headers
     * @return array
     */
    public function post($url, $data = null, array $headers = [])
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * PUT request
     * 
     * @param string $url
     * @param mixed  $data
     * @param array  $headers
     * @return array
     */
    public function put($url, $data = null, array $headers = [])
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    /**
     * PATCH request
     * 
     * @param string $url
     * @param mixed  $data
     * @param array  $headers
     * @return array
     */
    public function patch($url, $data = null, array $headers = [])
    {
        return $this->request('PATCH', $url, $data, $headers);
    }

    /**
     * DELETE request
     * 
     * @param string $url
     * @param array  $headers
     * @return array
     */
    public function delete($url, array $headers = [])
    {
        return $this->request('DELETE', $url, null, $headers);
    }

    /**
     * POST JSON data
     * 
     * @param string $url
     * @param array  $data
     * @param array  $headers
     * @return array
     */
    public function postJson($url, array $data, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';
        return $this->request('POST', $url, json_encode($data), $headers);
    }

    /**
     * Execute HTTP request
     * 
     * @param string      $method
     * @param string      $url
     * @param mixed|null  $data
     * @param array       $headers
     * @return array
     */
    public function request($method, $url, $data = null, array $headers = [])
    {
        $this->lastError = null;
        $this->lastInfo = [];

        $ch = curl_init();

        // Base options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HEADER => true,
        ]);

        // Method
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
                break;
        }

        // Data
        if ($data !== null) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        // Headers
        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $curlHeaders = [];
        foreach ($allHeaders as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }
        if (!empty($curlHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        // Execute
        $response = curl_exec($ch);
        $this->lastInfo = curl_getinfo($ch);

        if ($response === false) {
            $this->lastError = curl_error($ch);
            curl_close($ch);

            return [
                'success' => false,
                'status' => 0,
                'headers' => [],
                'body' => null,
                'error' => $this->lastError,
            ];
        }

        curl_close($ch);

        // Parse response
        $headerSize = $this->lastInfo['header_size'];
        $headerStr = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return [
            'success' => true,
            'status' => $this->lastInfo['http_code'],
            'headers' => $this->parseHeaders($headerStr),
            'body' => $body,
            'json' => $this->parseJson($body),
        ];
    }

    /**
     * Download file
     * 
     * @param string $url
     * @param string $destination
     * @return bool
     */
    public function download($url, $destination)
    {
        $ch = curl_init($url);

        $fp = fopen($destination, 'w');
        if ($fp === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $result !== false;
    }

    /**
     * Set default header
     * 
     * @param string $name
     * @param string $value
     * @return self
     */
    public function setHeader($name, $value)
    {
        $this->defaultHeaders[$name] = $value;
        return $this;
    }

    /**
     * Set bearer token
     * 
     * @param string $token
     * @return self
     */
    public function setBearerToken($token)
    {
        $this->defaultHeaders['Authorization'] = 'Bearer ' . $token;
        return $this;
    }

    /**
     * Set timeout
     * 
     * @param int $seconds
     * @return self
     */
    public function setTimeout($seconds)
    {
        $this->timeout = (int) $seconds;
        return $this;
    }

    /**
     * Get last response info
     * 
     * @return array
     */
    public function getLastInfo()
    {
        return $this->lastInfo;
    }

    /**
     * Get last error
     * 
     * @return string|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Parse response headers
     * 
     * @param string $headerStr
     * @return array
     */
    private function parseHeaders($headerStr)
    {
        $headers = [];
        $lines = explode("\r\n", $headerStr);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Attempt to parse JSON body
     * 
     * @param string $body
     * @return array|null
     */
    private function parseJson($body)
    {
        $data = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }
}
