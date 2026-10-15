
<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =========================
   ระงับ / เปิดใช้งานลูกค้า
========================= */

if (isset($_GET['action']) && isset($_GET['id'])) {

    $customer_id = $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'suspend') {

        $stmt = $conn->prepare("
            UPDATE customers
            SET status = 'suspended'
            WHERE customer_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("s", $customer_id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: customers.php");
        exit;

    } elseif ($action === 'activate') {

        $stmt = $conn->prepare("
            UPDATE customers
            SET status = 'active'
            WHERE customer_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("s", $customer_id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: customers.php");
        exit;
    }
}


/* =========================
   ดึงข้อมูลลูกค้า
========================= */

$sql = "SELECT
            customer_id,
            firstname,
            lastname,
            email,
            telephone,
            address,
            created_at,
            status
        FROM customers
        ORDER BY created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>ลูกค้า | KLONG 6 CAFE</title>

<link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap"
      rel="stylesheet">

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #fff0f3;
    color: #632b3b;
    font-family: 'Mali', cursive;
}

/* =========================
   Header
========================= */

header {
    background: #ffd6e2;
    padding: 25px 20px;
    text-align: center;
}

header h1 {
    margin: 0;
    color: #c1123f;
}


/* =========================
   Container
========================= */

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 18px;
}


/* =========================
   Toolbar
========================= */

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.toolbar h2 {
    margin: 0;
    color: #c1123f;
}

.toolbar-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}


/* =========================
   Buttons
   เหมือน cake.php
========================= */

.btn {
    display: inline-block;
    border: 1px solid transparent;
    border-radius: 25px;
    padding: 10px 20px;
    text-decoration: none;
    font-family: inherit;
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    transition: .3s;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 12px rgba(193, 18, 63, .18);
}


/* ปุ่มย้อนกลับ */

.btn-home {
    background: white;
    color: #632b3b;
    border-color: #f3c4d1;
}

.btn-home:hover {
    background: #c1123f;
    color: white;
}


/* ปุ่มแก้ไข */

.btn-edit {
    background: #ffb6c8;
    color: #632b3b;
}

.btn-edit:hover {
    background: #c1123f;
    color: white;
}


/* ปุ่มระงับ */

.btn-suspend {
    background: #ffb6c8;
    color: #632b3b;
}

.btn-suspend:hover {
    background: #c1123f;
    color: white;
}


/* ปุ่มเปิดใช้งาน */

.btn-activate {
    background: #dcf7e5;
    color: #18743a;
}

.btn-activate:hover {
    background: #18743a;
    color: white;
}


/* =========================
   Table
========================= */

.table-wrap {
    background: white;
    border-radius: 16px;
    overflow-x: auto;
    box-shadow: 0 4px 18px rgba(193, 18, 63, .08);
}

table {
    width: 100%;
    min-width: 1200px;
    border-collapse: collapse;
}

th,
td {
    padding: 14px 12px;
    border-bottom: 1px solid #f3dce3;
    text-align: left;
    vertical-align: middle;
}

th {
    background: #ffd6e2;
    color: #8e1637;
}


/* =========================
   Status
========================= */

.status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    background: #dcf7e5;
    color: #18743a;
    white-space: nowrap;
}

.status-suspended {
    background: #ffe0e6;
    color: #c1123f;
}


/* =========================
   Actions
========================= */

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.actions a {
    white-space: nowrap;
}


/* =========================
   Empty
========================= */

.empty {
    text-align: center;
    padding: 35px;
}


/* =========================
   Responsive
========================= */

@media (max-width: 600px) {

    header h1 {
        font-size: 24px;
    }

    .container {
        margin-top: 20px;
    }

}

</style>

</head>

<body>


<header>

    <h1>🍒 KLONG 6 CAFE 🍰</h1>

    <p>ข้อมูลสมาชิกของร้าน</p>

</header>


<main class="container">


    <!-- Toolbar -->

    <div class="toolbar">

        <h2>👥 จัดการลูกค้า</h2>

        <div class="toolbar-actions">

            <a href="dashboard.php" class="btn btn-home">
                ← กลับหน้าแอดมิน
            </a>

        </div>

    </div>


    <!-- ตาราง -->

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Customer ID</th>

                    <th>ชื่อ</th>

                    <th>นามสกุล</th>

                    <th>อีเมล</th>

                    <th>เบอร์โทร</th>

                    <th>ที่อยู่</th>

                    <th>สมัครเมื่อ</th>

                    <th>สถานะ</th>

                    <th>จัดการ</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($customer = $result->fetch_assoc()): ?>

                    <tr>


                        <!-- Customer ID -->

                        <td>

                            <strong>
                                <?= h($customer['customer_id']) ?>
                            </strong>

                        </td>


                        <!-- ชื่อ -->

                        <td>

                            <?= h($customer['firstname']) ?>

                        </td>


                        <!-- นามสกุล -->

                        <td>

                            <?= h($customer['lastname']) ?>

                        </td>


                        <!-- อีเมล -->

                        <td>

                            <?= h($customer['email']) ?>

                        </td>


                        <!-- เบอร์โทร -->

                        <td>

                            <?= h($customer['telephone']) ?>

                        </td>


                        <!-- ที่อยู่ -->

                        <td style="color:#777;">

                            <?= h($customer['address']) ?>

                        </td>


                        <!-- วันที่สมัคร -->

                        <td>

                            <?= h($customer['created_at']) ?>

                        </td>


                        <!-- สถานะ -->

                        <td>

                            <?php if ($customer['status'] === 'suspended'): ?>

                                <span class="status status-suspended">
                                    🔴 ระงับการใช้งาน
                                </span>

                            <?php else: ?>

                                <span class="status">
                                    🟢 ใช้งานปกติ
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- จัดการ -->

                        <td>

                            <div class="actions">


                                <!-- แก้ไข -->

                                <a
                                    href="edit_customer.php?id=<?= urlencode($customer['customer_id']) ?>"
                                    class="btn btn-edit"
                                >
                                    แก้ไข
                                </a>


                                <?php if ($customer['status'] === 'suspended'): ?>

                                    <!-- เปิดใช้งาน -->

                                    <a
                                        href="customers.php?action=activate&id=<?= urlencode($customer['customer_id']) ?>"
                                        class="btn btn-activate"
                                        onclick="return confirm('ต้องการเปิดใช้งานลูกค้าคนนี้หรือไม่?');"
                                    >
                                        🟢 เปิดใช้งาน
                                    </a>

                                <?php else: ?>

                                    <!-- ระงับ -->

                                    <a
                                        href="customers.php?action=suspend&id=<?= urlencode($customer['customer_id']) ?>"
                                        class="btn btn-suspend"
                                        onclick="return confirm('ต้องการระงับการใช้งานลูกค้าคนนี้หรือไม่?');"
                                    >
                                        🔴 ระงับ
                                    </a>

                                <?php endif; ?>


                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>


            <?php else: ?>

                <tr>

                    <td colspan="9" class="empty">

                        <div style="font-size:45px;">
                            👥
                        </div>

                        <strong>
                            ยังไม่มีสมาชิกในระบบ
                        </strong>

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>


</body>

</html>

<?php
$conn->close();
?>