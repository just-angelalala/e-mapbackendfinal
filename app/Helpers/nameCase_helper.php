<?php 
function camelCaseToSnakeCase($array) {
    $result = [];
    foreach ($array as $key => $value) {
        $newKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
        if (is_array($value)) {
            $value = camelCaseToSnakeCase($value); // Recursive call for nested arrays
        }
        $result[$newKey] = $value;
    }
    return $result;
}
