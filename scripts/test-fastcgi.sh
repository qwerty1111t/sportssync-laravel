#!/bin/bash
# Test PHP-FPM and Laravel connectivity
# This script tests if a request can be successfully processed through the full stack

set -e

TEST_PORT=9000
TEST_HOST="127.0.0.1"
TEST_FILE="/var/www/html/public/health.php"
TEST_SCRIPT="/var/www/html/test_fastcgi.php"

echo "[FastCGI Test] Waiting 2 seconds for PHP-FPM to fully start..."
sleep 2

# Check if PHP-FPM is listening
echo "[FastCGI Test] Checking if PHP-FPM is listening on ${TEST_HOST}:${TEST_PORT}..."
if timeout 3 bash -c "cat < /dev/tcp/${TEST_HOST}/${TEST_PORT}" >/dev/null 2>&1; then
    echo "[FastCGI Test] ✓ PHP-FPM is listening on port ${TEST_PORT}"
else
    echo "[FastCGI Test] ✗ PHP-FPM NOT listening on port ${TEST_PORT}"
    exit 1
fi

# Create a test FastCGI request script
cat > "$TEST_SCRIPT" << 'PHPTESTEOF'
<?php
// Direct test without Laravel bootstrap
echo "PHP is working\n";
echo "Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n";
echo "Script filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";

// Try to bootstrap Laravel
$bootstrap_file = __DIR__ . '/../bootstrap/app.php';
if (file_exists($bootstrap_file)) {
    echo "Bootstrap file exists\n";
    try {
        require $bootstrap_file;
        echo "Bootstrap SUCCESS\n";
    } catch (Exception $e) {
        echo "Bootstrap FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "Bootstrap file NOT FOUND: $bootstrap_file\n";
}
PHPTESTEOF

echo "[FastCGI Test] Created test script at $TEST_SCRIPT"

# Create a minimal FastCGI request
echo "[FastCGI Test] Attempting FastCGI request to test PHP-FPM..."
exec 3<>/dev/tcp/${TEST_HOST}/${TEST_PORT}

# Send FastCGI protocol request for test_fastcgi.php
{
    printf "FCGI_BEGIN_REQUEST request_id=1 role=RESPONDER keep_conn=0\n"
    printf "FCGI_PARAMS request_id=1 "
    printf "REQUEST_METHOD=GET "
    printf "SCRIPT_FILENAME=$TEST_SCRIPT "
    printf "QUERY_STRING= "
    printf "SERVER_NAME=localhost "
    printf "SERVER_PORT=8000 "
    printf "SERVER_PROTOCOL=HTTP/1.1 "
    printf "CONTENT_LENGTH=0\n"
    printf "FCGI_STDIN request_id=1\n"
    sleep 1
} >&3

# Read response
timeout 5 cat <&3 | head -50

exec 3>&-

echo "[FastCGI Test] Test completed"
