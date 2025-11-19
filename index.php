<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    $rolePath = ($_SESSION["role"] === 'admin') ? 'registrar' : $_SESSION["role"];
    header("location: " . $rolePath . "/dashboard.php");
    exit;
}

require_once "php/db_connect.php";

$username = $password = "";
$username_err = $password_err = $login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    if(empty($username_err) && empty($password_err)){
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            if($stmt->execute()){
                $stmt->store_result();
                
                if($stmt->num_rows == 1){                    
                    $stmt->bind_result($id, $username, $hashed_password, $role);
                    if($stmt->fetch()){
                        if(password_verify($password, $hashed_password)){
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;                            
                            $_SESSION["role"] = $role;
                            
                            $rolePath = ($role === 'admin') ? 'registrar' : $role;
                            header("location: " . $rolePath . "/dashboard.php");
                        } else{
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else{
                    $login_err = "Invalid username or password.";
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MARC Agape Christian Learning School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="image/marclogo.png">
    <style>
        html, body { height: 100%; overflow: hidden; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #4E8BC4 0%, #96CBFC 25%, #C2E1FC 50%, #FFC2D9 75%, #FF99BE 100%);
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.88);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            border: 2px solid transparent;
            border-image: linear-gradient(135deg, #4E8BC4, #FF99BE) 1;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo {
            width: 80px;
            height: auto;
            margin-bottom: 10px;
        }
        .login-header h2 {
            color: #4E8BC4;
        }
        .form-label { color: #2f3b4a; font-weight: 500; }
        .form-control { border-radius: 10px; border-color: rgba(78,139,196,0.35); }
        .form-control::placeholder { color: #9aa6b2; }
        .form-control:focus {
            border-color: #4E8BC4;
            box-shadow: 0 0 0 0.25rem rgba(78, 139, 196, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #4E8BC4, #FF99BE);
            border: none;
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 18px rgba(78,139,196,0.25);
            transition: transform .15s ease, box-shadow .2s ease, filter .2s ease, background-position .4s ease;
            background-size: 200% 200%;
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover {
            filter: brightness(0.97);
            box-shadow: 0 10px 22px rgba(255,153,190,0.28);
            transform: translateY(-1px) scale(1.02);
            animation: gradientShift 2s ease infinite;
        }
        .btn-login:active { transform: translateY(0); box-shadow: 0 6px 14px rgba(78,139,196,0.25); }
        .btn-login:focus-visible { outline: 3px solid rgba(78,139,196,0.35); outline-offset: 2px; }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .login-container .text-muted { color: #6c7a89 !important; }
        .invalid-feedback { color: #d63384; }
        .mb-3, .mb-4 { margin-bottom: 1rem !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <img src="image/marclogo.png" alt="MARC Logo" class="login-logo">
                <h2>MACLCI</h2>
                <p class="text-muted">Please use your LRN as the username to login.</p>
            </div>
            
            <?php 
            if(!empty($login_err)){
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }        
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label class="form-label">LRN (Username)</label>
                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>" placeholder="Enter your LRN">
                    <span class="invalid-feedback"><?php echo $username_err; ?></span>
                </div>    
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-login">Login</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
