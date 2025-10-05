<?php
// File: CMS/includes/UploadHandler.php

class UploadHandler
{
    /** @var string */
    private $uploadsDir;

    /** @var string */
    private $uploadsRoot;

    /** @var string */
    private $baseDir;

    /** @var string */
    private $relativePrefix;

    /** @var callable */
    private $isUploadedFileCallback;

    /** @var callable */
    private $moveUploadedFileCallback;

    public function __construct(
        string $uploadsDir,
        string $baseDir,
        ?callable $isUploadedFileCallback = null,
        ?callable $moveUploadedFileCallback = null
    ) {
        $this->uploadsDir = rtrim($uploadsDir, DIRECTORY_SEPARATOR);
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);

        if ($this->uploadsDir === '' || $this->baseDir === '') {
            throw new RuntimeException('Invalid upload configuration.');
        }

        if (!is_dir($this->uploadsDir) && !@mkdir($this->uploadsDir, 0777, true) && !is_dir($this->uploadsDir)) {
            throw new RuntimeException('Unable to create uploads directory.');
        }

        $uploadsRoot = realpath($this->uploadsDir);
        $baseReal = realpath($this->baseDir);
        if ($uploadsRoot === false || $baseReal === false) {
            throw new RuntimeException('Unable to resolve upload paths.');
        }

        $this->uploadsRoot = $uploadsRoot;
        $this->baseDir = $baseReal;
        $relative = str_replace($this->baseDir . DIRECTORY_SEPARATOR, '', $this->uploadsRoot . DIRECTORY_SEPARATOR);
        $relative = trim(str_replace('\\', '/', $relative), '/');
        $this->relativePrefix = $relative !== '' ? $relative : basename($this->uploadsRoot);
        $this->isUploadedFileCallback = $isUploadedFileCallback ?: static function ($path) {
            return is_uploaded_file($path);
        };
        $this->moveUploadedFileCallback = $moveUploadedFileCallback ?: static function ($from, $to) {
            return move_uploaded_file($from, $to);
        };
    }

    public function validateImageUpload(array $file, array $allowedTypes, int $maxSize, string $fieldLabel): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            return;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(sprintf('%s upload failed. Please try again.', $fieldLabel));
        }

        if (!call_user_func($this->isUploadedFileCallback, $file['tmp_name'])) {
            throw new InvalidArgumentException(sprintf('%s upload failed validation.', $fieldLabel));
        }

        if (isset($file['size']) && $file['size'] > $maxSize) {
            throw new InvalidArgumentException(
                sprintf('%s must be smaller than %d MB.', $fieldLabel, (int) ($maxSize / (1024 * 1024)))
            );
        }

        $mime = null;
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
        }

        if (!$mime && function_exists('getimagesize')) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo && isset($imageInfo['mime'])) {
                $mime = $imageInfo['mime'];
            }
        }

        if (!$mime || !in_array($mime, $allowedTypes, true)) {
            throw new InvalidArgumentException(sprintf('%s must be a valid image file.', $fieldLabel));
        }
    }

    public function storeUploadedFile(array $file, string $prefix, array $allowedExtensions = [], ?string $fallbackExtension = null): ?string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(sprintf('Unable to save %s upload.', strtolower($prefix)));
        }

        if (!call_user_func($this->isUploadedFileCallback, $file['tmp_name'])) {
            throw new RuntimeException(sprintf('%s upload failed integrity checks.', ucfirst($prefix)));
        }

        $name = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions, true)) {
            $extension = $fallbackExtension ?? $allowedExtensions[0];
        }

        $filename = $prefix . '_' . uniqid('', true);
        if ($extension !== '') {
            $filename .= '.' . $extension;
        }

        $destination = $this->uploadsRoot . DIRECTORY_SEPARATOR . $filename;
        if (!call_user_func($this->moveUploadedFileCallback, $file['tmp_name'], $destination)) {
            throw new RuntimeException(sprintf('Unable to move uploaded %s.', strtolower($prefix)));
        }

        return $this->relativePrefix . '/' . $filename;
    }

    public function deleteUploadFile($relativePath): void
    {
        if (!is_string($relativePath) || $relativePath === '') {
            return;
        }

        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === '') {
            return;
        }

        $prefix = $this->relativePrefix;
        if ($prefix !== '' && strpos($normalized, $prefix . '/') !== 0 && $normalized !== $prefix) {
            return;
        }

        $fullPath = $this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $realFullPath = realpath($fullPath);
        if ($realFullPath === false) {
            return;
        }

        if (strpos($realFullPath, $this->uploadsRoot) === 0 && is_file($realFullPath)) {
            @unlink($realFullPath);
        }
    }

}
