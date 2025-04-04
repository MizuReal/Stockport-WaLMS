<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        h1 {
            color: #333;
            margin-bottom: 1.5rem;
        }
        .contact-info {
            text-align: left;
            margin: 1rem 0;
            padding: 1rem;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
        .contact-person {
            margin-bottom: 1rem;
        }
        .contact-person:last-child {
            margin-bottom: 0;
        }
        .email, .phone {
            margin: 0.5rem 0;
            color: #666;
        }
        .back-btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .back-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Forgot Password?</h1>
        <p>If you've forgotten your password, please contact one of our administrators:</p>
        
        <div class="contact-info">
            <div class="contact-person">
                <div class="email">Email: kimjensenyebes@gmail.com</div>
                <div class="phone">Phone: 09123456789</div>
            </div>
            
            <div class="contact-person">
                <div class="email">Email: christianearltapit@gmail.com</div>
                <div class="phone">Phone: 09123456789</div>
            </div>
        </div>
        <a href="index.php" class="back-btn">Back to Login</a>
    </div>
</body>
</html>
