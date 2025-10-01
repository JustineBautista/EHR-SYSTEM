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
      background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 50%, #e0e7ff 100%);
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* Animated background particles */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: 
        radial-gradient(circle at 20% 30%, rgba(16, 185, 129, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(52, 211, 153, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, rgba(5, 150, 105, 0.05) 0%, transparent 50%);
      animation: pulse 10s ease-in-out infinite;
      z-index: 0;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }

    /* Header */
    .header {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      padding: 1.25rem 2rem;
      box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
      position: relative;
      z-index: 10;
      animation: slideDown 0.8s ease-out;
      overflow: hidden;
      flex-shrink: 0;
    }

    .header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      animation: rotate 20s linear infinite;
    }

    @keyframes rotate {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
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
      gap: 3rem;
      max-width: 1400px;
      margin: 0 auto;
      flex-wrap: wrap;
      position: relative;
      z-index: 1;
    }

    .header-logo-wrapper {
      background: rgba(255, 255, 255, 0.95);
      padding: 0.75rem;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
      transition: all 0.4s ease;
      border: 3px solid rgba(255, 255, 255, 0.5);
    }

    .header-logo-wrapper:hover {
      transform: translateY(-5px) scale(1.05);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
      border-color: rgba(255, 255, 255, 0.8);
    }

    .header-logo {
      height: 70px;
      width: auto;
      display: block;
    }

    .header-text {
      text-align: center;
      flex: 1;
      min-width: 300px;
      padding: 1rem;
    }

    .header-text h1 {
      font-size: 1.6rem;
      color: white;
      margin-bottom: 0.5rem;
      font-weight: 800;
      text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
      letter-spacing: 0.5px;
      line-height: 1.2;
    }

    .header-divider {
      width: 80%;
      height: 2px;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
      margin: 0.5rem auto;
    }

    .header-text h2 {
      font-size: 0.9rem;
      color: #d1fae5;
      font-weight: 600;
      text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.15);
      letter-spacing: 0.3px;
    }

    /* Main Container */
    .main-container {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem 2rem;
      gap: 2rem;
      position: relative;
      z-index: 1;
      animation: fadeIn 1s ease-out;
      overflow-y: auto;
      max-height: calc(100vh - 140px);
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Side Cards */
    .info-card {
      background: white;
      border-radius: 24px;
      padding: 0;
      max-width: 320px;
      box-shadow: 0 10px 40px rgba(16, 185, 129, 0.15);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      animation: slideIn 1s ease-out;
      border: 2px solid rgba(16, 185, 129, 0.1);
      overflow: hidden;
      flex-shrink: 0;
    }

    .info-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 16px 50px rgba(16, 185, 129, 0.25);
      border-color: rgba(16, 185, 129, 0.3);
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(-80px) translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateX(0) translateY(0);
      }
    }

    .info-card:last-of-type {
      animation: slideInRight 1s ease-out;
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(80px) translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateX(0) translateY(0);
      }
    }

    .info-card-header {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      padding: 1.25rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .info-card-header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
      animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
      0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
      100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
    }

    .info-card-header h3 {
      color: white;
      font-size: 1.4rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
      position: relative;
      z-index: 1;
    }

    .info-card-content {
      padding: 1.25rem;
      color: #374151;
      line-height: 1.6;
      text-align: justify;
      font-size: 0.85rem;
    }

    /* Login Card */
    .login-container {
      width: 100%;
      max-width: 420px;
      animation: scaleIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      flex-shrink: 0;
    }

    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.85) translateY(30px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }

    .login-card {
      background: white;
      border-radius: 28px;
      padding: 2.5rem;
      box-shadow: 0 20px 60px rgba(16, 185, 129, 0.2);
      transition: all 0.4s ease;
      border: 2px solid rgba(16, 185, 129, 0.15);
      position: relative;
      overflow: hidden;
    }

    .login-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #10b981, #34d399, #10b981);
      background-size: 200% 100%;
      animation: gradientMove 3s ease infinite;
    }

    @keyframes gradientMove {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }

    .login-card:hover {
      box-shadow: 0 25px 80px rgba(16, 185, 129, 0.3);
      border-color: rgba(16, 185, 129, 0.3);
    }

    .login-logo {
      display: block;
      margin: 0 auto 1.5rem;
      height: 100px;
      width: auto;
      filter: drop-shadow(0 6px 16px rgba(16, 185, 129, 0.4));
      animation: float 4s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      25% { transform: translateY(-8px) rotate(1deg); }
      75% { transform: translateY(-8px) rotate(-1deg); }
    }

    .login-title {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .login-title h2 {
      font-size: 1.6rem;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 0.5rem;
      font-weight: 800;
    }

    .login-title p {
      color: #6b7280;
      font-size: 0.9rem;
    }

    /* Alert */
    .alert-danger {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
      padding: 0.9rem;
      border-radius: 14px;
      margin-bottom: 1.25rem;
      border-left: 4px solid #dc2626;
      animation: shake 0.6s ease-in-out;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
      font-size: 0.9rem;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-8px); }
      20%, 40%, 60%, 80% { transform: translateX(8px); }
    }

    /* Form */
    .form-group {
      margin-bottom: 1.25rem;
      position: relative;
    }

    .form-label {
      display: block;
      margin-bottom: 0.4rem;
      color: #374151;
      font-weight: 600;
      font-size: 0.85rem;
    }

    .input-wrapper {
      position: relative;
    }

    .form-group i {
      position: absolute;
      left: 1.1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      font-size: 1rem;
      transition: all 0.3s ease;
      z-index: 1;
    }

    .form-control {
      width: 100%;
      padding: 0.9rem 1rem 0.9rem 3rem;
      font-size: 0.95rem;
      border: 2px solid #e5e7eb;
      border-radius: 14px;
      background: #f9fafb;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      font-family: inherit;
    }

    .form-control:focus {
      outline: none;
      border-color: #10b981;
      background: white;
      box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
      transform: translateY(-2px);
    }

    .form-control:focus + i {
      color: #10b981;
      transform: translateY(-50%) scale(1.15);
    }

    /* Button */
    .btn {
      width: 100%;
      padding: 1rem;
      font-size: 1rem;
      font-weight: 700;
      color: white;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      border: none;
      border-radius: 14px;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
      margin-top: 0.25rem;
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    .btn:hover::before {
      width: 400px;
      height: 400px;
    }

    .btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 32px rgba(16, 185, 129, 0.5);
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
    }

    .btn:active {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }

    .btn span {
      position: relative;
      z-index: 1;
    }

    /* Access Info */
    .access-info {
      text-align: center;
      margin-top: 1.25rem;
      padding: 0.9rem;
      background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
      border-radius: 14px;
      font-size: 0.85rem;
      color: #065f46;
      border: 2px solid #a7f3d0;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
    }

    .access-info strong {
      color: #10b981;
      font-weight: 800;
      font-size: 0.9rem;
    }

    .access-info i {
      color: #10b981;
      margin-right: 0.5rem;
      font-size: 1rem;
    }

    /* Footer Decorative Element */
    .decorative-footer {
      display: none;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
      .main-container {
        flex-direction: column;
        padding: 2rem 1.5rem;
      }

      .info-card {
        max-width: 100%;
        width: 100%;
      }
    }

    @media (max-width: 768px) {
      .header {
        padding: 1.5rem 1rem;
      }

      .header-content {
        gap: 1.5rem;
      }

      .header-logo-wrapper {
        padding: 0.75rem;
      }

      .header-logo {
        height: 70px;
      }

      .header-text h1 {
        font-size: 1.5rem;
      }

      .header-text h2 {
        font-size: 0.9rem;
      }

      .login-card {
        padding: 2.5rem 2rem;
      }

      .info-card-content {
        padding: 1.5rem;
      }

      .info-card-header {
        padding: 1.5rem;
      }

      .info-card-header h3 {
        font-size: 1.4rem;
      }
    }

    @media (max-width: 480px) {
      .main-container {
        padding: 1.5rem 1rem;
        gap: 1.5rem;
      }

      .login-card {
        padding: 2rem 1.5rem;
      }

      .header-text h1 {
        font-size: 1.2rem;
      }

      .header-text h2 {
        font-size: 0.8rem;
      }

      .login-title h2 {
        font-size: 1.6rem;
      }
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <!-- Header -->
  <header class="header">
    <div class="header-content">
      <div class="header-logo-wrapper">
        <img src="IMAGES/OCT_LOGO.png" alt="Olvarez College Tagaytay Logo" class="header-logo">
      </div>
      <div class="header-text">
        <h1>OLVAREZ COLLEGE TAGAYTAY</h1>
        <div class="header-divider"></div>
        <h2>College of Nursing and Health-Related Sciences</h2>
      </div>
      <div class="header-logo-wrapper">
        <img src="IMAGES/NURSING_LOGO.png" alt="Nursing Department Logo" class="header-logo">
      </div>
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
            <label for="username" class="form-label">Username</label>
            <div class="input-wrapper">
              <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
              <i class="fa-solid fa-user"></i>
            </div>
          </div>

          <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <div class="input-wrapper">
              <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
              <i class="fa-solid fa-lock"></i>
            </div>
          </div>

          <button type="submit" class="btn">
            <span>Login</span>
          </button>
        </form>

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

  <!-- Decorative Footer -->
  <footer class="decorative-footer">
    <p>Secure Electronic Health Records System</p>
  </footer>
</body>
</html>