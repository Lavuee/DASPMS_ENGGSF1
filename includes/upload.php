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

    if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Image upload failed.");
    }

    $allowedTypes = [
        'jpg' => IMAGETYPE_JPEG,
        'jpeg' => IMAGETYPE_JPEG,
        'png' => IMAGETYPE_PNG,
        'webp' => IMAGETYPE_WEBP
    ];

    $tmpName = $_FILES[$fileInputName]['tmp_name'];
    $extension = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
    $imageInfo = @getimagesize($tmpName);

    if (!isset($allowedTypes[$extension]) || !$imageInfo || $imageInfo[2] !== $allowedTypes[$extension]) {
        throw new Exception("Invalid image type. Only JPG, PNG, and WEBP are allowed.");
    }

    $newFileName = $prefix . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $uploadDir = rtrim($uploadDir, '/') . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
        throw new Exception("Unable to save uploaded image.");
    }

    return $newFileName;
}
