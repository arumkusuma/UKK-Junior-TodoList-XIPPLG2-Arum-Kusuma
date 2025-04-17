<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'ukk2025_todolist';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Tambah tugas
if (isset($_POST['add_task'])) {
    $task = trim($_POST['task']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $due_date = $_POST['due_date'];

    if ($task !== "" && in_array($priority, ['Low', 'Medium', 'High'])) {
        $stmt = $conn->prepare("INSERT INTO task (task, description, priority, due_date, status) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("ssss", $task, $description, $priority, $due_date);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Tandai selesai atau undo
if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    $conn->query("UPDATE task SET status = 1 WHERE id = $id");
}
if (isset($_GET['undo'])) {
    $id = (int)$_GET['undo'];
    $conn->query("UPDATE task SET status = 0 WHERE id = $id");
}

// Hapus tugas
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM task WHERE id = $id");
}

// Ambil data edit
$edit_task = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM task WHERE id = $id");
    $edit_task = $result->fetch_assoc();
}

// Update tugas
if (isset($_POST['update_task'])) {
    $id = (int)$_POST['id'];
    $task = trim($_POST['task']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $due_date = $_POST['due_date'];

    if ($task !== "" && in_array($priority, ['Low', 'Medium', 'High'])) {
        $stmt = $conn->prepare("UPDATE task SET task = ?, description = ?, priority = ?, due_date = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $task, $description, $priority, $due_date, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Filter, cari, sortir
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';
$query = "SELECT * FROM task WHERE 1=1";

if ($filter === 'completed') {
    $query .= " AND status = 1";
} elseif ($filter === 'pending') {
    $query .= " AND status = 0";
}
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (task LIKE '%$search%' OR description LIKE '%$search%')";
}
if ($sort === 'priority_asc') {
    $query .= " ORDER BY FIELD(priority, 'Low', 'Medium', 'High')";
} elseif ($sort === 'priority_desc') {
    $query .= " ORDER BY FIELD(priority, 'High', 'Medium', 'Low')";
} else {
    $query .= " ORDER BY due_date ASC";
}
$tasks = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>To-Do List Stylish</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background: #f1f3f6;
        }

        header {
            background-color: #0066cc;
            color: white;
            padding: 20px 40px;
            text-align: center;
        }

        main {
            display: flex;
            flex-direction: column;
            padding: 30px 40px;
            gap: 30px;
            max-width: 1200px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        h2 {
            margin-top: 0;
        }

        form input[type="text"],
        form textarea,
        form select,
        form input[type="date"] {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            margin-bottom: 16px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        form button {
            background-color: #28a745;
            border: none;
            padding: 10px 20px;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
        }

        form button:hover {
            background-color: #218838;
        }

        .filters form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .filters select,
        .filters input[type="text"] {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .task-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .task-table th,
        .task-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .task-table th {
            background-color: #f9fafc;
        }

        .task-table tr.done {
            text-decoration: line-through;
            background-color: #e0f7e0;
        }

        .task-table a {
            margin-right: 10px;
            text-decoration: none;
            color: #007bff;
        }

        .task-table a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            main {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<header>
    <h1>📝 To-Do List</h1>
</header>

<main>
    <!-- Form Tambah/Edit -->
    <section class="card">
        <h2><?= $edit_task ? "✏️ Edit Tugas" : "➕ Tambah Tugas" ?></h2>
        <form method="post">
            <input type="hidden" name="id" value="<?= $edit_task['id'] ?? '' ?>">
            <label>Nama Tugas:</label>
            <input type="text" name="task" value="<?= htmlspecialchars($edit_task['task'] ?? '') ?>" required>

            <label>Deskripsi:</label>
            <textarea name="description"><?= htmlspecialchars($edit_task['description'] ?? '') ?></textarea>

            <label>Prioritas:</label>
            <select name="priority">
                <?php
                    $priorities = ['Low', 'Medium', 'High'];
                    $selected = $edit_task['priority'] ?? 'Medium';
                    foreach ($priorities as $p) {
                        echo "<option value='$p'" . ($p === $selected ? " selected" : "") . ">$p</option>";
                    }
                ?>
            </select>

            <label>Jatuh Tempo:</label>
            <input type="date" name="due_date" value="<?= $edit_task['due_date'] ?? '' ?>" min="<?= date('Y-m-d') ?>">

            <button type="submit" name="<?= $edit_task ? 'update_task' : 'add_task' ?>">
                <?= $edit_task ? '💾 Update' : '➕ Tambah' ?>
            </button>
        </form>
    </section>

    <!-- Filter dan Pencarian -->
    <section class="card filters">
        <form method="get">
            <select name="filter">
                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="completed" <?= $filter == 'completed' ? 'selected' : '' ?>>Selesai</option>
                <option value="pending" <?= $filter == 'pending' ? 'selected' : '' ?>>Belum Selesai</option>
            </select>
            <select name="sort">
                <option value="">Urutkan</option>
                <option value="priority_asc" <?= $sort == 'priority_asc' ? 'selected' : '' ?>>Prioritas Rendah → Tinggi</option>
                <option value="priority_desc" <?= $sort == 'priority_desc' ? 'selected' : '' ?>>Prioritas Tinggi → Rendah</option>
            </select>
            <input type="text" name="search" placeholder="Cari tugas..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">🔍 Cari</button>
        </form>
    </section>

    <!-- Daftar Tugas -->
    <section class="card task-table">
        <table>
            <thead>
                <tr>
                    <th>Tugas</th>
                    <th>Deskripsi</th>
                    <th>Jatuh Tempo</th>
                    <th>Prioritas</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($task = $tasks->fetch_assoc()): ?>
                    <tr class="<?= $task['status'] ? 'done' : '' ?>">
                        <td><?= htmlspecialchars($task['task']) ?></td>
                        <td><?= htmlspecialchars($task['description']) ?></td>
                        <td><?= htmlspecialchars($task['due_date']) ?></td>
                        <td><?= htmlspecialchars($task['priority']) ?></td>
                        <td><?= $task['status'] ? '✅ Selesai' : '❗ Belum' ?></td>
                        <td>
                            <?php if (!$task['status']): ?>
                                <a href="?complete=<?= $task['id'] ?>">✔️</a>
                            <?php else: ?>
                                <a href="?undo=<?= $task['id'] ?>">↩️</a>
                            <?php endif; ?>
                            <a href="?edit=<?= $task['id'] ?>">✏️</a>
                            <a href="?delete=<?= $task['id'] ?>" onclick="return confirm('Yakin ingin menghapus tugas ini?')">🗑️</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </section>
</main>

</body>
</html>
