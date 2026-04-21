<?php
// index.php – Landing page
session_start();
// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    $loc = ($_SESSION['user_role'] ?? '') === 'admin' ? 'admin/dashboard.php' : 'dashboard.php';
    header('Location: ' . $loc);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenyan Meal Planner – Smart Meal Planning for Kenyan Homes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fef9e7;
            color: #2d3e2f;
            line-height: 1.6;
        }
        a {
            text-decoration: none;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);
            color: #fff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .logo i {
            color: #FB8C00;
            margin-right: 8px;
        }
        .nav a {
            color: #fff;
            margin-left: 1.5rem;
            font-weight: 500;
            transition: 0.3s;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            background: rgba(255,255,255,0.15);
        }
        .nav a:hover {
            background: #FB8C00;
            color: #fff;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(46,125,50,0.85), rgba(27,94,32,0.85)), url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=2070&auto=format');
            background-size: cover;
            background-position: center;
            color: #fff;
            text-align: center;
            padding: 5rem 2rem;
        }
        .hero h2 {
            font-size: 2.8rem;
            margin-bottom: 1rem;
        }
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn-primary {
            background: #FB8C00;
            color: #fff;
            padding: 1rem 2rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .btn-primary:hover {
            background: #E65100;
            transform: scale(1.05);
        }

        /* Features */
        .features {
            padding: 4rem 0;
            background: #fff;
        }
        .section-title {
            text-align: center;
            font-size: 2rem;
            color: #1B5E20;
            margin-bottom: 3rem;
            position: relative;
        }
        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: #FB8C00;
            margin: 0.8rem auto 0;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        .feature-card {
            text-align: center;
            padding: 2rem;
            background: #fef9e7;
            border-radius: 20px;
            transition: 0.3s;
            border: 1px solid rgba(46,125,50,0.1);
        }
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(46,125,50,0.1);
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            background: #2E7D32;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .feature-icon i {
            font-size: 2rem;
            color: #fff;
        }
        .feature-card h3 {
            color: #1B5E20;
            margin-bottom: 1rem;
            font-size: 1.4rem;
        }
        .feature-card p {
            color: #5f6b5f;
        }

        /* How It Works */
        .how-it-works {
            background: #fef9e7;
            padding: 4rem 0;
        }
        .steps {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 2rem;
            margin-top: 2rem;
        }
        .step {
            flex: 1;
            min-width: 200px;
            text-align: center;
            background: #fff;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .step-number {
            width: 50px;
            height: 50px;
            background: #FB8C00;
            color: #fff;
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1rem;
        }
        .step h4 {
            color: #1B5E20;
            margin-bottom: 0.5rem;
        }

        /* About & Contact Sections */
        .about, .contact {
            background: #fff;
            padding: 4rem 0;
        }
        .about .container, .contact .container {
            max-width: 1000px;
        }
        .about-content {
            display: flex;
            gap: 3rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .about-text {
            flex: 2;
        }
        .about-text p {
            margin-bottom: 1rem;
        }
        .about-image {
            flex: 1;
            text-align: center;
        }
        .about-image i {
            font-size: 8rem;
            color: #FB8C00;
        }
        .contact-grid {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .contact-card {
            background: #fef9e7;
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            flex: 1;
            min-width: 250px;
            border: 1px solid rgba(46,125,50,0.1);
        }
        .contact-card i {
            font-size: 2.5rem;
            color: #FB8C00;
            margin-bottom: 1rem;
        }
        .contact-card h4 {
            color: #1B5E20;
            margin-bottom: 0.5rem;
        }
        .contact-card a {
            color: #2E7D32;
            text-decoration: none;
            font-weight: 500;
        }
        .contact-card a:hover {
            color: #FB8C00;
        }

        /* CTA */
        .cta {
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            color: #fff;
            text-align: center;
            padding: 4rem 2rem;
        }
        .cta h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .cta p {
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        .btn-secondary {
            background: #fff;
            color: #1B5E20;
            padding: 1rem 2rem;
            border-radius: 40px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
        }
        .btn-secondary:hover {
            background: #FB8C00;
            color: #fff;
            transform: scale(1.05);
        }

        /* Footer */
        footer {
            background: #1B5E20;
            color: rgba(255,255,255,0.9);
            text-align: center;
            padding: 2rem;
        }
        .footer-mission {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .footer-mission-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .footer-mission-item i {
            font-size: 1.2rem;
            color: #FB8C00;
        }
        .footer-copyright {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            .nav a {
                margin: 0 0.5rem;
            }
            .hero h2 {
                font-size: 2rem;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .steps {
                flex-direction: column;
            }
            .about-content {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <h1><i class="fas fa-utensils"></i> Kenyan Meal Planner</h1>
        </div>
        <div class="nav">
            <a href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
            <a href="login.php">Login</a>
            <a href="register.php">Sign Up</a>
        </div>
    </header>

    <!-- Hero Section (Home) -->
    <section id="home" class="hero">
        <div class="container">
            <h2>Plan Your Kenyan Meals, Save Money, Eat Healthy</h2>
            <p>Discover delicious local recipes, manage your household budget, and reduce food waste – all in one smart platform.</p>
            <a href="register.php" class="btn-primary"><i class="fas fa-arrow-right"></i> Get Started – It's Free</a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <h2 class="section-title">Why Kenyan Meal Planner?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-wallet"></i></div>
                    <h3>Budget Smart</h3>
                    <p>Set a weekly budget and track your spending. Get smart suggestions to stay within your limits.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-calendar-week"></i></div>
                    <h3>Plan Meals</h3>
                    <p>Easily plan breakfast, lunch, and dinner for the whole week. Never wonder what to cook again.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-trash-alt"></i></div>
                    <h3>Reduce Waste</h3>
                    <p>Use leftovers creatively and shop only what you need. Help the environment and your pocket.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-utensil-spoon"></i></div>
                    <h3>Local Recipes</h3>
                    <p>Explore a growing collection of Kenyan recipes – from Ugali to Nyama Choma, and everything in between.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h4>Create Account</h4>
                    <p>Sign up in seconds – tell us your household size and weekly budget.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h4>Pick Recipes</h4>
                    <p>Choose from our curated Kenyan recipes or add your own family favourites.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h4>Plan & Save</h4>
                    <p>Build your weekly meal plan, see cost breakdowns, and get money-saving tips.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <h2 class="section-title">About Us</h2>
            <div class="about-content">
                <div class="about-text">
                    <p><strong>Kenyan Meal Planner</strong> was born out of a passion for healthy eating and smart budgeting. We believe that every Kenyan family deserves to enjoy nutritious, delicious meals without breaking the bank.</p>
                    <p>Our platform combines traditional Kenyan recipes with modern meal‑planning tools, helping you reduce food waste, save money, and eat balanced meals. Whether you're a busy professional, a parent, or a student, we make meal planning simple and enjoyable.</p>
                    <p>The system was developed by <strong>Emma Nyawira Muriuki</strong> as a project at <strong>Murang'a University of Technology</strong>, with the goal of promoting food sustainability and local culinary heritage.</p>
                </div>
                <div class="about-image">
                    <i class="fas fa-leaf"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2 class="section-title">Get In Touch</h2>
            <div class="contact-grid">
                <div class="contact-card">
                    <i class="fas fa-envelope"></i>
                    <h4>Email</h4>
                    <a href="mailto:info@kenyanmealplanner.co.ke">info@kenyanmealplanner.co.ke</a>
                </div>
                <div class="contact-card">
                    <i class="fas fa-phone-alt"></i>
                    <h4>Phone</h4>
                    <a href="tel:+254712345678">+254 712 345 678</a>
                </div>
                <div class="contact-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h4>Location</h4>
                    <p>Murang'a University of Technology<br>Murang'a, Kenya</p>
                </div>
                <div class="contact-card">
                    <i class="fas fa-globe"></i>
                    <h4>Social Media</h4>
                    <p>
                        <a href="#" style="margin-right: 10px;"><i class="fab fa-twitter"></i> Twitter</a><br>
                        <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h3>Ready to transform your meal planning?</h3>
            <p>Join thousands of Kenyan families saving money and eating better.</p>
            <a href="register.php" class="btn-secondary"><i class="fas fa-user-plus"></i> Create Free Account</a>
        </div>
    </section>

    <footer>
        <div class="footer-mission">
            <div class="footer-mission-item"><i class="fas fa-trash-alt"></i> Reduce Food Waste</div>
            <div class="footer-mission-item"><i class="fas fa-balance-scale"></i> Eat Balanced Meals</div>
            <div class="footer-mission-item"><i class="fas fa-coins"></i> Budget Wisely</div>
        </div>
        <div class="footer-copyright">
            <i class="fas fa-leaf"></i> &copy; <?= date('Y') ?> Kenyan Meal Planner. Developed by Emma Nyawira Muriuki. <i class="fas fa-leaf"></i>
        </div>
    </footer>
</body>
</html>