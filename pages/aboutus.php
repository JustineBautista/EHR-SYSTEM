<?php
echo '<link rel="icon" href="IMAGES/aurora.png" type="image/png">';
?>

<?php
// Check if session is not already started before starting it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}

$page_title = "About Us";
include "../includes/header.php";
?>

<style>
    .hero-section {
        background-image: url('../assets/IMAGES/about-image.png');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        margin:5rem 0 0 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        color: white;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
    }

    .hero-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 1;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
        max-width: 800px;
        padding: 2rem;
    }

    .hero-title {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 1rem;
        -webkit-background-clip: text;
        background-clip: text;
        text-shadow:none;
        color:rgb(240, 240, 240);
        text-shadow:2px 2px 2px rgb(0,0,0,0.5);

    }

    .hero-subtitle {
        font-size: 1.25rem;
        font-weight: 400;
        margin-bottom: 2rem;
        opacity: 0.9;
    }

    .hero-text {
        font-size: 1rem;
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .features-section {
        padding: 4rem 0;
        background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .feature-card {
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

    .feature-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.1), transparent);
        transition: left 0.5s;
    }

    .feature-card:hover::before {
        left: 100%;
    }

    .feature-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 15px 40px rgba(16, 185, 129, 0.2);
        border-color: rgba(16, 185, 129, 0.3);
    }

    .feature-icon {
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

    .feature-card:hover .feature-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 12px 30px rgba(16, 185, 129, 0.4);
    }
    .container-format{
        display:flex;
        flex-direction:row;
        background: linear-gradient(180deg,rgb(236, 236, 236) 0%,rgb(123, 211, 149) 80%);
        padding: 0 10rem 0 10rem;
        position: relative;
        gap:5rem;
    }
    .container-format p{
        margin:1.50rem 0 0 0;
        font-size:1.05rem;
        font-weight:bold;
    }

    .mission-section {
        /* background-color:rgba(0,0,0,0.8); */
        /* backdrop-filter: blur(8px);          
        -webkit-backdrop-filter: blur(8px);    */
        padding: 4rem 0;
        background: linear-gradient(180deg,rgb(236, 236, 236) 0%,rgb(123, 211, 149) 80%);
        position:relative;
        
    }

    .mission-content {
        max-width: 600px;
        margin: 0 auto;
        text-align: center;
        
    }
    /* .overlay{
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(98, 98, 98, 0.3);
        z-index: 1;
    } */
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
    }
</style>

<div class="hero-section">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <h1 class="hero-title">AURORA EHR SYSTEM</h1>
        <p class="hero-subtitle">Revolutionizing Healthcare Management with Innovative Technology</p>
        <p class="hero-text">
            AURORA is a comprehensive Electronic Health Records (EHR) system designed to streamline healthcare operations,
            enhance patient care, and empower medical professionals with cutting-edge tools for efficient data management.
        </p>
    </div>
</div>

<div class="features-section">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-4">
                <h2 class="section-title">Key Features</h2>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h4>Patient Management</h4>
                    <p>Comprehensive patient profiles with detailed medical histories, demographics, and contact information for personalized care.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                    <h4>Vital Signs Tracking</h4>
                    <p>Real-time monitoring and recording of vital signs, enabling healthcare providers to make informed decisions quickly.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-capsule"></i>
                    </div>
                    <h4>Medication Management</h4>
                    <p>Efficient tracking of prescriptions, dosages, and medication history to ensure safe and effective treatment plans.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <h4>Lab Results Integration</h4>
                    <p>Seamless integration of laboratory results and diagnostic reports for comprehensive patient care coordination.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h4>Data Security</h4>
                    <p>Advanced security measures including encryption, access controls, and audit trails to protect sensitive patient information.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h4>Analytics & Reporting</h4>
                    <p>Powerful analytics tools for generating insights, tracking performance metrics, and improving healthcare outcomes.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container-format">
    <div class="overlay"></div>
    <div class="mission-section">
        <div class="container">
            <div class="mission-content">
                <h2 class="section-title">Mission</h2>
                <p>Automated Unified Records for Optimized Retrieval and Archiving AURORA's mission is to revolutionize healthcare documentation through seamless record integration, rapid and reliable data retrieval, and uncompromising data security—enabling healthcare professionals to focus on what matters most: delivering quality, compassionate, and efficient care.</p>
            </div>
        </div>
    </div>
    
    <div class="mission-section">
        <div class="container">
            <div class="mission-content">
                <h2 class="section-title">Vision</h2>
                <p>
                To set the standard for next generation electronic health records by delivering a unified, intelligent, and secure platform that drives excellence in healthcare, empowers providers, and enhances patient outcomes.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
