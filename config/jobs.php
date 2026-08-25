<?php
// Antrean pekerjaan git.
//
// PHP web di aaPanel dimatikan exec()-nya lewat disable_functions, jadi panel
// tidak menjalankan git sendiri melainkan menitipkannya di tabel job_queue.
// tools/worker.php yang dipanggil cron yang mengerjakan, lalu menulis hasilnya
// kembali ke baris yang sama.

/**
 * Mengambil project milik user, atau null bila bukan miliknya.
 *
 * $asAdmin melewati pemeriksaan kepemilikan. Nilainya HARUS berasal dari
 * is_admin() atas data user yang dibaca ulang dari database, bukan dari
 * sesi -- kalau tidak, peran admin bisa dipalsukan lewat sesi basi.
 */
function find_project($conn, int $project_id, int $user_id, bool $asAdmin = false): ?array
{
    if ($asAdmin) {
        $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $project_id, $user_id);
    }
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** Pekerjaan yang belum selesai untuk sebuah project, bila ada. */
function active_job($conn, int $project_id): ?array
{
    $stmt = $conn->prepare(
        "SELECT * FROM job_queue
          WHERE project_id = ? AND status IN ('pending','running')
          ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param("i", $project_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc() ?: null;
}

/**
 * Menitipkan pekerjaan. Bila untuk project itu masih ada yang berjalan,
 * yang lama dikembalikan supaya klik berulang tidak menumpuk antrean.
 */
function queue_job($conn, int $project_id, int $user_id, string $action): array
{
    if ($existing = active_job($conn, $project_id)) {
        return ['job' => $existing, 'created' => false];
    }

    $stmt = $conn->prepare(
        "INSERT INTO job_queue (project_id, user_id, action) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $project_id, $user_id, $action);
    $stmt->execute();

    $id = $conn->insert_id;

    $stmt = $conn->prepare("SELECT * FROM job_queue WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    return ['job' => $stmt->get_result()->fetch_assoc(), 'created' => true];
}

/** Bentuk JSON yang dikirim ke browser. */
function job_payload(array $job): array
{
    return [
        'job_id'  => (int) $job['id'],
        'action'  => $job['action'],
        'status'  => $job['status'],
        'output'  => $job['output'],
        'message' => job_message($job),
    ];
}

function job_message(array $job): string
{
    $label = $job['action'] === 'clone' ? 'Clone' : 'Pull';

    return match ($job['status']) {
        'pending' => "$label menunggu giliran",
        'running' => "$label sedang berjalan",
        'success' => "$label selesai.",
        'failed'  => "$label gagal.",
        default   => $label,
    };
}
