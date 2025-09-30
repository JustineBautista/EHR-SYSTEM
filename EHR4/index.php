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
<html>
<head>
  <meta charset="utf-8">
  <title>EHR Admin Login</title>
  <style>
    /* Natural CSS Equivalent of Bootstrap Styles */
    body {
      padding:0;
      margin:0;
      display: flex;
      flex-direction:column;
      font-family: cambria;
      background-color:#dbd6d6ff; /* bg-light */
      justify-content:center;
    }

    .container {
      width: 100%;
      max-width: 205px; /* Standard container width */
      margin-left: 220px;
      margin-right: auto;
      padding-left: 15px;
      padding-right: 15px;
      padding-top:3rem;
      z-index:1000;
    }

    /* Media query for tablets and smaller screens */
    @media (max-width: 992px) {
      .container {
        margin-left: auto;
        margin-right: auto;
        max-width: none;
      }
    }

    /* Media query for mobile devices */
    @media (max-width: 768px) {
      .container {
        margin-left: auto;
        margin-right: auto;
        padding-left: 10px;
        padding-right: 10px;
        padding-top: 1rem;
      }
    }

    .row {
      
      display: flex;
      flex-wrap: wrap;
      justify-content: center; /* justify-content-center */
    }

    .col-md-5 {
      flex: 0 0 auto;
      width: 100%;
      max-width: 41.666667%; /* col-md-5 is 5/12 or ~41.66% of the grid */
    }
    
    /* Media query for smaller screens (like md breakpoint in BS) */
    @media (max-width: 768px) {
      .col-md-5 {
        max-width: 90%; /* Adjust for better  viewing on smaller devices */
      }
    }

    /* Media query for larger screens */
    @media (min-width: 769px) {
      .col-md-5 {
        max-width: 60%; /* Adjust for better viewing on larger devices */
      }
    }

    .card {
      /* background: rgba(0,0,0, 0.1); 
      backdrop-filter: blur(8px);          
      -webkit-backdrop-filter: blur(8px);   */
      background-color: #10b981;
      width: 500px;
      border: 1px solid rgba(0, 0, 0, 0.125);
      border-radius: 2.25rem; /* Standard border-radius */
      transition:0.5s ease;
      box-shadow: 0 8px 16px rgba(0,0,0,0.9);
    }
    .card:hover{
      margin-left:10px;
    }

    .card-body {
      
      padding: 1.5rem; /* Padding inside the card */
    }

    .card-title {
      margin-bottom: 1rem; /* mb-3 */
      text-align: center;
      font-size: 1.5rem;
      font-weight: 500;
      padding-bottom:0px;
    }

    .alert-danger {
      padding: 0.75rem 1.25rem;
      margin-bottom: 1rem;
      color: #721c24; /* Red text */
      background-color: #f8d7da; /* Light red background */
      border: 1px solid #f5c6cb; /* Red border */
      border-radius: 0.25rem;
    }

    .mb-3 {
      display:flex;
      flex-direction:row;
      margin-bottom: 1rem !important; /* mb-3 equivalent */
      position: relative;
    }

    .mb-3 i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      z-index: 1;
      color: #495057;
      padding: 0;
    }


    .form-label {
      display: inline-block;
      margin-bottom: 0.5rem;
      font-weight: 600;
    }

    .form-control {
      display: block;
      width: 100%;
      padding: 0.375rem 0.75rem 0.375rem 40px;
      font-size: 1rem;
      line-height: 1.5;
      color: #495057;
      background-color: #fff;
      background-clip: padding-box;
      border: 1px solid #ced4da;
      border-radius: 2rem;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-control:focus {
        color: #495057;
        background-color: #fff;
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .btn {
      display: inline-block;
      font-weight: 400;
      color: #212529;
      text-align: center;
      vertical-align: middle;
      user-select: none;
      background-color: transparent;
      border: 1px solid transparent;
      padding: 0.375rem 0.75rem ;
      font-size: 1rem;
      line-height: 1.5;
      border-radius: 2rem;
      transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
      cursor: pointer;
      margin-bottom:20px;
    }
    

    .btn-primary {
      color: #fff;
      background-color: #0d6efd; /* Primary blue */
      border-color: #0d6efd;
      transition:0.4s;
      
    }

    .btn-primary:hover {
      background-color: #0b5ed7; /* Darker blue on hover */
      border-color: #0a58ca;
      margin-left:5px;
      
    }
    .w-100 {
      width: 100% !important; /* w-100 */
    }

    .text-center {
      padding:20px 0 0 37px;
      margin-left:100px;
    }

    .text-muted {
      color:rgb(0, 0, 0) !important; /* text-muted */
    }

    .mt-2 {
      margin-top: 0.5rem !important; /* mt-2 */
    }
    .nav {
      display: flex;
      flex-direction: row;
      align-items: center;
      padding: 1rem 2rem;
      margin: 1rem auto;
      justify-content:center;
      height: 105px;
      width: 95vw;
      max-width: 1400px;
      border-radius: 30px;
      background-color: #10b981;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.6);
      transition: all 0.4s ease;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }
    .nav::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
      transition: left 0.5s;
    }
    .nav:hover::before {
      left: 100%;
    }
    .nav:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.8);
    }
    .nav img {
      height: 80px;
      width: auto;
      margin-right: 2rem;
      transition: transform 0.3s ease;
      z-index: 1;
    }
    .nav img:hover {
      transform: scale(1.05);
    }
    @media (max-width: 768px) {
      .nav {
        flex-direction: column;
        height: auto;
        padding: 1rem;
      }
      .nav img {
        margin-right: 0;
        margin-bottom: 1rem;
      }
      .text h2 {
        font-size: 1.2rem;
      }
      .text h3 {
        font-size: 0.9rem;
      }
    }
    .text{
      padding-top:3rem;
      
      display:flex;
      flex-direction:column;
      color:white;
      
    }
    .text h2, .text h3 {

      margin: 0;       /* removes browser default spacing */
      line-height: 1.2; /* tighter spacing */
    }
    i{
      padding:10px;
    }
    .main_contaier{
      display:flex;
      flex-direction:row;
    }
    .side_container{
      height:400px;
      max-width:400px;
      background-color:#dbd6d6ff;
      font-family:monospace;  
      margin:50px 20px 20px 20px;
      border-radius:20px;
      text-align:center;
      background: rgba(0,0,0, 0.1); 
      backdrop-filter: blur(8px);         
      -webkit-backdrop-filter: blur(8px);
      border-radius:40px;
    }
    .content{ 
      padding-left:0px;
    }
    .content_cotainer{
      text-align:justify;
      padding:10px 30px 0 30px;
    }
    .header_container{
      position:relative;
      height:100%;
      margin:0;
      padding:0;
      border-radius:15px 15px 0 0;
      background:#10b981
    }
    strong{
      color:#dbd6d6ff !important;
    }
    .nav-content{
      display:flex;
      flex-direction:row;

    }
    
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <div class="nav">
    <div class="nav-content">
    
      <img src="IMAGES/OCT_LOGO.png" alt="OCT_alternate_img" style="height: 150px; width: 200px; margin-right:20px; margin-left:30px;">
      <div class="text">
          <h2 style="padding-left:40px; border-bottom:0.2px solid black;">OLVAREZ COLLEGE TAGAYTAY <br><h3>College of Nursing and Health-Related Sciences</h3></h2>
      </div>
      <img src="IMAGES/NURSING_LOGO.png" alt="OCT_alternate_img" style="height: 150px; width: 200px; margin-right:20px; margin-left:30px;">
    </div>
  </div>    
  <div class="main_contaier">
    <div class="side_container">
      <div class="content">
      <div class="header_container">
          <h1>Mision</h1>
        </div>
        <h2 class="content_cotainer">Automated Unified Records for Optimized Retrieval and Archiving AURORA's mission is to revolutionize healthcare documentation through seamless record integration, rapid and reliable data retrieval, and uncompromising data security-enabling healthcare professionals to focus on what matters most: delivering quality, compassionate, and efficient care.</h2>
      </div>
        
    </div>
    <div class="container"> 
      <div class="row">
        <div class="col">
          <div class="card">
            <div class="card-body">
              <img src="IMAGES/aurora.png" alt="aurora_alternative_text" style="height:150px; width: 200px; padding-left:120px;" >
              <?php if ($error): ?>
                <div class="alert-danger"><?php echo htmlspecialchars($error);?></div>
              <?php endif; ?>
              <form method="post">
                <div class="mb-3">
                  <i class="fa-solid fa-user"></i>
                  <input class="form-control" name="username" placeholder="Username" required>
                </div>
                <div class="mb-3">
                  <i class="fa-solid fa-lock"></i>
                  <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
                <button class="btn btn-primary w-100">Login</button>
              </form>
            </div>
          </div>
          <p class="text-center text-muted mt-2 col-md-2">Access Passcode: <strong>admin / admin123</strong></p>
        </div>
      </div>
    </div>
    <div class="side_container">
      <div class="content">
        <div class="header_container">
          <h1>Vision</h1>
        </div>
        <h2 class="content_cotainer">To set the standard for next generation electronic health records by delivering a unified, intelligent, and secure platform that drives excellence in healthcare, empowers providers, and enhances patient outcomes.</h2>
        
      </div>
  </div>

</body>
</html>
