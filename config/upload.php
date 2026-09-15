<?php
declare(strict_types=1);

/**
 * Validates and stores an uploaded image file.
 *
 * @return string|null Relative path (e.g. "uploads/photos/xxxx.jpg") on success,
 *                      or null if no file was submitted for this field.
 * @throws RuntimeException with a user-facing message when validation fails.
 */
function store_uploaded_image(string $fieldName, string $subdir, int $maxBytes = 3145728): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The file could not be uploaded. Please try again.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The file could not be uploaded. Please try again.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('The image is too large. Maximum size is ' . round($maxBytes / 1024 / 1024, 1) . 'MB.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($imageInfo === false || !isset($allowed[$imageInfo['mime']])) {
        throw new RuntimeException('Please upload a valid JPG, PNG, or WEBP image.');
    }

    $extension = $allowed[$imageInfo['mime']];
    $uploadDir = __DIR__ . '/../assets/uploads/' . $subdir . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('The file could not be saved. Please try again.');
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new RuntimeException('The file could not be saved. Please try again.');
    }

    return 'uploads/' . $subdir . '/' . $filename;
}

function delete_uploaded_file(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $fullPath = __DIR__ . '/../assets/' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
