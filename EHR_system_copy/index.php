<?php
session_start();
include "db.php";

if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, password FROM admin WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        
        // Check if password is still in plain text (for backward compatibility)
        if ($user['password'] === 'admin123') {
            // This is the default password, let's verify and update it to hashed version
            if ($password === 'admin123') {
                // Update to hashed password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE admin SET password=? WHERE id=?");
                $update_stmt->bind_param("si", $hashed_password, $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Set session and redirect
                $_SESSION['admin'] = $username;
                $_SESSION['admin_id'] = $user['id'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            // Verify password against stored hash
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin'] = $username;
                $_SESSION['admin_id'] = $user['id'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        }
    } else {
        $error = "Invalid username or password.";
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AURORA - EHR Admin Login</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
    }

    /* Animated background */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: 
        radial-gradient(circle at 20% 50%, rgba(16, 185, 129, 0.2) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(52, 211, 153, 0.2) 0%, transparent 50%);
      animation: pulse 8s ease-in-out infinite;
      z-index: 0;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.6; }
    }

    /* Header */
    .header {
      background: rgba(13, 138, 2, 0.98);
      backdrop-filter: blur(10px);
      padding: 1.5rem 2rem;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      position: relative;
      z-index: 10;
      animation: slideDown 0.6s ease-out;
      
    }

    @keyframes slideDown {
      from {
        transform: translateY(-100%);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .header-content {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 2rem;
      max-width: 1400px;
      margin: 0 auto;
      flex-wrap: wrap;
    }

    .header-logo {
      height: 80px;
      width: auto;
      transition: transform 0.3s ease;
    }

    .header-logo:hover {
      transform: scale(1.05) rotate(2deg);
    }

    .header-text {
      text-align: center;
      flex: 1;
      min-width: 300px;
    }

    .header-text h1 {
      font-size: 1.8rem;
      color: #ffffffff;
      margin-bottom: 0.5rem;
      font-weight: 700;
    }

    .header-text h2 {
      font-size: 1rem;
      color: #10b981;
      font-weight: 500;
    }

    /* Main Container */
    .main-container {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      gap: 2rem;
      position: relative;
      z-index: 1;
      animation: fadeIn 0.8s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Side Cards */
    .info-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 2rem;
      max-width: 350px;
      box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2);
      transition: all 0.4s ease;
      animation: slideIn 0.8s ease-out;
      border: 1px solid rgba(16, 185, 129, 0.1);
    }

    .info-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 48px rgba(16, 185, 129, 0.3);
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(-50px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .info-card:last-of-type {
      animation: slideInRight 0.8s ease-out;
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(50px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .info-card-header {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      margin: -2rem -2rem 1.5rem -2rem;
      padding: 1.5rem;
      border-radius: 24px 24px 0 0;
      text-align: center;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .info-card-header h3 {
      color: white;
      font-size: 1.5rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .info-card-content {
      color: #374151;
      line-height: 1.7;
      text-align: justify;
      font-size: 0.95rem;
    }

    /* Login Card */
    .login-container {
      width: 100%;
      max-width: 450px;
      animation: scaleIn 0.6s ease-out;
    }

    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .login-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 28px;
      padding: 3rem;
      box-shadow: 0 12px 48px rgba(16, 185, 129, 0.25);
      transition: all 0.4s ease;
      border: 1px solid rgba(16, 185, 129, 0.1);
    }

    .login-card:hover {
      box-shadow: 0 16px 64px rgba(16, 185, 129, 0.35);
    }

    .login-logo {
      display: block;
      margin: 0 auto 2rem;
      height: 120px;
      width: auto;
      filter: drop-shadow(0 4px 12px rgba(16, 185, 129, 0.3));
      animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    .login-title {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-title h2 {
      font-size: 1.8rem;
      color: #1f2937;
      margin-bottom: 0.5rem;
      font-weight: 700;
    }

    .login-title p {
      color: #6b7280;
      font-size: 0.95rem;
    }

    /* Alert */
    .alert-danger {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
      padding: 1rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      border-left: 4px solid #dc2626;
      animation: shake 0.5s ease-in-out;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }

    /* Form */
    .form-group {
      margin-bottom: 1.5rem;
      position: relative;
    }

    .form-group i {
      position: absolute;
      left: 1.2rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      z-index: 1;
    }

    .form-control {
      width: 100%;
      padding: 1rem 1rem 1rem 3.2rem;
      font-size: 1rem;
      border: 2px solid #e5e7eb;
      border-radius: 14px;
      background: #f9fafb;
      transition: all 0.3s ease;
      font-family: inherit;
    }

    .form-control:focus {
      outline: none;
      border-color: #10b981;
      background: white;
      box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }

    .form-control:focus + i {
      color: #10b981;
    }

    .form-group:has(.form-control:focus) i {
      color: #10b981;
      transform: translateY(-50%) scale(1.1);
    }

    /* Button */
    .btn {
      width: 100%;
      padding: 1rem;
      font-size: 1.1rem;
      font-weight: 600;
      color: white;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      border: none;
      border-radius: 14px;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    .btn:hover::before {
      width: 300px;
      height: 300px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(16, 185, 129, 0.5);
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
    }

    .btn:active {
      transform: translateY(0);
    }

    /* Access Info */
    .access-info {
      text-align: center;
      margin-top: 1.5rem;
      padding: 1rem;
      background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
      border-radius: 12px;
      font-size: 0.9rem;
      color: #065f46;
      border: 1px solid #bbf7d0;
    }

    .access-info strong {
      color: #10b981;
      font-weight: 700;
    }

    .access-info i {
      color: #10b981;
      margin-right: 0.5rem;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
      .main-container {
        flex-direction: column;
      }

      .info-card {
        max-width: 100%;
      }
    }

    @media (max-width: 768px) {
      .header-content {
        gap: 1rem;
      }

      .header-logo {
        height: 60px;
      }

      .header-text h1 {
        font-size: 1.3rem;
      }

      .header-text h2 {
        font-size: 0.85rem;
      }

      .login-card {
        padding: 2rem;
      }

      .info-card {
        padding: 1.5rem;
      }

      .info-card-header {
        margin: -1.5rem -1.5rem 1rem -1.5rem;
      }
    }

    @media (max-width: 480px) {
      .main-container {
        padding: 1rem;
      }

      .login-card {
        padding: 1.5rem;
      }
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <!-- Header -->
  <header class="header">
    <div class="header-content">
      <img src="IMAGES/OCT_LOGO.png" alt="Olvarez College Tagaytay Logo" class="header-logo">
      <div class="header-text">
        <h1>OLVAREZ COLLEGE TAGAYTAY</h1>
        <h2>College of Nursing and Health-Related Sciences</h2>
      </div>
      <img src="IMAGES/NURSING_LOGO.png" alt="Nursing Department Logo" class="header-logo">
    </div>
  </header>

  <!-- Main Content -->
  <main class="main-container">
    <!-- Mission Card -->
    <div class="info-card">
      <div class="info-card-header">
        <h3>Mission</h3>
      </div>
      <div class="info-card-content">
        <p>Automated Unified Records for Optimized Retrieval and Archiving AURORA's mission is to revolutionize healthcare documentation through seamless record integration, rapid and reliable data retrieval, and uncompromising data security—enabling healthcare professionals to focus on what matters most: delivering quality, compassionate, and efficient care.</p>
      </div>
    </div>

    <!-- Login Card -->
    <div class="login-container">
      <div class="login-card">
        <img src="IMAGES/aurora.png" alt="Aurora Logo" class="login-logo">
        
        <div class="login-title">
          <h2>Welcome Back</h2>
          <p>Please login to access the admin dashboard</p>
        </div>

        <?php if ($error): ?>
          <div class="alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
          </div>
        <?php endif; ?>

        <form method="post">
          <div class="form-group">
            <input type="text" class="form-control" name="username" placeholder="Username" required>
            <i class="fa-solid fa-user"></i>
          </div>

          <div class="form-group">
            <input type="password" class="form-control" name="password" placeholder="Password" required>
            <i class="fa-solid fa-lock"></i>
          </div>

          <button type="submit" class="btn">
            <span>Login</span>
          </button>
        </form>

        <div class="access-info">
          <i class="fa-solid fa-key"></i>Access Passcode: <strong>admin / admin123</strong>
        </div>
      </div>
    </div>

    <!-- Vision Card -->
    <div class="info-card">
      <div class="info-card-header">
        <h3>Vision</h3>
      </div>
      <div class="info-card-content">
        <p>To set the standard for next generation electronic health records by delivering a unified, intelligent, and secure platform that drives excellence in healthcare, empowers providers, and enhances patient outcomes.</p>
      </div>
    </div>
  </main>
</body>
</html>