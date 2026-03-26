<?php
// Fix all medicines.type fields to be valid JSON arrays
use Illuminate\Support\Facades\DB;

// Run with: php artisan tinker < scripts/fix_medicine_type_json.php

$all = DB::table('medicines')->select('id', 'type')->get();
foreach ($all as $row) {
    $type = $row->type;
    // If already valid JSON array, skip
    if (is_string($type) && json_decode($type) !== null && is_array(json_decode($type))) {
        continue;
    }
    // If type is a PHP serialized array or string, convert to JSON array
    $typeArr = [];
    if (is_string($type)) {
        // Try to unserialize PHP array string
        $unser = @unserialize($type);
        if ($unser !== false && is_array($unser)) {
            $typeArr = $unser;
        } elseif (strpos($type, ',') !== false) {
            // Split comma-separated string
            $typeArr = array_map('trim', explode(',', $type));
        } elseif (!empty($type)) {
            $typeArr = [$type];
        }
    }
    DB::table('medicines')->where('id', $row->id)->update([
        'type' => json_encode($typeArr)
    ]);
}
echo "All medicine type fields fixed to valid JSON arrays.\n";
