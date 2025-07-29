<?php

namespace FlexCMS\Services;

use Intervention\Image\ImageManagerStatic as Image;

class MediaService
{
    protected $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'],
        'audio' => ['mp3', 'wav', 'ogg', 'flac', 'm4a']
    ];

    protected $maxFileSize = 10485760; // 10MB
    protected $uploadPath = 'uploads';

    /**
     * Upload file
     */
    public function upload($file, $type = 'general', $options = [])
    {
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Create directory structure
        $dateFolder = date('Y/m');
        $uploadDir = public_path($this->uploadPath . '/' . $type . '/' . $dateFolder);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $this->generateUniqueFilename($file['name'], $uploadDir);
        $filepath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Failed to upload file'];
        }

        // Process image if needed
        if ($this->isImage($extension)) {
            $this->processImage($filepath, $options);
        }

        // Store file info in database
        $fileInfo = $this->storeFileInfo($filename, $type . '/' . $dateFolder, $file, $options);

        return [
            'success' => true,
            'file' => $fileInfo,
            'url' => asset($this->uploadPath . '/' . $type . '/' . $dateFolder . '/' . $filename)
        ];
    }

    /**
     * Validate uploaded file
     */
    protected function validateFile($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload error: ' . $file['error']];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File too large. Max size: ' . $this->formatBytes($this->maxFileSize)];
        }

        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!$this->isAllowedType($extension)) {
            return ['valid' => false, 'error' => 'File type not allowed: ' . $extension];
        }

        // Check MIME type for security
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!$this->isValidMimeType($mimeType, $extension)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }

        return ['valid' => true];
    }

    /**
     * Check if file type is allowed
     */
    protected function isAllowedType($extension)
    {
        foreach ($this->allowedTypes as $category => $extensions) {
            if (in_array($extension, $extensions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if MIME type is valid for extension
     */
    protected function isValidMimeType($mimeType, $extension)
    {
        $validMimes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'mp4' => ['video/mp4'],
            'mp3' => ['audio/mpeg']
        ];

        return isset($validMimes[$extension]) && in_array($mimeType, $validMimes[$extension]);
    }

    /**
     * Check if file is an image
     */
    protected function isImage($extension)
    {
        return in_array($extension, $this->allowedTypes['image']);
    }

    /**
     * Process uploaded image
     */
    protected function processImage($filepath, $options = [])
    {
        try {
            $image = Image::make($filepath);
            
            // Auto-orient image
            $image->orientate();
            
            // Optimize quality
            $quality = $options['quality'] ?? 85;
            
            // Resize if needed
            if (isset($options['max_width']) || isset($options['max_height'])) {
                $maxWidth = $options['max_width'] ?? null;
                $maxHeight = $options['max_height'] ?? null;
                
                $image->resize($maxWidth, $maxHeight, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }
            
            // Save optimized image
            $image->save($filepath, $quality);
            
            // Create thumbnails
            $this->createThumbnails($filepath, $image, $options);
            
        } catch (\Exception $e) {
            logger()->error('Image processing failed', ['file' => $filepath, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Create image thumbnails
     */
    protected function createThumbnails($originalPath, $image, $options = [])
    {
        $sizes = $options['thumbnail_sizes'] ?? [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300],
            'large' => ['width' => 1024, 'height' => 1024]
        ];

        $directory = dirname($originalPath);
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION);

        foreach ($sizes as $sizeName => $dimensions) {
            $thumbnailPath = $directory . '/' . $filename . '-' . $sizeName . '.' . $extension;
            
            $thumbnail = clone $image;
            $thumbnail->fit($dimensions['width'], $dimensions['height'], function ($constraint) {
                $constraint->upsize();
            });
            
            $thumbnail->save($thumbnailPath, 85);
        }
    }

    /**
     * Generate unique filename
     */
    protected function generateUniqueFilename($originalName, $directory)
    {
        $info = pathinfo($originalName);
        $name = $this->sanitizeFilename($info['filename']);
        $extension = strtolower($info['extension']);
        
        $filename = $name . '.' . $extension;
        $counter = 1;
        
        while (file_exists($directory . '/' . $filename)) {
            $filename = $name . '-' . $counter . '.' . $extension;
            $counter++;
        }
        
        return $filename;
    }

    /**
     * Sanitize filename
     */
    protected function sanitizeFilename($filename)
    {
        // Remove special characters and spaces
        $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        $filename = trim($filename, '-');
        
        return strtolower($filename);
    }

    /**
     * Store file information in database
     */
    protected function storeFileInfo($filename, $path, $fileData, $options = [])
    {
        // This would store in a media/files table
        $fileInfo = [
            'filename' => $filename,
            'original_name' => $fileData['name'],
            'path' => $path,
            'size' => $fileData['size'],
            'mime_type' => $fileData['type'],
            'uploaded_by' => user() ? user()->id : null,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'alt_text' => $options['alt_text'] ?? '',
            'title' => $options['title'] ?? '',
            'description' => $options['description'] ?? ''
        ];

        // Store in database (implement based on your database structure)
        // Media::create($fileInfo);

        return $fileInfo;
    }

    /**
     * Delete file and its thumbnails
     */
    public function deleteFile($filepath)
    {
        $fullPath = public_path($this->uploadPath . '/' . $filepath);
        
        if (file_exists($fullPath)) {
            unlink($fullPath);
            
            // Delete thumbnails
            $this->deleteThumbnails($fullPath);
            
            return true;
        }
        
        return false;
    }

    /**
     * Delete thumbnails
     */
    protected function deleteThumbnails($originalPath)
    {
        $directory = dirname($originalPath);
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
        
        $thumbnailSizes = ['thumbnail', 'medium', 'large'];
        
        foreach ($thumbnailSizes as $size) {
            $thumbnailPath = $directory . '/' . $filename . '-' . $size . '.' . $extension;
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }
    }

    /**
     * Get file list with pagination
     */
    public function getFiles($type = null, $page = 1, $perPage = 20)
    {
        // This would query from database
        // For now, return filesystem scan
        $uploadDir = public_path($this->uploadPath . ($type ? '/' . $type : ''));
        
        if (!is_dir($uploadDir)) {
            return ['files' => [], 'total' => 0];
        }
        
        $files = $this->scanDirectory($uploadDir, $type);
        $total = count($files);
        
        // Paginate
        $offset = ($page - 1) * $perPage;
        $files = array_slice($files, $offset, $perPage);
        
        return ['files' => $files, 'total' => $total];
    }

    /**
     * Scan directory for files
     */
    protected function scanDirectory($directory, $type = null)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                
                if ($type && !in_array($extension, $this->allowedTypes[$type] ?? [])) {
                    continue;
                }
                
                $relativePath = str_replace(public_path($this->uploadPath) . '/', '', $file->getPathname());
                
                $files[] = [
                    'filename' => $file->getFilename(),
                    'path' => $relativePath,
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    'type' => $this->getFileType($extension),
                    'url' => asset($this->uploadPath . '/' . $relativePath)
                ];
            }
        }
        
        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        
        return $files;
    }

    /**
     * Get file type category
     */
    protected function getFileType($extension)
    {
        foreach ($this->allowedTypes as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                return $type;
            }
        }
        return 'unknown';
    }

    /**
     * Format file size
     */
    protected function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Get image dimensions
     */
    public function getImageInfo($filepath)
    {
        $fullPath = public_path($this->uploadPath . '/' . $filepath);
        
        if (!file_exists($fullPath) || !$this->isImage(pathinfo($fullPath, PATHINFO_EXTENSION))) {
            return null;
        }
        
        $imageInfo = getimagesize($fullPath);
        
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime' => $imageInfo['mime'],
            'size' => filesize($fullPath),
            'url' => asset($this->uploadPath . '/' . $filepath)
        ];
    }

    /**
     * Optimize all images in directory
     */
    public function optimizeImages($directory = null)
    {
        $targetDir = $directory ? public_path($directory) : public_path($this->uploadPath);
        $optimized = 0;
        
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($targetDir));
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $this->isImage($file->getExtension())) {
                try {
                    $image = Image::make($file->getPathname());
                    $image->orientate();
                    $image->save($file->getPathname(), 85);
                    $optimized++;
                } catch (\Exception $e) {
                    logger()->error('Image optimization failed', ['file' => $file->getPathname()]);
                }
            }
        }
        
        return $optimized;
    }

    /**
     * Clean up unused files
     */
    public function cleanupUnusedFiles($dryRun = true)
    {
        // This would check database for unused files and optionally delete them
        $unusedFiles = [];
        
        // Implementation would depend on your database structure
        // You'd query for files not referenced in posts, pages, etc.
        
        if (!$dryRun) {
            foreach ($unusedFiles as $file) {
                $this->deleteFile($file);
            }
        }
        
        return $unusedFiles;
    }
}