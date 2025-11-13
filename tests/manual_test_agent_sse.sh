#!/bin/bash
# Manual test script to simulate browser EventSource request to test_agent endpoint

echo "=== Manual Test: test_agent Endpoint with EventSource-like Request ==="
echo

# Set up
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR/.."

# Generate test credentials (for testing only, not for production)
ADMIN_API_KEY="${ADMIN_API_KEY:-}"
TEST_OPENAI_KEY="${TEST_OPENAI_KEY:-sk-test-fake-key-for-demo}"

if [ -z "$ADMIN_API_KEY" ]; then
    echo "⚠️  Set ADMIN_API_KEY environment variable to a valid super-admin API key before running."
    exit 1
fi

# Create .env for testing
cat > .env << EOF
OPENAI_API_KEY=$TEST_OPENAI_KEY
ADMIN_ENABLED=true
DATABASE_PATH=./data/chatbot.db
EOF

echo "Created test .env file"

# Start PHP server
echo "Starting PHP server on port 8088..."
php -S localhost:8088 -t . > /tmp/manual_test_server.log 2>&1 &
SERVER_PID=$!
sleep 3
echo "Server started with PID: $SERVER_PID"
echo

# Create test agent using the API
echo "Creating test agent..."
AGENT_RESPONSE=$(curl -s -X POST \
  -H "Authorization: Bearer $ADMIN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Manual Test Agent",
    "description": "Agent for manual testing",
    "api_type": "chat",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "system_prompt": "You are a helpful assistant.",
    "is_default": false
  }' \
  "http://localhost:8088/admin-api.php?action=create_agent")

echo "Agent creation response: $AGENT_RESPONSE"
AGENT_ID=$(echo "$AGENT_RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
echo "Created agent with ID: $AGENT_ID"
echo

if [ -z "$AGENT_ID" ]; then
    echo "❌ Failed to create agent!"
    kill $SERVER_PID
    rm .env
    exit 1
fi

# Test 1: EventSource-like GET request with Authorization header
echo "--- Test 1: EventSource-like GET with Authorization header ---"
echo "This simulates what happens when the browser's EventSource connects after login"
echo "URL: http://localhost:8088/admin-api.php?action=test_agent&id=$AGENT_ID"
echo

# Use curl with timeout to get first few events
timeout 5 curl -s -N \
  -H "Accept: text/event-stream" \
  -H "Cache-Control: no-cache" \
  -H "Authorization: Bearer $ADMIN_API_KEY" \
  "http://localhost:8088/admin-api.php?action=test_agent&id=$AGENT_ID" \
  | head -20

echo
echo "Result: If you see SSE events above (event: message), the fix is working!"
echo

# Test 2: Check HTTP status code directly
echo "--- Test 2: Check HTTP status code ---"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Authorization: Bearer $ADMIN_API_KEY" \
  "http://localhost:8088/admin-api.php?action=test_agent&id=$AGENT_ID")

echo "HTTP Status Code: $HTTP_CODE"
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ PASS: Got 200 OK (instead of 403 Forbidden)"
else
    echo "❌ FAIL: Got $HTTP_CODE (expected 200)"
fi
echo

# Clean up
echo "Cleaning up..."
curl -s -X DELETE \
  -H "Authorization: Bearer $ADMIN_API_KEY" \
  "http://localhost:8088/admin-api.php?action=delete_agent&id=$AGENT_ID" > /dev/null

kill $SERVER_PID
rm .env
echo "Stopped server and cleaned up"
echo

if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Manual test PASSED - test_agent endpoint works with admin API key authorization"
    exit 0
else
    echo "❌ Manual test FAILED"
    exit 1
fi
