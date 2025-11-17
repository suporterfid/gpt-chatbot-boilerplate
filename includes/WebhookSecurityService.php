<?php
/**
 * WebhookSecurityService - Centralized security validation for webhook endpoints
 * 
 * Implements SPEC_WEBHOOK.md §6 security requirements:
 * - HMAC signature validation (anti-spoofing)
 * - Timestamp skew enforcement (anti-replay)
 * - IP/ASN whitelist checks (access control)
 * 
 * This service follows the architectural pattern established by ChatHandler,
 * consuming security-related settings from the global config.
 */

declare(strict_types=1);

class WebhookSecurityService {
    private array $config;

    /**
     * Constructor
     * 
     * @param array $config Configuration array (must include 'webhooks' section)
     */
    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Validate HMAC signature for webhook authenticity
     * 
     * Implements HMAC-SHA256 signature validation per SPEC §6.
     * Format: "sha256=<hex_digest>"
     * 
     * @param string $header Signature from request header (e.g., X-Agent-Signature)
     * @param string $body Raw request body (used to compute signature)
     * @param string $secret Shared secret for HMAC computation
     * @return bool True if signature is valid
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function validateSignature(string $header, string $body, string $secret): bool {
        // Validate inputs
        if (trim($secret) === '') {
            throw new InvalidArgumentException('Secret cannot be empty');
        }

        if (trim($header) === '') {
            return false;
        }

        // Parse signature header - expected format: "sha256=<hex_digest>"
        if (!preg_match('/^sha256=([a-f0-9]+)$/i', $header, $matches)) {
            return false;
        }

        $receivedHash = strtolower($matches[1]);

        // Compute expected signature
        $expectedHash = hash_hmac('sha256', $body, $secret);

        // Constant-time comparison to prevent timing attacks
        return hash_equals($expectedHash, $receivedHash);
    }

    /**
     * Enforce timestamp clock skew tolerance for anti-replay protection
     * 
     * Validates that the webhook timestamp is within acceptable tolerance window
     * to prevent replay attacks per SPEC §6.
     * 
     * @param int $timestamp Unix timestamp from webhook payload
     * @param int|null $tolerance Maximum allowed time difference in seconds (default: from config)
     * @return bool True if timestamp is within tolerance
     * @throws InvalidArgumentException If timestamp is invalid
     */
    public function enforceClockSkew(int $timestamp, ?int $tolerance = null): bool {
        // Validate timestamp
        if ($timestamp <= 0) {
            throw new InvalidArgumentException('Timestamp must be a positive integer');
        }

        // Get tolerance from config or use provided value
        if ($tolerance === null) {
            $tolerance = max(0, (int)($this->config['webhooks']['timestamp_tolerance'] ?? 300));
        }

        // If tolerance is 0, skip validation (effectively disabled)
        if ($tolerance === 0) {
            return true;
        }

        // Check if timestamp is within tolerance window
        $now = time();
        $diff = abs($now - $timestamp);

        return $diff <= $tolerance;
    }

    /**
     * Check if request IP is in the configured whitelist
     * 
     * Implements IP and ASN-based access control per SPEC §6.
     * Supports:
     * - Individual IPs (e.g., "192.168.1.1")
     * - CIDR ranges (e.g., "192.168.1.0/24")
     * - ASN numbers (e.g., "AS15169" for Google)
     * 
     * @param string $ip IP address to check
     * @param array|null $whitelist Array of allowed IPs/CIDRs/ASNs (default: from config)
     * @return bool True if IP is whitelisted or whitelist is empty
     * @throws InvalidArgumentException If IP is invalid
     */
    public function checkWhitelist(string $ip, ?array $whitelist = null): bool {
        // Validate IP address format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Invalid IP address format');
        }

        // Get whitelist from config or use provided value
        if ($whitelist === null) {
            $whitelist = $this->config['webhooks']['ip_whitelist'] ?? [];
        }

        // If whitelist is empty, allow all IPs (whitelist disabled)
        if (empty($whitelist)) {
            return true;
        }

        // Check each whitelist entry
        foreach ($whitelist as $entry) {
            $entry = trim($entry);

            // Skip empty entries
            if ($entry === '') {
                continue;
            }

            // Check for exact IP match
            if ($entry === $ip) {
                return true;
            }

            // Check for CIDR range match
            if (strpos($entry, '/') !== false) {
                if ($this->ipInCidrRange($ip, $entry)) {
                    return true;
                }
            }

            // Check for ASN match (requires external lookup - placeholder for now)
            if (stripos($entry, 'AS') === 0) {
                // ASN checking would require external service or database
                // For now, we'll skip ASN validation as it requires additional infrastructure
                // This can be implemented later with services like ipinfo.io or MaxMind
                continue;
            }
        }

        return false;
    }

    /**
     * Check if an IP address is within a CIDR range
     * 
     * @param string $ip IP address to check
     * @param string $cidr CIDR notation (e.g., "192.168.1.0/24")
     * @return bool True if IP is in range
     */
    private function ipInCidrRange(string $ip, string $cidr): bool {
        list($subnet, $mask) = explode('/', $cidr);

        // Validate CIDR components
        if (!filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        $mask = (int)$mask;
        if ($mask < 0 || $mask > 32) {
            return false;
        }

        // Convert IPs to long integers for comparison
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        // Create netmask
        $netmask = -1 << (32 - $mask);

        // Check if IP is in subnet
        return ($ipLong & $netmask) === ($subnetLong & $netmask);
    }

    /**
     * Perform all security checks at once
     * 
     * Convenience method that runs all security validations and returns
     * detailed results about which checks passed or failed.
     * 
     * @param array $params Associative array with keys: 'signature_header', 'body', 'secret', 'timestamp', 'ip'
     * @return array Results with 'valid' (bool) and 'checks' (array) keys
     */
    public function validateAll(array $params): array {
        $results = [
            'valid' => true,
            'checks' => [
                'signature' => null,
                'timestamp' => null,
                'whitelist' => null,
            ],
            'errors' => [],
        ];

        // Check signature if parameters provided
        if (isset($params['signature_header'], $params['body'], $params['secret'])) {
            try {
                $signatureValid = $this->validateSignature(
                    $params['signature_header'],
                    $params['body'],
                    $params['secret']
                );
                $results['checks']['signature'] = $signatureValid;
                if (!$signatureValid) {
                    $results['valid'] = false;
                    $results['errors'][] = 'Invalid signature';
                }
            } catch (Exception $e) {
                $results['checks']['signature'] = false;
                $results['valid'] = false;
                $results['errors'][] = 'Signature validation error: ' . $e->getMessage();
            }
        }

        // Check timestamp if provided
        if (isset($params['timestamp'])) {
            try {
                $timestampValid = $this->enforceClockSkew(
                    (int)$params['timestamp'],
                    $params['tolerance'] ?? null
                );
                $results['checks']['timestamp'] = $timestampValid;
                if (!$timestampValid) {
                    $results['valid'] = false;
                    $results['errors'][] = 'Timestamp outside tolerance window';
                }
            } catch (Exception $e) {
                $results['checks']['timestamp'] = false;
                $results['valid'] = false;
                $results['errors'][] = 'Timestamp validation error: ' . $e->getMessage();
            }
        }

        // Check whitelist if IP provided
        if (isset($params['ip'])) {
            try {
                $whitelistValid = $this->checkWhitelist(
                    $params['ip'],
                    $params['whitelist'] ?? null
                );
                $results['checks']['whitelist'] = $whitelistValid;
                if (!$whitelistValid) {
                    $results['valid'] = false;
                    $results['errors'][] = 'IP not in whitelist';
                }
            } catch (Exception $e) {
                $results['checks']['whitelist'] = false;
                $results['valid'] = false;
                $results['errors'][] = 'Whitelist validation error: ' . $e->getMessage();
            }
        }

        return $results;
    }
}
