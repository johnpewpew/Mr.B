<?php
// Database connection
$servername = "localhost";
$username = "root"; // Use your DB username
$password = ""; // Use your DB password
$dbname = "database_pos";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the ID of the employee to delete
$data = json_decode(file_get_contents("php://input"), true);
$employeeId = $data['id'];

// Delete employee from the database
$sql = "DELETE FROM employees WHERE id = $employeeId";

if ($conn->query($sql) === TRUE) {
    echo "Employee deleted successfully";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();

