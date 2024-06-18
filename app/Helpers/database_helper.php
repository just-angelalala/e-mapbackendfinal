<?php

if (!function_exists('insertData')) {
    function insertData($model, $data, $errorMessage, $transactionModel, $isBatch = false, $returnId = false)
    {
        // Check if data is empty
        if (empty($data)) {
            return ['error' => true, 'message' => 'No data provided to insert.'];
        }

        // Check for validation errors before inserting
        if (!$model->validate($data)) {
            $transactionModel->transRollback();
            $validationErrors = $model->errors();
            $errorMsg = is_array($validationErrors) ? implode("; ", $validationErrors) : $validationErrors;
            return ['error' => true, 'message' => $errorMsg];
        }

        $insertMethod = $isBatch ? 'insertBatch' : 'insert';
        if (!$model->$insertMethod($data)) {
            $transactionModel->transRollback();
            // Use a generic error message if a specific one is not provided
            $error = $errorMessage ?: 'Error occurred during the insert operation.';
            return ['error' => true, 'message' => $error];
        }

        // If returnId is true, fetch and return the ID of the newly inserted record
        if ($returnId) {
            $insertedId = $model->getInsertID();
            return ['error' => false, 'id' => $insertedId];
        }

        return ['error' => false];
    }
}

if (!function_exists('updateData')) {
    /**
     * Updates data in the database using the provided model.
     * 
     * @param object $model The model used for the update.
     * @param mixed $condition Condition for updating, can be an array specifying the WHERE clause.
     * @param array $data The data to update in the database.
     * @param string $errorMessage Custom error message for update failure.
     * @param object $transactionModel Model on which transaction control should be applied.
     * @param bool $skipValidation Optional. Whether to skip data validation. Defaults to false.
     * @return array Result of the update operation.
     */
    function updateData($model, $condition, $data, $errorMessage, $transactionModel, $skipValidation = false)
    {
        // Check if data is empty
        if (empty($data)) {
            return ['error' => true, 'message' => 'No data provided to update.'];
        }

        // Check if condition is empty
        if (empty($condition)) {
            return ['error' => true, 'message' => 'No condition provided for update.'];
        }

        // Validate data if validation is not skipped
        if (!$skipValidation) {
            if (!$model->validate($data)) {
                $transactionModel->transRollback();
                $validationErrors = $model->errors();
                $errorMsg = is_array($validationErrors) ? implode("; ", $validationErrors) : $validationErrors;
                return ['error' => true, 'message' => $errorMsg];
            }
        }

        // Attempt to perform the update
        $updateResult = $model->where($condition)->set($data)->update();

        if (!$updateResult) {
            $transactionModel->transRollback();
            // Use a generic error message if a specific one is not provided
            $error = $errorMessage ?: 'Error occurred during the update operation.';
            return ['error' => true, 'message' => $error];
        }

        return ['error' => false];
    }
}



if (!function_exists('deleteData')) {
    /**
     * Deletes data from the database using the provided model.
     * 
     * @param object $model The model used for the deletion.
     * @param mixed $condition Condition for deletion, can be an array or a single value for primary key deletion.
     * @param string $errorMessage Custom error message for deletion failure.
     * @param object $transactionModel Model on which transaction control should be applied.
     * @return array Result of the deletion operation.
     */
    function deleteData($model, $condition, $errorMessage, $transactionModel)
    {
        if (empty($condition)) {
            return ['error' => true, 'message' => 'No condition provided for deletion.'];
        }

        // Attempt to perform the deletion
        $deleteResult = is_array($condition) ? 
            $model->where($condition)->delete() : 
            $model->delete($condition);

        if (!$deleteResult) {
            $transactionModel->transRollback();
            // Use a generic error message if a specific one is not provided
            $error = $errorMessage ?: 'Error occurred during the delete operation.';
            return ['error' => true, 'message' => $error];
        }

        return ['error' => false];
    }
}

function fetchData($model, $conditions = [], $options = []) {
    // Start building the query using the model's defined table
    $builder = $model->db->table($model->table);

    // Apply conditions if provided
    if (!empty($conditions)) {
        $builder->where($conditions);
    }

    // Apply joins if provided
    if (isset($options['joins']) && is_array($options['joins'])) {
        foreach ($options['joins'] as $join) {
            if (isset($join['table']) && isset($join['condition'])) {
                $type = $join['type'] ?? 'inner'; // Default join type to inner if not specified
                $builder->join($join['table'], $join['condition'], $type);
            }
        }
    }

    // Apply group by if provided
    if (isset($options['groupBy'])) {
        $builder->groupBy($options['groupBy']);
    }

    // Apply additional options like limit and order by if provided
    if (isset($options['limit'])) {
        $builder->limit($options['limit']);
    }
    
    if (isset($options['orderBy'])) {
        $builder->orderBy($options['orderBy']);
    }

    // Execute the query and return the result as an associative array
    $query = $builder->get();
    return $query->getResultArray();
}


