<?php
include 'config.php';

$query = "SELECT id, name, email, password FROM users";
$result = mysqli_query($conn, $query);

$employees = [];
while ($row = mysqli_fetch_assoc($result)) {
    $employees[] = $row;
}

header('Content-Type: application/json');
echo json_encode($employees);

