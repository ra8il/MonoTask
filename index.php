<?php
session_start();

if(isset($_SESSION['username'])) {
    header("Location: todo.php");
    exit();
}

require 'db.php';

$error = '';

if(isset($_POST['submit'])) {
    $username      = trim($_POST['username']);
    $password      = $_POST['password'];
    $date_of_birth = $_POST['dob'];
    $email         = trim($_POST['email']);

    if(empty($username) || empty($password) || empty($date_of_birth) || empty($email)) {
        $error = "All fields are required!";
    } else {
        $check = $con->prepare("SELECT username FROM user_data WHERE username = :username");
        $check->execute([':username' => $username]);

        if($check->rowCount() > 0) {
            $error = "Username already taken!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $query = "INSERT INTO user_data(username, password, date_of_birth, email)
                      VALUES(:username, :password, :date_of_birth, :email)";
            $stmt = $con->prepare($query);
            $stmt->execute([
                ':username'      => $username,
                ':password'      => $hashed,
                ':date_of_birth' => $date_of_birth,
                ':email'         => $email
            ]);

            header("Location: login.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | TODO LIST</title>
    <link rel="stylesheet" href="todo_style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <h2>Create Account</h2>

            <?php if(!empty($error)): ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="auth-field">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" placeholder="Choose a username">
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="Choose a password">
                </div>

                <div class="auth-field">
                    <label for="dob">Date of Birth</label>
                    <input type="date" name="dob" id="dob">
                </div>

                <div class="auth-field">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" placeholder="you@example.com">
                </div>

                <div class="auth-actions">
                    <button type="submit" name="submit" class="auth-btn-primary">Register</button>
                    <button type="reset" class="auth-btn-reset">Reset</button>
                </div>
            </form>

            <p class="auth-footer">Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>