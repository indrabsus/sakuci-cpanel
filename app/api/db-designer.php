<?php
/**
 * API Backend untuk Desainer Skema Database ala SQLyog & phpMyAdmin
 * Mengambil metadata skema tabel, kolom, PK, relasi FK (eksplisit & inferensi),
 * serta mengelola struktur tabel (Create, Drop, Add Column) dan isi data (Preview, Insert, Delete).
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db-tools.php';

// Cek sesi login
$user = require_login($conn);
$user_id = (int) $user['id'];
$isAdmin = is_admin($user);

$db_id = (int) ($_REQUEST['db_id'] ?? 0);
$action = $_REQUEST['action'] ?? 'schema';

if ($db_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Parameter db_id wajib disertakan.']);
    exit;
}

// Ambil data database siswa dengan verifikasi kepemilikan
$sql = "SELECT d.id, d.user_id, d.db_name, d.db_host, d.db_port, d.project_id, du.username, du.password
          FROM db_list d
          LEFT JOIN db_users du ON du.db_id = d.id
         WHERE d.id = ?" . ($isAdmin ? "" : " AND d.user_id = ?");
$stmt = $conn->prepare($sql);
if ($isAdmin) {
    $stmt->bind_param("i", $db_id);
} else {
    $stmt->bind_param("ii", $db_id, $user_id);
}
$stmt->execute();
$dbRow = $stmt->get_result()->fetch_assoc();

if (!$dbRow || empty($dbRow['username'])) {
    echo json_encode(['ok' => false, 'error' => 'Database tidak ditemukan atau Anda tidak memiliki hak akses.']);
    exit;
}

$sambung = sambung_sebagai_siswa($dbRow);
if (!$sambung['ok']) {
    echo json_encode(['ok' => false, 'error' => 'Koneksi ke database gagal: ' . $sambung['pesan']]);
    exit;
}
$dbConn = $sambung['conn'];
$dbConn->set_charset('utf8mb4');

// Fungsi pembantu validasi nama identifier SQL
function validate_sql_ident($ident, $maxLen = 64) {
    if (!is_string($ident) || !preg_match('/^[a-zA-Z0-9_]{1,' . $maxLen . '}$/', $ident)) {
        return false;
    }
    return true;
}

// Konversi nama tabel ke nama Model PascalCase ala Sakuci / Laravel
function table_to_model_name(string $table): string {
    $name = strtolower(trim($table));
    $name = preg_replace('/^(tbl_|tb_)/', '', $name);
    if (str_ends_with($name, 'ies')) {
        $name = substr($name, 0, -3) . 'y';
    } elseif (str_ends_with($name, 'es') && (str_ends_with($name, 'sses') || str_ends_with($name, 'shes') || str_ends_with($name, 'ches') || str_ends_with($name, 'xes'))) {
        $name = substr($name, 0, -2);
    } elseif (str_ends_with($name, 's') && !str_ends_with($name, 'ss')) {
        if (!in_array($name, ['kelas', 'status', 'basis', 'karcis', 'beras', 'petugas', 'proses', 'akses', 'kursus'])) {
            $name = substr($name, 0, -1);
        }
    }
    $parts = explode('_', $name);
    $studly = '';
    foreach ($parts as $p) {
        $studly .= ucfirst($p);
    }
    return $studly ?: 'MyModel';
}

// Generate kode Model baru
function generate_model_code(string $modelName, string $tableName, string $pkCol, array $fillableCols, bool $hasTimestamps = true): string {
    $fillableStr = empty($fillableCols) 
        ? "[]" 
        : "[\n        '" . implode("',\n        '", $fillableCols) . "'\n    ]";
    
    $pkProperty = ($pkCol !== '' && $pkCol !== 'id') 
        ? "\n    protected string \$primaryKey = '{$pkCol}';\n" 
        : "";

    $tsProperty = !$hasTimestamps 
        ? "\n    public bool \$timestamps = false;\n" 
        : "";

    return "<?php\n\n"
        . "namespace App\\Models;\n\n"
        . "use Sakuci\\Database\\Model;\n\n"
        . "class {$modelName} extends Model\n"
        . "{\n"
        . "    protected static ?string \$table = '{$tableName}';\n"
        . $pkProperty
        . $tsProperty . "\n"
        . "    protected array \$fillable = {$fillableStr};\n"
        . "}\n";
}

// Perbarui kode Model yang sudah ada tanpa merusak method kustom siswa
function update_existing_model_code(string $existingCode, string $tableName, string $pkCol, array $fillableCols, bool $hasTimestamps = true): string {
    $fillableStr = empty($fillableCols)
        ? "[]"
        : "[\n        '" . implode("',\n        '", $fillableCols) . "'\n    ]";
    
    if (preg_match('/(protected|public)\s+array\s+\$fillable\s*=\s*(\[[^\]]*\]);/s', $existingCode)) {
        $newCode = preg_replace(
            '/(protected|public)\s+array\s+\$fillable\s*=\s*(\[[^\]]*\]);/s',
            "protected array \$fillable = {$fillableStr};",
            $existingCode,
            1
        );
    } elseif (preg_match('/(protected|public)\s+\$fillable\s*=\s*(\[[^\]]*\]);/s', $existingCode)) {
        $newCode = preg_replace(
            '/(protected|public)\s+\$fillable\s*=\s*(\[[^\]]*\]);/s',
            "protected array \$fillable = {$fillableStr};",
            $existingCode,
            1
        );
    } else {
        $lastBrace = strrpos($existingCode, '}');
        if ($lastBrace !== false) {
            $insert = "\n    protected array \$fillable = {$fillableStr};\n";
            $newCode = substr($existingCode, 0, $lastBrace) . $insert . substr($existingCode, $lastBrace);
        } else {
            $newCode = $existingCode;
        }
    }

    if ($pkCol !== '' && $pkCol !== 'id') {
        if (!preg_match('/\$primaryKey\s*=/', $newCode)) {
            if (strpos($newCode, '$fillable') !== false) {
                $newCode = preg_replace(
                    '/(protected\s+(?:array\s+)?\$fillable)/',
                    "protected string \$primaryKey = '{$pkCol}';\n\n    $1",
                    $newCode,
                    1
                );
            }
        }
    }


    if (!$hasTimestamps) {
        if (!preg_match('/\$timestamps\s*=/', $newCode)) {
            if (strpos($newCode, '$fillable') !== false) {
                $newCode = preg_replace(
                    '/(protected\s+(?:array\s+)?\$fillable)/',
                    "public bool \$timestamps = false;\n\n    $1",
                    $newCode,
                    1
                );
            }
        }
    }

    return $newCode;
}


try {
    // -------------------------------------------------------------
    // 1. ACTION: PREVIEW & GET ROWS (Pratinjau data & metadata kolom)
    // -------------------------------------------------------------
    if ($action === 'preview') {
        $tableName = trim($_REQUEST['table'] ?? '');
        if (!validate_sql_ident($tableName)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel tidak valid.']);
            exit;
        }

        $limit = max(1, min(100, (int) ($_REQUEST['limit'] ?? 50)));
        $offset = max(0, (int) ($_REQUEST['offset'] ?? 0));

        // Ambil informasi kolom & Primary Key
        $colRes = $dbConn->query("SHOW FULL COLUMNS FROM `" . str_replace("`", "``", $tableName) . "`");
        if (!$colRes) {
            echo json_encode(['ok' => false, 'error' => "Tabel '$tableName' tidak ditemukan."]);
            exit;
        }

        $columns = [];
        $columnsMeta = [];
        $pks = [];

        while ($c = $colRes->fetch_assoc()) {
            $isPk = (strpos($c['Key'] ?? '', 'PRI') !== false);
            $isAi = (strpos($c['Extra'] ?? '', 'auto_increment') !== false);
            $columns[] = $c['Field'];
            $columnsMeta[] = [
                'name' => $c['Field'],
                'type' => $c['Type'],
                'null' => ($c['Null'] === 'YES'),
                'is_pk' => $isPk,
                'is_ai' => $isAi,
                'default' => $c['Default'],
                'extra' => $c['Extra']
            ];
            if ($isPk) {
                $pks[] = $c['Field'];
            }
        }

        // Hitung total baris
        $countRes = $dbConn->query("SELECT COUNT(*) AS total FROM `" . str_replace("`", "``", $tableName) . "`");
        $totalRows = $countRes ? (int) ($countRes->fetch_assoc()['total'] ?? 0) : 0;

        // Ambil baris data
        $previewQuery = $dbConn->query("SELECT * FROM `" . str_replace("`", "``", $tableName) . "` LIMIT {$limit} OFFSET {$offset}");
        $rows = [];

        if ($previewQuery) {
            while ($r = $previewQuery->fetch_assoc()) {
                $sanitizedRow = [];
                foreach ($r as $colName => $val) {
                    if ($val === null) {
                        $sanitizedRow[$colName] = null;
                    } elseif (!mb_check_encoding($val, 'UTF-8')) {
                        $sanitizedRow[$colName] = utf8_encode($val);
                    } else {
                        $sanitizedRow[$colName] = (string) $val;
                    }
                }
                $rows[] = $sanitizedRow;
            }
        }

        echo json_encode([
            'ok' => true,
            'table' => $tableName,
            'total_rows' => $totalRows,
            'limit' => $limit,
            'offset' => $offset,
            'pks' => $pks,
            'columns' => $columns,
            'columns_meta' => $columnsMeta,
            'rows' => $rows
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 2. ACTION: CREATE_TABLE (Buat tabel baru ala phpMyAdmin)
    // -------------------------------------------------------------
    if ($action === 'create_table') {
        $tableName = trim($_POST['table_name'] ?? '');
        if (!validate_sql_ident($tableName)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel tidak valid (hanya huruf, angka, garis bawah, maks 64 karakter).']);
            exit;
        }

        $columnsRaw = $_POST['columns'] ?? [];
        if (is_string($columnsRaw)) {
            $columnsRaw = json_decode($columnsRaw, true) ?: [];
        }
        if (!is_array($columnsRaw) || empty($columnsRaw)) {
            echo json_encode(['ok' => false, 'error' => 'Minimal harus ada 1 kolom untuk membuat tabel.']);
            exit;
        }

        $allowedTypes = ['INT', 'BIGINT', 'TINYINT', 'SMALLINT', 'VARCHAR', 'CHAR', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'BOOLEAN', 'JSON'];
        $colDefs = [];
        $pkCols = [];

        foreach ($columnsRaw as $col) {
            $cName = trim($col['name'] ?? '');
            if (!validate_sql_ident($cName)) {
                echo json_encode(['ok' => false, 'error' => "Nama kolom '$cName' tidak valid."]);
                exit;
            }

            $cType = strtoupper(trim($col['type'] ?? 'VARCHAR'));
            if (!in_array($cType, $allowedTypes, true)) {
                $cType = 'VARCHAR';
            }

            $cLength = trim((string) ($col['length'] ?? ''));
            $typeWithLen = $cType;
            if ($cLength !== '' && preg_match('/^[0-9]+(,[0-9]+)?$/', $cLength)) {
                $typeWithLen .= "({$cLength})";
            } elseif ($cType === 'VARCHAR' && $cLength === '') {
                $typeWithLen .= "(255)";
            }

            $nullSql = (!empty($col['is_null']) || (isset($col['null']) && $col['null'] === true)) ? "NULL" : "NOT NULL";
            $extraSql = "";
            if (!empty($col['is_ai'])) {
                $extraSql .= " AUTO_INCREMENT";
            }

            $defaultSql = "";
            $defaultVal = $col['default'] ?? null;
            if ($defaultVal !== null && $defaultVal !== '') {
                if (strtoupper($defaultVal) === 'NULL') {
                    $defaultSql = " DEFAULT NULL";
                } elseif (strtoupper($defaultVal) === 'CURRENT_TIMESTAMP') {
                    $defaultSql = " DEFAULT CURRENT_TIMESTAMP";
                } else {
                    $escapedDef = $dbConn->real_escape_string($defaultVal);
                    $defaultSql = " DEFAULT '{$escapedDef}'";
                }
            }

            $colDefs[] = "`{$cName}` {$typeWithLen} {$nullSql}{$defaultSql}{$extraSql}";

            if (!empty($col['is_pk'])) {
                $pkCols[] = "`{$cName}`";
            }
        }

        if (!empty($pkCols)) {
            $colDefs[] = "PRIMARY KEY (" . implode(', ', $pkCols) . ")";
        }

        $createSql = "CREATE TABLE `{$tableName}` (\n  " . implode(",\n  ", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$dbConn->query($createSql)) {
            echo json_encode(['ok' => false, 'error' => 'Gagal membuat tabel: ' . $dbConn->error]);
            exit;
        }

        echo json_encode(['ok' => true, 'pesan' => "Tabel '$tableName' berhasil dibuat!"]);
        exit;
    }

    // -------------------------------------------------------------
    // 3. ACTION: DROP_TABLE (Hapus tabel)
    // -------------------------------------------------------------
    if ($action === 'drop_table') {
        $table = trim($_POST['table'] ?? '');
        if (!validate_sql_ident($table)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel tidak valid.']);
            exit;
        }

        if (!$dbConn->query("DROP TABLE `{$table}`")) {
            echo json_encode(['ok' => false, 'error' => 'Gagal menghapus tabel: ' . $dbConn->error]);
            exit;
        }

        echo json_encode(['ok' => true, 'pesan' => "Tabel '$table' berhasil dihapus!"]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: RENAME_TABLE (Ubah nama tabel)
    // -------------------------------------------------------------
    if ($action === 'rename_table') {
        $table = trim($_POST['table'] ?? '');
        $newTableName = trim($_POST['new_table_name'] ?? '');
        if (!validate_sql_ident($table) || !validate_sql_ident($newTableName)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel lama atau nama tabel baru tidak valid.']);
            exit;
        }

        try {
            if (!$dbConn->query("RENAME TABLE `{$table}` TO `{$newTableName}`")) {
                echo json_encode(['ok' => false, 'error' => 'Gagal mengubah nama tabel: ' . $dbConn->error]);
                exit;
            }
            echo json_encode(['ok' => true, 'pesan' => "Tabel '$table' berhasil diubah namanya menjadi '$newTableName'!"]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Gagal mengubah nama tabel: ' . $e->getMessage()]);
            exit;
        }
    }

    // -------------------------------------------------------------
    // 4. ACTION: ADD_COLUMN (Tambah kolom ke tabel)
    // -------------------------------------------------------------
    if ($action === 'add_column') {
        $table = trim($_POST['table'] ?? '');
        $colName = trim($_POST['column_name'] ?? '');
        if (!validate_sql_ident($table) || !validate_sql_ident($colName)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel atau kolom tidak valid.']);
            exit;
        }

        $cType = strtoupper(trim($_POST['type'] ?? 'VARCHAR'));
        $allowedTypes = ['INT', 'BIGINT', 'TINYINT', 'SMALLINT', 'VARCHAR', 'CHAR', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'BOOLEAN', 'JSON'];
        if (!in_array($cType, $allowedTypes, true)) {
            $cType = 'VARCHAR';
        }

        $cLength = trim((string) ($_POST['length'] ?? ''));
        $typeWithLen = $cType;
        if ($cLength !== '' && preg_match('/^[0-9]+(,[0-9]+)?$/', $cLength)) {
            $typeWithLen .= "({$cLength})";
        } elseif ($cType === 'VARCHAR' && $cLength === '') {
            $typeWithLen .= "(255)";
        }

        $nullSql = !empty($_POST['is_null']) ? "NULL" : "NOT NULL";
        $defaultSql = "";
        $defaultVal = $_POST['default'] ?? null;
        if ($defaultVal !== null && $defaultVal !== '') {
            if (strtoupper($defaultVal) === 'NULL') {
                $defaultSql = " DEFAULT NULL";
            } elseif (strtoupper($defaultVal) === 'CURRENT_TIMESTAMP') {
                $defaultSql = " DEFAULT CURRENT_TIMESTAMP";
            } else {
                $escapedDef = $dbConn->real_escape_string($defaultVal);
                $defaultSql = " DEFAULT '{$escapedDef}'";
            }
        }

        $positionSql = "";
        $pos = trim($_POST['position'] ?? '');
        $afterCol = trim($_POST['after_column'] ?? '');
        if ($pos === 'FIRST') {
            $positionSql = " FIRST";
        } elseif ($pos === 'AFTER' && validate_sql_ident($afterCol)) {
            $positionSql = " AFTER `{$afterCol}`";
        }

        $alterSql = "ALTER TABLE `{$table}` ADD COLUMN `{$colName}` {$typeWithLen} {$nullSql}{$defaultSql}{$positionSql}";
        if (!$dbConn->query($alterSql)) {
            echo json_encode(['ok' => false, 'error' => 'Gagal menambah kolom: ' . $dbConn->error]);
            exit;
        }

        echo json_encode(['ok' => true, 'pesan' => "Kolom '$colName' berhasil ditambahkan ke tabel '$table'!"]);
        exit;
    }

    // -------------------------------------------------------------
    // 5. ACTION: DROP_COLUMN (Hapus kolom dari tabel)
    // -------------------------------------------------------------
    if ($action === 'drop_column') {
        $table = trim($_POST['table'] ?? '');
        $colName = trim($_POST['column'] ?? '');
        if (!validate_sql_ident($table) || !validate_sql_ident($colName)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel atau kolom tidak valid.']);
            exit;
        }

        $alterSql = "ALTER TABLE `{$table}` DROP COLUMN `{$colName}`";
        if (!$dbConn->query($alterSql)) {
            echo json_encode(['ok' => false, 'error' => 'Gagal menghapus kolom: ' . $dbConn->error]);
            exit;
        }

        echo json_encode(['ok' => true, 'pesan' => "Kolom '$colName' berhasil dihapus dari tabel '$table'!"]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: MODIFY_COLUMN (Ubah nama / tipe data / atribut kolom)
    // -------------------------------------------------------------
    if ($action === 'modify_column') {
        $table = trim($_POST['table'] ?? '');
        $oldCol = trim($_POST['old_column'] ?? '');
        $newCol = trim($_POST['column_name'] ?? '');
        if (!validate_sql_ident($table) || !validate_sql_ident($oldCol) || !validate_sql_ident($newCol)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel atau kolom tidak valid.']);
            exit;
        }

        $cType = strtoupper(trim($_POST['type'] ?? 'VARCHAR'));
        $allowedTypes = ['INT', 'BIGINT', 'TINYINT', 'SMALLINT', 'VARCHAR', 'CHAR', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'DECIMAL', 'FLOAT', 'DOUBLE', 'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'BOOLEAN', 'JSON'];
        if (!in_array($cType, $allowedTypes, true)) {
            $cType = 'VARCHAR';
        }

        $cLength = trim((string) ($_POST['length'] ?? ''));
        $typeWithLen = $cType;
        if ($cLength !== '' && preg_match('/^[0-9]+(,[0-9]+)?$/', $cLength)) {
            $typeWithLen .= "({$cLength})";
        } elseif ($cType === 'VARCHAR' && $cLength === '') {
            $typeWithLen .= "(255)";
        }

        $nullSql = !empty($_POST['is_null']) ? "NULL" : "NOT NULL";
        $defaultSql = "";
        $defaultVal = $_POST['default'] ?? null;
        if ($defaultVal !== null && $defaultVal !== '') {
            if (strtoupper($defaultVal) === 'NULL') {
                $defaultSql = " DEFAULT NULL";
            } elseif (strtoupper($defaultVal) === 'CURRENT_TIMESTAMP') {
                $defaultSql = " DEFAULT CURRENT_TIMESTAMP";
            } else {
                $escapedDef = $dbConn->real_escape_string($defaultVal);
                $defaultSql = " DEFAULT '{$escapedDef}'";
            }
        }

        $extraSql = "";
        if (!empty($_POST['is_ai'])) {
            $extraSql .= " AUTO_INCREMENT";
        }

        try {
            $alterSql = "ALTER TABLE `{$table}` CHANGE COLUMN `{$oldCol}` `{$newCol}` {$typeWithLen} {$nullSql}{$defaultSql}{$extraSql}";
            if (!$dbConn->query($alterSql)) {
                echo json_encode(['ok' => false, 'error' => 'Gagal mengubah kolom: ' . $dbConn->error]);
                exit;
            }

            echo json_encode(['ok' => true, 'pesan' => "Kolom '$oldCol' berhasil diperbarui!"]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Gagal mengubah kolom: ' . $e->getMessage()]);
            exit;
        }
    }

    // -------------------------------------------------------------
    // 6. ACTION: INSERT_ROW (Input baris data baru ke tabel)
    // -------------------------------------------------------------
    if ($action === 'insert_row') {
        $table = trim($_POST['table'] ?? '');
        if (!validate_sql_ident($table)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel tidak valid.']);
            exit;
        }

        $dataRaw = $_POST['data'] ?? [];
        if (is_string($dataRaw)) {
            $dataRaw = json_decode($dataRaw, true) ?: [];
        }
        if (!is_array($dataRaw) || empty($dataRaw)) {
            echo json_encode(['ok' => false, 'error' => 'Data baris tidak boleh kosong.']);
            exit;
        }

        $colRes = $dbConn->query("SHOW FULL COLUMNS FROM `{$table}`");
        if (!$colRes) {
            echo json_encode(['ok' => false, 'error' => "Tabel '$table' tidak ditemukan."]);
            exit;
        }
        $tableCols = [];
        while ($c = $colRes->fetch_assoc()) {
            $tableCols[$c['Field']] = $c;
        }

        $insertCols = [];
        $insertPlaceholders = [];
        $insertValues = [];
        $types = "";

        foreach ($dataRaw as $colName => $val) {
            if (!isset($tableCols[$colName])) continue;
            $colMeta = $tableCols[$colName];

            // Jika Auto Increment dan nilainya kosong, biarkan MySQL generate otomatis
            if (strpos($colMeta['Extra'] ?? '', 'auto_increment') !== false && ($val === '' || $val === null)) {
                continue;
            }

            $insertCols[] = "`{$colName}`";
            $insertPlaceholders[] = "?";

            if ($val === '' && $colMeta['Null'] === 'YES') {
                $insertValues[] = null;
                $types .= "s";
            } else {
                $insertValues[] = $val;
                $types .= "s";
            }
        }

        if (empty($insertCols)) {
            echo json_encode(['ok' => false, 'error' => 'Tidak ada kolom yang diisi untuk dimasukkan.']);
            exit;
        }

        $sql = "INSERT INTO `{$table}` (" . implode(", ", $insertCols) . ") VALUES (" . implode(", ", $insertPlaceholders) . ")";
        $stmt = $dbConn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'Gagal menyiapkan query: ' . $dbConn->error]);
            exit;
        }

        $stmt->bind_param($types, ...$insertValues);
        if (!$stmt->execute()) {
            echo json_encode(['ok' => false, 'error' => 'Gagal memasukkan data: ' . $stmt->error]);
            exit;
        }

        echo json_encode(['ok' => true, 'pesan' => 'Baris data baru berhasil disimpan!', 'insert_id' => $dbConn->insert_id]);
        exit;
    }

    // -------------------------------------------------------------
    // 7. ACTION: DELETE_ROW (Hapus baris data dari tabel)
    // -------------------------------------------------------------
    if ($action === 'delete_row') {
        $table = trim($_POST['table'] ?? '');
        if (!validate_sql_ident($table)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel tidak valid.']);
            exit;
        }

        $pkRaw = $_POST['pk'] ?? [];
        if (is_string($pkRaw)) {
            $pkRaw = json_decode($pkRaw, true) ?: [];
        }
        if (!is_array($pkRaw) || empty($pkRaw)) {
            echo json_encode(['ok' => false, 'error' => 'Identitas baris (Primary Key) tidak boleh kosong.']);
            exit;
        }

        $whereParts = [];
        $whereValues = [];
        $types = "";

        foreach ($pkRaw as $pkCol => $pkVal) {
            if (!validate_sql_ident($pkCol)) continue;
            $whereParts[] = "`{$pkCol}` = ?";
            $whereValues[] = $pkVal;
            $types .= "s";
        }

        if (empty($whereParts)) {
            echo json_encode(['ok' => false, 'error' => 'Kondisi Primary Key tidak valid.']);
            exit;
        }

        $sql = "DELETE FROM `{$table}` WHERE " . implode(" AND ", $whereParts) . " LIMIT 1";
        $stmt = $dbConn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'Gagal menyiapkan query: ' . $dbConn->error]);
            exit;
        }

        $stmt->bind_param($types, ...$whereValues);
        if (!$stmt->execute()) {
            echo json_encode(['ok' => false, 'error' => 'Gagal menghapus baris data: ' . $stmt->error]);
            exit;
        }

        echo json_encode(['ok' => true, 'pesan' => 'Baris data berhasil dihapus!']);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION: UPDATE_ROW (Ubah data baris tabel)
    // -------------------------------------------------------------
    if ($action === 'update_row') {
        $table = trim($_POST['table'] ?? '');
        if (!validate_sql_ident($table)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel tidak valid.']);
            exit;
        }

        $pkRaw = $_POST['pk'] ?? [];
        if (is_string($pkRaw)) {
            $pkRaw = json_decode($pkRaw, true) ?: [];
        }
        if (!is_array($pkRaw) || empty($pkRaw)) {
            echo json_encode(['ok' => false, 'error' => 'Identitas baris (Primary Key) tidak boleh kosong.']);
            exit;
        }

        $dataRaw = $_POST['data'] ?? [];
        if (is_string($dataRaw)) {
            $dataRaw = json_decode($dataRaw, true) ?: [];
        }
        if (!is_array($dataRaw) || empty($dataRaw)) {
            echo json_encode(['ok' => false, 'error' => 'Data perubahan tidak boleh kosong.']);
            exit;
        }

        $colRes = $dbConn->query("SHOW FULL COLUMNS FROM `{$table}`");
        if (!$colRes) {
            echo json_encode(['ok' => false, 'error' => "Tabel '$table' tidak ditemukan."]);
            exit;
        }
        $tableCols = [];
        while ($c = $colRes->fetch_assoc()) {
            $tableCols[$c['Field']] = $c;
        }

        $setParts = [];
        $setValues = [];
        $types = "";

        foreach ($dataRaw as $colName => $val) {
            if (!isset($tableCols[$colName])) continue;
            $colMeta = $tableCols[$colName];

            $setParts[] = "`{$colName}` = ?";
            if ($val === '' && $colMeta['Null'] === 'YES') {
                $setValues[] = null;
                $types .= "s";
            } else {
                $setValues[] = $val;
                $types .= "s";
            }
        }

        if (empty($setParts)) {
            echo json_encode(['ok' => false, 'error' => 'Tidak ada data kolom yang diubah.']);
            exit;
        }

        $whereParts = [];
        foreach ($pkRaw as $pkCol => $pkVal) {
            if (!validate_sql_ident($pkCol)) continue;
            $whereParts[] = "`{$pkCol}` = ?";
            $setValues[] = $pkVal;
            $types .= "s";
        }

        if (empty($whereParts)) {
            echo json_encode(['ok' => false, 'error' => 'Kondisi Primary Key tidak valid.']);
            exit;
        }

        try {
            $sql = "UPDATE `{$table}` SET " . implode(", ", $setParts) . " WHERE " . implode(" AND ", $whereParts) . " LIMIT 1";
            $stmt = $dbConn->prepare($sql);
            if (!$stmt) {
                echo json_encode(['ok' => false, 'error' => 'Gagal menyiapkan query: ' . $dbConn->error]);
                exit;
            }

            $stmt->bind_param($types, ...$setValues);
            if (!$stmt->execute()) {
                echo json_encode(['ok' => false, 'error' => 'Gagal memperbarui data: ' . $stmt->error]);
                exit;
            }

            echo json_encode(['ok' => true, 'pesan' => 'Baris data berhasil diperbarui!']);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Gagal memperbarui data: ' . $e->getMessage()]);
            exit;
        }
    }

    // -------------------------------------------------------------
    // 8. ACTION: ADD_FOREIGN_KEY (Buat relasi Foreign Key InnoDB)
    // -------------------------------------------------------------
    if ($action === 'add_foreign_key') {
        $fromTable = trim($_POST['from_table'] ?? '');
        $fromCol   = trim($_POST['from_column'] ?? '');
        $toTable   = trim($_POST['to_table'] ?? '');
        $toCol     = trim($_POST['to_column'] ?? '');
        $onDelete  = strtoupper(trim($_POST['on_delete'] ?? 'CASCADE'));
        $onUpdate  = strtoupper(trim($_POST['on_update'] ?? 'CASCADE'));
        $autoAlign = !isset($_POST['auto_align']) || $_POST['auto_align'] === '1' || $_POST['auto_align'] === 'true';

        $allowedActions = ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'];
        if (!in_array($onDelete, $allowedActions, true)) $onDelete = 'CASCADE';
        if (!in_array($onUpdate, $allowedActions, true)) $onUpdate = 'CASCADE';

        if (!validate_sql_ident($fromTable) || !validate_sql_ident($fromCol) || !validate_sql_ident($toTable) || !validate_sql_ident($toCol)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel atau kolom relasi tidak valid.']);
            exit;
        }

        // Ambil info kolom toTable (induk) dan fromTable (anak)
        $toColRes = $dbConn->query("SHOW FULL COLUMNS FROM `{$toTable}` WHERE Field = '" . $dbConn->real_escape_string($toCol) . "'");
        $toColMeta = $toColRes ? $toColRes->fetch_assoc() : null;

        $fromColRes = $dbConn->query("SHOW FULL COLUMNS FROM `{$fromTable}` WHERE Field = '" . $dbConn->real_escape_string($fromCol) . "'");
        $fromColMeta = $fromColRes ? $fromColRes->fetch_assoc() : null;

        if (!$toColMeta || !$fromColMeta) {
            echo json_encode(['ok' => false, 'error' => 'Kolom asal atau kolom referensi tidak ditemukan di tabel.']);
            exit;
        }

        $alignedMsg = "";
        if ($autoAlign && strtolower($fromColMeta['Type']) !== strtolower($toColMeta['Type'])) {
            $toType = $toColMeta['Type'];
            $nullSql = ($fromColMeta['Null'] === 'YES') ? 'NULL' : 'NOT NULL';
            try {
                $dbConn->query("ALTER TABLE `{$fromTable}` MODIFY `{$fromCol}` {$toType} {$nullSql}");
                $alignedMsg = " (Tipe data kolom '{$fromCol}' otomatis disesuaikan menjadi {$toType})";
            } catch (Throwable $e) {
                // Biarkan lanjut, tangani di ADD CONSTRAINT
            }
        }

        $fkName = "fk_" . substr($fromTable, 0, 14) . "_" . substr($fromCol, 0, 14) . "_" . substr(md5(uniqid('', true)), 0, 6);

        $sql = "ALTER TABLE `{$fromTable}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$fromCol}`) REFERENCES `{$toTable}`(`{$toCol}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}";
        
        try {
            $dbConn->query($sql);
            echo json_encode([
                'ok' => true, 
                'pesan' => "Relasi Foreign Key '{$fromTable}.{$fromCol} ➔ {$toTable}.{$toCol}' berhasil dibuat!{$alignedMsg}", 
                'constraint_name' => $fkName
            ]);
            exit;
        } catch (Throwable $e) {
            $err = $e->getMessage();
            $customErr = $err;
            if (stripos($err, 'are incompatible') !== false) {
                $customErr = "Tipe data kolom '{$fromCol}' ({$fromColMeta['Type']}) dan '{$toCol}' ({$toColMeta['Type']}) tidak kompatibel di MySQL. Pastikan tipe data dan unsigned sama persis.";
            } elseif (stripos($err, 'Cannot add or update a child row') !== false || stripos($err, 'foreign key constraint fails') !== false) {
                $customErr = "Gagal menghubungkan relasi: Pada tabel anak '{$fromTable}', terdapat data yang nilainya tidak ditemukan di tabel induk '{$toTable}'.";
            }
            echo json_encode(['ok' => false, 'error' => "Gagal membuat Foreign Key: {$customErr}"]);
            exit;
        }
    }

    // -------------------------------------------------------------
    // 9. ACTION: DROP_FOREIGN_KEY (Hapus relasi Foreign Key)
    // -------------------------------------------------------------
    if ($action === 'drop_foreign_key') {
        $table = trim($_POST['table'] ?? '');
        $constraint = trim($_POST['constraint_name'] ?? '');
        if (!validate_sql_ident($table) || !validate_sql_ident($constraint)) {
            echo json_encode(['ok' => false, 'error' => 'Nama tabel atau constraint tidak valid.']);
            exit;
        }

        try {
            $sql = "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`";
            $dbConn->query($sql);
            echo json_encode(['ok' => true, 'pesan' => "Relasi Foreign Key '$constraint' berhasil dihapus dari tabel '$table'!"]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Gagal menghapus Foreign Key: ' . $e->getMessage()]);
            exit;
        }
    }

    
    // -------------------------------------------------------------
    // 10. ACTION: GET_SYNC_STATUS (Cek status sinkronisasi ke kodingan)
    // -------------------------------------------------------------
    if ($action === 'get_sync_status') {
        $project_id = (int) ($dbRow['project_id'] ?? 0);
        $project = null;
        if ($project_id > 0) {
            $pStmt = $conn->prepare("SELECT id, user_id, name, domain, local_path FROM projects WHERE id = ?");
            $pStmt->bind_param("i", $project_id);
            $pStmt->execute();
            $project = $pStmt->get_result()->fetch_assoc();
        }
        if (!$project) {
            $uId = (int) ($dbRow['user_id'] ?? $user_id);
            $pStmt = $conn->prepare("SELECT id, user_id, name, domain, local_path FROM projects WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $pStmt->bind_param("i", $uId);
            $pStmt->execute();
            $project = $pStmt->get_result()->fetch_assoc();
        }

        if (!$project || empty($project['local_path'])) {
            echo json_encode(['ok' => false, 'error' => 'Database ini belum terhubung ke projek kodingan manapun. Pastikan projek sudah dibuat di cPanel.']);
            exit;
        }

        $projectPath = rtrim($project['local_path'], '/');
        if (!is_dir($projectPath)) {
            echo json_encode(['ok' => false, 'error' => "Direktori projek tidak ditemukan di server: {$projectPath}"]);
            exit;
        }

        $modelsDir = $projectPath . '/app/Models';
        $migrationsDir = $projectPath . '/database/migrations';
        if (!is_dir($modelsDir)) {
            @mkdir($modelsDir, 0755, true);
        }
        if (!is_dir($migrationsDir)) {
            @mkdir($migrationsDir, 0755, true);
        }

        // Ambil seluruh migrasi yang tercatat di database siswa
        $recordedMigrations = [];
        $mRes = $dbConn->query("SELECT migration FROM migrations");
        if ($mRes) {
            while ($mRow = $mRes->fetch_assoc()) {
                $recordedMigrations[$mRow['migration']] = true;
            }
        }

        $modelFiles = glob($modelsDir . '/*.php') ?: [];
        $migrationFiles = glob($migrationsDir . '/*.sql') ?: [];

        // Ambil semua tabel dasar (abaikan tabel migrations)
        $tablesRes = $dbConn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tableList = [];
        if ($tablesRes) {
            while ($tRow = $tablesRes->fetch_array()) {
                if ($tRow[0] === 'migrations') continue;
                $tableList[] = $tRow[0];
            }
        }

        $resultTables = [];
        foreach ($tableList as $tName) {
            // Ambil kolom dan PK
            $colRes = $dbConn->query("SHOW FULL COLUMNS FROM `{$tName}`");
            $columns = [];
            $pkCol = '';
            $fillableCols = [];

            if ($colRes) {
                while ($c = $colRes->fetch_assoc()) {
                    $cName = $c['Field'];
                    $isPk = ($c['Key'] === 'PRI');
                    $isAi = (stripos($c['Extra'], 'auto_increment') !== false);
                    if ($isPk && empty($pkCol)) {
                        $pkCol = $cName;
                    }
                    $columns[] = [
                        'name' => $cName,
                        'type' => $c['Type'],
                        'is_pk' => $isPk,
                        'is_ai' => $isAi
                    ];

                    // Hitung fillable: abaikan PK auto_increment, created_at, updated_at
                    if (!$isAi && !in_array($cName, ['created_at', 'updated_at'])) {
                        $fillableCols[] = $cName;
                    }
                }
            }

            // Cari Model yang cocok
            $modelName = table_to_model_name($tName);
            $matchedModelFile = null;
            $matchedModelPath = null;

            foreach ($modelFiles as $mf) {
                $content = file_get_contents($mf);
                if (preg_match('/\$table\s*=\s*[\'"]' . preg_quote($tName, '/') . '[\'"]/', $content)) {
                    $matchedModelFile = basename($mf);
                    $matchedModelPath = $mf;
                    break;
                }
                $base = basename($mf, '.php');
                if (strcasecmp($base, $modelName) === 0 || strcasecmp($base, $tName) === 0) {
                    $matchedModelFile = basename($mf);
                    $matchedModelPath = $mf;
                }
            }

            $modelInfo = [
                'name' => $modelName,
                'file' => $matchedModelFile,
                'exists' => ($matchedModelFile !== null),
                'status' => 'missing',
                'badge' => 'Belum Ada',
                'badge_type' => 'new',
                'detail' => "Akan dibuat app/Models/{$modelName}.php",
                'fillable' => $fillableCols,
                'missing_cols' => []
            ];

            if ($matchedModelPath && is_file($matchedModelPath)) {
                $code = file_get_contents($matchedModelPath);
                $existingFillable = [];
                if (preg_match('/\$fillable\s*=\s*\[(.*?)\]/s', $code, $fm)) {
                    preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', $fm[1], $cm);
                    $existingFillable = $cm[1] ?? [];
                }
                $missingCols = array_values(array_diff($fillableCols, $existingFillable));
                if (!empty($missingCols)) {
                    $modelInfo['status'] = 'needs_update';
                    $modelInfo['badge'] = 'Perlu Update';
                    $modelInfo['badge_type'] = 'update';
                    $modelInfo['detail'] = 'Kolom baru terdeteksi: ' . implode(', ', $missingCols);
                    $modelInfo['missing_cols'] = $missingCols;
                } else {
                    $modelInfo['status'] = 'synced';
                    $modelInfo['badge'] = 'Sudah Cocok';
                    $modelInfo['badge_type'] = 'ok';
                    $modelInfo['detail'] = 'Struktur model sudah sesuai';
                }
            }

            // Cari Berkas Migrasi yang cocok
            $matchedMigrationFile = null;
            $singularTable = rtrim($tName, 's');
            foreach ($migrationFiles as $mf) {
                $bName = basename($mf);
                if (preg_match('/_create_' . preg_quote($tName, '/') . '_table\.sql$/i', $bName) ||
                    preg_match('/_create_' . preg_quote($tName . 's', '/') . '_table\.sql$/i', $bName) ||
                    ($singularTable !== $tName && preg_match('/_create_' . preg_quote($singularTable, '/') . '_table\.sql$/i', $bName))) {
                    $matchedMigrationFile = $bName;
                    break;
                }
            }
            if (!$matchedMigrationFile) {
                foreach ($migrationFiles as $mf) {
                    $mContent = file_get_contents($mf);
                    if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($tName, '/') . '`?\s*\(/i', $mContent)) {
                        $matchedMigrationFile = basename($mf);
                        break;
                    }
                }
            }

            $migInfo = [
                'file' => $matchedMigrationFile,
                'exists' => ($matchedMigrationFile !== null),
                'recorded' => false,
                'status' => 'missing',
                'badge' => 'Belum Ada',
                'badge_type' => 'new',
                'detail' => "Akan dibuat berkas database/migrations/..._create_{$tName}_table.sql"
            ];

            if ($matchedMigrationFile) {
                $isRecorded = isset($recordedMigrations[$matchedMigrationFile]);
                $migInfo['recorded'] = $isRecorded;
                if ($isRecorded) {
                    $migInfo['status'] = 'synced';
                    $migInfo['badge'] = 'Tercatat';
                    $migInfo['badge_type'] = 'ok';
                    $migInfo['detail'] = "Berkas {$matchedMigrationFile} & tercatat di database";
                } else {
                    $migInfo['status'] = 'unrecorded';
                    $migInfo['badge'] = 'Belum Dicatat';
                    $migInfo['badge_type'] = 'update';
                    $migInfo['detail'] = "Berkas {$matchedMigrationFile} ada tapi belum dicatat di tabel migrations";
                }
            }

            $resultTables[] = [
                'name' => $tName,
                'pk_column' => $pkCol,
                'columns_count' => count($columns),
                'model' => $modelInfo,
                'migration' => $migInfo
            ];
        }

        echo json_encode([
            'ok' => true,
            'project' => [
                'id' => (int) $project['id'],
                'name' => $project['name'],
                'domain' => $project['domain'],
                'local_path' => $project['local_path']
            ],
            'tables' => $resultTables
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 11. ACTION: SYNC_CODEBASE (Buat / perbarui Model & Migrasi SQL)
    // -------------------------------------------------------------
    if ($action === 'sync_codebase') {
        $itemsRaw = $_POST['items'] ?? '[]';
        $items = json_decode($itemsRaw, true);
        if (!is_array($items) || empty($items)) {
            echo json_encode(['ok' => false, 'error' => 'Tidak ada tabel yang dipilih untuk disinkronkan.']);
            exit;
        }

        $project_id = (int) ($dbRow['project_id'] ?? 0);
        $project = null;
        if ($project_id > 0) {
            $pStmt = $conn->prepare("SELECT id, user_id, name, domain, local_path FROM projects WHERE id = ?");
            $pStmt->bind_param("i", $project_id);
            $pStmt->execute();
            $project = $pStmt->get_result()->fetch_assoc();
        }
        if (!$project) {
            $uId = (int) ($dbRow['user_id'] ?? $user_id);
            $pStmt = $conn->prepare("SELECT id, user_id, name, domain, local_path FROM projects WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $pStmt->bind_param("i", $uId);
            $pStmt->execute();
            $project = $pStmt->get_result()->fetch_assoc();
        }

        if (!$project || empty($project['local_path'])) {
            echo json_encode(['ok' => false, 'error' => 'Projek kodingan tidak ditemukan.']);
            exit;
        }

        $projectPath = rtrim($project['local_path'], '/');
        $modelsDir = $projectPath . '/app/Models';
        $migrationsDir = $projectPath . '/database/migrations';
        if (!is_dir($modelsDir)) {
            @mkdir($modelsDir, 0755, true);
        }
        if (!is_dir($migrationsDir)) {
            @mkdir($migrationsDir, 0755, true);
        }

        // Pastikan tabel migrations ada di database siswa
        $dbConn->query("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255),
            ran_at VARCHAR(32)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $modelsCreated = [];
        $modelsUpdated = [];
        $migrationsCreated = [];
        $migrationsRegistered = [];
        $errors = [];

        $modelFiles = glob($modelsDir . '/*.php') ?: [];
        $migrationFiles = glob($migrationsDir . '/*.sql') ?: [];

        $timeOffset = 0;

        foreach ($items as $item) {
            $tName = trim($item['table'] ?? '');
            $doModel = !empty($item['sync_model']);
            $doMigration = !empty($item['sync_migration']);

            if (!validate_sql_ident($tName)) {
                continue;
            }

            // Ambil kolom dan PK
            $colRes = $dbConn->query("SHOW FULL COLUMNS FROM `{$tName}`");
            if (!$colRes) {
                continue;
            }
            $pkCol = '';
            $fillableCols = [];
            $hasCreatedAt = false;
            $hasUpdatedAt = false;
            while ($c = $colRes->fetch_assoc()) {
                $cName = $c['Field'];
                $isPk = ($c['Key'] === 'PRI');
                $isAi = (stripos($c['Extra'], 'auto_increment') !== false);
                if ($cName === 'created_at') $hasCreatedAt = true;
                if ($cName === 'updated_at') $hasUpdatedAt = true;
                if ($isPk && empty($pkCol)) {
                    $pkCol = $cName;
                }
                if (!$isAi && !in_array($cName, ['created_at', 'updated_at'])) {
                    $fillableCols[] = $cName;
                }
            }
            $hasTimestamps = ($hasCreatedAt && $hasUpdatedAt);

            // 1. SINKRONKAN MODEL
            if ($doModel) {
                $modelName = table_to_model_name($tName);
                $matchedModelPath = null;
                $matchedModelFile = null;

                foreach ($modelFiles as $mf) {
                    $content = file_get_contents($mf);
                    if (preg_match('/\$table\s*=\s*[\'"]' . preg_quote($tName, '/') . '[\'"]/', $content)) {
                        $matchedModelFile = basename($mf);
                        $matchedModelPath = $mf;
                        break;
                    }
                    $base = basename($mf, '.php');
                    if (strcasecmp($base, $modelName) === 0 || strcasecmp($base, $tName) === 0) {
                        $matchedModelFile = basename($mf);
                        $matchedModelPath = $mf;
                    }
                }

                if ($matchedModelPath && is_file($matchedModelPath)) {
                    $existingCode = file_get_contents($matchedModelPath);
                    $newCode = update_existing_model_code($existingCode, $tName, $pkCol, $fillableCols, $hasTimestamps);
                    if (file_put_contents($matchedModelPath, $newCode) !== false) {
                        $modelsUpdated[] = $matchedModelFile;
                    } else {
                        $errors[] = "Gagal memperbarui file {$matchedModelFile}";
                    }
                } else {
                    $newCode = generate_model_code($modelName, $tName, $pkCol, $fillableCols, $hasTimestamps);
                    $targetPath = $modelsDir . '/' . $modelName . '.php';
                    if (file_put_contents($targetPath, $newCode) !== false) {
                        $modelsCreated[] = $modelName . '.php';
                        $modelFiles[] = $targetPath;
                    } else {
                        $errors[] = "Gagal membuat file {$modelName}.php";
                    }
                }
            }

            // 2. SINKRONKAN MIGRASI
            if ($doMigration) {
                $matchedMigrationFile = null;
                $singularTable = rtrim($tName, 's');
                foreach ($migrationFiles as $mf) {
                    $bName = basename($mf);
                    if (preg_match('/_create_' . preg_quote($tName, '/') . '_table\.sql$/i', $bName) ||
                        preg_match('/_create_' . preg_quote($tName . 's', '/') . '_table\.sql$/i', $bName) ||
                        ($singularTable !== $tName && preg_match('/_create_' . preg_quote($singularTable, '/') . '_table\.sql$/i', $bName))) {
                        $matchedMigrationFile = $bName;
                        break;
                    }
                }
                if (!$matchedMigrationFile) {
                    foreach ($migrationFiles as $mf) {
                        $mContent = file_get_contents($mf);
                        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($tName, '/') . '`?\s*\(/i', $mContent)) {
                            $matchedMigrationFile = basename($mf);
                            break;
                        }
                    }
                }

                $nowStr = date('Y-m-d H:i:s');
                if ($matchedMigrationFile) {
                    $checkStmt = $dbConn->prepare("SELECT id FROM migrations WHERE migration = ? LIMIT 1");
                    $checkStmt->bind_param("s", $matchedMigrationFile);
                    $checkStmt->execute();
                    $hasRec = $checkStmt->get_result()->fetch_assoc();
                    if (!$hasRec) {
                        $insStmt = $dbConn->prepare("INSERT INTO migrations (migration, ran_at) VALUES (?, ?)");
                        $insStmt->bind_param("ss", $matchedMigrationFile, $nowStr);
                        $insStmt->execute();
                        $migrationsRegistered[] = $matchedMigrationFile;
                    }
                } else {
                    $createRes = $dbConn->query("SHOW CREATE TABLE `{$tName}`");
                    if ($createRes) {
                        $cRow = $createRes->fetch_assoc();
                        $rawSql = $cRow['Create Table'] ?? '';
                        if (!empty($rawSql)) {
                            if (!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/i', $rawSql)) {
                                $rawSql = preg_replace('/CREATE\s+TABLE\s+(`?' . preg_quote($tName, '/') . '`?)/i', 'CREATE TABLE IF NOT EXISTS $1', $rawSql, 1);
                            }
                            $sqlContent = "-- create_{$tName}_table\n\n" . trim($rawSql) . ";\n";

                            $waktu = time() + $timeOffset;
                            $timeOffset++;
                            $migFilename = date('Y_m_d_His', $waktu) . "_create_{$tName}_table.sql";
                            $targetMigPath = $migrationsDir . '/' . $migFilename;

                            if (file_put_contents($targetMigPath, $sqlContent) !== false) {
                                $migrationsCreated[] = $migFilename;
                                $migrationFiles[] = $targetMigPath;

                                $insStmt = $dbConn->prepare("INSERT INTO migrations (migration, ran_at) VALUES (?, ?)");
                                $insStmt->bind_param("ss", $migFilename, $nowStr);
                                $insStmt->execute();
                            } else {
                                $errors[] = "Gagal membuat berkas migrasi {$migFilename}";
                            }
                        }
                    }
                }
            }
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Sinkronisasi kodingan berhasil dijalankan!',
            'models_created' => $modelsCreated,
            'models_updated' => $modelsUpdated,
            'migrations_created' => $migrationsCreated,
            'migrations_registered' => $migrationsRegistered,
            'errors' => $errors
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 12. DEFAULT ACTION: SCHEMA (Ambil seluruh skema database & relasi)
    // -------------------------------------------------------------
    // 1. Ambil seluruh tabel
    $tablesMeta = [];
    $statusRes = $dbConn->query("SHOW TABLE STATUS");
    if ($statusRes) {
        while ($sRow = $statusRes->fetch_assoc()) {
            $tablesMeta[$sRow['Name']] = [
                'engine' => $sRow['Engine'] ?? 'InnoDB',
                'rows' => (int) ($sRow['Rows'] ?? 0),
                'data_length' => (int) ($sRow['Data_length'] ?? 0),
                'collation' => $sRow['Collation'] ?? '',
                'comment' => $sRow['Comment'] ?? ''
            ];
        }
    }

    if (empty($tablesMeta)) {
        $tablesRes = $dbConn->query("SHOW TABLES");
        if ($tablesRes) {
            while ($tRow = $tablesRes->fetch_row()) {
                $tablesMeta[$tRow[0]] = [
                    'engine' => 'InnoDB',
                    'rows' => 0,
                    'data_length' => 0,
                    'collation' => '',
                    'comment' => ''
                ];
            }
        }
    }

    // 2. Ambil informasi kolom per tabel
    $tables = [];
    foreach ($tablesMeta as $tName => $tMeta) {
        $colRes = $dbConn->query("SHOW FULL COLUMNS FROM `" . str_replace("`", "``", $tName) . "`");
        $cols = [];
        $pks = [];

        if ($colRes) {
            while ($c = $colRes->fetch_assoc()) {
                $isPk = (strpos($c['Key'] ?? '', 'PRI') !== false);
                $isUnique = (strpos($c['Key'] ?? '', 'UNI') !== false);
                $isIndexed = ($c['Key'] ?? '') !== '';
                $isAi = (strpos($c['Extra'] ?? '', 'auto_increment') !== false);

                $cols[] = [
                    'name' => $c['Field'],
                    'type' => $c['Type'],
                    'null' => ($c['Null'] === 'YES'),
                    'key' => $c['Key'],
                    'is_pk' => $isPk,
                    'is_unique' => $isUnique,
                    'is_indexed' => $isIndexed,
                    'is_ai' => $isAi,
                    'default' => $c['Default'],
                    'extra' => $c['Extra'],
                    'comment' => $c['Comment'] ?? ''
                ];

                if ($isPk) {
                    $pks[] = $c['Field'];
                }
            }
        }

        $tables[$tName] = [
            'name' => $tName,
            'engine' => $tMeta['engine'],
            'rows' => $tMeta['rows'],
            'data_length' => $tMeta['data_length'],
            'pks' => $pks,
            'columns' => $cols
        ];
    }

    // 3. Ambil Foreign Key Eksplisit dari information_schema
    $relations = [];
    $existingRelKeys = [];

    $fkSql = "SELECT 
                k.TABLE_NAME, 
                k.COLUMN_NAME, 
                k.CONSTRAINT_NAME, 
                k.REFERENCED_TABLE_NAME, 
                k.REFERENCED_COLUMN_NAME,
                r.UPDATE_RULE,
                r.DELETE_RULE
              FROM information_schema.KEY_COLUMN_USAGE k
              LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS r 
                ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME 
               AND k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA
             WHERE k.TABLE_SCHEMA = ?
               AND k.REFERENCED_TABLE_NAME IS NOT NULL";
    $fkStmt = $dbConn->prepare($fkSql);
    if ($fkStmt) {
        $fkStmt->bind_param("s", $dbRow['db_name']);
        $fkStmt->execute();
        $fkRes = $fkStmt->get_result();
        while ($fk = $fkRes->fetch_assoc()) {
            $fromTable = $fk['TABLE_NAME'];
            $fromCol   = $fk['COLUMN_NAME'];
            $toTable   = $fk['REFERENCED_TABLE_NAME'];
            $toCol     = $fk['REFERENCED_COLUMN_NAME'];
            $relKey    = "{$fromTable}.{$fromCol}->{$toTable}.{$toCol}";

            $existingRelKeys[$relKey] = true;
            $relations[] = [
                'id' => md5($relKey),
                'from_table' => $fromTable,
                'from_column' => $fromCol,
                'to_table' => $toTable,
                'to_column' => $toCol,
                'type' => 'explicit',
                'label' => $fk['CONSTRAINT_NAME'] ?: 'FK',
                'constraint_name' => $fk['CONSTRAINT_NAME'],
                'on_delete' => $fk['DELETE_RULE'] ?: 'CASCADE',
                'on_update' => $fk['UPDATE_RULE'] ?: 'CASCADE'
            ];
        }
    }

    // 4. Deteksi Hubungan Konvensi (Inferred Relations) untuk mempermudah siswa
    foreach ($tables as $tName => $tData) {
        foreach ($tData['columns'] as $col) {
            $colName = strtolower($col['name']);
            if ($col['is_pk']) continue;

            $candidateTables = [];
            $targetCol = 'id';

            if (preg_match('/^(.+)_id$/', $colName, $m)) {
                $base = $m[1];
                $candidateTables = [
                    $base . 's',
                    $base . 'es',
                    $base,
                    rtrim($base, 's'),
                ];
                $targetCol = 'id';
            } elseif (preg_match('/^id_(.+)$/', $colName, $m)) {
                $base = $m[1];
                $candidateTables = [
                    $base,
                    $base . 's',
                    $base . 'es',
                ];
                $targetCol = 'id';
            } elseif (!in_array($colName, ['id', 'name', 'title', 'status', 'type', 'description', 'created_at', 'updated_at', 'deleted_at', 'password', 'email', 'migration', 'ran_at', 'remember_token'], true)) {
                $candidateTables = [
                    $colName . 's',
                    $colName . 'es',
                    $colName,
                ];
                $targetCol = 'id';
            }

            if (!empty($candidateTables)) {
                foreach ($candidateTables as $candTable) {
                    if (isset($tables[$candTable]) && $candTable !== $tName) {
                        $hasTargetCol = false;
                        foreach ($tables[$candTable]['columns'] as $tc) {
                            if (strcasecmp($tc['name'], $targetCol) === 0) {
                                $targetCol = $tc['name'];
                                $hasTargetCol = true;
                                break;
                            }
                            if (strcasecmp($tc['name'], $col['name']) === 0) {
                                $targetCol = $tc['name'];
                                $hasTargetCol = true;
                                break;
                            }
                            if ($tc['is_unique'] && strcasecmp($tc['name'], 'name') === 0 && (strpos($col['type'], 'char') !== false || strpos($col['type'], 'text') !== false)) {
                                $targetCol = $tc['name'];
                                $hasTargetCol = true;
                                break;
                            }
                        }

                        if ($hasTargetCol) {
                            $relKey = "{$tName}.{$col['name']}->{$candTable}.{$targetCol}";
                            if (!isset($existingRelKeys[$relKey])) {
                                $existingRelKeys[$relKey] = true;
                                $relations[] = [
                                    'id' => md5($relKey),
                                    'from_table' => $tName,
                                    'from_column' => $col['name'],
                                    'to_table' => $candTable,
                                    'to_column' => $targetCol,
                                    'type' => 'inferred',
                                    'label' => 'Konvensi ' . $col['name'],
                                    'constraint_name' => null
                                ];
                            }
                            break;
                        }
                    }
                }
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'database' => $dbRow['db_name'],
        'tables_count' => count($tables),
        'relations_count' => count($relations),
        'tables' => array_values($tables),
        'relations' => $relations
    ]);
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Operasi database gagal: ' . $e->getMessage()
    ]);
}
