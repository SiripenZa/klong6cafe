
<?php
require_once '../conn.php';

$sql = "SELECT drink_id, drink_name, price FROM drink";
$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

echo "จำนวนเครื่องดื่ม: " . $result->num_rows . "<br><br>";

while ($row = $result->fetch_assoc()) {
    echo "รหัส: " . htmlspecialchars($row['drink_id']) .
         " | ชื่อ: " . htmlspecialchars($row['drink_name']) .
         " | ราคา: " . $row['price'] . " บาท<br>";
}
?>
