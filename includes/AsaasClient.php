<?php
/**
 * Asaas API Client - Integration with Asaas payment gateway
 * Handles payment processing, customer management, and webhooks
 * Documentation: https://docs.asaas.com/
 */

class AsaasClient {
    private $apiKey;
    private $baseUrl;
    private $isProduction;
    
    public function __construct($apiKey, $isProduction = false) {
        $this->apiKey = $apiKey;
        $this->isProduction = $isProduction;
        $this->baseUrl = $isProduction 
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';
    }
    
    /**
     * Create a customer in Asaas
     */
    public function createCustomer($data) {
        return $this->request('POST', '/customers', [
            'name' => $data['name'],
            'cpfCnpj' => $data['cpf_cnpj'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobilePhone' => $data['mobile_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'addressNumber' => $data['address_number'] ?? null,
            'complement' => $data['complement'] ?? null,
            'province' => $data['province'] ?? null,
            'postalCode' => $data['postal_code'] ?? null,
            'externalReference' => $data['external_reference'] ?? null,
            'notificationDisabled' => $data['notification_disabled'] ?? false,
            'additionalEmails' => $data['additional_emails'] ?? null,
            'municipalInscription' => $data['municipal_inscription'] ?? null,
            'stateInscription' => $data['state_inscription'] ?? null,
            'observations' => $data['observations'] ?? null,
            'groupName' => $data['group_name'] ?? null
        ]);
    }
    
    /**
     * Update customer in Asaas
     */
    public function updateCustomer($customerId, $data) {
        return $this->request('POST', "/customers/{$customerId}", $data);
    }
    
    /**
     * Get customer from Asaas
     */
    public function getCustomer($customerId) {
        return $this->request('GET', "/customers/{$customerId}");
    }
    
    /**
     * Create a payment/charge
     */
    public function createPayment($data) {
        return $this->request('POST', '/payments', [
            'customer' => $data['customer_id'],
            'billingType' => $data['billing_type'], // BOLETO, CREDIT_CARD, PIX, UNDEFINED
            'value' => $data['value'],
            'dueDate' => $data['due_date'],
            'description' => $data['description'] ?? null,
            'externalReference' => $data['external_reference'] ?? null,
            'installmentCount' => $data['installment_count'] ?? null,
            'installmentValue' => $data['installment_value'] ?? null,
            'discount' => $data['discount'] ?? null,
            'interest' => $data['interest'] ?? null,
            'fine' => $data['fine'] ?? null,
            'postalService' => $data['postal_service'] ?? false,
            'split' => $data['split'] ?? null,
            'callback' => $data['callback'] ?? null
        ]);
    }
    
    /**
     * Get payment details
     */
    public function getPayment($paymentId) {
        return $this->request('GET', "/payments/{$paymentId}");
    }
    
    /**
     * Update payment
     */
    public function updatePayment($paymentId, $data) {
        return $this->request('POST', "/payments/{$paymentId}", $data);
    }
    
    /**
     * Delete payment
     */
    public function deletePayment($paymentId) {
        return $this->request('DELETE', "/payments/{$paymentId}");
    }
    
    /**
     * Create a subscription
     */
    public function createSubscription($data) {
        return $this->request('POST', '/subscriptions', [
            'customer' => $data['customer_id'],
            'billingType' => $data['billing_type'],
            'value' => $data['value'],
            'nextDueDate' => $data['next_due_date'],
            'cycle' => $data['cycle'], // WEEKLY, BIWEEKLY, MONTHLY, QUARTERLY, SEMIANNUALLY, YEARLY
            'description' => $data['description'] ?? null,
            'endDate' => $data['end_date'] ?? null,
            'maxPayments' => $data['max_payments'] ?? null,
            'externalReference' => $data['external_reference'] ?? null,
            'split' => $data['split'] ?? null,
            'callback' => $data['callback'] ?? null
        ]);
    }
    
    /**
     * Get subscription details
     */
    public function getSubscription($subscriptionId) {
        return $this->request('GET', "/subscriptions/{$subscriptionId}");
    }
    
    /**
     * Update subscription
     */
    public function updateSubscription($subscriptionId, $data) {
        return $this->request('POST', "/subscriptions/{$subscriptionId}", $data);
    }
    
    /**
     * Delete subscription
     */
    public function deleteSubscription($subscriptionId) {
        return $this->request('DELETE', "/subscriptions/{$subscriptionId}");
    }
    
    /**
     * List payments for a customer
     */
    public function listPayments($filters = []) {
        $queryParams = http_build_query($filters);
        return $this->request('GET', "/payments?{$queryParams}");
    }
    
    /**
     * Create credit card token
     */
    public function tokenizeCreditCard($data) {
        return $this->request('POST', '/creditCard/tokenize', [
            'creditCard' => [
                'holderName' => $data['holder_name'],
                'number' => $data['number'],
                'expiryMonth' => $data['expiry_month'],
                'expiryYear' => $data['expiry_year'],
                'ccv' => $data['ccv']
            ],
            'creditCardHolderInfo' => $data['holder_info'] ?? null,
            'customer' => $data['customer_id'] ?? null
        ]);
    }
    
    /**
     * Process credit card payment
     */
    public function processCreditCardPayment($paymentId, $data) {
        return $this->request('POST', "/payments/{$paymentId}/payWithCreditCard", [
            'creditCard' => [
                'holderName' => $data['holder_name'],
                'number' => $data['number'],
                'expiryMonth' => $data['expiry_month'],
                'expiryYear' => $data['expiry_year'],
                'ccv' => $data['ccv']
            ],
            'creditCardHolderInfo' => [
                'name' => $data['holder_info']['name'],
                'email' => $data['holder_info']['email'],
                'cpfCnpj' => $data['holder_info']['cpf_cnpj'],
                'postalCode' => $data['holder_info']['postal_code'],
                'addressNumber' => $data['holder_info']['address_number'],
                'phone' => $data['holder_info']['phone']
            ],
            'remoteIp' => $data['remote_ip'] ?? null
        ]);
    }
    
    /**
     * Generate PIX QR Code
     */
    public function generatePixQrCode($paymentId) {
        return $this->request('GET', "/payments/{$paymentId}/pixQrCode");
    }
    
    /**
     * Get account balance
     */
    public function getBalance() {
        return $this->request('GET', '/finance/balance');
    }
    
    /**
     * Webhook signature validation
     */
    public function validateWebhookSignature($payload, $signature, $accessToken) {
        $calculatedSignature = hash_hmac('sha256', $payload, $accessToken);
        return hash_equals($calculatedSignature, $signature);
    }
    
    /**
     * Make HTTP request to Asaas API
     */
    private function request($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'access_token: ' . $this->apiKey
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Asaas API request failed: {$error}");
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $result['errors'][0]['description'] ?? 'Unknown error';
            throw new Exception("Asaas API error: {$errorMessage}", $httpCode);
        }
        
        return $result;
    }
}
