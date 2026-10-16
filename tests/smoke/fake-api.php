<?php
// Fake mypclist.net API v1. Mode is read from /tmp/fake-api-mode.
$mode = is_file('/tmp/fake-api-mode') ? trim(file_get_contents('/tmp/fake-api-mode')) : 'ok';
file_put_contents('/tmp/fake-api-log', json_encode($_POST) . "\n", FILE_APPEND);
header('Content-Type: application/json');
$params = isset($_POST['params']) ? $_POST['params'] : array();
$total = 120;
switch ($mode) {
    case 'null': echo json_encode(array('status' => 'success', 'response' => null)); exit;
    case 'empty': echo json_encode(array('status' => 'success', 'response' => array('total' => 5, 'length' => 0, 'items' => array()))); exit;
    case 'error': echo json_encode(array('status' => 'error', 'message' => 'API key not authorized', 'error_id' => -1)); exit;
    case 'html': echo '<html>oops</html>'; exit;
}
if ('k' !== ($_POST['api_key'] ?? '') || 't' !== ($_POST['api_token'] ?? '')) {
    echo json_encode(array('status' => 'error', 'message' => 'API key not authorized', 'error_id' => -1)); exit;
}
$limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
$offset = max(0, (int) ($params['offset'] ?? 0));
$items = array();
for ($id = $total - $offset; $id > max(0, $total - $offset - $limit); $id--) {
    $items[] = array('id' => $id, 'uid' => 1, 'created' => 1000 + $id, 'modified' => 2000 + $id, 'slug' => "m$id", 'is_visible' => true,
        'description' => "[b]Machine {$id}[/b]\nZażółć 💾", 'physical_state' => $id % 6, 'custom_name' => null, 'name' => "Machine $id 💾",
        'manufacturer' => 'Maker', 'manufacturer_id' => null, 'year_of_production' => '1990', 'type_id' => null, 'serial_number' => null,
        'price' => null, 'photos' => array(array('id' => $id, 'slug' => "p$id", 'orig_name' => 'a.jpg', 'extension' => 'jpg',
        'orig_uri' => "https://img.example/$id.jpg", 'thumbnails' => array(100 => "https://img.example/$id-100.jpg", 300 => "https://img.example/$id-300.jpg"))),
        'is_extension' => false, 'is_standalone' => false, 'type_name' => 'Computer', 'is_forsale' => false);
    // Even ids: Markdown with server-rendered HTML; odd ids: BBCode, whose HTML the plugin must ignore.
    // Mode "legacy" mimics a server without these fields.
    if ('legacy' !== $mode) {
        $last = count($items) - 1;
        if (0 === $id % 2) {
            $items[$last]['description'] = "**Markdown {$id}**\n\n- item";
            $items[$last]['description_format'] = 'markdown';
            $items[$last]['description_html'] = "<p><strong>Markdown {$id}</strong></p><ul><li>item</li></ul><script>alert(1)</script>";
        } else {
            $items[$last]['description_format'] = 'bbcode';
            $items[$last]['description_html'] = '<p>SERVER-RENDERED</p>';
        }
    }
}
echo json_encode(array('status' => 'success', 'time' => time(), 'response' => array('total' => $total, 'length' => count($items), 'items' => $items), 'params' => null));
