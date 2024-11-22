<?php
// Include database configuration
include 'config.php';
session_start();

$error = []; // Initialize an array to store error messages

if (isset($_POST['submit'])) {
    // Sanitize and escape user input
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass = md5($_POST['password']); // Use stronger hashing methods like bcrypt in production

    // Query to check user credentials
    $select = "SELECT * FROM users WHERE email = '$email' AND password = '$pass'";
    $result = mysqli_query($conn, $select);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result);

        // Redirect based on user type
        if ($row['user_type'] == 'admin') {
            $_SESSION['admin_name'] = $row['name'];
            header('location: admin_dash.php');
            exit(); // Ensure no further code is executed
        } elseif ($row['user_type'] == 'user') {
            $_SESSION['user_name'] = $row['name'];
            header('location: order_management.php');
            exit(); // Ensure no further code is executed
        }
    } else {
        $error[] = 'Incorrect email or password!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Sign Up</title>
    <link rel="stylesheet" href="Loginstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="wrapper">
        <span class="bg-animate"></span>
        <span class="bg-animate2"></span>

        <div class="form-box login">
        <h2 class="login-header animation" style="--data:0;"></h2>
            <form action="" method="POST">
                <div class="input-box animation" style="--data:1;">
                    <input type="text" name="email" placeholder="Email" required>
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="input-box animation" style="--data:3;">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class="fa-solid fa-lock"></i>
                </div>
                <button type="submit" name="submit" class="btn animation" style="--data:4">Login</button>

                <?php
                if (!empty($error)) {
                    foreach ($error as $msg) {
                        echo "<p class='error-msg' style='color: red; text-align: center;'>$msg</p>";
                    }
                }
                ?>
            </form>
        </div>

        <div class="info-text login">
            <div class="circle-background">
                <img src="img/ja.png" alt="Login Image" class="login-image">
            </div>
        </div>


        <!-- Powered by text as seen on image -->
<div class="powered-by">
    <p class="powered-by-text">Bubble Leaf Build</p>
    <p class="powered-by-text">powered by: Pearl Nest</p>
</div>

    </div>
</body>
</html>
