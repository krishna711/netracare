<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use ZipArchive;

class BackupService
{
    public static function backup(): string
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = env('DB_DATABASE');
        $tableKey = 'Tables_in_' . $dbName;
        
        $sql = "-- Database Backup generated at " . now()->toDateTimeString() . "\n\n";
        
        $pdo = DB::connection()->getPdo();
        
        foreach ($tables as $table) {
            $tableName = $table->$tableKey ?? current((array)$table);
            
            // Views shouldn't be backed up this way or need special handling
            // For this app we assume standard tables.
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $createTableKey = 'Create Table';
            
            // If it's a view, 'Create Table' might be 'Create View'
            if (!isset($createTable[0]->$createTableKey)) {
                continue;
            }
            
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createTable[0]->$createTableKey . ";\n\n";
            
            $rows = DB::table($tableName)->get();
            if ($rows->count() > 0) {
                // Chunk the inserts to avoid massive single strings, though for small/medium DBs it's fine.
                foreach ($rows as $row) {
                    $rowArray = (array)$row;
                    $keys = array_map(function($key) { return "`$key`"; }, array_keys($rowArray));
                    $values = array_map(function($value) use ($pdo) {
                        if (is_null($value)) return "NULL";
                        return $pdo->quote($value);
                    }, array_values($rowArray));
                    
                    $sql .= "INSERT INTO `{$tableName}` (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $values) . ");\n";
                }
            }
            $sql .= "\n\n";
        }
        
        $fileName = 'backup_' . date('Y_m_d_H_i_s') . '.sql';
        $filePath = storage_path('app/' . $fileName);
        file_put_contents($filePath, $sql);
        
        $zipFileName = 'backup_' . date('Y_m_d_H_i_s') . '.zip';
        $zipFilePath = storage_path('app/' . $zipFileName);
        
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filePath, $fileName);
            $zip->close();
        }
        
        @unlink($filePath);
        
        return $zipFilePath;
    }
}
