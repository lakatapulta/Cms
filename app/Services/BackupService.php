<?php

namespace FlexCMS\Services;

use Exception;
use ZipArchive;

class BackupService
{
    protected $backupPath;
    protected $tempPath;
    protected $excludeDirectories = ['vendor', 'node_modules', '.git', 'storage/cache', 'storage/logs'];
    protected $maxBackups = 10;

    public function __construct()
    {
        $this->backupPath = storage_path('backups');
        $this->tempPath = storage_path('temp');
        $this->ensureDirectories();
    }

    /**
     * Create full backup
     */
    public function createFullBackup($options = [])
    {
        try {
            $backupName = 'full_backup_' . date('Y-m-d_H-i-s');
            $backupDir = $this->tempPath . '/' . $backupName;
            
            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception('Failed to create backup directory');
            }

            $result = [
                'name' => $backupName,
                'type' => 'full',
                'started_at' => date('Y-m-d H:i:s'),
                'status' => 'running',
                'steps' => []
            ];

            // Step 1: Backup database
            $result['steps']['database'] = $this->backupDatabase($backupDir);
            
            // Step 2: Backup files
            $result['steps']['files'] = $this->backupFiles($backupDir);
            
            // Step 3: Backup configuration
            $result['steps']['config'] = $this->backupConfiguration($backupDir);
            
            // Step 4: Create backup info file
            $this->createBackupInfo($backupDir, $result);
            
            // Step 5: Compress backup
            $zipFile = $this->compressBackup($backupDir, $backupName);
            
            // Step 6: Store backup
            $finalPath = $this->storeBackup($zipFile, $backupName);
            
            // Step 7: Cleanup temp files
            $this->cleanupTemp($backupDir);
            
            // Step 8: Manage backup retention
            $this->manageBackupRetention();

            $result['status'] = 'completed';
            $result['completed_at'] = date('Y-m-d H:i:s');
            $result['file_path'] = $finalPath;
            $result['file_size'] = filesize($finalPath);

            logger()->info('Full backup completed', ['backup' => $backupName, 'size' => $result['file_size']]);

            return ['success' => true, 'backup' => $result];

        } catch (Exception $e) {
            logger()->error('Backup failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create incremental backup
     */
    public function createIncrementalBackup($lastBackupDate = null)
    {
        try {
            $lastBackupDate = $lastBackupDate ?: $this->getLastBackupDate();
            $backupName = 'incremental_backup_' . date('Y-m-d_H-i-s');
            $backupDir = $this->tempPath . '/' . $backupName;
            
            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception('Failed to create backup directory');
            }

            $result = [
                'name' => $backupName,
                'type' => 'incremental',
                'since' => $lastBackupDate,
                'started_at' => date('Y-m-d H:i:s'),
                'status' => 'running',
                'steps' => []
            ];

            // Backup only changed files since last backup
            $result['steps']['files'] = $this->backupChangedFiles($backupDir, $lastBackupDate);
            
            // Always backup database (it's usually small)
            $result['steps']['database'] = $this->backupDatabase($backupDir);
            
            // Backup configuration if changed
            $result['steps']['config'] = $this->backupConfigurationIfChanged($backupDir, $lastBackupDate);
            
            $this->createBackupInfo($backupDir, $result);
            $zipFile = $this->compressBackup($backupDir, $backupName);
            $finalPath = $this->storeBackup($zipFile, $backupName);
            $this->cleanupTemp($backupDir);

            $result['status'] = 'completed';
            $result['completed_at'] = date('Y-m-d H:i:s');
            $result['file_path'] = $finalPath;
            $result['file_size'] = filesize($finalPath);

            logger()->info('Incremental backup completed', ['backup' => $backupName]);

            return ['success' => true, 'backup' => $result];

        } catch (Exception $e) {
            logger()->error('Incremental backup failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Backup database
     */
    protected function backupDatabase($backupDir)
    {
        $dbHost = config('database.host', 'localhost');
        $dbName = config('database.database');
        $dbUser = config('database.username');
        $dbPass = config('database.password');
        $dbPort = config('database.port', 3306);

        $sqlFile = $backupDir . '/database.sql';
        
        // Use mysqldump if available
        if ($this->commandExists('mysqldump')) {
            $command = sprintf(
                'mysqldump -h%s -P%s -u%s -p%s %s > %s 2>&1',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($sqlFile)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($sqlFile)) {
                return [
                    'status' => 'success',
                    'method' => 'mysqldump',
                    'file' => 'database.sql',
                    'size' => filesize($sqlFile)
                ];
            }
        }

        // Fallback to PHP export
        return $this->backupDatabasePHP($sqlFile);
    }

    /**
     * Backup database using PHP
     */
    protected function backupDatabasePHP($sqlFile)
    {
        try {
            $pdo = app('database')->getConnection()->getPdo();
            $sql = '';

            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // Table structure
                $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_ASSOC);
                $sql .= "\n\n-- Table structure for `$table`\n";
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $createTable['Create Table'] . ";\n";

                // Table data
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $sql .= "\n-- Data for `$table`\n";
                    foreach ($rows as $row) {
                        $values = array_map(function($value) use ($pdo) {
                            return $value === null ? 'NULL' : $pdo->quote($value);
                        }, $row);
                        
                        $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                    }
                }
            }

            file_put_contents($sqlFile, $sql);

            return [
                'status' => 'success',
                'method' => 'php',
                'file' => 'database.sql',
                'size' => filesize($sqlFile)
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'method' => 'php',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Backup files
     */
    protected function backupFiles($backupDir)
    {
        $filesDir = $backupDir . '/files';
        mkdir($filesDir, 0755, true);

        $rootPath = ROOT_PATH;
        $copiedFiles = 0;
        $totalSize = 0;

        $this->copyDirectoryRecursive($rootPath, $filesDir, $copiedFiles, $totalSize);

        return [
            'status' => 'success',
            'files_count' => $copiedFiles,
            'total_size' => $totalSize,
            'directory' => 'files'
        ];
    }

    /**
     * Backup changed files since date
     */
    protected function backupChangedFiles($backupDir, $sinceDate)
    {
        $filesDir = $backupDir . '/files';
        mkdir($filesDir, 0755, true);

        $rootPath = ROOT_PATH;
        $copiedFiles = 0;
        $totalSize = 0;
        $sinceTimestamp = strtotime($sinceDate);

        $this->copyChangedFilesRecursive($rootPath, $filesDir, $sinceTimestamp, $copiedFiles, $totalSize);

        return [
            'status' => 'success',
            'files_count' => $copiedFiles,
            'total_size' => $totalSize,
            'since' => $sinceDate,
            'directory' => 'files'
        ];
    }

    /**
     * Copy directory recursively
     */
    protected function copyDirectoryRecursive($source, $destination, &$fileCount, &$totalSize)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace($source . '/', '', $item->getPathname());
            
            // Skip excluded directories
            if ($this->shouldExclude($relativePath)) {
                continue;
            }

            $targetPath = $destination . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                if (copy($item->getPathname(), $targetPath)) {
                    $fileCount++;
                    $totalSize += $item->getSize();
                }
            }
        }
    }

    /**
     * Copy only changed files since timestamp
     */
    protected function copyChangedFilesRecursive($source, $destination, $sinceTimestamp, &$fileCount, &$totalSize)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace($source . '/', '', $item->getPathname());
            
            if ($this->shouldExclude($relativePath)) {
                continue;
            }

            // Only include files modified since the timestamp
            if ($item->isFile() && $item->getMTime() <= $sinceTimestamp) {
                continue;
            }

            $targetPath = $destination . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                if (copy($item->getPathname(), $targetPath)) {
                    $fileCount++;
                    $totalSize += $item->getSize();
                }
            }
        }
    }

    /**
     * Backup configuration
     */
    protected function backupConfiguration($backupDir)
    {
        $configDir = $backupDir . '/config';
        mkdir($configDir, 0755, true);

        $configFiles = [
            '.env',
            'composer.json',
            'composer.lock'
        ];

        $copiedFiles = 0;
        foreach ($configFiles as $file) {
            $sourcePath = ROOT_PATH . '/' . $file;
            $targetPath = $configDir . '/' . $file;
            
            if (file_exists($sourcePath)) {
                if (copy($sourcePath, $targetPath)) {
                    $copiedFiles++;
                }
            }
        }

        // Backup active modules and themes info
        $systemInfo = [
            'active_theme' => config('theme.active', 'default'),
            'active_modules' => $this->getActiveModules(),
            'php_version' => phpversion(),
            'flexcms_version' => config('app.version', '1.0.0'),
            'backup_date' => date('Y-m-d H:i:s'),
            'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ];

        file_put_contents($configDir . '/system_info.json', json_encode($systemInfo, JSON_PRETTY_PRINT));

        return [
            'status' => 'success',
            'files_count' => $copiedFiles + 1,
            'directory' => 'config'
        ];
    }

    /**
     * Backup configuration if changed
     */
    protected function backupConfigurationIfChanged($backupDir, $sinceDate)
    {
        $sinceTimestamp = strtotime($sinceDate);
        $shouldBackup = false;

        $configFiles = ['.env', 'composer.json', 'composer.lock'];
        
        foreach ($configFiles as $file) {
            $filePath = ROOT_PATH . '/' . $file;
            if (file_exists($filePath) && filemtime($filePath) > $sinceTimestamp) {
                $shouldBackup = true;
                break;
            }
        }

        if ($shouldBackup) {
            return $this->backupConfiguration($backupDir);
        }

        return [
            'status' => 'skipped',
            'reason' => 'No configuration changes since ' . $sinceDate
        ];
    }

    /**
     * Get active modules
     */
    protected function getActiveModules()
    {
        // This would integrate with the module manager
        $moduleManager = app('modules');
        return $moduleManager->getActiveModules();
    }

    /**
     * Create backup info file
     */
    protected function createBackupInfo($backupDir, $backupData)
    {
        $infoFile = $backupDir . '/backup_info.json';
        file_put_contents($infoFile, json_encode($backupData, JSON_PRETTY_PRINT));
    }

    /**
     * Compress backup directory
     */
    protected function compressBackup($backupDir, $backupName)
    {
        $zipFile = $this->tempPath . '/' . $backupName . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Failed to create zip file');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = str_replace($backupDir . '/', '', $file->getPathname());
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        $zip->close();
        return $zipFile;
    }

    /**
     * Store backup in final location
     */
    protected function storeBackup($zipFile, $backupName)
    {
        $finalPath = $this->backupPath . '/' . $backupName . '.zip';
        
        if (!move($zipFile, $finalPath)) {
            throw new Exception('Failed to move backup to final location');
        }

        return $finalPath;
    }

    /**
     * Restore from backup
     */
    public function restoreFromBackup($backupPath, $options = [])
    {
        try {
            if (!file_exists($backupPath)) {
                throw new Exception('Backup file not found');
            }

            $restoreName = 'restore_' . date('Y-m-d_H-i-s');
            $extractDir = $this->tempPath . '/' . $restoreName;
            
            // Extract backup
            $zip = new ZipArchive();
            if ($zip->open($backupPath) !== TRUE) {
                throw new Exception('Failed to open backup file');
            }

            $zip->extractTo($extractDir);
            $zip->close();

            $result = [
                'name' => $restoreName,
                'started_at' => date('Y-m-d H:i:s'),
                'steps' => []
            ];

            // Read backup info
            $backupInfo = $this->getBackupInfo($extractDir);
            
            // Restore database
            if (isset($options['restore_database']) && $options['restore_database']) {
                $result['steps']['database'] = $this->restoreDatabase($extractDir);
            }

            // Restore files
            if (isset($options['restore_files']) && $options['restore_files']) {
                $result['steps']['files'] = $this->restoreFiles($extractDir);
            }

            // Restore configuration
            if (isset($options['restore_config']) && $options['restore_config']) {
                $result['steps']['config'] = $this->restoreConfiguration($extractDir);
            }

            $this->cleanupTemp($extractDir);

            $result['status'] = 'completed';
            $result['completed_at'] = date('Y-m-d H:i:s');

            logger()->info('Restore completed', ['restore' => $restoreName]);

            return ['success' => true, 'restore' => $result];

        } catch (Exception $e) {
            logger()->error('Restore failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Restore database
     */
    protected function restoreDatabase($extractDir)
    {
        $sqlFile = $extractDir . '/database.sql';
        
        if (!file_exists($sqlFile)) {
            return ['status' => 'skipped', 'reason' => 'No database backup found'];
        }

        try {
            $pdo = app('database')->getConnection()->getPdo();
            $sql = file_get_contents($sqlFile);
            
            // Execute SQL statements
            $pdo->exec($sql);

            return [
                'status' => 'success',
                'file' => 'database.sql',
                'size' => filesize($sqlFile)
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restore files
     */
    protected function restoreFiles($extractDir)
    {
        $filesDir = $extractDir . '/files';
        
        if (!is_dir($filesDir)) {
            return ['status' => 'skipped', 'reason' => 'No files backup found'];
        }

        $restoredFiles = 0;
        $this->copyDirectoryRecursive($filesDir, ROOT_PATH, $restoredFiles, $totalSize);

        return [
            'status' => 'success',
            'files_count' => $restoredFiles,
            'total_size' => $totalSize ?? 0
        ];
    }

    /**
     * Restore configuration
     */
    protected function restoreConfiguration($extractDir)
    {
        $configDir = $extractDir . '/config';
        
        if (!is_dir($configDir)) {
            return ['status' => 'skipped', 'reason' => 'No config backup found'];
        }

        $restoredFiles = 0;
        $configFiles = ['.env', 'composer.json', 'composer.lock'];

        foreach ($configFiles as $file) {
            $sourcePath = $configDir . '/' . $file;
            $targetPath = ROOT_PATH . '/' . $file;
            
            if (file_exists($sourcePath)) {
                if (copy($sourcePath, $targetPath)) {
                    $restoredFiles++;
                }
            }
        }

        return [
            'status' => 'success',
            'files_count' => $restoredFiles
        ];
    }

    /**
     * Get backup info
     */
    protected function getBackupInfo($extractDir)
    {
        $infoFile = $extractDir . '/backup_info.json';
        
        if (file_exists($infoFile)) {
            return json_decode(file_get_contents($infoFile), true);
        }

        return null;
    }

    /**
     * List available backups
     */
    public function listBackups()
    {
        $backups = [];
        
        if (!is_dir($this->backupPath)) {
            return $backups;
        }

        $files = glob($this->backupPath . '/*.zip');
        
        foreach ($files as $file) {
            $filename = basename($file, '.zip');
            $backups[] = [
                'name' => $filename,
                'path' => $file,
                'size' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => strpos($filename, 'incremental') !== false ? 'incremental' : 'full'
            ];
        }

        // Sort by creation date (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $backups;
    }

    /**
     * Delete backup
     */
    public function deleteBackup($backupName)
    {
        $backupFile = $this->backupPath . '/' . $backupName . '.zip';
        
        if (file_exists($backupFile)) {
            return unlink($backupFile);
        }

        return false;
    }

    /**
     * Manage backup retention
     */
    protected function manageBackupRetention()
    {
        $backups = $this->listBackups();
        
        if (count($backups) > $this->maxBackups) {
            $backupsToDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($backupsToDelete as $backup) {
                $this->deleteBackup($backup['name']);
            }
        }
    }

    /**
     * Get last backup date
     */
    protected function getLastBackupDate()
    {
        $backups = $this->listBackups();
        
        if (!empty($backups)) {
            return $backups[0]['created_at'];
        }

        return date('Y-m-d H:i:s', strtotime('-1 week'));
    }

    /**
     * Schedule automatic backups
     */
    public function scheduleBackups($frequency = 'daily')
    {
        // This would integrate with a job scheduler
        // For now, return the cron command
        $frequencies = [
            'daily' => '0 2 * * *',
            'weekly' => '0 2 * * 0',
            'monthly' => '0 2 1 * *'
        ];

        $cronTime = $frequencies[$frequency] ?? $frequencies['daily'];
        $phpPath = PHP_BINARY;
        $scriptPath = ROOT_PATH . '/scripts/backup.php';

        return "# FlexCMS Automatic Backup\n{$cronTime} {$phpPath} {$scriptPath}";
    }

    /**
     * Check if command exists
     */
    protected function commandExists($command)
    {
        $return = shell_exec(sprintf('which %s', escapeshellarg($command)));
        return !empty($return);
    }

    /**
     * Should exclude file/directory from backup
     */
    protected function shouldExclude($path)
    {
        foreach ($this->excludeDirectories as $exclude) {
            if (strpos($path, $exclude) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ensure backup directories exist
     */
    protected function ensureDirectories()
    {
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
        
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    /**
     * Cleanup temporary directory
     */
    protected function cleanupTemp($directory)
    {
        if (is_dir($directory)) {
            $this->deleteDirectoryRecursive($directory);
        }
    }

    /**
     * Delete directory recursively
     */
    protected function deleteDirectoryRecursive($directory)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats()
    {
        $backups = $this->listBackups();
        $totalSize = array_sum(array_column($backups, 'size'));
        
        $fullBackups = array_filter($backups, function($backup) {
            return $backup['type'] === 'full';
        });
        
        $incrementalBackups = array_filter($backups, function($backup) {
            return $backup['type'] === 'incremental';
        });

        return [
            'total_backups' => count($backups),
            'full_backups' => count($fullBackups),
            'incremental_backups' => count($incrementalBackups),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'last_backup' => !empty($backups) ? $backups[0] : null,
            'storage_path' => $this->backupPath
        ];
    }

    /**
     * Format bytes
     */
    protected function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}