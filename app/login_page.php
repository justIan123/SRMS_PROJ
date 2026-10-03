<?php
require_once("../public/index.php");
require_once(HANDLERS_PATH . DS . "loginProcess.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
?>
<!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title>Saint Roche SRMS(WIP)</title>
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
 <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

</head>
<body>

<header class="navbar-light bg-light">
    <h1>Saint Roche SRMS</h1>
    <p>SRMS: Student record management System (WIP)</p>
</header>

<nav>
    <div class="container">
        <div class="row">
            <div class="col-6">
                <div class="row">
                    <div class="col-2">
                        
                    </div>
                    <div class="col-8">
                        <img class="img-fluid" src="..\public\images\Saint_Roche.png" alt="Saint_Roche"/>
                    </div>
                    <div class="col-2">
    
                    </div>
                </div>
            </div>

            <div class="col-6">
                <form action="login_page" method="POST">
                    <div class="mb-3">
                        <h3>Log-in</h3>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="formEmail">Email address</label>
                        <input name="email" class="form-control" type="email" placeholder="Enter email"/>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="formPassword">Password</label>
                        <input name="password" class="form-control" type="password" placeholder="Password"/>
                    </div>
                    <div class="mb-3">
                        <?= renderAlert((!empty($err) ? $err: ""), "warning") ?>
                    </div>
                    <div class="mb-3">
                        <button class="btn btn-primary" type="submit">Login</button>
                    </div>
                    <div class="mb-1 text-center">
                        <label class="form-label"></label>
                        <a href="register_page">sdfsdfsdf</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</nav>

<footer class="bg-body-tertiary text-center text-lg-start">
    <div class="container p-4">
        <div class="row"><!-- Company Info -->
            <div class="col-6">
            <h5 class="text-uppercase">Company Name</h5>
            <p class="text-center">
                Lorem ipsum dolor sit amet, consectetur adipisicing elit
            </p>
        </div>

        <div class="col-lg-6"><!-- Contact -->
            <h5 class="text-uppercase">Contact</h5>
            <ul class="list-unstyled mb-0">
                <li>New York, NY 10012, US</li>
                <li>info@example.com</li>
                <li>+ 01 234 567 88</li>
            </ul>
        </div>
   </div>
 </div>
 <!-- Copyright -->
 <div class="text-center p-3">
    <div class="col-12"><!-- Links -->
        <h5 class="text-uppercase">
            Links
        </h5><br>
        <a href="#!">
            <i class="fab fa-facebook-f"></i>
        </a>
        <a href="#!">
            <i class="fab fa-twitter"></i>
        </a>
        <a href="#!">
            <i class="fab fa-google"></i>
        </a>
        <a href="#!">
            <i class="fab fa-linkedin-in"></i>
        </a>

    </div>
 </div>
</footer>

</body>
</html>