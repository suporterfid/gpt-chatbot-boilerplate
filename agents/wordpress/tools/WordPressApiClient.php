<?php
/**
 * WordPress REST API Client
 *
 * Handles communication with WordPress REST API v2.
 * Supports posts, pages, categories, tags, and media.
 *
 * @package ChatbotBoilerplate\Agents\WordPress
 * @version 1.0.0
 */

class WordPressApiClient
{
    /**
     * WordPress site URL
     * @var string
     */
    private $siteUrl;

    /**
     * API base URL
     * @var string
     */
    private $apiBase;

    /**
     * Username for authentication
     * @var string
     */
    private $username;

    /**
     * Application password
     * @var string
     */
    private $appPassword;

    /**
     * Request timeout in milliseconds
     * @var int
     */
    private $timeout;

    /**
     * Constructor
     *
     * @param string $siteUrl WordPress site URL
     * @param string $username WordPress username
     * @param string $appPassword WordPress application password
     * @param int $timeout Request timeout in milliseconds
     */
    public function __construct(string $siteUrl, string $username, string $appPassword, int $timeout = 30000)
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->apiBase = $this->siteUrl . '/wp-json/wp/v2';
        $this->username = $username;
        $this->appPassword = $appPassword;
        $this->timeout = $timeout;
    }

    // ==================== POSTS ====================

    /**
     * Create a new post
     *
     * @param array $data Post data
     * @return array Created post data
     * @throws Exception if request fails
     */
    public function createPost(array $data): array
    {
        $payload = [
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => $data['status'] ?? 'draft',
            'author' => $data['author'] ?? 1
        ];

        if (isset($data['excerpt'])) {
            $payload['excerpt'] = $data['excerpt'];
        }

        if (isset($data['categories'])) {
            $payload['categories'] = $data['categories'];
        }

        if (isset($data['tags'])) {
            $payload['tags'] = $data['tags'];
        }

        if (isset($data['featured_media'])) {
            $payload['featured_media'] = $data['featured_media'];
        }

        return $this->request('POST', '/posts', $payload);
    }

    /**
     * Update an existing post
     *
     * @param int $postId Post ID
     * @param array $data Update data
     * @return array Updated post data
     * @throws Exception if request fails
     */
    public function updatePost(int $postId, array $data): array
    {
        $payload = [];

        if (isset($data['title'])) {
            $payload['title'] = $data['title'];
        }

        if (isset($data['content'])) {
            $payload['content'] = $data['content'];
        }

        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        if (isset($data['excerpt'])) {
            $payload['excerpt'] = $data['excerpt'];
        }

        if (isset($data['categories'])) {
            $payload['categories'] = $data['categories'];
        }

        if (isset($data['tags'])) {
            $payload['tags'] = $data['tags'];
        }

        return $this->request('POST', "/posts/{$postId}", $payload);
    }

    /**
     * Get a post by ID
     *
     * @param int $postId Post ID
     * @return array Post data
     * @throws Exception if request fails
     */
    public function getPost(int $postId): array
    {
        return $this->request('GET', "/posts/{$postId}");
    }

    /**
     * Search posts
     *
     * @param array $params Search parameters
     * @return array Array of posts
     * @throws Exception if request fails
     */
    public function searchPosts(array $params = []): array
    {
        $queryParams = [];

        if (isset($params['search'])) {
            $queryParams['search'] = $params['search'];
        }

        if (isset($params['per_page'])) {
            $queryParams['per_page'] = min((int)$params['per_page'], 100);
        }

        if (isset($params['page'])) {
            $queryParams['page'] = (int)$params['page'];
        }

        if (isset($params['status'])) {
            $queryParams['status'] = $params['status'];
        }

        if (isset($params['author'])) {
            $queryParams['author'] = $params['author'];
        }

        if (isset($params['categories'])) {
            $queryParams['categories'] = $params['categories'];
        }

        if (isset($params['tags'])) {
            $queryParams['tags'] = $params['tags'];
        }

        if (isset($params['order'])) {
            $queryParams['order'] = $params['order'];
        }

        if (isset($params['orderby'])) {
            $queryParams['orderby'] = $params['orderby'];
        }

        $query = http_build_query($queryParams);
        $endpoint = '/posts' . ($query ? '?' . $query : '');

        return $this->request('GET', $endpoint);
    }

    /**
     * Delete a post
     *
     * @param int $postId Post ID
     * @param bool $force Force delete (true) or move to trash (false)
     * @return array Deleted post data
     * @throws Exception if request fails
     */
    public function deletePost(int $postId, bool $force = false): array
    {
        $query = $force ? '?force=true' : '';
        return $this->request('DELETE', "/posts/{$postId}{$query}");
    }

    // ==================== CATEGORIES ====================

    /**
     * Get all categories
     *
     * @param array $params Optional parameters (per_page, page, etc.)
     * @return array Array of categories
     * @throws Exception if request fails
     */
    public function getCategories(array $params = []): array
    {
        $queryParams = array_merge(['per_page' => 100], $params);
        $query = http_build_query($queryParams);
        $endpoint = '/categories' . ($query ? '?' . $query : '');

        return $this->request('GET', $endpoint);
    }

    /**
     * Create a category
     *
     * @param array $data Category data (name, slug, description, parent)
     * @return array Created category data
     * @throws Exception if request fails
     */
    public function createCategory(array $data): array
    {
        return $this->request('POST', '/categories', $data);
    }

    // ==================== TAGS ====================

    /**
     * Get all tags
     *
     * @param array $params Optional parameters
     * @return array Array of tags
     * @throws Exception if request fails
     */
    public function getTags(array $params = []): array
    {
        $queryParams = array_merge(['per_page' => 100], $params);
        $query = http_build_query($queryParams);
        $endpoint = '/tags' . ($query ? '?' . $query : '');

        return $this->request('GET', $endpoint);
    }

    /**
     * Create a tag
     *
     * @param array $data Tag data (name, slug, description)
     * @return array Created tag data
     * @throws Exception if request fails
     */
    public function createTag(array $data): array
    {
        return $this->request('POST', '/tags', $data);
    }

    // ==================== MEDIA ====================

    /**
     * Upload media file
     *
     * @param string $filePath Path to file
     * @param array $data Optional media metadata (title, caption, description)
     * @return array Uploaded media data
     * @throws Exception if request fails
     */
    public function uploadMedia(string $filePath, array $data = []): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        $mimeType = mime_content_type($filePath);
        $fileName = basename($filePath);

        $headers = [
            'Content-Disposition: attachment; filename="' . $fileName . '"',
            'Content-Type: ' . $mimeType
        ];

        $fileContent = file_get_contents($filePath);

        return $this->request('POST', '/media', $fileContent, $headers);
    }

    // ==================== HTTP REQUEST ====================

    /**
     * Make HTTP request to WordPress API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param mixed $data Request payload (array for JSON, string for raw)
     * @param array $additionalHeaders Additional headers
     * @return array Response data
     * @throws Exception if request fails
     */
    private function request(string $method, string $endpoint, $data = null, array $additionalHeaders = []): array
    {
        $url = $this->apiBase . $endpoint;

        $ch = curl_init();

        // Set URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Set authentication
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->appPassword);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        // Set headers
        $headers = array_merge([
            'Accept: application/json'
        ], $additionalHeaders);

        // Set data
        if ($data !== null) {
            if (is_array($data)) {
                $jsonData = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                $headers[] = 'Content-Type: application/json';
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // Handle errors
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }

        // Decode response
        $responseData = json_decode($response, true);

        if ($responseData === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
        }

        // Check HTTP status
        if ($httpCode >= 400) {
            $errorMessage = $responseData['message'] ?? 'Unknown error';
            $errorCode = $responseData['code'] ?? 'unknown_error';
            throw new Exception("WordPress API error ({$httpCode}): {$errorMessage} (Code: {$errorCode})");
        }

        return $responseData;
    }

    /**
     * Test API connection
     *
     * @return bool True if connection successful
     */
    public function testConnection(): bool
    {
        try {
            $this->request('GET', '/posts?per_page=1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get API information
     *
     * @return array API info
     */
    public function getApiInfo(): array
    {
        try {
            return $this->request('GET', '/');
        } catch (Exception $e) {
            throw new Exception("Failed to get API info: " . $e->getMessage());
        }
    }
}
