<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WordPressAssetOrganizer
 *
 * Tests Google Drive integration for asset organization including
 * folder creation, file uploads, and manifest generation.
 *
 * Note: These tests use mock responses since we can't call the actual Google Drive API.
 */
class AssetOrganizerTest extends TestCase {
    private $assetOrganizer;
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/test_assets_' . time();
        mkdir($this->tempDir, 0755, true);

        // Create organizer with test API key
        $this->assetOrganizer = new WordPressAssetOrganizer('test-api-key', 'root');
    }

    protected function tearDown(): void {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveDeleteDirectory($this->tempDir);
        }

        $this->assetOrganizer = null;
    }

    /**
     * Helper: Recursively delete directory
     */
    private function recursiveDeleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDeleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Helper: Create test image file
     */
    private function createTestImage($filename, $width = 1024, $height = 1024) {
        $path = $this->tempDir . '/' . $filename;
        $image = imagecreate($width, $height);
        imagecolorallocate($image, 255, 255, 255);
        imagepng($image, $path);
        imagedestroy($image);
        return $path;
    }

    /**
     * Test: Validate assets - valid structure
     */
    public function testValidateAssetsValidStructure(): void {
        $assets = [
            'content_files' => [
                ['name' => 'article.md', 'content' => '# Article']
            ],
            'images' => [
                'featured_image' => ['local_path' => '/path/to/image.png']
            ]
        ];

        // This should not throw an exception
        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('validateAssets');
        $method->setAccessible(true);

        $this->expectNotToPerformAssertions();
        $method->invoke($this->assetOrganizer, $assets);
    }

    /**
     * Test: Validate assets - empty assets
     */
    public function testValidateAssetsEmpty(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Assets data cannot be empty');

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('validateAssets');
        $method->setAccessible(true);

        $method->invoke($this->assetOrganizer, []);
    }

    /**
     * Test: Validate assets - missing both content and images
     */
    public function testValidateAssetsMissingContentAndImages(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must contain either content_files or images');

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('validateAssets');
        $method->setAccessible(true);

        $method->invoke($this->assetOrganizer, ['metadata' => []]);
    }

    /**
     * Test: Count files - all file types
     */
    public function testCountFilesAllTypes(): void {
        $uploadedFiles = [
            'content' => [
                ['name' => 'file1.md'],
                ['name' => 'file2.md']
            ],
            'images' => [
                'featured' => ['name' => 'featured.png'],
                'chapters' => [
                    ['name' => 'ch1.png'],
                    ['name' => 'ch2.png']
                ]
            ],
            'metadata' => ['name' => 'metadata.json'],
            'manifest' => ['name' => 'manifest.json']
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('countFiles');
        $method->setAccessible(true);

        $count = $method->invoke($this->assetOrganizer, $uploadedFiles);

        // 2 content + 3 images + 1 metadata + 1 manifest = 7
        $this->assertEquals(7, $count);
    }

    /**
     * Test: Count files - only content
     */
    public function testCountFilesOnlyContent(): void {
        $uploadedFiles = [
            'content' => [
                ['name' => 'file1.md'],
                ['name' => 'file2.md'],
                ['name' => 'file3.md']
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('countFiles');
        $method->setAccessible(true);

        $count = $method->invoke($this->assetOrganizer, $uploadedFiles);

        $this->assertEquals(3, $count);
    }

    /**
     * Test: Count image files
     */
    public function testCountImageFiles(): void {
        $images = [
            'featured' => ['name' => 'featured.png'],
            'chapters' => [
                ['name' => 'ch1.png'],
                ['name' => 'ch2.png'],
                ['name' => 'ch3.png']
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('countImageFiles');
        $method->setAccessible(true);

        $count = $method->invoke($this->assetOrganizer, $images);

        // 1 featured + 3 chapters = 4
        $this->assertEquals(4, $count);
    }

    /**
     * Test: Count image files - only featured
     */
    public function testCountImageFilesOnlyFeatured(): void {
        $images = [
            'featured' => ['name' => 'featured.png']
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('countImageFiles');
        $method->setAccessible(true);

        $count = $method->invoke($this->assetOrganizer, $images);

        $this->assertEquals(1, $count);
    }

    /**
     * Test: Calculate total size
     */
    public function testCalculateTotalSize(): void {
        $uploadedFiles = [
            'content' => [
                ['size' => 1024],
                ['size' => 2048]
            ],
            'images' => [
                'featured' => ['size' => 5120],
                'chapters' => [
                    ['size' => 3072],
                    ['size' => 4096]
                ]
            ],
            'metadata' => ['size' => 512]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('calculateTotalSize');
        $method->setAccessible(true);

        $totalSize = $method->invoke($this->assetOrganizer, $uploadedFiles);

        // 1024 + 2048 + 5120 + 3072 + 4096 + 512 = 15872
        $this->assertEquals(15872, $totalSize);
    }

    /**
     * Test: Calculate total size - empty
     */
    public function testCalculateTotalSizeEmpty(): void {
        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('calculateTotalSize');
        $method->setAccessible(true);

        $totalSize = $method->invoke($this->assetOrganizer, []);

        $this->assertEquals(0, $totalSize);
    }

    /**
     * Test: Get public URL format
     */
    public function testGetPublicUrl(): void {
        $fileId = 'abc123xyz';

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('getPublicUrl');
        $method->setAccessible(true);

        $url = $method->invoke($this->assetOrganizer, $fileId);

        $this->assertEquals('https://drive.google.com/uc?id=abc123xyz&export=download', $url);
    }

    /**
     * Test: Generate manifest structure
     */
    public function testGenerateManifest(): void {
        $uploadResult = [
            'folder_id' => 'folder123',
            'folder_url' => 'https://drive.google.com/drive/folders/folder123',
            'uploaded_files' => [
                'content' => [
                    ['name' => 'article.md', 'size' => 1024]
                ],
                'images' => [
                    'featured' => ['name' => 'featured.png', 'size' => 5120],
                    'chapters' => [
                        ['name' => 'ch1.png', 'size' => 3072]
                    ]
                ],
                'metadata' => ['name' => 'metadata.json', 'size' => 512]
            ]
        ];

        $originalAssets = [
            'metadata' => [
                'title' => 'Test Article',
                'author' => 'Test Author'
            ]
        ];

        $articleSlug = 'test-article';

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('generateManifest');
        $method->setAccessible(true);

        $manifest = $method->invoke($this->assetOrganizer, $uploadResult, $originalAssets, $articleSlug);

        // Validate manifest structure
        $this->assertEquals('1.0', $manifest['version']);
        $this->assertEquals('test-article', $manifest['article_slug']);
        $this->assertArrayHasKey('generated_at', $manifest);
        $this->assertArrayHasKey('folder', $manifest);
        $this->assertEquals('folder123', $manifest['folder']['id']);
        $this->assertArrayHasKey('files', $manifest);
        $this->assertArrayHasKey('statistics', $manifest);
        $this->assertArrayHasKey('metadata', $manifest);

        // Validate statistics
        $stats = $manifest['statistics'];
        $this->assertEquals(4, $stats['total_files']); // 1 content + 2 images + 1 metadata
        $this->assertEquals(1, $stats['content_files']);
        $this->assertEquals(2, $stats['image_files']);
        $this->assertEquals(9728, $stats['total_size_bytes']); // 1024 + 5120 + 3072 + 512
    }

    /**
     * Test: Cleanup local files
     */
    public function testCleanupLocalFiles(): void {
        $featuredPath = $this->createTestImage('featured.png', 1792, 1024);
        $chapter1Path = $this->createTestImage('chapter1.png', 1024, 1024);

        $assets = [
            'images' => [
                'featured_image' => [
                    'local_path' => $featuredPath
                ],
                'chapter_images' => [
                    ['local_path' => $chapter1Path]
                ]
            ]
        ];

        $this->assertFileExists($featuredPath);
        $this->assertFileExists($chapter1Path);

        $deletedCount = $this->assetOrganizer->cleanupLocalFiles($assets);

        $this->assertEquals(2, $deletedCount);
        $this->assertFileDoesNotExist($featuredPath);
        $this->assertFileDoesNotExist($chapter1Path);
    }

    /**
     * Test: Cleanup local files - handles missing files
     */
    public function testCleanupLocalFilesHandlesMissingFiles(): void {
        $assets = [
            'images' => [
                'featured_image' => [
                    'local_path' => '/non/existent/file.png'
                ]
            ]
        ];

        $deletedCount = $this->assetOrganizer->cleanupLocalFiles($assets);

        $this->assertEquals(0, $deletedCount);
    }

    /**
     * Test: Cleanup local files - empty assets
     */
    public function testCleanupLocalFilesEmptyAssets(): void {
        $deletedCount = $this->assetOrganizer->cleanupLocalFiles([]);

        $this->assertEquals(0, $deletedCount);
    }

    /**
     * Test: Get statistics from organization result
     */
    public function testGetStatistics(): void {
        $organizationResult = [
            'folder_url' => 'https://drive.google.com/drive/folders/abc123',
            'manifest' => [
                'statistics' => [
                    'total_files' => 10,
                    'content_files' => 3,
                    'image_files' => 5,
                    'total_size_bytes' => 2097152 // 2 MB
                ]
            ]
        ];

        $stats = $this->assetOrganizer->getStatistics($organizationResult);

        $this->assertEquals(10, $stats['total_files']);
        $this->assertEquals(3, $stats['content_files']);
        $this->assertEquals(5, $stats['image_files']);
        $this->assertEquals(2097152, $stats['total_size_bytes']);
        $this->assertEquals(2.0, $stats['total_size_mb']);
        $this->assertEquals('https://drive.google.com/drive/folders/abc123', $stats['folder_url']);
    }

    /**
     * Test: Get statistics - empty result
     */
    public function testGetStatisticsEmpty(): void {
        $stats = $this->assetOrganizer->getStatistics([]);

        $this->assertEquals(0, $stats['total_files']);
        $this->assertEquals(0, $stats['content_files']);
        $this->assertEquals(0, $stats['image_files']);
        $this->assertEquals(0, $stats['total_size_bytes']);
        $this->assertEquals(0, $stats['total_size_mb']);
        $this->assertNull($stats['folder_url']);
    }

    /**
     * Test: Manifest version is correct
     */
    public function testManifestVersionIsCorrect(): void {
        $uploadResult = [
            'folder_id' => 'test',
            'folder_url' => 'test',
            'uploaded_files' => [
                'content' => []
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('generateManifest');
        $method->setAccessible(true);

        $manifest = $method->invoke($this->assetOrganizer, $uploadResult, [], 'test');

        $this->assertEquals('1.0', $manifest['version']);
    }

    /**
     * Test: Manifest includes all required fields
     */
    public function testManifestIncludesAllRequiredFields(): void {
        $uploadResult = [
            'folder_id' => 'test',
            'folder_url' => 'test',
            'uploaded_files' => [
                'content' => []
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('generateManifest');
        $method->setAccessible(true);

        $manifest = $method->invoke($this->assetOrganizer, $uploadResult, [], 'test-slug');

        $requiredFields = ['version', 'article_slug', 'generated_at', 'folder', 'files', 'statistics', 'metadata'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $manifest, "Manifest missing required field: {$field}");
        }
    }

    /**
     * Test: Manifest folder structure
     */
    public function testManifestFolderStructure(): void {
        $uploadResult = [
            'folder_id' => 'folder123',
            'folder_url' => 'https://drive.google.com/drive/folders/folder123',
            'uploaded_files' => ['content' => []]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('generateManifest');
        $method->setAccessible(true);

        $manifest = $method->invoke($this->assetOrganizer, $uploadResult, [], 'test');

        $this->assertArrayHasKey('id', $manifest['folder']);
        $this->assertArrayHasKey('url', $manifest['folder']);
        $this->assertEquals('folder123', $manifest['folder']['id']);
        $this->assertEquals('https://drive.google.com/drive/folders/folder123', $manifest['folder']['url']);
    }

    /**
     * Test: Manifest files structure
     */
    public function testManifestFilesStructure(): void {
        $uploadResult = [
            'folder_id' => 'test',
            'folder_url' => 'test',
            'uploaded_files' => [
                'content' => [['name' => 'test.md']],
                'images' => ['featured' => ['name' => 'featured.png']],
                'metadata' => ['name' => 'metadata.json']
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('generateManifest');
        $method->setAccessible(true);

        $manifest = $method->invoke($this->assetOrganizer, $uploadResult, [], 'test');

        $this->assertArrayHasKey('content', $manifest['files']);
        $this->assertArrayHasKey('images', $manifest['files']);
        $this->assertArrayHasKey('metadata', $manifest['files']);
    }

    /**
     * Test: Statistics calculation accuracy
     */
    public function testStatisticsCalculationAccuracy(): void {
        $uploadedFiles = [
            'content' => [
                ['size' => 1000],
                ['size' => 2000]
            ],
            'images' => [
                'featured' => ['size' => 5000],
                'chapters' => [
                    ['size' => 3000],
                    ['size' => 4000]
                ]
            ],
            'metadata' => ['size' => 1000]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $countMethod = $reflection->getMethod('countFiles');
        $countMethod->setAccessible(true);
        $sizeMethod = $reflection->getMethod('calculateTotalSize');
        $sizeMethod->setAccessible(true);

        $totalFiles = $countMethod->invoke($this->assetOrganizer, $uploadedFiles);
        $totalSize = $sizeMethod->invoke($this->assetOrganizer, $uploadedFiles);

        // 2 content + 3 images + 1 metadata = 6 files
        $this->assertEquals(6, $totalFiles);

        // 1000 + 2000 + 5000 + 3000 + 4000 + 1000 = 16000 bytes
        $this->assertEquals(16000, $totalSize);
    }

    /**
     * Test: Asset validation with only content files
     */
    public function testValidateAssetsOnlyContent(): void {
        $assets = [
            'content_files' => [
                ['name' => 'article.md', 'content' => '# Article']
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('validateAssets');
        $method->setAccessible(true);

        $this->expectNotToPerformAssertions();
        $method->invoke($this->assetOrganizer, $assets);
    }

    /**
     * Test: Asset validation with only images
     */
    public function testValidateAssetsOnlyImages(): void {
        $assets = [
            'images' => [
                'featured_image' => ['local_path' => '/path/to/image.png']
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('validateAssets');
        $method->setAccessible(true);

        $this->expectNotToPerformAssertions();
        $method->invoke($this->assetOrganizer, $assets);
    }

    /**
     * Test: Multiple cleanup operations don't interfere
     */
    public function testMultipleCleanupOperations(): void {
        $path1 = $this->createTestImage('image1.png');
        $path2 = $this->createTestImage('image2.png');

        $assets1 = [
            'images' => [
                'featured_image' => ['local_path' => $path1]
            ]
        ];

        $assets2 = [
            'images' => [
                'featured_image' => ['local_path' => $path2]
            ]
        ];

        $count1 = $this->assetOrganizer->cleanupLocalFiles($assets1);
        $count2 = $this->assetOrganizer->cleanupLocalFiles($assets2);

        $this->assertEquals(1, $count1);
        $this->assertEquals(1, $count2);
        $this->assertFileDoesNotExist($path1);
        $this->assertFileDoesNotExist($path2);
    }

    /**
     * Test: Size calculation handles missing size fields
     */
    public function testSizeCalculationHandlesMissingSizeFields(): void {
        $uploadedFiles = [
            'content' => [
                ['name' => 'file1.md'], // No size
                ['name' => 'file2.md', 'size' => 1024]
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('calculateTotalSize');
        $method->setAccessible(true);

        $totalSize = $method->invoke($this->assetOrganizer, $uploadedFiles);

        $this->assertEquals(1024, $totalSize);
    }

    /**
     * Test: Metadata preserved in manifest
     */
    public function testMetadataPreservedInManifest(): void {
        $uploadResult = [
            'folder_id' => 'test',
            'folder_url' => 'test',
            'uploaded_files' => ['content' => []]
        ];

        $originalAssets = [
            'metadata' => [
                'title' => 'Test Article',
                'author' => 'John Doe',
                'created_at' => '2024-01-01',
                'custom_field' => 'custom_value'
            ]
        ];

        $reflection = new ReflectionClass($this->assetOrganizer);
        $method = $reflection->getMethod('generateManifest');
        $method->setAccessible(true);

        $manifest = $method->invoke($this->assetOrganizer, $uploadResult, $originalAssets, 'test');

        $this->assertEquals($originalAssets['metadata'], $manifest['metadata']);
    }
}
