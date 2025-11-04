/**
 * K6 Load Testing Script for GPT Chatbot
 * 
 * Usage:
 *   k6 run --vus 10 --duration 30s tests/load/chat_api.js
 *   k6 run --vus 5 --duration 60s --env BASE_URL=https://chatbot.example.com tests/load/chat_api.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const chatCompletionTime = new Trend('chat_completion_time');

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const ADMIN_TOKEN = __ENV.ADMIN_TOKEN || '';

export const options = {
    stages: [
        { duration: '30s', target: 10 },  // Ramp up to 10 users
        { duration: '1m', target: 10 },   // Stay at 10 users
        { duration: '30s', target: 20 },  // Ramp up to 20 users
        { duration: '1m', target: 20 },   // Stay at 20 users
        { duration: '30s', target: 0 },   // Ramp down to 0
    ],
    thresholds: {
        'http_req_duration': ['p(95)<2000'], // 95% of requests should be below 2s
        'errors': ['rate<0.1'],              // Error rate should be below 10%
        'http_req_failed': ['rate<0.05'],    // Less than 5% failed requests
    },
};

// Test scenarios
export default function () {
    const scenario = Math.random();
    
    if (scenario < 0.7) {
        // 70% - Chat completions
        testChatCompletion();
    } else if (scenario < 0.9) {
        // 20% - Agent testing
        testAgentTest();
    } else {
        // 10% - Admin API
        testAdminAPI();
    }
    
    sleep(Math.random() * 3 + 1); // Random sleep between 1-4 seconds
}

function testChatCompletion() {
    const payload = JSON.stringify({
        message: 'What is the weather like today?',
        api_type: 'responses',
        stream: false
    });
    
    const params = {
        headers: {
            'Content-Type': 'application/json',
        },
        timeout: '30s',
    };
    
    const start = new Date();
    const response = http.post(`${BASE_URL}/chat-unified.php`, payload, params);
    const duration = new Date() - start;
    
    chatCompletionTime.add(duration);
    
    const success = check(response, {
        'status is 200': (r) => r.status === 200,
        'has response': (r) => {
            try {
                const data = r.json();
                return data !== undefined;
            } catch {
                return false;
            }
        },
    });
    
    errorRate.add(!success);
}

function testAgentTest() {
    if (!ADMIN_TOKEN) {
        return; // Skip if no admin token
    }
    
    const payload = JSON.stringify({
        message: 'Hello, this is a test message',
        agent_id: null // Use default agent
    });
    
    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${ADMIN_TOKEN}`,
        },
        timeout: '60s',
    };
    
    const response = http.post(`${BASE_URL}/admin-api.php/test_agent`, payload, params);
    
    const success = check(response, {
        'agent test status is 200': (r) => r.status === 200,
    });
    
    errorRate.add(!success);
}

function testAdminAPI() {
    if (!ADMIN_TOKEN) {
        return; // Skip if no admin token
    }
    
    const params = {
        headers: {
            'Authorization': `Bearer ${ADMIN_TOKEN}`,
        },
    };
    
    // Test various admin endpoints
    const endpoints = [
        '/admin-api.php/health',
        '/admin-api.php/list_agents',
        '/admin-api.php/job_stats',
    ];
    
    const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];
    const response = http.get(`${BASE_URL}${endpoint}`, params);
    
    const success = check(response, {
        'admin api status is 200': (r) => r.status === 200,
        'has data field': (r) => {
            try {
                return r.json('data') !== undefined;
            } catch {
                return false;
            }
        },
    });
    
    errorRate.add(!success);
}

// Teardown function
export function teardown(data) {
    console.log('========================================');
    console.log('Load Test Results');
    console.log('========================================');
    console.log(`Error Rate: ${(errorRate.rate * 100).toFixed(2)}%`);
    console.log('========================================');
}
