<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PropertyPro | Premium Apartment Living</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts - Modern Sans Serif -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3f37c9;
            --primary-light: #4895ef;
            --accent: #4cc9f0;
            --success: #43aa8b;
            --warning: #f8961e;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #f1f3f5;
        }
        
        body {
            font-family: 'Manrope', sans-serif;
            color: var(--dark);
            overflow-x: hidden;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
        }
        
        .hero-section {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.9) 0%, rgba(63, 55, 201, 0.9) 100%), 
                        url('https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-light:hover {
            color: var(--primary);
        }
        
        .feature-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .apartment-card {
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            border-radius: 12px;
        }
        
        .apartment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .amenities-section {
            background-color: var(--light-gray);
        }
        
        .amenity-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .testimonial-card {
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .testimonial-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
        }
        
        .footer {
            background-color: var(--dark);
            color: white;
        }
        
        .footer a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer a:hover {
            color: white;
        }
        
        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .section-title {
            position: relative;
            display: inline-block;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            width: 50%;
            height: 4px;
            background: var(--primary);
            bottom: -10px;
            left: 0;
            border-radius: 2px;
        }
        
        .property-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .property-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .property-detail-icon {
            color: var(--primary);
            margin-right: 5px;
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--primary);
        }
        
        .nav-pills .nav-link {
            color: var(--dark);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand text-primary" href="#">
                <i class="fas fa-building me-2"></i>PropertyPro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#apartments">Apartments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#amenities">Amenities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#location">Location</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonials</a>
                    </li>
                </ul>
                <div class="ms-lg-3 mt-3 mt-lg-0">
                    <a href="login.php" class="btn btn-primary px-4">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-white" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7" data-aos="fade-up">
                    <h1 class="display-4 fw-bold mb-4">Experience Premium Living in General Santos City</h1>
                    <p class="lead mb-5">PropertyPro offers luxurious apartments with world-class amenities, designed for those who appreciate quality and comfort.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#apartments" class="btn btn-light btn-lg px-4 py-3">Explore Properties</a>
                        <a href="#contact" class="btn btn-outline-light btn-lg px-4 py-3">Virtual Tour</a>
                    </div>
                </div>
                <div class="col-lg-5 d-none d-lg-block" data-aos="fade-left">
                    <div class="position-relative">
                        <img src="https://images.unsplash.com/photo-1580041065738-e72023775cdc?q=80&w=1740&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" 
                             alt="Luxury Apartment" 
                             class="img-fluid rounded-3 shadow-lg animate__animated animate__pulse animate__infinite animate__slower">
                        <div class="position-absolute bottom-0 start-0 bg-primary p-3 rounded-top-end">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-map-marker-alt fa-lg me-2"></i>
                                <span>General Santos City</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up">
                    <div class="feature-card bg-white p-4 text-center shadow-sm">
                        <div class="amenity-icon mx-auto">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="mb-3">24/7 Security</h4>
                        <p class="text-muted">Advanced security systems with CCTV monitoring and professional guards for your safety.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card bg-white p-4 text-center shadow-sm">
                        <div class="amenity-icon mx-auto">
                            <i class="fas fa-swimming-pool"></i>
                        </div>
                        <h4 class="mb-3">Infinity Pool</h4>
                        <p class="text-muted">Stunning rooftop infinity pool with panoramic views of General Santos City.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card bg-white p-4 text-center shadow-sm">
                        <div class="amenity-icon mx-auto">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <h4 class="mb-3">Fitness Center</h4>
                        <p class="text-muted">State-of-the-art gym equipment with personal training services available.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Apartments Section -->
    <section class="py-5" id="apartments">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title display-5 fw-bold">Our Featured Properties</h2>
                <p class="lead text-muted mt-3">Discover our selection of premium apartments designed for modern living</p>
            </div>
            
            <div class="row g-4">
                <!-- Apartment 1 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up">
                    <div class="apartment-card card h-100 shadow-sm">
                        <div class="position-relative">
                            <img src="https://images.unsplash.com/photo-1493809842364-78817add7ffb?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80" 
                                 class="card-img-top" 
                                 alt="Luxury Apartment">
                            <div class="property-badge">Available</div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">The Skyline Residence</h5>
                            <p class="card-text text-muted">Modern 2-bedroom apartment with stunning city views and premium finishes.</p>
                            <div class="property-price mb-3">₱50,000/month</div>
                            <div class="d-flex justify-content-between text-muted mb-3">
                                <span><i class="fas fa-bed property-detail-icon"></i> 2 Bedrooms</span>
                                <span><i class="fas fa-bath property-detail-icon"></i> 2 Bathrooms</span>
                                <span><i class="fas fa-ruler-combined property-detail-icon"></i> 85 sqm</span>
                            </div>
                            <a href="#" class="btn btn-outline-primary w-100">View Details</a>
                        </div>
                    </div>
                </div>
                
                
                <!-- Apartment 2 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="apartment-card card h-100 shadow-sm">
                        <div class="position-relative">
                            <img src="https://images.unsplash.com/photo-1584622650111-993a426fbf0a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80" 
                                 class="card-img-top" 
                                 alt="Luxury Apartment">
                            <div class="property-badge">Available</div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">The Urban Loft</h5>
                            <p class="card-text text-muted">Spacious loft-style apartment with industrial-chic design and smart home features.</p>
                            <div class="property-price mb-3">₱50,000/month</div>
                            <div class="d-flex justify-content-between text-muted mb-3">
                                <span><i class="fas fa-bed property-detail-icon"></i> 1 Bedroom</span>
                                <span><i class="fas fa-bath property-detail-icon"></i> 1 Bathroom</span>
                                <span><i class="fas fa-ruler-combined property-detail-icon"></i> 65 sqm</span>
                            </div>
                            <a href="#" class="btn btn-outline-primary w-100">View Details</a>
                        </div>
                    </div>
                </div>
                
                <!-- Apartment 3 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="apartment-card card h-100 shadow-sm">
                        <div class="position-relative">
                            <img src="https://images.unsplash.com/photo-1512917774080-9991f1c4c750?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80" 
                                 class="card-img-top" 
                                 alt="Luxury Apartment">
                            <div class="property-badge">Available</div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">The Executive Suite</h5>
                            <p class="card-text text-muted">Luxurious 3-bedroom suite with premium appliances and concierge service.</p>
                            <div class="property-price mb-3">₱100,000/month</div>
                            <div class="d-flex justify-content-between text-muted mb-3">
                                <span><i class="fas fa-bed property-detail-icon"></i> 3 Bedrooms</span>
                                <span><i class="fas fa-bath property-detail-icon"></i> 2.5 Bathrooms</span>
                                <span><i class="fas fa-ruler-combined property-detail-icon"></i> 120 sqm</span>
                            </div>
                            <a href="#" class="btn btn-outline-primary w-100">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5" data-aos="fade-up">
                <a href="#" class="btn btn-primary btn-lg px-4 py-3">View All Properties</a>
            </div>
        </div>
    </section>

    <!-- Amenities Section -->
    <section class="py-5 amenities-section" id="amenities">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title display-5 fw-bold">World-Class Amenities</h2>
                <p class="lead text-muted mt-3">Designed to enhance your lifestyle and provide ultimate convenience</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-aos="fade-up">
                    <div class="bg-white p-4 rounded-3 shadow-sm h-100 text-center">
                        <div class="amenity-icon mx-auto">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <h4 class="mb-3">High-Speed Internet</h4>
                        <p class="text-muted mb-0">Fiber optic internet throughout the building with premium bandwidth.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="bg-white p-4 rounded-3 shadow-sm h-100 text-center">
                        <div class="amenity-icon mx-auto">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h4 class="mb-3">Private Dining</h4>
                        <p class="text-muted mb-0">Chef's kitchen and dining room available for private events.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="bg-white p-4 rounded-3 shadow-sm h-100 text-center">
                        <div class="amenity-icon mx-auto">
                            <i class="fas fa-spa"></i>
                        </div>
                        <h4 class="mb-3">Wellness Spa</h4>
                        <p class="text-muted mb-0">Relaxation spaces including sauna and massage rooms.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="bg-white p-4 rounded-3 shadow-sm h-100 text-center">
                        <div class="amenity-icon mx-auto">
                            <i class="fas fa-car"></i>
                        </div>
                        <h4 class="mb-3">Secure Parking</h4>
                        <p class="text-muted mb-0">Underground parking with EV charging stations available.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Location Section -->
    <section class="py-5" id="location">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right">
                    <h2 class="section-title display-5 fw-bold mb-4">Prime Location in General Santos City</h2>
                    <p class="lead text-muted mb-4">Our property is strategically located in the heart of General Santos City, providing easy access to business districts, shopping centers, and entertainment hubs.</p>
                    
                    <div class="d-flex mb-3">
                        <div class="me-4">
                            <i class="fas fa-map-marker-alt fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Address</h5>
                            <p class="text-muted mb-0">123 Premium Heights, General Santos City</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-3">
                        <div class="me-4">
                            <i class="fas fa-clock fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Office Hours</h5>
                            <p class="text-muted mb-0">Monday - Saturday: 9:00 AM - 6:00 PM</p>
                        </div>
                    </div>
                    
                    <div class="d-flex">
                        <div class="me-4">
                            <i class="fas fa-phone-alt fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Contact</h5>
                            <p class="text-muted mb-0">(083) 123-4567 | info@propertypro-gensan.com</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="ratio ratio-16x9 rounded-3 overflow-hidden shadow-lg">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126845.395783886!2d125.0919192!3d6.1203475!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x32f80d5f2d0eaa3b%3A0x4b0f119758a0d5c9!2sGeneral%20Santos%20City%2C%20South%20Cotabato!5e0!3m2!1sen!2sph!4v1689831234567!5m2!1sen!2sph" 
                                width="600" 
                                height="450" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5 bg-light" id="testimonials">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title display-5 fw-bold">What Our Residents Say</h2>
                <p class="lead text-muted mt-3">Hear from people who call PropertyPro home</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up">
                    <div class="testimonial-card bg-white p-4 h-100">
                        <div class="d-flex align-items-center mb-4">
                            <img src="https://randomuser.me/api/portraits/women/32.jpg" 
                                 alt="Testimonial" 
                                 class="testimonial-img me-3">
                            <div>
                                <h5 class="mb-1">Maria Santos</h5>
                                <p class="text-muted mb-0">Resident since 2021</p>
                            </div>
                        </div>
                        <p class="text-muted">"Living at PropertyPro has been an amazing experience. The amenities are top-notch and the management team is always responsive to any concerns."</p>
                        <div class="text-warning mt-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card bg-white p-4 h-100">
                        <div class="d-flex align-items-center mb-4">
                            <img src="https://randomuser.me/api/portraits/men/45.jpg" 
                                 alt="Testimonial" 
                                 class="testimonial-img me-3">
                            <div>
                                <h5 class="mb-1">Juan Dela Cruz</h5>
                                <p class="text-muted mb-0">Resident since 2022</p>
                            </div>
                        </div>
                        <p class="text-muted">"The location is perfect for my work and the building facilities are maintained impeccably. I especially love the rooftop pool area after a long day."</p>
                        <div class="text-warning mt-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card bg-white p-4 h-100">
                        <div class="d-flex align-items-center mb-4">
                            <img src="https://randomuser.me/api/portraits/women/68.jpg" 
                                 alt="Testimonial" 
                                 class="testimonial-img me-3">
                            <div>
                                <h5 class="mb-1">Andrea Reyes</h5>
                                <p class="text-muted mb-0">Resident since 2020</p>
                            </div>
                        </div>
                        <p class="text-muted">"As someone who values security, I appreciate the 24/7 monitoring and quick response from the staff. The community events are also a nice touch."</p>
                        <div class="text-warning mt-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-8 mb-4 mb-lg-0" data-aos="fade-right">
                    <h2 class="display-5 fw-bold mb-3">Ready to Experience PropertyPro?</h2>
                    <p class="lead mb-0">Schedule a tour today and see why we're the premier choice for luxury living in General Santos City.</p>
                </div>
                <div class="col-lg-4 text-lg-end" data-aos="fade-left">
                    <a href="#" class="btn btn-light btn-lg px-4 py-3">Schedule a Tour</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer pt-5" id="contact">
        <div class="container pt-5">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h3 class="text-white mb-4">
                        <i class="fas fa-building me-2"></i>PropertyPro
                    </h3>
                    <p class="text-muted">Premium apartment living with luxury amenities in the heart of General Santos City.</p>
                    <div class="d-flex mt-4 gap-3">
                        <a href="#" class="social-icon">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4">
                    <h5 class="text-white mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home">Home</a></li>
                        <li class="mb-2"><a href="#apartments">Apartments</a></li>
                        <li class="mb-2"><a href="#amenities">Amenities</a></li>
                        <li class="mb-2"><a href="#location">Location</a></li>
                        <li class="mb-2"><a href="#testimonials">Testimonials</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-4">
                    <h5 class="text-white mb-4">Contact Us</h5>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                            123 Premium Heights, General Santos City
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone-alt me-2 text-primary"></i>
                            (083) 123-4567
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2 text-primary"></i>
                            info@propertypro-gensan.com
                        </li>
                    </ul>
                </div>
                
               <div class="col-lg-3 col-md-4">
    <h5 class="text-white mb-4">Powered By</h5>
    <div class="d-flex align-items-center mb-3">
        <i class="fas fa-laptop-code fa-2x text-primary me-3"></i>
        <div>
            <h6 class="mb-0">J7 IT Solution and Services/
                Develop by: Jazel Jade Selayro
            </h6>
            <small class="text-muted">General Santos City</small>
        </div>
    </div>
    <p class="text-muted small">Providing innovative technology solutions for modern businesses and properties.</p>
    
</div>

            
            <hr class="my-5 border-secondary">
            
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="text-muted small mb-0">&copy; 2023 PropertyPro. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="#" class="text-muted small">Privacy Policy</a></li>
                        <li class="list-inline-item"><span class="text-muted mx-2">•</span></li>
                        <li class="list-inline-item"><a href="#" class="text-muted small">Terms of Service</a></li>
                        <li class="list-inline-item"><span class="text-muted mx-2">•</span></li>
                        <li class="list-inline-item"><a href="#" class="text-muted small">Sitemap</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <a href="#" class="btn btn-primary btn-lg rounded-circle shadow back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </a>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS animation
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });
        
        // Back to top button
        document.addEventListener('DOMContentLoaded', function() {
            var backToTop = document.getElementById('backToTop');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTop.classList.add('show');
                } else {
                    backToTop.classList.remove('show');
                }
            });
            
            backToTop.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({top: 0, behavior: 'smooth'});
            });
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>