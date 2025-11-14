<?php
/**
 * Simple PHP script to log all HTTP request headers to stdout
 */

// Get all request headers
$headers = getallheaders();

// Log timestamp
error_log("[" . date('Y-m-d H:i:s') . "] Incoming Request");
error_log(str_repeat('-', 50) . "");

// Log all headers
foreach ($headers as $name => $value) {
    error_log("$name: $value");
}

error_log(str_repeat('-', 50) . "\n");

// Optional: Send a simple response back to the client
http_response_code(418);
header('Content-Type: text/plain');
echo "G'day, I'm just a teapot.\n";
?>
