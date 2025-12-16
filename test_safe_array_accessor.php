<?php
/**
 * Test SafeArrayAccessor functionality
 */

define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/includes/Core/SafeArrayAccessor.php';

use SMO_Social\Core\SafeArray;

echo "Testing SafeArrayAccessor...\n\n";

// Test 1: Basic array access
echo "Test 1: Basic array access\n";
$data = ['name' => 'John', 'age' => 30];
$name = SafeArray::get_string($data, 'name', 'Unknown');
$age = SafeArray::get_int($data, 'age', 0);
$missing = SafeArray::get_string($data, 'missing', 'Default');
echo "Name: $name (Expected: John)\n";
echo "Age: $age (Expected: 30)\n";
echo "Missing: $missing (Expected: Default)\n";
echo ($name === 'John' && $age === 30 && $missing === 'Default') ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 2: Dot notation for nested arrays
echo "Test 2: Dot notation for nested arrays\n";
$data = [
    'user' => [
        'profile' => [
            'name' => 'Jane',
            'email' => 'jane@example.com'
        ]
    ]
];
$profile_name = SafeArray::get($data, 'user.profile.name');
$profile_email = SafeArray::get($data, 'user.profile.email');
$missing_nested = SafeArray::get($data, 'user.profile.phone', 'Not found');
echo "Profile Name: $profile_name (Expected: Jane)\n";
echo "Profile Email: $profile_email (Expected: jane@example.com)\n";
echo "Missing Nested: $missing_nested (Expected: Not found)\n";
echo ($profile_name === 'Jane' && $profile_email === 'jane@example.com' && $missing_nested === 'Not found') ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 3: JSON decoding with null safety
echo "Test 3: JSON decoding with null safety\n";
$valid_json = '{"status": "success", "data": {"id": 123}}';
$invalid_json = 'invalid json';
$null_json = null;

$decoded_valid = SafeArray::json_decode($valid_json, true, []);
$decoded_invalid = SafeArray::json_decode($invalid_json, true, ['error' => true]);
$decoded_null = SafeArray::json_decode($null_json, true, []);

$valid_status = SafeArray::get_string($decoded_valid, 'status');
$valid_id = SafeArray::get($decoded_valid, 'data.id');
$has_error = SafeArray::get_bool($decoded_invalid, 'error');

echo "Valid JSON status: $valid_status (Expected: success)\n";
echo "Valid JSON data.id: $valid_id (Expected: 123)\n";
echo "Invalid JSON has error: " . ($has_error ? 'true' : 'false') . " (Expected: true)\n";
echo "Null JSON is array: " . (is_array($decoded_null) ? 'true' : 'false') . " (Expected: true)\n";
echo ($valid_status === 'success' && $valid_id == 123 && $has_error === true && is_array($decoded_null)) ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 4: Type casting
echo "Test 4: Type casting\n";
$data = [
    'string_num' => '42',
    'bool_string' => 'true',
    'bool_int' => 1,
    'array_val' => ['item1', 'item2']
];
$int_from_string = SafeArray::get_int($data, 'string_num', 0);
$bool_from_string = SafeArray::get_bool($data, 'bool_string', false);
$bool_from_int = SafeArray::get_bool($data, 'bool_int', false);
$array_val = SafeArray::get_array($data, 'array_val', []);

echo "Int from string: $int_from_string (Expected: 42)\n";
echo "Bool from string: " . ($bool_from_string ? 'true' : 'false') . " (Expected: true)\n";
echo "Bool from int: " . ($bool_from_int ? 'true' : 'false') . " (Expected: true)\n";
echo "Array count: " . count($array_val) . " (Expected: 2)\n";
echo ($int_from_string === 42 && $bool_from_string === true && $bool_from_int === true && count($array_val) === 2) ? "✓ PASS\n\n" : "✗ FAIL\n\n";

// Test 5: Real-world scenario - simulating API response with missing fields
echo "Test 5: Real-world API response scenario\n";
$api_response = [
    'list' => [
        '12345' => [
            'resolved_title' => 'Article Title',
            'excerpt' => 'This is an excerpt',
            'top_image_url' => 'https://example.com/image.jpg'
            // Note: 'image' nested array is missing
        ]
    ]
];

$items = SafeArray::get_array($api_response, 'list', []);
$item = SafeArray::get_array($items, '12345', []);
$title = SafeArray::get_string($item, 'resolved_title', 'Untitled');
$image_url = SafeArray::get_string($item, 'top_image_url');
$nested_image = SafeArray::get($item, 'image.src', ''); // Safely access missing nested array
$time_added = SafeArray::get_int($item, 'time_added', time());

echo "Title: $title (Expected: Article Title)\n";
echo "Image URL: $image_url (Expected: https://example.com/image.jpg)\n";
echo "Nested image (missing): " . ($nested_image === '' ? 'empty' : $nested_image) . " (Expected: empty)\n";
echo "Time added has default: " . ($time_added > 0 ? 'yes' : 'no') . " (Expected: yes)\n";
echo ($title === 'Article Title' && !empty($image_url) && $nested_image === '' && $time_added > 0) ? "✓ PASS\n\n" : "✗ FAIL\n\n";

echo "All tests completed!\n";
