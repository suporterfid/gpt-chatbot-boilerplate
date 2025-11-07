#!/usr/bin/env php
<?php
/**
 * Generate Monthly Invoices
 * Run this script to generate invoices based on usage for each tenant
 * 
 * Usage:
 *   php scripts/generate_invoices.php [--tenant-id=xxx] [--month=YYYY-MM] [--dry-run]
 * 
 * Cron example:
 *   # Generate invoices on the 1st of each month
 *   0 3 1 * * cd /path/to/app && php scripts/generate_invoices.php
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/BillingService.php';
require_once __DIR__ . '/../includes/TenantUsageService.php';
require_once __DIR__ . '/../includes/NotificationService.php';

// Parse command line arguments
$options = getopt('', ['tenant-id::', 'month::', 'dry-run::', 'verbose::']);
$tenantId = $options['tenant-id'] ?? null;
$month = $options['month'] ?? date('Y-m', strtotime('-1 month')); // Default to last month
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']) || $dryRun;

try {
    // Initialize services
    $db = DB::getInstance();
    $billingService = new BillingService($db);
    $tenantUsageService = new TenantUsageService($db);
    $notificationService = new NotificationService($db);
    
    if ($verbose) {
        echo "Starting invoice generation...\n";
        echo "Month: $month\n";
        if ($dryRun) {
            echo "*** DRY RUN MODE - No invoices will be created ***\n";
        }
    }
    
    // Calculate date range for the month
    $startDate = date('Y-m-01 00:00:00', strtotime($month . '-01'));
    $endDate = date('Y-m-t 23:59:59', strtotime($month . '-01'));
    
    if ($verbose) {
        echo "Period: $startDate to $endDate\n\n";
    }
    
    // Get all tenants or specific tenant
    if ($tenantId) {
        $tenants = [$db->queryOne("SELECT * FROM tenants WHERE id = ?", [$tenantId])];
    } else {
        $tenants = $db->query("SELECT * FROM tenants WHERE status = 'active'");
    }
    
    if (empty($tenants)) {
        if ($verbose) {
            echo "No active tenants found.\n";
        }
        exit(0);
    }
    
    $invoicesGenerated = 0;
    $totalAmount = 0;
    
    // Pricing configuration (can be moved to database or config file)
    $pricing = [
        'message' => 0.01,        // $0.01 per message
        'completion' => 0.02,     // $0.02 per completion
        'file_upload' => 0.10,    // $0.10 per file upload
        'file_storage' => 0.001,  // $0.001 per MB per month
        'vector_query' => 0.005,  // $0.005 per query
        'tool_call' => 0.01,      // $0.01 per tool call
        'embedding' => 0.001      // $0.001 per embedding
    ];
    
    foreach ($tenants as $tenant) {
        if (!$tenant) continue;
        
        $tid = $tenant['id'];
        
        if ($verbose) {
            echo "Processing tenant: {$tenant['name']} ($tid)\n";
        }
        
        // Get usage for the month
        $usage = $tenantUsageService->getTenantUsage($tid, 'monthly', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        if (empty($usage)) {
            if ($verbose) {
                echo "  No usage data found for this period.\n\n";
            }
            continue;
        }
        
        // Calculate line items and total
        $lineItems = [];
        $subtotal = 0;
        
        foreach ($usage as $usageRecord) {
            $resourceType = $usageRecord['resource_type'];
            $quantity = $usageRecord['total_quantity'];
            
            if (!isset($pricing[$resourceType])) {
                continue;
            }
            
            $unitPrice = $pricing[$resourceType];
            $amount = $quantity * $unitPrice;
            $subtotal += $amount;
            
            $lineItems[] = [
                'description' => ucfirst(str_replace('_', ' ', $resourceType)),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount
            ];
            
            if ($verbose) {
                echo "  - $resourceType: $quantity x \${$unitPrice} = \${$amount}\n";
            }
        }
        
        if ($subtotal == 0) {
            if ($verbose) {
                echo "  No billable usage found.\n\n";
            }
            continue;
        }
        
        $amountCents = (int)($subtotal * 100);
        
        if ($verbose) {
            echo "  Subtotal: \${$subtotal}\n";
        }
        
        if (!$dryRun) {
            // Generate invoice
            $invoice = $billingService->createInvoice($tid, [
                'amount_cents' => $amountCents,
                'currency' => 'USD',
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'line_items' => $lineItems,
                'billing_details' => [
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'month' => $month
                ]
            ]);
            
            // Send notification
            $notificationService->createNotification($tid, [
                'type' => 'invoice_created',
                'title' => 'New Invoice Generated',
                'message' => "Invoice {$invoice['invoice_number']} for \${$subtotal} has been generated for the period $month.",
                'priority' => 'medium',
                'metadata' => [
                    'invoice_id' => $invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'amount' => $subtotal,
                    'currency' => 'USD'
                ]
            ]);
            
            if ($verbose) {
                echo "  ✓ Invoice created: {$invoice['invoice_number']}\n\n";
            }
            
            $invoicesGenerated++;
            $totalAmount += $subtotal;
        } else {
            if ($verbose) {
                echo "  [DRY RUN] Would create invoice for \${$subtotal}\n\n";
            }
        }
    }
    
    if ($verbose) {
        echo "Invoice generation complete!\n";
        if (!$dryRun) {
            echo "Invoices generated: $invoicesGenerated\n";
            echo "Total amount: \${$totalAmount}\n";
        }
    }
    
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Invoice generation error: " . $e->getMessage());
    exit(1);
}
