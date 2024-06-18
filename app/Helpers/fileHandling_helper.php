<?php

function processUpload($fileKey, $path) {
    $file = service('request')->getFile($fileKey);
    if ($file->isValid() && !$file->hasMoved()) {
        $file->move(WRITEPATH . $path);
        return $file->getName();
    }
    return false;
}

function uploadGcashReceipt($fileKey, $filePath, $maxSize = 10240, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf']) {
    $path = "uploads/{$filePath}/";
    $targetPath = rtrim(FCPATH, '/') . '/' . ltrim($path, '/');

    // Ensure the target directory exists
    if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
        throw new \Exception('Failed to create target directory.');
    }

    $files = service('request')->getFiles();

    // Check if the file is uploaded with the correct key
    if (!isset($files[$fileKey]) || !is_array($files[$fileKey])) {
        throw new \Exception('No files were uploaded or the file key is incorrect.');
    }

    $fileNames = [];

    foreach ($files[$fileKey] as $file) {
        // Validate the file
        if (!$file->isValid()) {
            throw new \Exception('File is not valid: ' . $file->getErrorString());
        }

        // Ensure the file has not been moved yet
        if ($file->hasMoved()) {
            throw new \Exception('File has already been moved');
        }

        // Check file size
        if ($file->getSizeByUnit('kb') > $maxSize) {
            throw new \Exception('File exceeds the maximum size limit');
        }

        // Validate the file type
        if (!in_array(strtolower($file->getExtension()), $allowedTypes)) {
            throw new \Exception('File type not allowed');
        }

        // Move the file to the target directory
        $newFilename = $file->getRandomName();
        if (!$file->move($targetPath, $newFilename)) {
            throw new \Exception('Failed to move the file');
        }

        $fileNames[] = $newFilename;
    }

    return ['success' => true, 'fileNames' => $fileNames];
}

