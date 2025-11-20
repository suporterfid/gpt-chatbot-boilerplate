<?php
/**
 * Test Runner for Specialized Agents System
 *
 * Runs all specialized agent tests and reports results
 */

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  Specialized Agents System - Test Suite            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

$testFiles = [
    __DIR__ . '/test_specialized_agents_registry.php',
    __DIR__ . '/test_specialized_agents_service.php',
    __DIR__ . '/../agents/wordpress/tests/WordPressAgentTest.php',
    __DIR__ . '/../agents/_template/tests/TemplateAgentTest.php'
];

$totalPassed = 0;
$totalFailed = 0;
$suiteResults = [];

foreach ($testFiles as $testFile) {
    if (!file_exists($testFile)) {
        echo "⚠️  Test file not found: {$testFile}\n";
        continue;
    }

    echo "\n";
    echo "═══════════════════════════════════════════════════════\n";
    echo "Running: " . basename($testFile) . "\n";
    echo "═══════════════════════════════════════════════════════\n";

    // Capture output
    ob_start();
    $success = false;

    try {
        require_once $testFile;

        // Determine test class name from file name
        $className = str_replace('.php', '', basename($testFile));

        if (class_exists($className)) {
            $test = new $className();
            if (method_exists($test, 'runAll')) {
                $success = $test->runAll();
            }
        }
    } catch (Exception $e) {
        echo "✗ Test file failed with exception: " . $e->getMessage() . "\n";
        $success = false;
    }

    $output = ob_get_clean();
    echo $output;

    // Parse results from output (simple parsing)
    if (preg_match('/Passed:\s+(\d+)/', $output, $passedMatches)) {
        $passed = (int)$passedMatches[1];
        $totalPassed += $passed;
    }

    if (preg_match('/Failed:\s+(\d+)/', $output, $failedMatches)) {
        $failed = (int)$failedMatches[1];
        $totalFailed += $failed;
    }

    $suiteResults[basename($testFile)] = $success;
}

// Final summary
echo "\n\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║             FINAL TEST SUMMARY                      ║\n";
echo "╠══════════════════════════════════════════════════════╣\n";

foreach ($suiteResults as $file => $success) {
    $status = $success ? '✓ PASS' : '✗ FAIL';
    $statusPadded = str_pad($status, 7, ' ', STR_PAD_LEFT);
    $filePadded = str_pad($file, 42, ' ', STR_PAD_RIGHT);
    echo "║ {$statusPadded}  {$filePadded} ║\n";
}

echo "╠══════════════════════════════════════════════════════╣\n";
echo "║ Total Passed: " . str_pad($totalPassed, 37, ' ', STR_PAD_LEFT) . " ║\n";
echo "║ Total Failed: " . str_pad($totalFailed, 37, ' ', STR_PAD_LEFT) . " ║\n";
echo "╠══════════════════════════════════════════════════════╣\n";

$allPassed = $totalFailed === 0;
if ($allPassed) {
    echo "║              🎉 ALL TESTS PASSED! 🎉                ║\n";
} else {
    echo "║           ⚠️  SOME TESTS FAILED ⚠️                 ║\n";
}

echo "╚══════════════════════════════════════════════════════╝\n\n";

exit($allPassed ? 0 : 1);
