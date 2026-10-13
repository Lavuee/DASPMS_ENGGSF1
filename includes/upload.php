<?php
/*
    Shared image upload handling for part and service images.
*/

/*
    Saves the uploaded image in $_FILES[$fileInputName] to $uploadDir with a random name
    and returns the new file name. Returns $existingImage when no file was uploaded.
    Validates both the extension and the actual file content.
*/
function uploadImage($fileInputName, $uploadDir, $prefix, $existingImage = '') {
    if (
        !isset($_FILES[$fileInputName]) ||
        $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return $existingImage;
    }

    return saveUploadedImage($_FILES[$fileInputName], $uploadDir, $prefix);
}

/*
    Multiple-file version for inputs named like "extra_images[]". Returns the new file names
    in upload order; empty slots are skipped. Throws before saving anything if a file is invalid
    or the count is over $maxFiles.
*/
function uploadImages($fileInputName, $uploadDir, $prefix, $maxFiles) {
    if (!isset($_FILES[$fileInputName]) || !is_array($_FILES[$fileInputName]['name'])) {
        return [];
    }

    $files = [];
    foreach ($_FILES[$fileInputName]['name'] as $index => $name) {
        if ($_FILES[$fileInputName]['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => $name,
            'tmp_name' => $_FILES[$fileInputName]['tmp_name'][$index],
            'error' => $_FILES[$fileInputName]['error'][$index],
        ];
    }

    if (count($files) > $maxFiles) {
        throw new Exception("You can add up to {$maxFiles} more photo" . ($maxFiles === 1 ? '' : 's') . " for this part.");
    }

    foreach ($files as $file) {
        validateUploadedImage($file);
    }

    return array_map(function ($file) use ($uploadDir, $prefix) {
        return saveUploadedImage($file, $uploadDir, $prefix);
    }, $files);
}

// Returns the file extension when the upload is a real JPG, PNG or WEBP image.
function validateUploadedImage(array $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Image upload failed.");
    }

    $allowedTypes = [
        'jpg' => IMAGETYPE_JPEG,
        'jpeg' => IMAGETYPE_JPEG,
        'png' => IMAGETYPE_PNG,
        'webp' => IMAGETYPE_WEBP
    ];

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $imageInfo = @getimagesize($file['tmp_name']);

    if (!isset($allowedTypes[$extension]) || !$imageInfo || $imageInfo[2] !== $allowedTypes[$extension]) {
        throw new Exception("Invalid image type. Only JPG, PNG, and WEBP are allowed.");
    }

    return $extension;
}

// Removes image files from $uploadDir; only plain file names are accepted, never paths.
function deleteImageFiles($uploadDir, array $files) {
    $uploadDir = rtrim($uploadDir, '/') . '/';

    foreach ($files as $file) {
        $name = basename((string) $file);

        if ($name !== '' && is_file($uploadDir . $name)) {
            @unlink($uploadDir . $name);
        }
    }
}

function saveUploadedImage(array $file, $uploadDir, $prefix) {
    $extension = validateUploadedImage($file);

    $newFileName = $prefix . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $uploadDir = rtrim($uploadDir, '/') . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
        throw new Exception("Unable to save uploaded image.");
    }

    return $newFileName;
}
