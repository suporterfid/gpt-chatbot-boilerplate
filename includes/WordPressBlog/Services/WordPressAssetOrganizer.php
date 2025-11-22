<?php
/**
 * WordPress Asset Organizer Service
 *
 * Organizes generated assets (markdown files, images, metadata) in Google Drive.
 * Creates folder structure, uploads files, generates manifest, and provides public URLs.
 *
 * Responsibilities:
 * - Create organized folder structure in Google Drive
 * - Upload markdown content files
 * - Upload generated images
 * - Upload metadata.json
 * - Generate asset manifest with URLs
 * - Make files publicly accessible
 * - Handle quota and upload errors
 * - Clean up local temporary files
 *
 * @package WordPressBlog\Services
 */

class WordPressAssetOrganizer {
    private $apiKey;
    private $baseUrl;
    private $rootFolderId;

    /**
     * Constructor
     *
     * @param string $apiKey Google Drive API key
     * @param string|null $rootFolderId Optional root folder ID (defaults to 'root')
     */
    public function __construct($apiKey, $rootFolderId = null) {
        $this->apiKey = $apiKey;
        $this->baseUrl = 'https://www.googleapis.com/drive/v3';
        $this->rootFolderId = $rootFolderId ?? 'root';
    }

    /**
     * Organize all assets for an article
     *
     * Creates folder structure, uploads all files, and generates manifest.
     *
     * @param array $assets Asset data including content, images, metadata
     * @param string $articleSlug Article slug for folder naming
     * @return array Organization result with URLs and manifest
     * @throws Exception If organization fails
     */
    public function organizeAssets(array $assets, $articleSlug) {
        // Validate assets structure
        $this->validateAssets($assets);

        // Create folder structure
        $folderStructure = $this->createFolderStructure($articleSlug);

        $result = [
            'folder_id' => $folderStructure['root_folder_id'],
            'folder_url' => $folderStructure['root_folder_url'],
            'uploaded_files' => [],
            'manifest' => [],
            'organized_at' => date('Y-m-d H:i:s')
        ];

        // Upload content files (markdown)
        if (isset($assets['content_files'])) {
            $contentFiles = $this->uploadContentFiles(
                $assets['content_files'],
                $folderStructure['content_folder_id']
            );
            $result['uploaded_files']['content'] = $contentFiles;
        }

        // Upload images
        if (isset($assets['images'])) {
            $imageFiles = $this->uploadImages(
                $assets['images'],
                $folderStructure['images_folder_id']
            );
            $result['uploaded_files']['images'] = $imageFiles;
        }

        // Upload metadata
        if (isset($assets['metadata'])) {
            $metadataFile = $this->uploadMetadata(
                $assets['metadata'],
                $folderStructure['root_folder_id']
            );
            $result['uploaded_files']['metadata'] = $metadataFile;
        }

        // Generate manifest
        $manifest = $this->generateManifest($result, $assets, $articleSlug);

        // Upload manifest
        $manifestFile = $this->uploadManifest(
            $manifest,
            $folderStructure['root_folder_id']
        );
        $result['uploaded_files']['manifest'] = $manifestFile;
        $result['manifest'] = $manifest;

        return $result;
    }

    /**
     * Create folder structure in Google Drive
     *
     * Structure:
     * - {article-slug}/
     *   - content/
     *   - images/
     *   - metadata.json
     *   - manifest.json
     *
     * @param string $articleSlug Article slug
     * @return array Folder IDs and URLs
     */
    private function createFolderStructure($articleSlug) {
        // Create root folder
        $rootFolder = $this->createFolder($articleSlug, $this->rootFolderId);

        // Create content subfolder
        $contentFolder = $this->createFolder('content', $rootFolder['id']);

        // Create images subfolder
        $imagesFolder = $this->createFolder('images', $rootFolder['id']);

        return [
            'root_folder_id' => $rootFolder['id'],
            'root_folder_url' => $rootFolder['url'],
            'content_folder_id' => $contentFolder['id'],
            'content_folder_url' => $contentFolder['url'],
            'images_folder_id' => $imagesFolder['id'],
            'images_folder_url' => $imagesFolder['url']
        ];
    }

    /**
     * Create a folder in Google Drive
     *
     * @param string $folderName Folder name
     * @param string $parentId Parent folder ID
     * @return array Folder data with ID and URL
     */
    private function createFolder($folderName, $parentId) {
        $metadata = [
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId]
        ];

        $response = $this->makeRequest(
            'POST',
            '/files',
            $metadata
        );

        if (!isset($response['id'])) {
            throw new Exception("Failed to create folder: {$folderName}");
        }

        // Make folder publicly viewable
        $this->makePublic($response['id']);

        return [
            'id' => $response['id'],
            'name' => $response['name'],
            'url' => 'https://drive.google.com/drive/folders/' . $response['id']
        ];
    }

    /**
     * Upload content files (markdown)
     *
     * @param array $contentFiles Content file data
     * @param string $folderId Folder ID
     * @return array Uploaded file data
     */
    private function uploadContentFiles(array $contentFiles, $folderId) {
        $uploaded = [];

        foreach ($contentFiles as $file) {
            $fileName = $file['name'] ?? 'content.md';
            $content = $file['content'] ?? '';

            if (empty($content)) {
                continue;
            }

            $fileData = $this->uploadFile(
                $fileName,
                $content,
                'text/markdown',
                $folderId
            );

            $uploaded[] = $fileData;
        }

        return $uploaded;
    }

    /**
     * Upload images
     *
     * @param array $images Image data with local paths
     * @param string $folderId Folder ID
     * @return array Uploaded file data
     */
    private function uploadImages(array $images, $folderId) {
        $uploaded = [];

        // Upload featured image
        if (isset($images['featured_image'])) {
            $featuredImage = $this->uploadImageFromPath(
                $images['featured_image'],
                'featured-image.png',
                $folderId
            );
            $uploaded['featured'] = $featuredImage;
        }

        // Upload chapter images
        if (isset($images['chapter_images']) && is_array($images['chapter_images'])) {
            $uploaded['chapters'] = [];
            foreach ($images['chapter_images'] as $index => $imageData) {
                $chapterImage = $this->uploadImageFromPath(
                    $imageData,
                    'chapter-' . ($index + 1) . '-image.png',
                    $folderId
                );
                $uploaded['chapters'][] = $chapterImage;
            }
        }

        return $uploaded;
    }

    /**
     * Upload image from local path
     *
     * @param array|string $imageData Image data (can be array with local_path or direct path)
     * @param string $fileName File name
     * @param string $folderId Folder ID
     * @return array Uploaded file data
     */
    private function uploadImageFromPath($imageData, $fileName, $folderId) {
        $localPath = is_array($imageData) ? ($imageData['local_path'] ?? null) : $imageData;

        if (!$localPath || !file_exists($localPath)) {
            throw new Exception("Image file not found: {$localPath}");
        }

        $imageContent = file_get_contents($localPath);
        $mimeType = mime_content_type($localPath);

        return $this->uploadFile($fileName, $imageContent, $mimeType, $folderId);
    }

    /**
     * Upload metadata file
     *
     * @param array $metadata Metadata array
     * @param string $folderId Folder ID
     * @return array Uploaded file data
     */
    private function uploadMetadata(array $metadata, $folderId) {
        $content = json_encode($metadata, JSON_PRETTY_PRINT);

        return $this->uploadFile(
            'metadata.json',
            $content,
            'application/json',
            $folderId
        );
    }

    /**
     * Upload manifest file
     *
     * @param array $manifest Manifest array
     * @param string $folderId Folder ID
     * @return array Uploaded file data
     */
    private function uploadManifest(array $manifest, $folderId) {
        $content = json_encode($manifest, JSON_PRETTY_PRINT);

        return $this->uploadFile(
            'manifest.json',
            $content,
            'application/json',
            $folderId
        );
    }

    /**
     * Upload a file to Google Drive
     *
     * @param string $fileName File name
     * @param string $content File content
     * @param string $mimeType MIME type
     * @param string $folderId Parent folder ID
     * @return array Uploaded file data
     */
    private function uploadFile($fileName, $content, $mimeType, $folderId) {
        // Create file metadata
        $metadata = [
            'name' => $fileName,
            'parents' => [$folderId],
            'mimeType' => $mimeType
        ];

        // Use multipart upload
        $boundary = uniqid();
        $delimiter = '--' . $boundary;
        $closeDelimiter = '--' . $boundary . '--';

        $multipartBody =
            $delimiter . "\r\n" .
            "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
            json_encode($metadata) . "\r\n" .
            $delimiter . "\r\n" .
            "Content-Type: {$mimeType}\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            base64_encode($content) . "\r\n" .
            $closeDelimiter;

        $response = $this->makeUploadRequest(
            '/upload/drive/v3/files',
            $multipartBody,
            "multipart/related; boundary={$boundary}"
        );

        if (!isset($response['id'])) {
            throw new Exception("Failed to upload file: {$fileName}");
        }

        // Make file publicly accessible
        $this->makePublic($response['id']);

        // Get public URL
        $publicUrl = $this->getPublicUrl($response['id']);

        return [
            'id' => $response['id'],
            'name' => $response['name'],
            'mime_type' => $response['mimeType'],
            'size' => $response['size'] ?? 0,
            'url' => $publicUrl,
            'created_time' => $response['createdTime'] ?? date('c')
        ];
    }

    /**
     * Make a file or folder public
     *
     * @param string $fileId File or folder ID
     * @return bool Success
     */
    private function makePublic($fileId) {
        $permission = [
            'role' => 'reader',
            'type' => 'anyone'
        ];

        try {
            $this->makeRequest(
                'POST',
                "/files/{$fileId}/permissions",
                $permission
            );
            return true;
        } catch (Exception $e) {
            // Non-fatal - continue even if permission setting fails
            error_log("Failed to make file public: {$fileId} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get public URL for a file
     *
     * @param string $fileId File ID
     * @return string Public URL
     */
    private function getPublicUrl($fileId) {
        return "https://drive.google.com/uc?id={$fileId}&export=download";
    }

    /**
     * Generate asset manifest
     *
     * @param array $uploadResult Upload result data
     * @param array $originalAssets Original asset data
     * @param string $articleSlug Article slug
     * @return array Manifest data
     */
    private function generateManifest(array $uploadResult, array $originalAssets, $articleSlug) {
        return [
            'version' => '1.0',
            'article_slug' => $articleSlug,
            'generated_at' => date('c'),
            'folder' => [
                'id' => $uploadResult['folder_id'],
                'url' => $uploadResult['folder_url']
            ],
            'files' => [
                'content' => $uploadResult['uploaded_files']['content'] ?? [],
                'images' => $uploadResult['uploaded_files']['images'] ?? [],
                'metadata' => $uploadResult['uploaded_files']['metadata'] ?? []
            ],
            'statistics' => [
                'total_files' => $this->countFiles($uploadResult['uploaded_files']),
                'content_files' => count($uploadResult['uploaded_files']['content'] ?? []),
                'image_files' => $this->countImageFiles($uploadResult['uploaded_files']['images'] ?? []),
                'total_size_bytes' => $this->calculateTotalSize($uploadResult['uploaded_files'])
            ],
            'metadata' => $originalAssets['metadata'] ?? []
        ];
    }

    /**
     * Count total files
     *
     * @param array $uploadedFiles Uploaded files data
     * @return int Total count
     */
    private function countFiles(array $uploadedFiles) {
        $count = 0;

        if (isset($uploadedFiles['content'])) {
            $count += count($uploadedFiles['content']);
        }

        if (isset($uploadedFiles['images'])) {
            $count += $this->countImageFiles($uploadedFiles['images']);
        }

        if (isset($uploadedFiles['metadata'])) {
            $count += 1;
        }

        if (isset($uploadedFiles['manifest'])) {
            $count += 1;
        }

        return $count;
    }

    /**
     * Count image files
     *
     * @param array $images Image data
     * @return int Count
     */
    private function countImageFiles(array $images) {
        $count = 0;

        if (isset($images['featured'])) {
            $count += 1;
        }

        if (isset($images['chapters'])) {
            $count += count($images['chapters']);
        }

        return $count;
    }

    /**
     * Calculate total size of uploaded files
     *
     * @param array $uploadedFiles Uploaded files data
     * @return int Total size in bytes
     */
    private function calculateTotalSize(array $uploadedFiles) {
        $total = 0;

        foreach ($uploadedFiles as $category => $files) {
            if ($category === 'images' && is_array($files)) {
                // Handle nested structure for images
                foreach ($files as $imageCategory => $imageFiles) {
                    if (is_array($imageFiles)) {
                        foreach ($imageFiles as $file) {
                            $total += intval($file['size'] ?? 0);
                        }
                    } else {
                        $total += intval($imageFiles['size'] ?? 0);
                    }
                }
            } elseif (is_array($files)) {
                foreach ($files as $file) {
                    $total += intval($file['size'] ?? 0);
                }
            } else {
                $total += intval($files['size'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * Make API request to Google Drive
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @return array Response data
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($data) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            if ($data) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("cURL error: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown error';
            throw new Exception("Google Drive API error (HTTP {$httpCode}): {$errorMessage}");
        }

        if (!$decoded) {
            throw new Exception("Failed to decode API response");
        }

        return $decoded;
    }

    /**
     * Make upload request (multipart)
     *
     * @param string $endpoint API endpoint
     * @param string $body Multipart body
     * @param string $contentType Content type with boundary
     * @return array Response data
     */
    private function makeUploadRequest($endpoint, $body, $contentType) {
        $url = 'https://www.googleapis.com' . $endpoint . '?uploadType=multipart';

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: ' . $contentType,
                'Content-Length: ' . strlen($body)
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 120 // Longer timeout for uploads
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Upload cURL error: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown error';

            // Check for quota exceeded
            if ($httpCode === 403 && strpos($errorMessage, 'quota') !== false) {
                throw new Exception("Google Drive quota exceeded: {$errorMessage}");
            }

            throw new Exception("Upload error (HTTP {$httpCode}): {$errorMessage}");
        }

        return $decoded;
    }

    /**
     * Validate assets structure
     *
     * @param array $assets Assets data
     * @throws Exception If validation fails
     */
    private function validateAssets(array $assets) {
        if (empty($assets)) {
            throw new Exception("Assets data cannot be empty");
        }

        // At least one of content_files or images should be present
        if (!isset($assets['content_files']) && !isset($assets['images'])) {
            throw new Exception("Assets must contain either content_files or images");
        }
    }

    /**
     * Clean up local temporary files
     *
     * @param array $assets Assets data with local paths
     * @return int Number of files deleted
     */
    public function cleanupLocalFiles(array $assets) {
        $deletedCount = 0;

        // Clean up image files
        if (isset($assets['images'])) {
            if (isset($assets['images']['featured_image']['local_path'])) {
                $path = $assets['images']['featured_image']['local_path'];
                if (file_exists($path)) {
                    unlink($path);
                    $deletedCount++;
                }
            }

            if (isset($assets['images']['chapter_images'])) {
                foreach ($assets['images']['chapter_images'] as $image) {
                    if (isset($image['local_path']) && file_exists($image['local_path'])) {
                        unlink($image['local_path']);
                        $deletedCount++;
                    }
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Get folder contents
     *
     * @param string $folderId Folder ID
     * @return array List of files in folder
     */
    public function getFolderContents($folderId) {
        $response = $this->makeRequest(
            'GET',
            "/files?q='{$folderId}'+in+parents"
        );

        return $response['files'] ?? [];
    }

    /**
     * Delete folder and all contents
     *
     * @param string $folderId Folder ID
     * @return bool Success
     */
    public function deleteFolder($folderId) {
        try {
            $this->makeRequest('DELETE', "/files/{$folderId}");
            return true;
        } catch (Exception $e) {
            error_log("Failed to delete folder: {$folderId} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get organization statistics
     *
     * @param array $organizationResult Result from organizeAssets()
     * @return array Statistics
     */
    public function getStatistics(array $organizationResult) {
        $manifest = $organizationResult['manifest'] ?? [];
        $stats = $manifest['statistics'] ?? [];

        return [
            'total_files' => $stats['total_files'] ?? 0,
            'content_files' => $stats['content_files'] ?? 0,
            'image_files' => $stats['image_files'] ?? 0,
            'total_size_bytes' => $stats['total_size_bytes'] ?? 0,
            'total_size_mb' => round(($stats['total_size_bytes'] ?? 0) / (1024 * 1024), 2),
            'folder_url' => $organizationResult['folder_url'] ?? null
        ];
    }
}
