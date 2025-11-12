<?php
/**
 * Integration tests for LeadSense daily notification limits
 */

require_once __DIR__ . '/../includes/LeadSense/LeadSenseService.php';

class LeadSenseDailyLimitTest {
    private $passed = 0;
    private $failed = 0;
    private $tempFiles = [];

    public function run() {
        echo "Running LeadSense Daily Limit Tests...\n\n";

        $this->testDailyLimitBlocksWhenThresholdReached();
        $this->testDailyLimitResetsEachDay();

        $this->cleanup();

        echo "\n=================================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "=================================\n";

        return $this->failed === 0;
    }

    private function setupService($maxDaily) {
        $dbPath = sys_get_temp_dir() . '/leadsense_limit_' . uniqid() . '.sqlite';
        $this->tempFiles[] = $dbPath;

        $db = new DB(['database_path' => $dbPath]);
        $db->runMigrations(__DIR__ . '/../db/migrations');

        $config = [
            'enabled' => true,
            'database_path' => $dbPath,
            'max_daily_notifications' => $maxDaily,
        ];

        $service = new LeadSenseService($config);
        $repository = $this->getRepository($service);

        return [$service, $repository, $dbPath];
    }

    private function getRepository($service) {
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('leadRepository');
        $property->setAccessible(true);
        return $property->getValue($service);
    }

    private function invokeHasReachedDailyLimit($service, $tenantId = null) {
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('hasReachedDailyLimit');
        $method->setAccessible(true);
        return $method->invoke($service, $tenantId);
    }

    private function createLead($repository, $tenantId, $suffix) {
        return $repository->createOrUpdateLead([
            'agent_id' => 'agent-' . $suffix,
            'conversation_id' => 'conversation-' . $suffix,
            'qualified' => true,
            'intent_level' => 'high',
            'score' => 90,
            'tenant_id' => $tenantId,
        ]);
    }

    private function testDailyLimitBlocksWhenThresholdReached() {
        echo "Test: Daily limit blocks notifications at threshold\n";

        [$service, $repository, $dbPath] = $this->setupService(2);
        $tenantId = 'tenant-limit';
        $this->ensureTenant($dbPath, $tenantId);
        $repository->setTenantId($tenantId);

        $this->assert(
            $this->invokeHasReachedDailyLimit($service, $tenantId) === false,
            'Should allow notifications when no events recorded yet'
        );

        $leadId = $this->createLead($repository, $tenantId, 'limit');

        $repository->addEvent($leadId, 'notified');
        $this->assert(
            $this->invokeHasReachedDailyLimit($service, $tenantId) === false,
            'Should allow notifications while under the daily limit'
        );

        $repository->addEvent($leadId, 'notified');
        $this->assert(
            $this->invokeHasReachedDailyLimit($service, $tenantId) === true,
            'Should block notifications once the daily limit is reached'
        );
    }

    private function testDailyLimitResetsEachDay() {
        echo "Test: Daily limit resets with a new UTC day\n";

        [$service, $repository, $dbPath] = $this->setupService(1);
        $tenantId = 'tenant-reset';
        $this->ensureTenant($dbPath, $tenantId);
        $repository->setTenantId($tenantId);

        $leadId = $this->createLead($repository, $tenantId, 'reset');
        $eventId = $repository->addEvent($leadId, 'notified');

        // Move the event to yesterday to simulate prior-day notifications
        $yesterday = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-1 day')
            ->format('Y-m-d H:i:s');

        $db = new DB(['database_path' => $dbPath]);
        $db->execute(
            'UPDATE lead_events SET created_at = :created_at WHERE id = :id',
            [
                'created_at' => $yesterday,
                'id' => $eventId,
            ]
        );

        $this->assert(
            $this->invokeHasReachedDailyLimit($service, $tenantId) === false,
            'Should allow notifications when prior-day events are ignored'
        );

        $repository->addEvent($leadId, 'notified');
        $this->assert(
            $this->invokeHasReachedDailyLimit($service, $tenantId) === true,
            'Should block notifications again after today\'s event'
        );
    }

    private function assert($condition, $message) {
        if ($condition) {
            echo "✓ $message\n";
            $this->passed++;
        } else {
            echo "✗ $message\n";
            $this->failed++;
        }
    }

    private function ensureTenant($dbPath, $tenantId) {
        $db = new DB(['database_path' => $dbPath]);

        $db->execute(
            'INSERT OR IGNORE INTO tenants (id, name, slug, status, plan, billing_email, settings_json, created_at, updated_at)
             VALUES (:id, :name, :slug, :status, :plan, :billing_email, :settings_json, :created_at, :updated_at)',
            [
                'id' => $tenantId,
                'name' => 'Tenant ' . $tenantId,
                'slug' => $tenantId,
                'status' => 'active',
                'plan' => null,
                'billing_email' => null,
                'settings_json' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    private function cleanup() {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
            $journal = $file . '-journal';
            if (file_exists($journal)) {
                @unlink($journal);
            }
            $wal = $file . '-wal';
            if (file_exists($wal)) {
                @unlink($wal);
            }
            $shm = $file . '-shm';
            if (file_exists($shm)) {
                @unlink($shm);
            }
        }
    }
}

$test = new LeadSenseDailyLimitTest();
$success = $test->run();
exit($success ? 0 : 1);
