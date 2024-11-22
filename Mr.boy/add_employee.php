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

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phoneNo = $_POST['phone_no'];
    $bday = $_POST['bday'];
    $age = $_POST['age'];

    // Insert employee into database
    $sql = "INSERT INTO employees (name, email, phone_no, birthdate, age)
            VALUES ('$name', '$email', '$phoneNo', '$bday', $age)";

    if ($conn->query($sql) === TRUE) {
        echo "Employee added successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();

