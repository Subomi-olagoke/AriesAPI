<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Subscription | Aries</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset & Base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        :root {
            /* Monochrome Color Palette */
            --color-black: #000000;
            --color-dark-gray: #222222;
            --color-medium-gray: #666666;
            --color-light-gray: #999999;
            --color-extra-light-gray: #f5f5f5;
            --color-white: #ffffff;
            
            /* Font sizes */
            --font-xs: 0.75rem;
            --font-sm: 0.875rem;
            --font-md: 1rem;
            --font-lg: 1.25rem;
            --font-xl: 1.5rem;
            --font-2xl: 2rem;
            --font-3xl: 3rem;
            
            /* Spacing */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-12: 3rem;
            --space-16: 4rem;
            
            /* Border radius */
            --radius-sm: 0.125rem;
            --radius-md: 0.25rem;
            --radius-lg: 0.5rem;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-white);
            color: var(--color-dark-gray);
            line-height: 1.5;
        }
        
        /* Typography */
        h1 {
            font-size: var(--font-3xl);
            font-weight: 700;
            margin-bottom: var(--space-6);
            color: var(--color-black);
        }
        
        h2 {
            font-size: var(--font-2xl);
            font-weight: 600;
            margin-bottom: var(--space-4);
            color: var(--color-black);
        }
        
        h3 {
            font-size: var(--font-xl);
            font-weight: 600;
            margin-bottom: var(--space-3);
            color: var(--color-black);
        }
        
        p {
            margin-bottom: var(--space-4);
            font-size: var(--font-md);
        }
        
        /* Layout */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-4);
        }
        
        .header {
            background-color: var(--color-black);
            color: var(--color-white);
            padding: var(--space-6) 0;
            text-align: center;
        }
        
        .hero {
            padding: var(--space-16) 0;
            text-align: center;
            background-color: var(--color-extra-light-gray);
            border-bottom: 1px solid var(--color-light-gray);
        }
        
        .hero-title {
            font-size: var(--font-3xl);
            margin-bottom: var(--space-4);
        }
        
        .hero-subtitle {
            font-size: var(--font-lg);
            color: var(--color-medium-gray);
            margin-bottom: var(--space-8);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features {
            padding: var(--space-16) 0;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-8);
            margin-top: var(--space-8);
        }
        
        .feature-card {
            padding: var(--space-6);
            background-color: var(--color-white);
            border: 1px solid var(--color-light-gray);
            border-radius: var(--radius-md);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: var(--font-2xl);
            margin-bottom: var(--space-4);
        }
        
        .pricing {
            padding: var(--space-16) 0;
            background-color: var(--color-extra-light-gray);
        }
        
        .pricing-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-8);
            margin-top: var(--space-8);
        }
        
        .pricing-card {
            background-color: var(--color-white);
            border: 1px solid var(--color-light-gray);
            border-radius: var(--radius-md);
            padding: var(--space-8);
            text-align: center;
            display: flex;
            flex-direction: column;
        }
        
        .pricing-card.featured {
            border: 2px solid var(--color-black);
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
        }
        
        .pricing-card.featured::before {
            content: "BEST VALUE";
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--color-black);
            color: var(--color-white);
            padding: var(--space-1) var(--space-3);
            font-size: var(--font-xs);
            font-weight: 600;
            border-radius: var(--radius-md);
        }
        
        .price-title {
            font-size: var(--font-xl);
            font-weight: 600;
            margin-bottom: var(--space-2);
        }
        
        .price {
            font-size: var(--font-3xl);
            font-weight: 700;
            margin-bottom: var(--space-4);
            color: var(--color-black);
        }
        
        .price-period {
            font-size: var(--font-sm);
            color: var(--color-medium-gray);
            margin-bottom: var(--space-6);
        }
        
        .price-features {
            text-align: left;
            margin-bottom: var(--space-6);
            flex-grow: 1;
        }
        
        .price-features li {
            margin-bottom: var(--space-2);
            list-style-type: none;
            position: relative;
            padding-left: var(--space-6);
        }
        
        .price-features li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--color-black);
            font-weight: bold;
        }
        
        .btn {
            display: inline-block;
            padding: var(--space-3) var(--space-6);
            background-color: var(--color-black);
            color: var(--color-white);
            border: none;
            border-radius: var(--radius-md);
            font-size: var(--font-md);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        
        .btn:hover {
            background-color: var(--color-dark-gray);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--color-black);
            border: 1px solid var(--color-black);
        }
        
        .btn-outline:hover {
            background-color: var(--color-black);
            color: var(--color-white);
        }
        
        .testimonials {
            padding: var(--space-16) 0;
        }
        
        .testimonial-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-8);
            margin-top: var(--space-8);
        }
        
        .testimonial-card {
            padding: var(--space-6);
            background-color: var(--color-white);
            border: 1px solid var(--color-light-gray);
            border-radius: var(--radius-md);
        }
        
        .testimonial-text {
            font-style: italic;
            margin-bottom: var(--space-4);
        }
        
        .testimonial-author {
            font-weight: 600;
        }
        
        .testimonial-role {
            font-size: var(--font-sm);
            color: var(--color-medium-gray);
        }
        
        .faq {
            padding: var(--space-16) 0;
            background-color: var(--color-extra-light-gray);
        }
        
        .faq-item {
            border-bottom: 1px solid var(--color-light-gray);
            padding: var(--space-4) 0;
        }
        
        .faq-item:last-child {
            border-bottom: none;
        }
        
        .faq-question {
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-answer {
            padding-top: var(--space-4);
            display: none;
        }
        
        .faq-question.active + .faq-answer {
            display: block;
        }
        
        .footer {
            background-color: var(--color-black);
            color: var(--color-white);
            padding: var(--space-8) 0;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .hero {
                padding: var(--space-8) 0;
            }
            
            .features, .pricing, .testimonials, .faq {
                padding: var(--space-8) 0;
            }
            
            .pricing-card.featured {
                transform: none;
                margin-top: var(--space-4);
                margin-bottom: var(--space-4);
            }
            
            .hero-title {
                font-size: var(--font-2xl);
            }
            
            .hero-subtitle {
                font-size: var(--font-md);
            }
            
            h1 {
                font-size: var(--font-2xl);
            }
            
            h2 {
                font-size: var(--font-xl);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>ARIES</h1>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1 class="hero-title">Upgrade to Premium</h1>
            <p class="hero-subtitle">Unlock advanced features and enhance your experience with Aries Premium</p>
            <a href="#pricing" class="btn">View Plans</a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <h2>Premium Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📹</div>
                    <h3>Larger Video Uploads</h3>
                    <p>Upload videos up to 500MB in size - that's 10x more than the free plan! Perfect for longer tutorials, presentations, and high-quality content.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🖼️</div>
                    <h3>High-Quality Images</h3>
                    <p>Share crystal-clear images up to 50MB in size. Ideal for detailed graphics, professional photography, and visually-rich content.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🧠</div>
                    <h3>AI Post Analysis</h3>
                    <p>Get intelligent insights and suggestions for your posts using our advanced AI. Optimize content engagement and reach your audience more effectively.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Personalized Recommendations</h3>
                    <p>Receive tailored suggestions to improve your content, including title optimizations, structure improvements, and engagement strategies.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🚫</div>
                    <h3>Ad-Free Experience</h3>
                    <p>Enjoy an uninterrupted, ad-free experience throughout the platform, allowing you to focus entirely on your content and learning.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📚</div>
                    <h3>Premium Content Access</h3>
                    <p>Get unlimited access to all courses, libraries, and premium educational content across the platform.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing">
        <div class="container">
            <h2>Choose Your Plan</h2>
            <div class="pricing-cards">
                <div class="pricing-card">
                    <div class="price-title">Free</div>
                    <div class="price">$0</div>
                    <div class="price-period">Forever</div>
                    <ul class="price-features">
                        <li>Upload videos up to 50MB</li>
                        <li>Upload images up to 5MB</li>
                        <li>Create and join channels</li>
                        <li>Follow other users</li>
                        <li>Like and comment on posts</li>
                        <li>Basic content creation</li>
                    </ul>
                    <a href="#" class="btn btn-outline">Current Plan</a>
                </div>
                <div class="pricing-card">
                    <div class="price-title">Monthly Premium</div>
                    <div class="price">$8</div>
                    <div class="price-period">per month</div>
                    <ul class="price-features">
                        <li>Upload videos up to 500MB</li>
                        <li>Upload images up to 50MB</li>
                        <li>AI-powered post analysis</li>
                        <li>Ad-free experience</li>
                        <li>Join live classes</li>
                        <li>Access to all premium content</li>
                        <li>Access to all library contents</li>
                        <li>Create collaboration channels</li>
                    </ul>
                    <a href="/api/premium/purchase?plan=monthly" class="btn">Subscribe Now</a>
                </div>
                <div class="pricing-card featured">
                    <div class="price-title">Yearly Premium</div>
                    <div class="price">$80</div>
                    <div class="price-period">per year (2 months free)</div>
                    <ul class="price-features">
                        <li>Upload videos up to 500MB</li>
                        <li>Upload images up to 50MB</li>
                        <li>AI-powered post analysis</li>
                        <li>Ad-free experience</li>
                        <li>Join live classes</li>
                        <li>Access to all premium content</li>
                        <li>Access to all library contents</li>
                        <li>Create collaboration channels</li>
                        <li>Priority support</li>
                    </ul>
                    <a href="/api/premium/purchase?plan=yearly" class="btn">Subscribe Now</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
        <div class="container">
            <h2>What Our Premium Users Say</h2>
            <div class="testimonial-cards">
                <div class="testimonial-card">
                    <p class="testimonial-text">"The AI post analysis feature has completely changed how I create content. My engagement has increased by 50% since upgrading to Premium!"</p>
                    <div class="testimonial-author">Sarah J.</div>
                    <div class="testimonial-role">Content Creator</div>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"Being able to upload high-quality videos has transformed my teaching experience. I can now share detailed tutorials without compromising on quality."</p>
                    <div class="testimonial-author">Michael T.</div>
                    <div class="testimonial-role">Educator</div>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"The Premium features have given me a competitive edge. The personalized content recommendations are like having a personal content strategy consultant."</p>
                    <div class="testimonial-author">Emma K.</div>
                    <div class="testimonial-role">Digital Creator</div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-item">
                <div class="faq-question">
                    <span>How do I upgrade to Premium?</span>
                    <span class="toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>Simply choose a plan above and click "Subscribe Now." You'll be guided through a secure payment process powered by Paystack. Once your payment is confirmed, your account will be instantly upgraded to Premium.</p>
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>Can I cancel my subscription anytime?</span>
                    <span class="toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>Yes, you can cancel your subscription at any time. Your Premium benefits will continue until the end of your current billing period. To cancel, go to your account settings and click on "Manage Subscription."</p>
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>How is the yearly plan billed?</span>
                    <span class="toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>The yearly plan is billed as a one-time payment of $80 for 12 months of service, offering two months free compared to the monthly plan. You'll receive a renewal notice before your subscription is automatically renewed.</p>
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>What payment methods are accepted?</span>
                    <span class="toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>We accept all major credit cards, debit cards, and bank transfers through our secure payment provider, Paystack.</p>
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    <span>How does the AI post analysis work?</span>
                    <span class="toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>Our AI analyzes your posts for key topics, writing style, potential audience reach, and provides actionable suggestions to improve engagement. You can access this feature from your post dashboard after publishing content.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>© 2025 Aries. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // FAQ accordion functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                question.classList.toggle('active');
                
                // Update the toggle symbol
                const toggle = question.querySelector('.toggle');
                toggle.textContent = question.classList.contains('active') ? '-' : '+';
            });
        });

        // Smooth scroll for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>