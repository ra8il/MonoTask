<?php
session_start();

if(isset($_SESSION['username'])) {
    header("Location: todo.php");
    exit();
}

require 'db.php';

$error = '';

if(isset($_POST['submit'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if(empty($username) || empty($password)) {
        $error = "All fields are required!";
    } else {
        $query = "SELECT * FROM user_data WHERE username = :username";
        $stmt  = $con->prepare($query);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['username'] = $username;
            header("Location: todo.php");
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TODO LIST</title>
    <link rel="stylesheet" href="todo_style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <h2>Welcome Back</h2>

            <?php if(!empty($error)): ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="auth-field">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" placeholder="Enter your username">
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password">
                </div>

                <div class="auth-actions">
                    <button type="submit" name="submit" class="auth-btn-primary">Login</button>
                    <button type="reset" class="auth-btn-reset">Reset</button>
                </div>
            </form>

            <p class="auth-footer">Don't have an account? <a href="index.php">Register here</a></p>
        </div>
    </div>
</body>
</html>