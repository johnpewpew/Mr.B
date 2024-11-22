<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="employees.css">
    <title>Employees</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="container">
        <!-- Employee Details Section -->
        <div class="employee-details">
            <h2>Employee Details</h2>
            <button id="register-btn">Register Employee</button>
        </div>

        <!-- Employee List Section -->
        <div class="employee-list">
            <h2>Employee List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone No</th>
                        <th>Age</th>
                        <th>BirthDate</th>
                        <th>Actions</th> <!-- New column for Actions -->
                    </tr>
                </thead>
                <tbody id="employee-list-body">
                    <?php
                    // Database connection
                    $servername = "localhost";
                    $username = "root";
                    $password = "";
                    $dbname = "database_pos";

                    $conn = new mysqli($servername, $username, $password, $dbname);

                    // Check connection
                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }

                    // Retrieve employees from database
                    $sql = "SELECT id, name, email, phone_no, age, birthdate FROM employees";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        // Output data for each row
                        while($row = $result->fetch_assoc()) {
                            echo "<tr data-id='{$row['id']}'>
                                    <td>{$row['name']}</td>
                                    <td>{$row['email']}</td>
                                    <td>{$row['phone_no']}</td>
                                    <td>{$row['age']}</td>
                                    <td>{$row['birthdate']}</td>
                                    <td><button class='delete-btn'>Delete</button></td>
                                  </tr>";
                        }
                    } 

                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- The Modal for Employee Registration -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Register New Employee</h2>
            <form id="register-form">
                <div>
                    <label for="reg-name">Name</label>
                    <input type="text" id="reg-name" name="name" placeholder="Enter name" required>
                </div>
                <div>
                    <label for="reg-email">Email</label>
                    <input type="email" id="reg-email" name="email" placeholder="Enter email" required>
                </div>
                <div>
                    <label for="reg-phone-no">Phone No</label>
                    <input type="text" id="reg-phone-no" name="phone_no" placeholder="Enter phone no" required>
                </div>
                <div>
                    <label for="reg-bday">Birth Date</label>
                    <input type="date" id="reg-bday" name="bday" required>
                </div>
                <div>
                    <label for="reg-age">Age</label>
                    <input type="number" id="reg-age" name="age" placeholder="Enter age" required>
                </div>
                <button type="submit">Register</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        var modal = document.getElementById("employeeModal");
        var registerBtn = document.getElementById("register-btn");
        var closeBtn = document.getElementsByClassName("close")[0];

        registerBtn.onclick = function() {
            modal.style.display = "block";
        }

        closeBtn.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Form submission
        document.getElementById("register-form").addEventListener("submit", function(event) {
            event.preventDefault();

            const formData = new FormData(document.getElementById("register-form"));

            fetch('add_employee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes("successfully")) {
                    const employeeListBody = document.getElementById("employee-list-body");
                    const newRow = employeeListBody.insertRow();
                    newRow.innerHTML = `
                        <td>${formData.get('name')}</td>
                        <td>${formData.get('email')}</td>
                        <td>${formData.get('phone_no')}</td>
                        <td>${formData.get('age')}</td>
                        <td>${formData.get('bday')}</td>
                        <td><button class='delete-btn'>Delete</button></td>
                    `;

                    document.getElementById("register-form").reset();
                    modal.style.display = "none";
                    alert("Employee Registered Successfully!");
                } else {
                    alert("Error: " + data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("There was an error registering the employee.");
            });
        });

        // Handle delete button click
        document.getElementById("employee-list-body").addEventListener("click", function(event) {
            if (event.target && event.target.classList.contains('delete-btn')) {
                var row = event.target.closest("tr");
                var employeeId = row.getAttribute("data-id");

                if (confirm("Are you sure you want to delete this employee?")) {
                    fetch('delete_employee.php', {
                        method: 'POST',
                        body: JSON.stringify({ id: employeeId }),
                        headers: { 'Content-Type': 'application/json' }
                    })
                    .then(response => response.text())
                    .then(data => {
                        if (data.includes("success")) {
                            row.remove();
                            alert("Employee deleted successfully.");
                        } else {
                            alert("Error deleting employee.");
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert("There was an error deleting the employee.");
                    });
                }
            }
        });
    </script>
</body>
</html>
