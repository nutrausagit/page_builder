<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Tidak Ditemukan - 404</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .error-container {
            text-align: center;
            max-width: 600px;
            padding: 2rem;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            line-height: 1;
            opacity: 0.8;
            margin-bottom: 1rem;
        }
        
        .error-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 300;
        }
        
        .error-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.9);
            color: #333;
        }
        
        .btn-primary:hover {
            background: white;
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 2rem;
            opacity: 0.7;
        }
        
        @media (max-width: 768px) {
            .error-code {
                font-size: 6rem;
            }
            
            .error-title {
                font-size: 2rem;
            }
            
            .error-message {
                font-size: 1rem;
            }
            
            .error-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
        
        /* Animation */
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
        
        .error-container > * {
            animation: fadeInUp 0.6s ease;
        }
        
        .error-container > *:nth-child(2) { animation-delay: 0.1s; }
        .error-container > *:nth-child(3) { animation-delay: 0.2s; }
        .error-container > *:nth-child(4) { animation-delay: 0.3s; }
        .error-container > *:nth-child(5) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🔍</div>
        <div class="error-code">404</div>
        <h1 class="error-title">Halaman Tidak Ditemukan</h1>
        <p class="error-message">
            Maaf, halaman yang Anda cari tidak dapat ditemukan. 
            Halaman mungkin telah dipindahkan, dihapus, atau URL yang Anda masukkan salah.
        </p>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">
                🏠 Kembali ke Beranda
            </a>
            <a href="javascript:history.back()" class="btn">
                ← Halaman Sebelumnya
            </a>
        </div>
    </div>
    
    <script>
        // Auto redirect to home after 10 seconds if no user interaction
        let redirectTimer;
        let countdown = 10;
        
        function startRedirectTimer() {
            redirectTimer = setInterval(function() {
                countdown--;
                if (countdown <= 0) {
                    window.location.href = '/';
                }
            }, 1000);
        }
        
        function stopRedirectTimer() {
            if (redirectTimer) {
                clearInterval(redirectTimer);
                redirectTimer = null;
            }
        }
        
        // Start timer
        setTimeout(startRedirectTimer, 5000);
        
        // Stop timer on any user interaction
        document.addEventListener('mousemove', stopRedirectTimer);
        document.addEventListener('keydown', stopRedirectTimer);
        document.addEventListener('click', stopRedirectTimer);
        document.addEventListener('scroll', stopRedirectTimer);
    </script>
</body>
</html>
