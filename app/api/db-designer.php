<?php
/**
 * API Backend untuk Desainer Skema Database ala SQLyog
 * Mengambil metadata skema tabel, kolom, PK, relasi FK (eksplisit & inferensi), serta pratinjau data.
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
$sql = "SELECT d.id, d.db_name, d.db_host, d.db_port, du.username, du.password
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

// -------------------------------------------------------------
// 1. ACTION: PREVIEW (Ambil 10 baris pertama suatu tabel)
// -------------------------------------------------------------
if ($action === 'preview') {
    $tableName = trim($_REQUEST['table'] ?? '');
    if ($tableName === '') {
        echo json_encode(['ok' => false, 'error' => 'Nama tabel tidak boleh kosong.']);
        exit;
    }

    // Validasi bahwa tabel memang ada di database siswa (mencegah SQL injection)
    $validTables = [];
    $showRes = $dbConn->query("SHOW TABLES");
    if ($showRes) {
        while ($r = $showRes->fetch_row()) {
            $validTables[] = $r[0];
        }
    }
    if (!in_array($tableName, $validTables, true)) {
        echo json_encode(['ok' => false, 'error' => "Tabel '$tableName' tidak ditemukan."]);
        exit;
    }

    // Hitung total baris
    $countRes = $dbConn->query("SELECT COUNT(*) AS total FROM `" . str_replace("`", "``", $tableName) . "`");
    $totalRows = $countRes ? (int) ($countRes->fetch_assoc()['total'] ?? 0) : 0;

    // Ambil maksimal 10 baris
    $previewQuery = $dbConn->query("SELECT * FROM `" . str_replace("`", "``", $tableName) . "` LIMIT 10");
    $columns = [];
    $rows = [];

    if ($previewQuery) {
        $fields = $previewQuery->fetch_fields();
        foreach ($fields as $f) {
            $columns[] = $f->name;
        }

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
        'columns' => $columns,
        'rows' => $rows
    ]);
    exit;
}

// -------------------------------------------------------------
// 2. ACTION: SCHEMA (Ambil seluruh tabel, kolom, PK, dan relasi)
// -------------------------------------------------------------
try {
    // 1. Ambil status tabel (Engine, Rows, Data_length, Comments)
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

    // Jika SHOW TABLE STATUS kosong, gunakan SHOW TABLES
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

                $cols[] = [
                    'name' => $c['Field'],
                    'type' => $c['Type'],
                    'null' => ($c['Null'] === 'YES'),
                    'key' => $c['Key'],
                    'is_pk' => $isPk,
                    'is_unique' => $isUnique,
                    'is_indexed' => $isIndexed,
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
                TABLE_NAME, 
                COLUMN_NAME, 
                CONSTRAINT_NAME, 
                REFERENCED_TABLE_NAME, 
                REFERENCED_COLUMN_NAME
              FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL";
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
                'label' => $fk['CONSTRAINT_NAME'] ?: 'FK'
            ];
        }
    }

    // 4. Deteksi Hubungan Konvensi (Inferred Relations) untuk mempermudah siswa
    // Contoh: siswa membuat kolom 'role_id' di tabel users -> mengarah ke tabel roles.id
    // Atau 'id_kategori' di tabel produk -> mengarah ke tabel kategori.id / kategori.id_kategori
    $tableNames = array_keys($tables);

    foreach ($tables as $tName => $tData) {
        foreach ($tData['columns'] as $col) {
            $colName = strtolower($col['name']);
            if ($col['is_pk']) continue; // Lewati jika kolom ini adalah PK tabel itu sendiri

            $candidateTables = [];
            $targetCol = 'id';

            // Pola 1: role_id, user_id, kategori_barang_id
            if (preg_match('/^(.+)_id$/', $colName, $m)) {
                $base = $m[1];
                $candidateTables = [
                    $base . 's',       // role -> roles
                    $base . 'es',      // class -> classes
                    $base,             // role -> role
                    rtrim($base, 's'), // users -> user
                ];
                $targetCol = 'id';
            }
            // Pola 2: id_role, id_kategori, id_user (gaya penamaan umum Indonesia)
            elseif (preg_match('/^id_(.+)$/', $colName, $m)) {
                $base = $m[1];
                $candidateTables = [
                    $base,
                    $base . 's',
                    $base . 'es',
                ];
                $targetCol = 'id';
            }
            // Pola 3: Kolom entitas tunggal (misal kolom 'role' pada tabel 'users' -> tabel 'roles')
            elseif (!in_array($colName, ['id', 'name', 'title', 'status', 'type', 'description', 'created_at', 'updated_at', 'deleted_at', 'password', 'email', 'migration', 'ran_at', 'remember_token'], true)) {
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
                        // Cek apakah target punya kolom targetCol atau kolom unik yang cocok
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
                                    'type' => 'inferred', // Konvensi penamaan
                                    'label' => 'Konvensi ' . $col['name']
                                ];
                            }
                            break; // Cukup temukan 1 target paling cocok
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
        'error' => 'Gagal membaca skema: ' . $e->getMessage()
    ]);
}
