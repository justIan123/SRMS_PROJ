<?php
//must include require_once(DB_PATH);
 

session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$isUserIsAdmin = false;
$isUserIsTeacher = false;
try{
  $stmt = $pdo->prepare("
          SELECT 1 
          FROM public.user_roles 
          WHERE user_id = :user_id AND role = 1 
          LIMIT 1
      ");
      $stmt->execute(['user_id' => $_SESSION['user_id']]);
      
      // If a matching row is found, fetch() returns an array (truthy), making it true
      $isUserIsAdmin = $stmt->fetch() !== false;
}catch(PDOException $e){
    
}

try{
  $stmt = $pdo->prepare("
          SELECT 1 
          FROM public.user_roles 
          WHERE user_id = :user_id AND role = 2 
          LIMIT 1
      ");
      $stmt->execute(['user_id' => $_SESSION['user_id']]);
      
      // If a matching row is found, fetch() returns an array (truthy), making it true
      $isUserIsTeacher = $stmt->fetch() !== false;
}catch(PDOException $e){
    
}