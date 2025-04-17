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

// Selesai dan Undo
if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    $stmt = $conn->prepare("UPDATE task SET status = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

if (isset($_GET['undo'])) {
    $id = (int)$_GET['undo'];
    $stmt = $conn->prepare("UPDATE task SET status = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

// Hapus tugas
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM task WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

// Ambil data untuk edit
$edit_task = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM task WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    $edit_task = $edit_result->fetch_assoc();
    $stmt->close();
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

// Filter, cari, dan sortir
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
    $search_param = "%" . $conn->real_escape_string($search) . "%";
    $query .= " AND (task LIKE '$search_param' OR description LIKE '$search_param')";
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
            background-color: #f4f6f8;
            color: #333;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        h1 {
            color: #0066cc;
        }
        form {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        input[type="text"], textarea, select, input[type="date"] {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            margin-bottom: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        button {
            background-color: #28a745;
            border: none;
            padding: 10px 18px;
            color: white;
            border-radius: 8px;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
        .task-table-container {
            width: 100%;
            max-width: 650px;
            overflow-x: auto;
        }
        .task-table {
            width: 100%;
            overflow-y: scroll;
            max-height: 400px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            background: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr.done {
            text-decoration: line-through;
            background-color: #e9fbe9;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .filters {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="form-container">
            <h1>📝 To-Do List</h1>

            <!-- Form tambah atau edit -->
            <form method="post">
                <input type="hidden" name="id" value="<?= $edit_task['id'] ?? '' ?>">
                <label>Tugas:</label>
                <input type="text" name="task" value="<?= htmlspecialchars($edit_task['task'] ?? '') ?>" required>

                <label>Deskripsi:</label>
                <textarea name="description"><?= htmlspecialchars($edit_task['description'] ?? '') ?></textarea>

                <label>Prioritas:</label>
                <select name="priority">
                    <?php
                        $priorities = ['Low', 'Medium', 'High'];
                        $selected_priority = $edit_task['priority'] ?? 'Medium';
                        foreach ($priorities as $priority) {
                            $selected = ($selected_priority == $priority) ? 'selected' : '';
                            echo "<option value='$priority' $selected>$priority</option>";
                        }
                    ?>
                </select>

                <label>Jatuh Tempo:</label>
                <input type="date" name="due_date" value="<?= htmlspecialchars($edit_task['due_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>">

                <button type="submit" name="<?= $edit_task ? 'update_task' : 'add_task' ?>">
                    <?= $edit_task ? '💾 Update' : '➕ Tambah' ?>
                </button>
            </form>
        </div>

        <div class="task-table-container">
            <!-- Filter dan pencarian -->
            <div class="filters">
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
            </div>

            <!-- List tugas -->
            <div class="task-table">
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
                                        <a href="?complete=<?= $task['id'] ?>">✔️ Tandai Selesai</a>
                                    <?php else: ?>
                                        <a href="?undo=<?= $task['id'] ?>">↩️ Batalkan</a>
                                    <?php endif; ?>
                                    <a href="?edit=<?= $task['id'] ?>">✏️ Edit</a>
                                    <a href="?delete=<?= $task['id'] ?>" onclick="return confirm('Yakin hapus tugas ini?')">🗑️ Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>