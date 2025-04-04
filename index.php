<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Stockport - Inventory Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/mainindex.css">
    <style>
        /* Global fixes to prevent horizontal scrolling */
        body, html {
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        *, *:before, *:after {
            box-sizing: inherit;
        }
        
        /* Materials Section Styles */
        .materials {
            padding: 80px 20px;
            background-color: #f8f9fa;
            text-align: center;
        }

        .materials h2 {
            margin-bottom: 50px;
            color: #333;
            font-size: 2.5rem;
        }

        .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .material-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .material-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .material-image {
            height: 200px;
            overflow: hidden;
        }

        .material-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .material-card:hover .material-image img {
            transform: scale(1.1);
        }

        .material-info {
            padding: 20px;
        }

        .material-info h3 {
            margin-bottom: 10px;
            color: #2c3e50;
            font-size: 1.4rem;
        }

        .material-info p {
            color: #666;
            line-height: 1.6;
        }

        /* Products Section Styles */
        .products {
            padding: 80px 20px;
            background-color: #edf2f7;
            text-align: center;
        }

        .products h2 {
            margin-bottom: 15px;
            color: #333;
            font-size: 2.5rem;
        }

        .products-subtitle {
            max-width: 700px;
            margin: 0 auto 50px;
            color: #666;
            font-size: 1.2rem;
            line-height: 1.6;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .product-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .product-image {
            height: 180px;
            overflow: hidden;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.08);
        }

        .product-name {
            padding: 16px;
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            border-top: 1px solid #eee;
        }

        /* Warehouse Locations Section */
        .locations {
            padding: 80px 20px;
            background-color: #fff;
            text-align: center;
        }

        .locations h2 {
            margin-bottom: 50px;
            color: #333;
            font-size: 2.5rem;
        }

        .locations-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .location-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .location-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .location-card i {
            font-size: 2.5rem;
            color: #3498db;
            margin-bottom: 15px;
        }

        .location-card h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .material-grid, .locations-grid, .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
            
            .navbar, .nav-links {
                flex-direction: column;
            }
            
            .landing-container {
                width: 100%;
                overflow-x: hidden;
            }
            
            .hero-content h1 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 480px) {
            .materials, .locations, .products {
                padding: 60px 15px;
            }
            
            .material-grid, .locations-grid, .products-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .btn {
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <header class="hero">
            <nav class="navbar">
                <div class="logo">Stockport</div>
                <div class="nav-links">
                    <a href="#features">Features</a>
                    <a href="#materials">Materials</a>
                    <a href="#products">Products</a>
                    <a href="#locations">Locations</a>
                    <a href="#about">About</a>
                    <a href="customer-login.php" class="nav-login">Login</a>
                </div>
            </nav>
            
            <div class="hero-content">
                <h1>Streamline Your Metal Inventory Management</h1>
                <p>Efficient. Reliable. Secure.</p>
                <div class="hero-buttons">
                    <a href="customer-apply.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register as Customer
                    </a>
                    <a href="employee-login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> Employee Portal
                    </a>
                </div>
            </div>
        </header>

        <section id="features" class="features">
            <h2>Why Choose Stockport?</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Real-time Tracking</h3>
                    <p>Monitor your inventory levels in real-time with accurate updates.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure System</h3>
                    <p>Advanced security measures to protect your valuable data.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-tasks"></i>
                    <h3>Easy Management</h3>
                    <p>Intuitive interface for effortless inventory control.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-users"></i>
                    <h3>Team Collaboration</h3>
                    <p>Work together seamlessly with role-based access.</p>
                </div>
            </div>
        </section>

        <section id="materials" class="materials">
            <h2>Materials We Process</h2>
            <div class="material-grid">
                <div class="material-card">
                    <div class="material-image">
                        <img src="assets/imgs/stainlesssteel.jpg" alt="Stainless Steel">
                    </div>
                    <div class="material-info">
                        <h3>Stainless Steel</h3>
                        <p>Corrosion-resistant alloy with excellent durability and strength, perfect for applications requiring high-performance in harsh environments.</p>
                    </div>
                </div>
                
                <div class="material-card">
                    <div class="material-image">
                        <img src="assets/imgs/steel.jpg" alt="Steel">
                    </div>
                    <div class="material-info">
                        <h3>Steel</h3>
                        <p>Versatile carbon and iron alloy offering superior strength-to-weight ratio, ideal for structural applications and manufacturing.</p>
                    </div>
                </div>
                
                <div class="material-card">
                    <div class="material-image">
                        <img src="assets/imgs/tinplate.jpg" alt="Tinplate">
                    </div>
                    <div class="material-info">
                        <h3>Tinplate</h3>
                        <p>Thin steel sheets coated with tin, providing excellent corrosion resistance and food-safe properties for packaging applications.</p>
                    </div>
                </div>
                
                <div class="material-card">
                    <div class="material-image">
                        <img src="assets/imgs/aluminum.jpg" alt="Aluminum">
                    </div>
                    <div class="material-info">
                        <h3>Aluminum</h3>
                        <p>Lightweight, highly conductive metal with natural corrosion resistance, perfect for applications requiring weight reduction and efficiency.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="products" class="products">
            <h2>Our Product Capabilities</h2>
            <p class="products-subtitle">We process materials into a wide range of high-quality products to meet diverse industry needs</p>
            <div class="products-grid">
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/aerosol_can.jpg" alt="Aerosol Can">
                    </div>
                    <div class="product-name">Aerosol Can</div>
                </div>
                
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/baking_mold.jpg" alt="Baking Mold">
                    </div>
                    <div class="product-name">Baking Mold</div>
                </div>
                
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/beverage_can.jpg" alt="Beverage Can">
                    </div>
                    <div class="product-name">Beverage Can</div>
                </div>
                
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/biscuit_tin.jpg" alt="Biscuit Tin">
                    </div>
                    <div class="product-name">Biscuit Tin</div>
                </div>
                
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/century_tuna_can.jpg" alt="Food Cans">
                    </div>
                    <div class="product-name">Food Cans</div>
                </div>
                
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/coin_bank.jpg" alt="Coin Banks">
                    </div>
                    <div class="product-name">Coin Banks</div>
                </div>
                
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/food_tray.jpg" alt="Food Tray">
                    </div>
                    <div class="product-name">Food Tray</div>
                </div>
                
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/fuel_tank.jpg" alt="Fuel Tank">
                    </div>
                    <div class="product-name">Fuel Tank</div>
                </div>
                
                <div class="product-card">
                    <div class="product-image">
                        <img src="assets/imgs/storage_bin.jpg" alt="Storage Bin">
                    </div>
                    <div class="product-name">Storage Bin</div>
                </div>
            </div>
        </section>

        <section id="locations" class="locations">
            <h2>Our Warehouse Locations</h2>
            <div class="locations-container">
                <div class="locations-grid">
                    <div class="location-card">
                        <i class="fas fa-warehouse"></i>
                        <h3>Upper Bicutan</h3>
                        <p>Full-service warehouse facility with specialized metal processing equipment.</p>
                    </div>
                    
                    <div class="location-card">
                        <i class="fas fa-warehouse"></i>
                        <h3>New Lower Bicutan</h3>
                        <p>Modern storage facility with state-of-the-art inventory management systems.</p>
                    </div>
                    
                    <div class="location-card">
                        <i class="fas fa-warehouse"></i>
                        <h3>Central Signal</h3>
                        <p>Strategic distribution center providing rapid delivery throughout the region.</p>
                    </div>
                    
                    <div class="location-card">
                        <i class="fas fa-warehouse"></i>
                        <h3>Ususan</h3>
                        <p>Specialized processing facility focused on precision metal fabrication.</p>
                    </div>
                    
                    <div class="location-card">
                        <i class="fas fa-warehouse"></i>
                        <h3>Fort Bonifacio</h3>
                        <p>Premium storage location with enhanced security for high-value materials.</p>
                    </div>
                    
                    <div class="location-card">
                        <i class="fas fa-warehouse"></i>
                        <h3>Bagumbayan</h3>
                        <p>Expansive facility offering comprehensive metal processing services.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="about">
            <div class="about-content">
                <h2>About Stockport</h2>
                <p>Stockport is a comprehensive inventory management system designed to help businesses maintain optimal stock levels, reduce costs, and improve efficiency. Join our team and be part of a revolutionary approach to inventory management.</p>
            </div>
        </section>

        <footer class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="customer-apply.php">Register as Customer</a>
                    <a href="employee-login.php">Employee Login</a>
                </div>
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p><i class="fas fa-envelope"></i> support@stockport.com</p>
                    <p><i class="fas fa-phone"></i> (555) 123-4567</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2023 Stockport. All rights reserved.</p>
            </div>
        </footer>
    </div>
</body>
</html>