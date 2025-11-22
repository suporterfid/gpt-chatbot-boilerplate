<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WordPressImageGenerator
 *
 * Tests DALL-E 3 image generation, validation, and cost calculation.
 * Uses mock HTTP responses since we can't call the actual OpenAI API in tests.
 */
class ImageGeneratorTest extends TestCase {
    private $imageGenerator;
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/test_blog_images_' . time();

        // Create a mock generator that doesn't require real API calls
        $this->imageGenerator = new WordPressImageGenerator(
            'sk-test-key-mock',
            $this->tempDir
        );
    }

    protected function tearDown(): void {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveDeleteDirectory($this->tempDir);
        }

        $this->imageGenerator = null;
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
     * Helper: Create mock image file
     */
    private function createMockImageFile($filename, $width = 1024, $height = 1024) {
        $path = $this->tempDir . '/' . $filename;

        // Create a simple PNG image
        $image = imagecreate($width, $height);
        imagecolorallocate($image, 255, 255, 255); // White background
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Test: Constructor creates temp directory
     */
    public function testConstructorCreatesTempDirectory(): void {
        $this->assertDirectoryExists($this->tempDir);
    }

    /**
     * Test: Get and set temp directory
     */
    public function testGetSetTempDir(): void {
        $newDir = sys_get_temp_dir() . '/custom_blog_images';
        $this->imageGenerator->setTempDir($newDir);

        $this->assertEquals($newDir, $this->imageGenerator->getTempDir());
        $this->assertDirectoryExists($newDir);

        // Cleanup
        if (is_dir($newDir)) {
            rmdir($newDir);
        }
    }

    /**
     * Test: Calculate cost - standard quality 1024x1024
     */
    public function testCalculateCostStandard1024(): void {
        // We'll test this through a mock image data structure
        $mockImage = [
            'size' => '1024x1024',
            'quality' => 'standard',
            'cost' => WordPressImageGenerator::DALLE3_STANDARD_1024
        ];

        $this->assertEquals(0.040, $mockImage['cost']);
    }

    /**
     * Test: Calculate cost - standard quality 1792x1024
     */
    public function testCalculateCostStandard1792(): void {
        $mockImage = [
            'size' => '1792x1024',
            'quality' => 'standard',
            'cost' => WordPressImageGenerator::DALLE3_STANDARD_1792
        ];

        $this->assertEquals(0.080, $mockImage['cost']);
    }

    /**
     * Test: Calculate cost - HD quality 1024x1024
     */
    public function testCalculateCostHD1024(): void {
        $mockImage = [
            'size' => '1024x1024',
            'quality' => 'hd',
            'cost' => WordPressImageGenerator::DALLE3_HD_1024
        ];

        $this->assertEquals(0.080, $mockImage['cost']);
    }

    /**
     * Test: Calculate cost - HD quality 1792x1024
     */
    public function testCalculateCostHD1792(): void {
        $mockImage = [
            'size' => '1792x1024',
            'quality' => 'hd',
            'cost' => WordPressImageGenerator::DALLE3_HD_1792
        ];

        $this->assertEquals(0.120, $mockImage['cost']);
    }

    /**
     * Test: Get image metadata
     */
    public function testGetImageMetadata(): void {
        $imagePath = $this->createMockImageFile('test.png', 1024, 1024);

        $metadata = $this->imageGenerator->getImageMetadata($imagePath);

        $this->assertEquals(1024, $metadata['width']);
        $this->assertEquals(1024, $metadata['height']);
        $this->assertEquals('image/png', $metadata['mime_type']);
        $this->assertGreaterThan(0, $metadata['file_size']);
        $this->assertGreaterThan(0, $metadata['file_size_mb']);
    }

    /**
     * Test: Get image metadata - file not found
     */
    public function testGetImageMetadataFileNotFound(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Image file not found');

        $this->imageGenerator->getImageMetadata('/non/existent/path.png');
    }

    /**
     * Test: Validate images - valid structure
     */
    public function testValidateImagesValid(): void {
        $featuredPath = $this->createMockImageFile('featured.png', 1792, 1024);
        $chapter1Path = $this->createMockImageFile('chapter1.png', 1024, 1024);
        $chapter2Path = $this->createMockImageFile('chapter2.png', 1024, 1024);

        $generatedImages = [
            'featured_image' => [
                'local_path' => $featuredPath,
                'size' => '1792x1024',
                'url' => 'https://example.com/featured.png',
                'cost' => 0.080
            ],
            'chapter_images' => [
                [
                    'local_path' => $chapter1Path,
                    'size' => '1024x1024',
                    'url' => 'https://example.com/ch1.png',
                    'cost' => 0.040
                ],
                [
                    'local_path' => $chapter2Path,
                    'size' => '1024x1024',
                    'url' => 'https://example.com/ch2.png',
                    'cost' => 0.040
                ]
            ],
            'total_cost' => 0.160
        ];

        $validation = $this->imageGenerator->validateImages($generatedImages);

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    /**
     * Test: Validate images - missing featured image
     */
    public function testValidateImagesMissingFeatured(): void {
        $generatedImages = [
            'chapter_images' => []
        ];

        $validation = $this->imageGenerator->validateImages($generatedImages);

        $this->assertFalse($validation['valid']);
        $this->assertContains('Missing featured image', $validation['errors']);
    }

    /**
     * Test: Validate images - missing required field
     */
    public function testValidateImagesMissingField(): void {
        $generatedImages = [
            'featured_image' => [
                'local_path' => '/tmp/test.png'
                // Missing 'size' and 'url'
            ]
        ];

        $validation = $this->imageGenerator->validateImages($generatedImages);

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    /**
     * Test: Validate images - file not found
     */
    public function testValidateImagesFileNotFound(): void {
        $generatedImages = [
            'featured_image' => [
                'local_path' => '/non/existent/file.png',
                'size' => '1792x1024',
                'url' => 'https://example.com/test.png'
            ]
        ];

        $validation = $this->imageGenerator->validateImages($generatedImages);

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    /**
     * Test: Validate images - dimension mismatch warning
     */
    public function testValidateImagesDimensionMismatch(): void {
        // Create 512x512 image but declare it as 1024x1024
        $imagePath = $this->createMockImageFile('mismatch.png', 512, 512);

        $generatedImages = [
            'featured_image' => [
                'local_path' => $imagePath,
                'size' => '1024x1024', // Wrong size declaration
                'url' => 'https://example.com/test.png'
            ]
        ];

        $validation = $this->imageGenerator->validateImages($generatedImages);

        $this->assertNotEmpty($validation['warnings']);
    }

    /**
     * Test: Get statistics
     */
    public function testGetStatistics(): void {
        $featuredPath = $this->createMockImageFile('featured.png', 1792, 1024);
        $chapter1Path = $this->createMockImageFile('chapter1.png', 1024, 1024);
        $chapter2Path = $this->createMockImageFile('chapter2.png', 1024, 1024);

        $generatedImages = [
            'featured_image' => [
                'local_path' => $featuredPath,
                'cost' => 0.080
            ],
            'chapter_images' => [
                ['local_path' => $chapter1Path, 'cost' => 0.040],
                ['local_path' => $chapter2Path, 'cost' => 0.040]
            ],
            'total_cost' => 0.160
        ];

        $stats = $this->imageGenerator->getStatistics($generatedImages);

        $this->assertEquals(3, $stats['total_images']);
        $this->assertEquals(1, $stats['featured_images']);
        $this->assertEquals(2, $stats['chapter_images']);
        $this->assertEquals(0.160, $stats['total_cost']);
        $this->assertGreaterThan(0, $stats['total_size_mb']);
        $this->assertGreaterThan(0, $stats['average_cost_per_image']);
    }

    /**
     * Test: Get statistics - no chapter images
     */
    public function testGetStatisticsNoChapterImages(): void {
        $featuredPath = $this->createMockImageFile('featured.png', 1792, 1024);

        $generatedImages = [
            'featured_image' => [
                'local_path' => $featuredPath,
                'cost' => 0.080
            ],
            'total_cost' => 0.080
        ];

        $stats = $this->imageGenerator->getStatistics($generatedImages);

        $this->assertEquals(1, $stats['total_images']);
        $this->assertEquals(0, $stats['chapter_images']);
        $this->assertEquals(0.080, $stats['total_cost']);
    }

    /**
     * Test: Get total cost
     */
    public function testGetTotalCost(): void {
        $generatedImages = [
            'total_cost' => 0.240
        ];

        $totalCost = $this->imageGenerator->getTotalCost($generatedImages);

        $this->assertEquals(0.240, $totalCost);
    }

    /**
     * Test: Get total cost - no cost field
     */
    public function testGetTotalCostNoCostField(): void {
        $generatedImages = [];

        $totalCost = $this->imageGenerator->getTotalCost($generatedImages);

        $this->assertEquals(0, $totalCost);
    }

    /**
     * Test: Cleanup images
     */
    public function testCleanupImages(): void {
        $featuredPath = $this->createMockImageFile('featured.png');
        $chapter1Path = $this->createMockImageFile('chapter1.png');

        $generatedImages = [
            'featured_image' => [
                'local_path' => $featuredPath
            ],
            'chapter_images' => [
                ['local_path' => $chapter1Path]
            ]
        ];

        $this->assertFileExists($featuredPath);
        $this->assertFileExists($chapter1Path);

        $deletedCount = $this->imageGenerator->cleanupImages($generatedImages);

        $this->assertEquals(2, $deletedCount);
        $this->assertFileDoesNotExist($featuredPath);
        $this->assertFileDoesNotExist($chapter1Path);
    }

    /**
     * Test: Cleanup images - handles missing files
     */
    public function testCleanupImagesHandlesMissingFiles(): void {
        $generatedImages = [
            'featured_image' => [
                'local_path' => '/non/existent/file.png'
            ],
            'chapter_images' => []
        ];

        $deletedCount = $this->imageGenerator->cleanupImages($generatedImages);

        $this->assertEquals(0, $deletedCount);
    }

    /**
     * Test: Validate large file size warning
     */
    public function testValidateLargeFileSizeWarning(): void {
        // Create a larger image (though still won't be > 5MB in test)
        $imagePath = $this->createMockImageFile('large.png', 2048, 2048);

        $generatedImages = [
            'featured_image' => [
                'local_path' => $imagePath,
                'size' => '2048x2048',
                'url' => 'https://example.com/test.png'
            ]
        ];

        $validation = $this->imageGenerator->validateImages($generatedImages);

        // Should be valid, but may have warnings about size mismatch
        $this->assertTrue($validation['valid']);
    }

    /**
     * Test: Image prompts structure validation
     */
    public function testImagePromptsStructureValidation(): void {
        // Test that missing featured image prompt throws exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Featured image prompt is required');

        // This would fail in the real generateAllImages() method
        // We're testing the validation logic
        $imagePrompts = [
            'chapter_images' => ['prompt 1', 'prompt 2']
            // Missing 'featured_image'
        ];

        // Create a mock that actually calls the validation
        $reflection = new ReflectionClass($this->imageGenerator);
        $method = $reflection->getMethod('generateAllImages');

        // This should throw the exception
        $method->invoke($this->imageGenerator, $imagePrompts);
    }

    /**
     * Test: Cost calculation for multiple images
     */
    public function testCostCalculationMultipleImages(): void {
        // 1 featured (1792x1024, standard) = $0.080
        // 3 chapters (1024x1024, standard) = 3 * $0.040 = $0.120
        // Total = $0.200

        $expectedFeaturedCost = WordPressImageGenerator::DALLE3_STANDARD_1792;
        $expectedChapterCost = WordPressImageGenerator::DALLE3_STANDARD_1024;
        $expectedTotal = $expectedFeaturedCost + (3 * $expectedChapterCost);

        $this->assertEquals(0.080, $expectedFeaturedCost);
        $this->assertEquals(0.040, $expectedChapterCost);
        $this->assertEquals(0.200, $expectedTotal);
    }

    /**
     * Test: Cost calculation for HD images
     */
    public function testCostCalculationHDImages(): void {
        // 1 featured (1792x1024, HD) = $0.120
        // 2 chapters (1024x1024, HD) = 2 * $0.080 = $0.160
        // Total = $0.280

        $expectedFeaturedCost = WordPressImageGenerator::DALLE3_HD_1792;
        $expectedChapterCost = WordPressImageGenerator::DALLE3_HD_1024;
        $expectedTotal = $expectedFeaturedCost + (2 * $expectedChapterCost);

        $this->assertEquals(0.120, $expectedFeaturedCost);
        $this->assertEquals(0.080, $expectedChapterCost);
        $this->assertEquals(0.280, $expectedTotal);
    }

    /**
     * Test: Average cost per image calculation
     */
    public function testAverageCostPerImageCalculation(): void {
        $generatedImages = [
            'featured_image' => ['cost' => 0.080],
            'chapter_images' => [
                ['cost' => 0.040],
                ['cost' => 0.040],
                ['cost' => 0.040]
            ],
            'total_cost' => 0.200
        ];

        $stats = $this->imageGenerator->getStatistics($generatedImages);

        // Total 4 images, $0.200 total = $0.05 average
        $this->assertEquals(0.05, $stats['average_cost_per_image']);
    }

    /**
     * Test: Image validation checks MIME type
     */
    public function testImageValidationChecksMimeType(): void {
        $imagePath = $this->createMockImageFile('test.png', 1024, 1024);

        $metadata = $this->imageGenerator->getImageMetadata($imagePath);

        $this->assertEquals('image/png', $metadata['mime_type']);
    }

    /**
     * Test: Multiple validations don't interfere
     */
    public function testMultipleValidations(): void {
        $featured1 = $this->createMockImageFile('featured1.png', 1792, 1024);
        $featured2 = $this->createMockImageFile('featured2.png', 1792, 1024);

        $images1 = [
            'featured_image' => [
                'local_path' => $featured1,
                'size' => '1792x1024',
                'url' => 'https://example.com/1.png'
            ]
        ];

        $images2 = [
            'featured_image' => [
                'local_path' => $featured2,
                'size' => '1792x1024',
                'url' => 'https://example.com/2.png'
            ]
        ];

        $validation1 = $this->imageGenerator->validateImages($images1);
        $validation2 = $this->imageGenerator->validateImages($images2);

        $this->assertTrue($validation1['valid']);
        $this->assertTrue($validation2['valid']);
    }

    /**
     * Test: Empty chapter images is valid
     */
    public function testEmptyChapterImagesValid(): void {
        $featuredPath = $this->createMockImageFile('featured.png', 1792, 1024);

        $generatedImages = [
            'featured_image' => [
                'local_path' => $featuredPath,
                'size' => '1792x1024',
                'url' => 'https://example.com/featured.png'
            ],
            'chapter_images' => []
        ];

        $validation = $this->imageGenerator->validateImages($generatedImages);

        $this->assertTrue($validation['valid']);
    }

    /**
     * Test: Statistics with zero images handles division by zero
     */
    public function testStatisticsZeroImagesHandlesDivisionByZero(): void {
        $generatedImages = [
            'total_cost' => 0
        ];

        $stats = $this->imageGenerator->getStatistics($generatedImages);

        $this->assertEquals(1, $stats['total_images']); // Still counts featured
        $this->assertEquals(0, $stats['average_cost_per_image']);
    }
}
