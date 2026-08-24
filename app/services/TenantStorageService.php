<?php
// app/services/TenantStorageService.php

class TenantStorageService
{
    private PDO $db;
    private array $coreTables = ['products', 'sales', 'orders'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get the combined metrics in a clean, human-readable format.
     */
    public function getFormattedStorage(int $tenantId): string
    {
        $fileSize = $this->calculateFileStorage($tenantId);
        
        $dbRows = $this->calculateDatabaseStorage($tenantId);
        // Estimate DB size: assume an average of 2KB per row across core tables
        $estimatedDbSize = $dbRows * 2048; 
        
        $totalBytes = $fileSize + $estimatedDbSize;
        return $this->formatSize($totalBytes);
    }

    /**
     * Recursively calculate the directory size of the tenant's specific upload folder.
     */
    public function calculateFileStorage(int $tenantId): int
    {
        // Assuming ROOT_PATH is defined globally. If not, default to standard structure.
        $rootPath = defined('ROOT_PATH') ? ROOT_PATH : realpath(__DIR__ . '/../../');
        $dir = $rootPath . "/public/assets/uploads/tenant_{$tenantId}/";
        
        if (!is_dir($dir)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    /**
     * Query PostgreSQL to count total records tied to a tenant across core tables.
     */
    public function calculateDatabaseStorage(int $tenantId): int
    {
        $totalRecords = 0;
        foreach ($this->coreTables as $table) {
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tenant_id");
                $stmt->execute(['tenant_id' => $tenantId]);
                $totalRecords += (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Skip if table doesn't exist or query fails
                continue;
            }
        }
        return $totalRecords;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
