<?php
include 'db.php';
session_start();

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email)) {
        $errors['email'] = "Email is required!";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required!";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            if (password_verify($password, $user['password'])) {
                //$_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['fullname'];
                header("Location: dashboard.php");
                exit();
            } else {
                $errors['password'] = "Incorrect password!";
            }
        } else {
            echo "<script>alert('User not found! Please sign up.');
                  window.location.href = 'signup.php';</script>";
            exit();
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BiteBalance - Login</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: url('images/background.jpg') center/cover no-repeat;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .overlay {
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1;
    }
    .login-container {
      position: relative;
      z-index: 2;
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      width: 350px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      text-align: center;
    }
    h2 { margin-bottom: 20px; color: #2c3e50; }
    input {
      width: 100%;
      padding: 12px;
      margin: 10px 0 5px 0;
      border: 1px solid #ccc;
      border-radius: 8px;
    }
    .error {
      color: red;
      font-size: 0.9rem;
      text-align: left;
      margin-bottom: 10px;
      min-height: 16px;
    }
    button {
      width: 100%;
      padding: 12px;
      background: #808080;
      border: none;
      border-radius: 8px;
      color: #fff;
      font-size: 1rem;
      cursor: pointer;
      transition: 0.3s;
    }
    button:hover {
      background: #666;
      transform: scale(1.05);
    }
    p { margin-top: 15px; }
    a { color: #eebd1c; text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="overlay"></div>
  <div class="login-container">
    <h2>Login to BiteBalance</h2>
    <form method="POST" id="loginForm" novalidate>
      <input type="email" placeholder="Email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
      <div class="error" id="emailError"><?= $errors['email'] ?? '' ?></div>

      <input type="password" placeholder="Password" name="password" id="password" required>
      <div class="error" id="passwordError"><?= $errors['password'] ?? '' ?></div>

      <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="signup.php">Sign up</a></p>
  </div>

  <script>
    document.getElementById("loginForm").addEventListener("submit", function(e) {
      let valid = true;

      // Clear errors
      document.getElementById("emailError").textContent = "";
      document.getElementById("passwordError").textContent = "";

      const email = document.querySelector("[name='email']").value.trim();
      const password = document.getElementById("password").value.trim();

      const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,}$/i;
      if (!emailPattern.test(email)) {
        document.getElementById("emailError").textContent = "Enter a valid email!";
        valid = false;
      }
      if (password.length < 6) {
        document.getElementById("passwordError").textContent = "Password must be at least 6 characters!";
        valid = false;
      }

      if (!valid) {
        e.preventDefault(); // stop form submission
      }
    });
  </script>
</body>
</html>

