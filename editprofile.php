<?php
session_start();
require_once 'conn.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (string)$_SESSION['customer_id'];
$csrfToken = $_SESSION['profile_csrf'] ??= bin2hex(random_bytes(32));
$error = '';

$stmt = $conn->prepare('SELECT firstname, lastname, email, telephone, address FROM customers WHERE customer_id = ? LIMIT 1');

if (!$stmt) {
    http_response_code(500);
    exit('ไม่สามารถโหลดข้อมูลโปรไฟล์ได้');
}

$stmt->bind_param('s', $customerId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    header('Location: logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $profile = compact('firstname', 'lastname', 'email', 'telephone', 'address');

    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง';
    } elseif ($firstname === '' || $lastname === '' || $telephone === '') {
        $error = 'กรุณากรอกชื่อ นามสกุล และเบอร์โทรศัพท์ให้ครบ';
    } elseif (!preg_match('/^[0-9]{10}$/', $telephone)) {
        $error = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'กรุณากรอกอีเมลให้ถูกต้อง';
    } else {
        $check = $conn->prepare('SELECT customer_id FROM customers WHERE email = ? AND customer_id <> ? LIMIT 1');

        if (!$check) {
            $error = 'ไม่สามารถตรวจสอบอีเมลได้ กรุณาลองใหม่';
        } else {
            $check->bind_param('ss', $email, $customerId);
            $check->execute();
            $emailExists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($emailExists) {
                $error = 'อีเมลนี้ถูกใช้งานแล้ว';
            } else {
                $update = $conn->prepare('UPDATE customers SET firstname = ?, lastname = ?, email = ?, telephone = ?, address = ? WHERE customer_id = ?');

                if (!$update) {
                    $error = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่';
                } else {
                    $update->bind_param('ssssss', $firstname, $lastname, $email, $telephone, $address, $customerId);

                    if ($update->execute()) {
                        $_SESSION['customer_firstname'] = $firstname;
                        $_SESSION['customer_lastname'] = $lastname;
                        $_SESSION['customer_email'] = $email;
                        $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
                        $update->close();
                        header('Location: viewprofile.php?updated=1');
                        exit;
                    }

                    $update->close();
                    $error = 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่';
                }
            }
        }
    }
}

$escape = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขโปรไฟล์ | Klong 6 Cafe</title>
    <link href="https://fonts.googleapis.com/css2?family=Mali:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pink: #ed367f;
            --pink-dark: #c51b62;
            --pink-soft: #fff0f6;
            --ink: #632b43;
            --line: #ffd0e0; 
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(145deg, #fff7fa, #ffe6ef);
            color: var(--ink);
            font-family: 'Mali', sans-serif; 
        }

        .site-header { 
            padding: 18px 5%;
            background: linear-gradient(120deg, #ff8fb8, #ed367f);
            color: #fff; 
        }

        .header-inner { 
            width: min(1180px, 100%);
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px; 
        }

        .brand { 
            color: inherit;
            font-size: 22px;
            font-weight: 700;
            text-decoration: none;
        }

        .header-link, .button {
             display: inline-flex;
             align-items: center;
             justify-content: center;
             min-height: 42px;
             padding: 9px 16px;
             border: 1px solid var(--line);
             border-radius: 6px;
             background: #fff;
             color: var(--pink-dark);
             font: inherit;
             font-size: 13px;
             font-weight: 700;
             text-decoration: none;
             cursor: pointer; 
            }

        .page { 
            width: min(760px, 92%);
            margin: 42px auto;
        }

        .edit-card { 
            overflow: hidden; 
            border: 1px solid var(--line); 
            border-radius: 12px; 
            background: rgba(255,255,255,.94); 
            box-shadow: 0 16px 40px rgba(174,45,96,.12); 
        }

        .card-heading { 
            padding: 24px 28px; 
            border-bottom: 1px solid var(--line); 
            background: var(--pink-soft); 
        }

        .card-heading h1 { 
            margin: 0; color: var(--pink-dark); 
            font-size: 27px; 
        }

        .form-body { 
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr)); 
            gap: 16px; 
            padding: 26px 28px; 
        }

        .field { 
            display: grid; 
            gap: 6px; 
        }

        .field-wide { 
            grid-column: 1 / -1; 
        }

        label { 
            font-size: 13px; 
            font-weight: 600; 
        }

        input, textarea { 
            width: 100%; 
            padding: 11px 13px; 
            border: 1px solid #edbfd0; 
            border-radius: 6px; 
            background: #fff; 
            color: var(--ink); 
            font: inherit; font-size: 14px; 
        }

        input:focus, textarea:focus { 
            outline: 2px solid rgba(237,54,127,.2); 
            border-color: var(--pink); 
        }

        textarea { 
            min-height: 110px; 
            resize: vertical; 
    }

        .error { 
            margin: 20px 28px 0; 
            padding: 11px 14px; 
            border: 1px solid #ffc4d3; 
            border-radius: 6px; 
            background: #fff0f3; 
            color: #a3194f; 
            font-size: 13px; 
        }

        .form-actions { 
            display: flex; 
            justify-content: flex-end; 
            gap: 10px; 
            padding: 0 28px 26px; 
        }

        .button-primary { 
            border-color: var(--pink); 
            background: var(--pink); 
            color: #fff; 
        }

        .button-primary:hover { 
            background: var(--pink-dark); 
        }
        
        @media (max-width: 600px) { .site-header { padding: 15px 4%; } .brand { font-size: 18px; } .page { margin: 24px auto; } .card-heading { padding: 20px; } .card-heading h1 { font-size: 22px; } .form-body { grid-template-columns: 1fr; padding: 20px; } .field-wide { grid-column: auto; } .error { margin: 16px 20px 0; } .form-actions { padding: 0 20px 20px; } }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="viewprofile.php">🍒 KLONG 6 CAFE</a>
            <a class="header-link" href="Home.php">กลับหน้าหลัก</a>
        </div>
    </header>
    <main class="page">
        <section class="edit-card">
            <div class="card-heading"><h1>แก้ไขโปรไฟล์</h1></div>
            <?php if ($error !== ''): ?>
                <div class="error" role="alert"><?= $escape($error) ?></div>
            <?php endif; ?>
            <form method="post" action="editprofile.php" class="form-body">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <div class="field"><label for="firstname">ชื่อ</label><input id="firstname" name="firstname" value="<?= $escape($profile['firstname']) ?>" autocomplete="given-name" required></div>
                <div class="field"><label for="lastname">นามสกุล</label><input id="lastname" name="lastname" value="<?= $escape($profile['lastname']) ?>" autocomplete="family-name" required></div>
                <div class="field"><label for="email">อีเมล</label><input id="email" type="email" name="email" value="<?= $escape($profile['email']) ?>" autocomplete="email" required></div>
                <div class="field"><label for="telephone">เบอร์โทรศัพท์</label><input id="telephone" type="tel" name="telephone" value="<?= $escape($profile['telephone']) ?>" autocomplete="tel" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" title="กรอกตัวเลข 10 หลัก" required></div>
                <div class="field field-wide"><label for="address">ที่อยู่</label><textarea id="address" name="address" autocomplete="street-address"><?= $escape($profile['address']) ?></textarea></div>
                <div class="form-actions field-wide">
                    <a class="button" href="viewprofile.php">ยกเลิก</a>
                    <button class="button button-primary" type="submit">บันทึกข้อมูล</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>