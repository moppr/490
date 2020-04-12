<?php

  $raw_json = $_POST["req"];
  $json = json_decode( $raw_json );
  $action = $json->action;
  
  switch( $action )
  {  
    case 'addquestion':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/upload_question.php");
      break;
    
    case 'createexam':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/upload_test.php");
      break;
      
    case 'chooseexamtograde':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_all_tests.php");
      break;
      
    case 'getstudents':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_all_graded_tests.php");
      break;
      
    case 'getgradedexam':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_graded_test.php");
      break;
      
    case 'finishedexam':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/update_graded_test.php");
      break;
      
    case 'chooseexamtotake':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_all_tests.php");
      break;
      
    case 'takeexam':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_test.php");
      break;
      
    case 'submitexam':
      $curl = curl_init("https://web.njit.edu/~mba27/cs490/grade.php");
      break;
      
    case 'chooseexamtorelease':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_all_tests.php");
      break; 
      
    case 'releaseexam':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/update_visibility.php");
      break;
      
    case 'chooseexamtoview':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_all_viewable_tests.php");
      break;
      
    case 'viewexam':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_graded_test.php");
      break;
      
    case 'qbank':
      $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/get_all_questions.php");
      break;
      
    default:
      break;
  }
  
  curl_setopt($curl, CURLOPT_POST, 1); 
  curl_setopt($curl, CURLOPT_POSTFIELDS, "req=".urlencode($raw_json));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  
  $result = curl_exec($curl);
  curl_close($curl);
  
  echo $result;
?>