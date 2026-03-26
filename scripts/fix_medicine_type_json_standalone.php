<?php
// Standalone script to fix medicines.type to valid JSON arrays using PDO
// Usage: php scripts/fix_medicine_type_json_standalone.php

$db = new PDO('sqlite:database/localhost.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->query('SELECT id, type FROM medicines');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $type = $row['type'];
    // If already valid JSON array, skip
    $decoded = json_decode($type, true);
    if (is_array($decoded)) {
        continue;
    }
    $typeArr = [];
    if (is_string($type)) {
        // Try unserialize
        $unser = @unserialize($type);
        if ($unser !== false && is_array($unser)) {
            $typeArr = $unser;
        } elseif (strpos($type, ',') !== false) {
            $typeArr = array_map('trim', explode(',', $type));
        } elseif (!empty($type)) {
            $typeArr = [$type];
        }
    }
    $update = $db->prepare('UPDATE medicines SET type = :type WHERE id = :id');
    $update->execute([
        ':type' => json_encode($typeArr),
        ':id' => $row['id']
    ]);
}
echo "All medicine type fields fixed to valid JSON arrays.\n";
