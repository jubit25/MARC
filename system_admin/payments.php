<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';
if ($_SESSION["role"] !== 'system_admin') {
    header("location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | System Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
    <style>
      html, body, #paymentsFrame { height: 100%; }
      #paymentsFrame { width: 100%; border: 0; min-height: calc(100vh - 140px); }
    </style>
  </head>
  <body class="topnav">
    <div class="wrapper">
      <?php include '../includes/sidebar.php'; ?>
      <div id="content">
        <?php include '../includes/navbar.php'; ?>
        <div class="container-fluid">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Payments</h2>
          </div>
          <iframe id="paymentsFrame" src="../registrar/payments.php?embed=1" title="Payments"></iframe>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
  </body>
</html>
<?php $conn->close(); ?>
