<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فريق PAC - تطوير الويب الاحترافي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #1a2530;
            --gold: #f1c40f;
            --success: #27ae60;
            --whatsapp: #25D366;
            --telegram: #0088cc;
            --email: #EA4335;
        }

        body {
            background: linear-gradient(135deg, #1a2980, #26d0ce);
            color: var(--light);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
            direction: rtl;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            text-align: center;
            padding: 40px 20px;
            margin-bottom: 40px;
            position: relative;
        }

        header::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 50%;
            transform: translateX(50%);
            width: 150px;
            height: 4px;
            background: var(--gold);
            border-radius: 2px;
        }

        .logo {
            font-size: 3.5rem;
            color: var(--gold);
            margin-bottom: 15px;
        }

        h1 {
            font-size: 3.2rem;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .tagline {
            font-size: 1.4rem;
            color: rgba(255,255,255,0.9);
            max-width: 800px;
            margin: 0 auto 30px;
        }

        .intro-text {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 50px;
            border-right: 5px solid var(--gold);
            font-size: 1.2rem;
            line-height: 1.8;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .team-section {
            margin-bottom: 60px;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 40px;
            color: var(--gold);
            position: relative;
            padding-bottom: 15px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 50%;
            transform: translateX(50%);
            width: 100px;
            height: 3px;
            background: var(--secondary);
        }

        .team-cards {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-bottom: 40px;
        }

        .member-card {
            background: rgba(255,255,255,0.95);
            color: var(--dark);
            border-radius: 15px;
            padding: 30px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }

        .member-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .member-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to left, var(--secondary), var(--accent));
        }

        .member-name {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .member-role {
            font-size: 1.2rem;
            color: var(--secondary);
            margin-bottom: 20px;
            font-weight: bold;
        }

        .member-location {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            color: var(--accent);
        }

        .member-bio {
            margin-bottom: 25px;
            font-size: 1.1rem;
            line-height: 1.7;
        }

        .contact-info {
            margin-top: 20px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px 15px;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 10px;
            border-right: 3px solid var(--secondary);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .contact-item:hover {
            background: rgba(52, 152, 219, 0.2);
            transform: translateX(-5px);
        }

        .contact-icon {
            font-size: 1.5rem;
            color: var(--secondary);
            min-width: 30px;
        }

        .contact-details {
            flex-grow: 1;
        }

        .contact-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 3px;
        }

        .contact-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
            display: block;
        }

        .whatsapp {
            color: var(--whatsapp);
        }

        .email {
            color: var(--email);
        }

        .phone {
            color: var(--secondary);
        }

        .flag {
            font-size: 1.5rem;
            margin-right: 5px;
        }

        .skills-section {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 50px;
        }

        .skill-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .skill-badge {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .backend-badge {
            border-right: 4px solid var(--accent);
        }

        .frontend-badge {
            border-right: 4px solid var(--success);
        }

        .humility-note {
            text-align: center;
            font-style: italic;
            margin: 40px 0;
            padding: 20px;
            background: rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 1.2rem;
            border-right: 3px solid var(--gold);
        }

        .telegram-section {
            background: rgba(0, 136, 204, 0.1);
            padding: 25px;
            border-radius: 15px;
            margin: 30px 0;
            text-align: center;
            border-right: 5px solid var(--telegram);
        }

        .telegram-title {
            color: var(--telegram);
            font-size: 1.8rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .telegram-links {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }

        .telegram-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: var(--telegram);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .telegram-link:hover {
            background: #0077b5;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 136, 204, 0.3);
        }

        .footer {
            text-align: center;
            padding: 30px;
            margin-top: 40px;
            border-top: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.8);
        }

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .social-icon {
            font-size: 1.8rem;
            color: var(--light);
            transition: color 0.3s, transform 0.3s;
        }

        .social-icon:hover {
            color: var(--gold);
            transform: scale(1.2);
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2.5rem;
            }

            .tagline {
                font-size: 1.2rem;
            }

            .member-card {
                padding: 20px;
            }

            .member-name {
                font-size: 1.7rem;
            }

            .contact-value {
                font-size: 1.1rem;
            }

            .skills-section {
                padding: 25px;
            }

            .telegram-links {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .logo {
                font-size: 2.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-code"></i>
            </div>
            <h1>فريق PAC</h1>
            <p class="tagline">تطوير ويب احترافي بكل تواضع وإتقان</p>
        </header>

        <div class="intro-text">
            <p>نحن فريق PAC المتخصص في تطوير حلول الويب المتكاملة. نجمع بين الإبداع في التصميم والدقة في البرمجة لإنشاء منتجات رقمية استثنائية. نؤمن بأن التميز الحقيقي يأتي من التواضع والإتقان في العمل.</p>
        </div>

        <div class="humility-note">
            <i class="fas fa-quote-left" style="color: var(--gold); margin-left: 10px;"></i>
            نعمل بكل تواضع، لأن الكبار لا يحتاجون للتباهي. إتقان العمل هو وسامنا الوحيد.
            <i class="fas fa-quote-right" style="color: var(--gold); margin-right: 10px;"></i>
        </div>

        <div class="team-section">
            <h2 class="section-title">فريقنا المتواضع</h2>

            <div class="team-cards">
                <div class="member-card">
                    <h3 class="member-name">
                        <i class="fas fa-user-tie"></i>
                        طارق عبد الرحمن
                    </h3>
                    <div class="member-role">
                        <i class="fas fa-server"></i>
                        مطور Backend متخصص
                    </div>
                    <div class="member-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>دمشق، سوريا</span>
                        <span class="flag">🇸🇾</span>
                    </div>
                    <div class="member-bio">
                        متخصص في تطوير الأنظمة الخلفية باستخدام Laravel وPHP وNode.js. أملك خبرة في بناء واجهات برمجة التطبيقات (APIs) المعقدة، قواعد البيانات، وأنظمة إدارة المحتوى. أعمل على ضمان الأداء الأمثل والأمان العالي للتطبيقات.
                    </div>

                    <div class="contact-info">
                        <!-- اتصال هاتفي مباشر -->
                        <div class="contact-item" onclick="window.open('tel:0993832567')">
                            <div class="contact-icon phone">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">الاتصال المباشر</div>
                                <div class="contact-value phone">0993832567</div>
                            </div>
                        </div>

                        <!-- واتساب -->
                        <div class="contact-item" onclick="window.open('https://wa.me/963993832567')">
                            <div class="contact-icon whatsapp">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">التواصل عبر واتساب</div>
                                <div class="contact-value whatsapp">0993832567</div>
                            </div>
                        </div>

                        <!-- إيميل -->
                        <div class="contact-item" onclick="window.open('mailto:ttaarreekk34567@gmail.com')">
                            <div class="contact-icon email">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">البريد الإلكتروني</div>
                                <div class="contact-value email">ttaarreekk34567@gmail.com</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="member-card">
                    <h3 class="member-name">
                        <i class="fas fa-user-tie"></i>
                        أحمد طه
                    </h3>
                    <div class="member-role">
                        <i class="fas fa-palette"></i>
                        مطور Frontend مبدع
                    </div>
                    <div class="member-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>إدلب، سوريا</span>
                        <span class="flag">🇸🇾</span>
                    </div>
                    <div class="member-bio">
                        متخصص في تطوير الواجهات الأمامية باستخدام React.js، Vue.js، وأحدث تقنيات CSS. أركز على إنشاء تجارب مستخدم استثنائية، واجهات تفاعلية سلسة، وتصاميم متجاوبة تعمل على جميع الأجهزة. أحرص على أدق التفاصيل الجمالية والتقنية.
                    </div>

                    <div class="contact-info">
                        <!-- اتصال هاتفي مباشر -->
                        <div class="contact-item" onclick="window.open('tel:+963954185769')">
                            <div class="contact-icon phone">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">الاتصال المباشر</div>
                                <div class="contact-value phone">+963 954 185 769</div>
                            </div>
                        </div>

                        <!-- واتساب -->
                        <div class="contact-item" onclick="window.open('https://wa.me/963954185769')">
                            <div class="contact-icon whatsapp">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">التواصل عبر واتساب</div>
                                <div class="contact-value whatsapp">+963 954 185 769</div>
                            </div>
                        </div>

                        <!-- إيميل -->
                        <div class="contact-item" onclick="window.open('mailto:at2951090@gmail.com')">
                            <div class="contact-icon email">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">البريد الإلكتروني</div>
                                <div class="contact-value email">at2951090@gmail.com</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- قسم التلغرام -->
        <div class="telegram-section">
            <h3 class="telegram-title">
                <i class="fab fa-telegram"></i>
                للتواصل معنا على تلغرام
            </h3>
            <p style="color: rgba(255,255,255,0.9); margin-bottom: 15px;">
                يمكنك التواصل معنا مباشرة عبر قنواتنا على تلغرام
            </p>

            <div class="telegram-links">
                <a href="https://t.me/Abo_esmahel" target="_blank" class="telegram-link">
                    <i class="fab fa-telegram"></i>
                    @Abo_esmahel
                </a>

                <a href="https://t.me/PHONE_APP_CHARGER" target="_blank" class="telegram-link">
                    <i class="fab fa-telegram"></i>
                    @PHONE_APP_CHARGER
                </a>
            </div>
        </div>

        <div class="skills-section">
            <h2 class="section-title">مجالات تخصصنا</h2>
            <p style="text-align: center; margin-bottom: 20px; font-size: 1.2rem;">نحن نكمل بعضنا البعض لإنشاء حلول ويب متكاملة</p>

            <div class="skill-badges">
                <div class="skill-badge backend-badge">
                    <i class="fas fa-server"></i>
                    Backend Development
                </div>
                <div class="skill-badge">
                    <i class="fab fa-laravel"></i>
                    Laravel Framework
                </div>
                <div class="skill-badge">
                    <i class="fab fa-php"></i>
                    PHP
                </div>
                <div class="skill-badge">
                    <i class="fas fa-database"></i>
                    قواعد البيانات
                </div>
                <div class="skill-badge">
                    <i class="fas fa-shield-alt"></i>
                    أمن التطبيقات
                </div>
                <div class="skill-badge frontend-badge">
                    <i class="fas fa-desktop"></i>
                    Frontend Development
                </div>
                <div class="skill-badge">
                    <i class="fab fa-react"></i>
                    React.js
                </div>
                <div class="skill-badge">
                    <i class="fab fa-vuejs"></i>
                    Vue.js
                </div>
                <div class="skill-badge">
                    <i class="fab fa-js"></i>
                    JavaScript
                </div>
                <div class="skill-badge">
                    <i class="fab fa-css3-alt"></i>
                    CSS3 & Animation
                </div>
            </div>
        </div>

        <div class="footer">
            <p>فريق PAC - تطوير ويب احترافي بكل تواضع</p>
            <p>دمشق وإدلب، سوريا</p>

            <div class="social-icons">
                <a href="https://wa.me/963993832567" target="_blank" class="social-icon whatsapp" title="واتساب طارق">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <a href="mailto:ttaarreekk34567@gmail.com" class="social-icon email" title="إيميل طارق">
                    <i class="fas fa-envelope"></i>
                </a>
                <a href="https://t.me/Abo_esmahel" target="_blank" class="social-icon" style="color: var(--telegram);" title="تلغرام طارق">
                    <i class="fab fa-telegram"></i>
                </a>
                <a href="https://wa.me/963954185769" target="_blank" class="social-icon whatsapp" title="واتساب أحمد">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <a href="mailto:at2951090@gmail.com" class="social-icon email" title="إيميل أحمد">
                    <i class="fas fa-envelope"></i>
                </a>
            </div>

            <p style="margin-top: 30px; font-size: 0.9rem; opacity: 0.7;">
                &copy; 2024 فريق PAC. جميع الحقوق محفوظة.
            </p>
        </div>
    </div>

    <script>
        // تأثير بسيط عند التمرير
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.member-card');
            const contactItems = document.querySelectorAll('.contact-item');

            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            contactItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(-5px)';
                });

                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });

            // تأثير كتابة النص الترحيبي (اختياري)
            const tagline = document.querySelector('.tagline');
            const originalText = tagline.textContent;
            tagline.textContent = '';

            let i = 0;
            function typeWriter() {
                if (i < originalText.length) {
                    tagline.textContent += originalText.charAt(i);
                    i++;
                    setTimeout(typeWriter, 50);
                }
            }

            // بدء تأثير الكتابة بعد تحميل الصفحة
            setTimeout(typeWriter, 1000);

            // إضافة رسالة عند النقر على أي عنصر اتصال
            contactItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    // منع التنفيذ المزدوج
                    e.stopPropagation();

                    // إضافة تأثير اهتزاز بسيط
                    this.style.animation = 'shake 0.5s ease-in-out';
                    setTimeout(() => {
                        this.style.animation = '';
                    }, 500);
                });
            });
        });

        // إضافة animation للاهتزاز
        const style = document.createElement('style');
        style.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                20%, 40%, 60%, 80% { transform: translateX(5px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
