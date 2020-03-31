<?php
  // retrieve POST data from frontend
  $user = $_POST["u"];
  $pass = $_POST["p"];
  
  
  // CONNECT TO BACKEND DATABASE
  $db_curl = curl_init("https://web.njit.edu/~co77/cs490/beta/");
  // tell post being sent to back how many fields and what they are
  curl_setopt($db_curl, CURLOPT_POST, 2); 
  curl_setopt($db_curl, CURLOPT_POSTFIELDS, "u=".$user."&p=".sha1($pass));
  // tell post to return the echoed value instead of displaying it directly 
  curl_setopt($db_curl, CURLOPT_RETURNTRANSFER, true);                                                              
                                                                                                                       
  $result = curl_exec($db_curl);
  curl_close($db_curl);
  $db_json = json_decode( $result );
  
  // response 200 means that it was successful; 403 means that it was not found; 503 means server error
  //$db_response = ("200" == $db_json->status ? "success connecting to database" : "failure connecting to database");
  
  if ( gettype($db_json->message) != string ) {  //indicates success - this is a json obj
    //$message_json = json_decode( $db_json->message );
    $message = "{\"username\":\"".$db_json->message->username."\",\"account_type\":\"".$db_json->message->account_type."\",\"professor\":\"".$db_json->message->professor."\"}";
  }
  else {
    $message = $db_json->message;
  }
  
  // send JSON to front end
  echo json_encode( array('status'=>$db_json->status, 'db'=>$message) );
?>





