<?php
// Check if session is not already started before starting it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}

$page_title = "Our Services";
include "../includes/header.php";
?>

<style>
    .hero-section {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        min-height: 60vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        color: white;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
        margin: 5rem 0 0 0;
        overflow: hidden;
    }

    .hero-section::before {
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

    .hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
        max-width: 800px;
        padding: 2rem;
        animation: fadeInUp 1.5s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .hero-title {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 1rem;
        text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .hero-subtitle {
        font-size: 1.25rem;
        font-weight: 400;
        margin-bottom: 2rem;
        opacity: 0.9;
    }

    .services-section {
        padding: 4rem 0;
        background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .service-card {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        margin-bottom: 2rem;
        border: 1px solid rgba(16, 185, 129, 0.1);
        position: relative;
        overflow: hidden;
    }

    .service-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.1), transparent);
        transition: left 0.5s;
    }

    .service-card:hover::before {
        left: 100%;
    }

    .service-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 15px 40px rgba(16, 185, 129, 0.2);
        border-color: rgba(16, 185, 129, 0.3);
    }

    .service-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-bottom: 1.5rem;
        font-size: 2rem;
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        transition: all 0.3s ease;
    }

    .service-card:hover .service-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 12px 30px rgba(16, 185, 129, 0.4);
    }

    .service-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: #2c3e50;
    }

    .service-description {
        color: #6c757d;
        line-height: 1.6;
        font-size: 1rem;
    }

    .section-title {
        font-size: 2.5rem;
        font-weight: 800;
        color: #2c3e50;
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 2px;
    }

    @media (max-width: 768px) {
        .hero-title {
            font-size: 2rem;
        }

        .hero-subtitle {
            font-size: 1rem;
        }

        .hero-content {
            padding: 1rem;
        }

        .service-card {
            padding: 2rem;
        }

        .section-title {
            font-size: 2rem;
        }
    }

    @media (max-width: 576px) {
        .service-card {
            padding: 1.5rem;
        }

        .service-icon {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
    }
</style>

<div class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">Our Services</h1>
        <p class="hero-subtitle">Comprehensive Healthcare Solutions Powered by AURORA EHR System</p>
    </div>
</div>

<div class="services-section">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="section-title">What We Offer</h2>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h3 class="service-title">Patient Care Coordination</h3>
                    <p class="service-description">Streamline patient onboarding with our advanced EHR system that creates comprehensive digital profiles, including medical histories, demographics, allergies, and contact details. Enable personalized care through instant access to patient data, reducing administrative time and improving clinical outcomes.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                    <h3 class="service-title">Vital Signs Monitoring</h3>
                    <p class="service-description">Monitor and record vital signs in real-time with our integrated EHR platform. Track blood pressure, heart rate, temperature, and more with automated alerts for abnormalities. Visualize health trends over time to support proactive care and early intervention strategies.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="bi bi-capsule"></i>
                    </div>
                    <h3 class="service-title">Medication Oversight</h3>
                    <p class="service-description">Manage medications seamlessly with our EHR system's prescription tracking, dosage monitoring, and comprehensive medication history. Prevent adverse drug interactions through automated checks, ensure accurate dosing, and maintain detailed records for improved patient safety and compliance.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <h3 class="service-title">Laboratory Data Integration</h3>
                    <p class="service-description">Integrate lab results and diagnostic reports directly into patient records with our EHR platform. Access test results instantly, compare historical data, and share findings securely with care teams. Accelerate diagnosis and treatment planning through unified, real-time data access.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h3 class="service-title">Data Protection</h3>
                    <p class="service-description">Protect patient data with enterprise-grade security features in our EHR system, including end-to-end encryption, role-based access controls, and comprehensive audit trails. Ensure HIPAA compliance, prevent unauthorized access, and maintain patient trust through robust data protection protocols.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h3 class="service-title">Analytics and Insights</h3>
                    <p class="service-description">Leverage powerful analytics and reporting tools within our EHR system to generate actionable insights from patient data. Track key performance metrics, identify trends, create custom reports, and drive evidence-based improvements in healthcare delivery and patient outcomes.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
