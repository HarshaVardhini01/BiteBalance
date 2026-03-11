<?php
include 'db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['cpassword'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format!";
    }
    if ($password !== $confirm_password) {
        $errors['cpassword'] = "Passwords do not match!";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $checkUser = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($checkUser->num_rows > 0) {
            $errors['email'] = "Email already registered!";
        } else {
            $query = "INSERT INTO users (fullname, email, password) 
                      VALUES ('$name', '$email', '$hashed_password')";
            if ($conn->query($query)) {
                header("Location: dashboard.php");
                exit();
            } else {
                $errors['general'] = "Database error: " . $conn->error;
            }
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BiteBalance - Sign Up</title>
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
    .signup-container {
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
  <div class="signup-container">
    <h2>Create Your Account</h2>
    <?php if (!empty($errors['general'])): ?>
      <div class="error"><?= $errors['general'] ?></div>
    <?php endif; ?>
    <form method="POST" id="signupForm" novalidate>
      <input type="text" placeholder="Full Name" required name="fullname" value="<?= htmlspecialchars($name ?? '') ?>">
      <div class="error" id="fullnameError"><?= $errors['fullname'] ?? '' ?></div>

      <input type="email" placeholder="Email" required name="email" value="<?= htmlspecialchars($email ?? '') ?>">
      <div class="error" id="emailError"><?= $errors['email'] ?? '' ?></div>

      <input type="password" placeholder="Password" required name="password" id="password">
      <div class="error" id="passwordError"><?= $errors['password'] ?? '' ?></div>

      <input type="password" placeholder="Confirm Password" required name="cpassword" id="cpassword">
      <div class="error" id="cpasswordError"><?= $errors['cpassword'] ?? '' ?></div>

      <button type="submit">Sign Up</button>
    </form>
    <p>Already have an account? <a href="login.php">Login</a></p>
  </div>

  <script>
    document.getElementById("signupForm").addEventListener("submit", function(e) {
      let valid = true;

      // Clear errors
      document.querySelectorAll(".error").forEach(el => el.textContent = "");

      const fullname = document.querySelector("[name='fullname']").value.trim();
      const email = document.querySelector("[name='email']").value.trim();
      const password = document.getElementById("password").value.trim();
      const cpassword = document.getElementById("cpassword").value.trim();

      if (fullname.length < 3) {
        document.getElementById("fullnameError").textContent = "Name must be at least 3 characters!";
        valid = false;
      }

      const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,}$/i;
      if (!emailPattern.test(email)) {
        document.getElementById("emailError").textContent = "Enter a valid email!";
        valid = false;
      }

      if (password.length < 6) {
        document.getElementById("passwordError").textContent = "Password must be at least 6 characters!";
        valid = false;
      }

      if (password !== cpassword) {
        document.getElementById("cpasswordError").textContent = "Passwords do not match!";
        valid = false;
      }

      if (!valid) {
        e.preventDefault(); // stop form submission
      }
    });
  </script>
</body>
</html>

