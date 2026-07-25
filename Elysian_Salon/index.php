<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elysian Salon — Booking & Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php require_once 'config/db.php'; enforce_country_access(ALLOWED_COUNTRY); ?>
    <div class="page-frame">
    <nav class="navbar">
        <div class="container nav-content">
            <div class="logo">
                <img src="assets/img/logo.png" class="logo-img" alt="Elysian Salon logo">
                <!-- <span class="logo-text">Elysian Salon</span> -->
            </div>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#benefits">Why Choose Us</a>
                <a href="auth/login.php">Login</a>
                <a href="auth/register.php" class="btn btn-small btn-primary">Start Free Trial</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container hero-content">
            <div class="hero-inner">
                <div class="hero-photo">
                    <img src="assets/img/Model1.png" alt="Salon model" />
                </div>
                <div class="hero-copy">
                    <h1>Salon Booking & Management</h1>
                    <p>Manage bookings, inventory, and CRM with a cloud-based web app.</p>
                    <div class="hero-actions">
                        <a href="auth/register.php" class="btn btn-accent">Start Free Trial</a>
                        <a href="auth/login.php" class="btn btn-outline">Sign In</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <div class="hero-wave">
        <svg viewBox="0 0 1440 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,40 C240,120 480,0 720,60 C960,120 1200,40 1440,80 L1440,120 L0,120 Z" fill="#fff8f2"></path>
        </svg>
    </div>

    <section id="features" class="features-grid container">
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.5 12"/><path d="M8.5 12L20 20"/></svg>
            </div>
            <h3>Booking & Scheduling</h3>
            <p>Create and manage appointments with calendar tools on desktop or mobile.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16"/><path d="M5 12h14"/></svg>
            </div>
            <h3>Reduce No Shows</h3>
            <p>Automated notifications and reminders help minimize cancellations.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="7" height="16"/><rect x="14" y="4" width="7" height="10"/><path d="M14 18h7"/></svg>
            </div>
            <h3>Inventory Management</h3>
            <p>Accurate reports and rules keep product stock and sales organized.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 11c0 2.761-2.239 5-5 5s-5-2.239-5-5 2.239-5 5-5 5 2.239 5 5z"/><path d="M20 20l-3.5-3.5"/></svg>
            </div>
            <h3>CRM</h3>
            <p>Build a customer database and improve engagement with profile insights.</p>
        </div>
    </section>

    <section id="benefits" class="benefits container">
        <div class="benefit-card">
            <h4>Easy Access</h4>
            <p>Access the system from your website on desktop or phone—no mobile app required.</p>
        </div>
        <div class="benefit-card">
            <h4>Convenience</h4>
            <p>Follow up on client history, finances, visits, and staff profiles.</p>
        </div>
        <div class="benefit-card">
            <h4>Support</h4>
            <p>Get help when you need it and start quickly with a free trial.</p>
        </div>
    </section>

    <section class="cta-banner">
        <div class="container cta-content">
            <h2>Work In A Way That Works Best For You</h2>
            <p>Start your free trial and streamline your salon operations.</p>
            <a href="auth/register.php" class="btn btn-primary btn-large">Start Free Trial</a>
        </div>
    </section>

    <section class="working container">
        <h2 class="section-title">Working In A Way That Works Best For You</h2>
        <div class="grid-3">
            <div class="info-card">
                <h3>Support</h3>
                <p>We offer a 7‑days free trial for new members to try the salon booking system! Our team is always here to help you 24/7. Your success and experience with using our booking website matters to us.</p>
            </div>
            <div class="info-card">
                <h3>Easy Access</h3>
                <p>You can access your booking panel via our website. Online scheduling makes management easy.</p>
            </div>
            <div class="info-card">
                <h3>Convenience</h3>
                <p>Follow up on client history, financial records, customer visits, and staff profiles from desktop or phone browsers.</p>
            </div>
        </div>
    </section>

    <section class="best-software container">
        <h2 class="section-title">Our Salon Services</h2>
        <p style="margin-bottom:40px;">Elysian Salon offers a full range of salon and spa services. Our online booking makes everyday operations simple and supported with a clean web interface.</p>
        <div class="mockups grid-2">
            <img src="assets/img/Hair.avif" alt="Hair activity" class="mockup-img slide-in-left">
            <img src="assets/img/Makeup.jpg" alt="Makeup activity" class="mockup-img slide-in-right">
        </div>
        <div class="split">
            <div>
                <h2>Online Booking for Salons</h2>
                <p>Manage bookings on the go. Through your desktop or phone browser you can schedule online, create bookings with calendar tools, manage inventory, and track sales income.</p>
            </div>
        </div>
    </section>

    <section class="choose-us container">
        <h2 class="section-title">See Why Companies Choose Us</h2>
        <div class="grid-3">
            <div class="info-card">
                <h3>Reduce No Shows</h3>
                <p>Automated notifications keep customers informed and reduce missed appointments.</p>
            </div>
            <div class="info-card">
                <h3>Efficiency</h3>
                <p>Automate operations to improve performance and generate more revenue.</p>
            </div>
            <div class="info-card">
                <h3>Inventory Management</h3>
                <p>Create accurate reports, track income, and keep management hassle‑free.</p>
            </div>
        </div>
    </section>

    <section class="for-everyone container">
        <h2 class="section-title">For Everyone</h2>
        <div class="tiles grid-5">
            <div class="tile"><span>Hair Salon</span></div>
            <div class="tile"><span>Barber Shops</span></div>
            <div class="tile"><span>Nail Salons</span></div>
            <div class="tile"><span>Spa Salons</span></div>
            <div class="tile"><span>Other Beauty</span></div>
        </div>
    </section>

    <section class="why-choose container">
        <h2 class="section-title">Why Choose Us?</h2>
        <div class="features-list grid-4">
            <div class="feature-item">Appointment</div>
            <div class="feature-item">Invoice</div>
            <div class="feature-item">Staff</div>
            <div class="feature-item">Accountancy</div>
            <div class="feature-item">Customer</div>
            <div class="feature-item">Feedback</div>
            <div class="feature-item">Mail</div>
            <div class="feature-item">Service</div>
            <div class="feature-item">Inventory</div>
        </div>
        <!-- visuals removed per request -->
    </section>

    <section class="activities container">
        <h2 class="section-title">Activities</h2>
        <div class="activities-grid">
            <div class="activity">
                <div class="activity-circle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.5 12"/><path d="M8.5 12L20 20"/></svg>
                    <div class="activity-overlay">Hair styling, cutting, and coloring</div>
                </div>
                <div class="activity-label">Hair</div>
            </div>
            <div class="activity">
                <div class="activity-circle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2l3 3v4H9V2z"/><rect x="7" y="9" width="10" height="7" rx="2"/><path d="M7 16h10v4H7z"/></svg>
                    <div class="activity-overlay">Professional makeup for events and daily glam</div>
                </div>
                <div class="activity-label">Makeup</div>
            </div>
            <div class="activity">
                <div class="activity-circle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="8" height="10" rx="2"/><path d="M12 2v6"/><rect x="10" y="2" width="4" height="4"/></svg>
                    <div class="activity-overlay">Manicure, pedicure, and nail art</div>
                </div>
                <div class="activity-label">Nail</div>
            </div>
        </div>
    </section>

    <section class="better-results container">
        <h2 class="section-title">Let Us Show You Better Results</h2>
        <img src="assets/img/laptop.svg" alt="Laptop interface preview" class="mockup-img mockup-laptop slide-in-right">
        <div class="text-center mt-2">
            <a href="auth/register.php" class="btn btn-primary btn-large">Register</a>
        </div>
    </section>

    <section class="blogs container">
        <h2 class="section-title">Our Blogs</h2>
        <div class="grid-3">
            <article class="blog-card">
                <div class="blog-tag">hair-salon-management</div>
                <h3>Hair Salon Management Software</h3>
                <p>Hair salon management software is an application enhancing your life in a fertile way.</p>
                <a href="#" class="btn btn-small read-more">Read More</a>
            </article>
            <article class="blog-card">
                <div class="blog-tag">barber-appointment</div>
                <h3>Barber Appointment Software</h3>
                <p>Modern lifestyles and barbershops evolve. Our tools help address new challenges.</p>
                <a href="#" class="btn btn-small read-more">Read More</a>
            </article>
            <article class="blog-card">
                <div class="blog-tag">barber-shop-management</div>
                <h3>Barber Shop Management Software</h3>
                <p>Running a business needs knowledge and a well‑functioning program to succeed.</p>
                <a href="#" class="btn btn-small read-more">Read More</a>
            </article>
        </div>
    </section>

    <footer class="site-footer">
        <div class="container footer-columns" style="background-color:#fef2f2;">
            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li>info@salonmanagementapp.com</li>
                    <li>WhatsApp</li>
                    <li>Telegram</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Site Map</h4>
                <ul>
                    <li>Home</li>
                    <li>Features</li>
                    <li>Pricing</li>
                    <li>How To</li>
                    <li>About</li>
                    <li>Contact</li>
                    <li>Support</li>
                    <li>Video Support</li>
                    <li>FAQ</li>
                    <li>Blog</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Categories</h4>
                <ul>
                    <li>Hair Salons</li>
                    <li>Barber Shops</li>
                    <li>Nail Salons</li>
                    <li>Spa Salons</li>
                    <li>Other Beauty</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Join Us</h4>
                <ul>
                    <li><a href="auth/login.php">Log In</a></li>
                    <li><a href="auth/register.php" class="signup-link">Sign Up</a></li>
                </ul>
            </div>
        </div>
        <div class="container text-center mt-2">
            <div class="logos-row">
                <span>Great User Experience Award</span>
                <span>Rising Star Award</span>
                <span>Compare Camp</span>
                <span>SaaSworthy</span>
                <span>Capterra</span>
                
            </div>
            <p class="mt-2">&copy; <?php echo date('Y'); ?> Elysian Salon. All rights reserved.</p>
        </div>
    </footer>
    </div>
    <script>
    (function(){
        var targets = document.querySelectorAll('.slide-in-left, .slide-in-right');
        if (!('IntersectionObserver' in window)) {
            for (var i=0; i<targets.length; i++) targets[i].classList.add('in-view');
            return;
        }
        var obs = new IntersectionObserver(function(entries){
            entries.forEach(function(e){
                if (e.isIntersecting) {
                    e.target.classList.add('in-view');
                    obs.unobserve(e.target);
                }
            });
        }, {threshold: 0.2});
        targets.forEach(function(el){ obs.observe(el); });
    })();
    (function(){
        var cards = document.querySelectorAll('.blogs .blog-card');
        for (var i=0; i<cards.length; i++) {
            var p = cards[i].querySelector('p');
            var btn = cards[i].querySelector('.read-more');
            if (!p || !btn) continue;
            var cs = window.getComputedStyle(p);
            var lh = parseFloat(cs.lineHeight);
            if (!(lh > 0)) lh = 20;
            var visible = 3;
            p.style.maxHeight = (visible * lh) + 'px';
            p.style.overflow = 'hidden';
            btn.addEventListener('click', function(ev){
                ev.preventDefault();
                var card = this.closest('.blog-card');
                var para = card.querySelector('p');
                var cstyle = window.getComputedStyle(para);
                var lineH = parseFloat(cstyle.lineHeight);
                if (!(lineH > 0)) lineH = 20;
                var current = parseFloat(para.style.maxHeight) || (3 * lineH);
                var next = current + (3 * lineH);
                para.style.maxHeight = next + 'px';
                if (para.scrollHeight <= next + 1) {
                    this.style.display = 'none';
                }
            });
        }
    })();
    </script>
</body>
</html>
