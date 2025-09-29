<?php
// Check if session is not already started before starting it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #10b981;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --light-color: #ffffff;
            --dark-color: #2c3e50;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .navbar {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.8), rgba(34, 197, 94, 0.8));
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            padding: 1.25rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            border-radius: 0 0 1rem 1rem;
        }

        .navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
            pointer-events: none;
        }

        .navbar:hover::before {
            left: 100%;
        }
        
        .navbar-brand {
            font-weight: 800;
            color: white !important;
            font-size: 1.8rem;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 2;
            letter-spacing: -0.025em;
        }

        .navbar-brand:hover {
            transform: scale(1.06) translateY(-2px);
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .navbar-nav .nav-link {
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 0.625rem;
            margin: 0 0.25rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 2;
            color: rgba(255, 255, 255, 0.95) !important;
            border: 1px solid transparent;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .dropdown-toggle {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 2;
        }

        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .dropdown-toggle::after {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-left: 0.75rem;
            border-top-color: rgba(255, 255, 255, 0.9);
        }

        .dropdown.locked .dropdown-toggle::after {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border-radius: 1rem;
            margin-top: 0.75rem;
            padding: 0.75rem 0;
            overflow: hidden;
        }

        .dropdown-item {
            padding: 0.875rem 1.75rem;
            margin: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            color: #2c3e50;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(34, 197, 94, 0.1));
            color: var(--primary-color);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .navbar-toggler {
            border: none;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 0.625rem;
            padding: 0.625rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 2;
        }

        .navbar-toggler:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.95%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2.5' d='m4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        @media (max-width: 991.98px) {
            .navbar-nav .nav-link {
                padding: 0.75rem 1rem;
                margin: 0.25rem 0;
            }

            .navbar-brand {
                font-size: 1.5rem;
            }
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn {
            border-radius: 5px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .table thead {
            background-color: var(--primary-color);
            color: white;
        }
        
        .form-control {
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 0.5rem 0.75rem;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
            border-color: var(--secondary-color);
        }
        
        .module-btn {
            text-align: center;
            border-radius: 10px;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            height: 100%;
        }
        
        .module-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            background-color: var(--accent-color);
            color: white;
        }
        
        .stats-card {
            border-left: 5px solid var(--secondary-color);
        }
        
        .modal-header {
            background-color: var(--primary-color);
            color: white;
        }
        
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .input-group-text {
            background-color: var(--light-color);
            border: 1px solid #ced4da;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>EHR System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="patients.php"><i class="bi bi-people-fill me-1"></i>Patients</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-clipboard2-pulse me-1"></i>Clinical
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="vitals.php">Vital Signs</a></li>
                            <li><a class="dropdown-item" href="lab_results.php">Lab Results</a></li>
                            <li><a class="dropdown-item" href="diagnostics.php">Diagnostics</a></li>
                            <li><a class="dropdown-item" href="medications.php">Medications</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-journal-text me-1"></i>Records
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="medical_history.php">Medical History</a></li>
                            <li><a class="dropdown-item" href="progress_notes.php">Progress Notes</a></li>
                            <li><a class="dropdown-item" href="treatment_plans.php">Treatment Plans</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>Admin
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-1"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Page content will go here -->