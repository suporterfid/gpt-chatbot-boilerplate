#!/usr/bin/env php
<?php
/**
 * Background Worker - Process jobs from the queue
 * 
 * Usage:
 *   php scripts/worker.php             # Process one batch and exit
 *   php scripts/worker.php --loop      # Run continuously
 *   php scripts/worker.php --daemon    # Run as daemon with graceful shutdown
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/JobQueue.php';
require_once __DIR__ . '/../includes/OpenAIAdminClient.php';
require_once __DIR__ . '/../includes/VectorStoreService.php';
require_once __DIR__ . '/../includes/PromptService.php';

// Parse command line arguments
$options = getopt('', ['loop', 'daemon', 'sleep:', 'verbose']);
$loop = isset($options['loop']) || isset($options['daemon']);
$daemon = isset($options['daemon']);
$sleepSeconds = isset($options['sleep']) ? (int)$options['sleep'] : 5;
$verbose = isset($options['verbose']);

// Graceful shutdown handler
$shutdown = false;
if ($daemon && extension_loaded('pcntl')) {
    pcntl_signal(SIGTERM, function() use (&$shutdown) {
        echo "[WORKER] Received SIGTERM, shutting down gracefully...\n";
        $shutdown = true;
    });
    
    pcntl_signal(SIGINT, function() use (&$shutdown) {
        echo "[WORKER] Received SIGINT, shutting down gracefully...\n";
        $shutdown = true;
    });
} elseif ($daemon && !extension_loaded('pcntl')) {
    echo "[WORKER] Warning: pcntl extension not available, graceful shutdown disabled\n";
}

// Initialize services
try {
    $db = new DB($config['database'] ?? []);
    $jobQueue = new JobQueue($db);
    $openaiClient = new OpenAIAdminClient($config['openai'] ?? []);
    $vectorStoreService = new VectorStoreService($db, $openaiClient);
    $promptService = new PromptService($db, $openaiClient);
    
    echo "[WORKER] Started at " . date('Y-m-d H:i:s') . "\n";
    echo "[WORKER] Mode: " . ($daemon ? "daemon" : ($loop ? "loop" : "single")) . "\n";
    
} catch (Exception $e) {
    echo "[WORKER] Failed to initialize: " . $e->getMessage() . "\n";
    exit(1);
}

// Job processor function
function processJob($job, $jobQueue, $openaiClient, $vectorStoreService, $promptService, $verbose) {
    $jobId = $job['id'];
    $type = $job['type'];
    $payload = $job['payload'];
    
    if ($verbose) {
        echo "[WORKER] Processing job $jobId (type: $type, attempt: " . ($job['attempts'] + 1) . ")\n";
    }
    
    try {
        $result = [];
        
        switch ($type) {
            case 'file_ingest':
                $result = handleFileIngest($payload, $openaiClient, $vectorStoreService, $verbose);
                break;
                
            case 'attach_file_to_store':
                $result = handleAttachFileToStore($payload, $openaiClient, $vectorStoreService, $verbose);
                break;
                
            case 'poll_ingestion_status':
                $result = handlePollIngestionStatus($payload, $openaiClient, $vectorStoreService, $jobQueue, $verbose);
                break;
                
            case 'prompt_version_create':
                $result = handlePromptVersionCreate($payload, $openaiClient, $promptService, $verbose);
                break;
                
            case 'send_webhook_event':
                $result = handleSendWebhookEvent($payload, $verbose);
                break;
                
            default:
                throw new Exception("Unknown job type: $type");
        }
        
        $jobQueue->markCompleted($jobId, $result);
        
        if ($verbose) {
            echo "[WORKER] Job $jobId completed successfully\n";
        }
        
        return true;
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("[WORKER] Job $jobId failed: $error");
        
        if ($verbose) {
            echo "[WORKER] Job $jobId failed: $error\n";
        }
        
        $jobQueue->markFailed($jobId, $error, true);
        return false;
    }
}

// Job handlers
function handleFileIngest($payload, $openaiClient, $vectorStoreService, $verbose) {
    $fileId = $payload['file_id'] ?? null;
    $vectorStoreId = $payload['vector_store_id'] ?? null;
    
    if (!$fileId || !$vectorStoreId) {
        throw new Exception("Missing file_id or vector_store_id in payload");
    }
    
    if ($verbose) {
        echo "[WORKER] Uploading file $fileId to OpenAI and attaching to store $vectorStoreId\n";
    }
    
    // Get file record from DB
    $file = $vectorStoreService->getFile($fileId);
    if (!$file) {
        throw new Exception("File not found: $fileId");
    }
    
    // Upload to OpenAI if not already uploaded
    if (!$file['openai_file_id']) {
        $openaiFile = $openaiClient->uploadFile(
            $file['filename'],
            $file['mime_type'],
            $file['file_data'],
            'assistants'
        );
        
        // Update DB with OpenAI file ID
        $vectorStoreService->updateFileOpenAIId($fileId, $openaiFile['id']);
        $file['openai_file_id'] = $openaiFile['id'];
    }
    
    // Attach to vector store
    $attachment = $openaiClient->addFileToVectorStore($vectorStoreId, $file['openai_file_id']);
    
    // Update ingestion status
    $vectorStoreService->updateFileIngestionStatus($fileId, 'in_progress');
    
    return [
        'openai_file_id' => $file['openai_file_id'],
        'attachment_status' => $attachment['status'] ?? 'unknown'
    ];
}

function handleAttachFileToStore($payload, $openaiClient, $vectorStoreService, $verbose) {
    $fileId = $payload['file_id'] ?? null;
    $vectorStoreId = $payload['vector_store_id'] ?? null;
    $openaiFileId = $payload['openai_file_id'] ?? null;
    
    if (!$fileId || !$vectorStoreId || !$openaiFileId) {
        throw new Exception("Missing required parameters in payload");
    }
    
    if ($verbose) {
        echo "[WORKER] Attaching file $openaiFileId to store $vectorStoreId\n";
    }
    
    $attachment = $openaiClient->addFileToVectorStore($vectorStoreId, $openaiFileId);
    
    // Update status
    $vectorStoreService->updateFileIngestionStatus($fileId, $attachment['status'] ?? 'in_progress');
    
    return [
        'attachment_id' => $attachment['id'] ?? null,
        'status' => $attachment['status'] ?? 'unknown'
    ];
}

function handlePollIngestionStatus($payload, $openaiClient, $vectorStoreService, $jobQueue, $verbose) {
    $fileId = $payload['file_id'] ?? null;
    $vectorStoreId = $payload['vector_store_id'] ?? null;
    $openaiFileId = $payload['openai_file_id'] ?? null;
    
    if (!$fileId || !$vectorStoreId || !$openaiFileId) {
        throw new Exception("Missing required parameters in payload");
    }
    
    if ($verbose) {
        echo "[WORKER] Polling ingestion status for file $openaiFileId in store $vectorStoreId\n";
    }
    
    // Get current status from OpenAI
    $status = $openaiClient->getVectorStoreFileStatus($vectorStoreId, $openaiFileId);
    
    $currentStatus = $status['status'] ?? 'unknown';
    
    // Update DB
    $vectorStoreService->updateFileIngestionStatus($fileId, $currentStatus);
    
    // If still in progress, enqueue another poll job with delay
    if ($currentStatus === 'in_progress') {
        $jobQueue->enqueue('poll_ingestion_status', $payload, 3, 30); // Poll again in 30 seconds
        
        if ($verbose) {
            echo "[WORKER] File still in_progress, scheduled next poll\n";
        }
    }
    
    return [
        'status' => $currentStatus,
        'last_error' => $status['last_error'] ?? null
    ];
}

function handlePromptVersionCreate($payload, $openaiClient, $promptService, $verbose) {
    $promptId = $payload['prompt_id'] ?? null;
    $definition = $payload['definition'] ?? null;
    
    if (!$promptId || !$definition) {
        throw new Exception("Missing prompt_id or definition in payload");
    }
    
    if ($verbose) {
        echo "[WORKER] Creating prompt version for prompt $promptId\n";
    }
    
    // Create version via OpenAI
    $version = $openaiClient->createPromptVersion($promptId, $definition);
    
    // Store version in DB
    $promptService->storePromptVersion($promptId, $version);
    
    return [
        'version_id' => $version['id'] ?? null,
        'version_number' => $version['version'] ?? null
    ];
}

function handleSendWebhookEvent($payload, $verbose) {
    $url = $payload['url'] ?? null;
    $data = $payload['data'] ?? [];
    
    if (!$url) {
        throw new Exception("Missing webhook URL in payload");
    }
    
    if ($verbose) {
        echo "[WORKER] Sending webhook to $url\n";
    }
    
    // Send HTTP POST to webhook URL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: GPT-Chatbot-Worker/1.0'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Webhook request failed: $error");
    }
    
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Webhook returned HTTP $httpCode: $response");
    }
    
    return [
        'http_code' => $httpCode,
        'response' => substr($response, 0, 1000) // Limit response size
    ];
}

// Main worker loop
$batchCount = 0;
do {
    if ($daemon && extension_loaded('pcntl')) {
        pcntl_signal_dispatch();
    }
    
    if ($shutdown) {
        break;
    }
    
    try {
        $job = $jobQueue->claimNext();
        
        if ($job) {
            processJob($job, $jobQueue, $openaiClient, $vectorStoreService, $promptService, $verbose);
            $batchCount = 0; // Reset idle counter
        } else {
            // No jobs available
            if ($loop) {
                if ($verbose && $batchCount === 0) {
                    echo "[WORKER] No jobs available, sleeping for {$sleepSeconds}s...\n";
                }
                sleep($sleepSeconds);
                $batchCount++;
            }
        }
    } catch (Exception $e) {
        error_log("[WORKER] Error in main loop: " . $e->getMessage());
        if ($verbose) {
            echo "[WORKER] Error: " . $e->getMessage() . "\n";
        }
        sleep($sleepSeconds);
    }
    
} while ($loop && !$shutdown);

echo "[WORKER] Stopped at " . date('Y-m-d H:i:s') . "\n";
exit(0);
