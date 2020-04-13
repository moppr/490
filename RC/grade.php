<?php

  // grab whatever was relayed to myself
  $json = json_decode( $_POST["req"], true );
  $contents = $json['contents'];
  $q_count = intval($contents['q_count']);
  $student = $contents['student'];
  $test_no = $contents['t_num'];
  $score = 0;
  $max_score = 0;
  $questions_arr = [];
  $send_to_back = [
    "student"=>$student,
    "t_num"=>$test_no
  ];
  
  // analyze each question from the test
  for ($i = 1; $i <= $q_count; $i++){
    $q = $contents[$i];
    $q_num = $q['q_num'];
    $q_text = $q['question'];
    $arg_c = $q['arg_c'];
    $arg_v = json_decode( $q['arg_v'], true );
    $args = "";
    for ($j = 1; $j <= $arg_c; $j++){
      $arg = $arg_v[$j];
      $args .= $arg['name'] . ", ";
    }
    $args = substr($args, 0, -2);    
    $func_name = $q['f_name'];
    $constraint = $q['constraint'];
    $constraint_exists = strcmp($constraint, "default") != 0;
    $student_response = $q['code'];    
    $max_points = intval($q['value']);  // points for the indiv. question, score for the whole test
    $points = 0;
    $comment = "";
    $max_score += $max_points;
    $test_c = intval($q['test_c']);
    $cases_passed = 0;
    $test_v = json_decode( $q['test_v'], true );
    $item_points = [];
    $item_max_points = ["f_name"=>2, "colon"=>"1", "args"=>"2"]; 
    $question_arr = [
      "q_num"=>$q_num,
      "answer"=>$student_response,
    ];    
    $student_func_name = "";
    $words = explode(' ', $student_response);
    $first_line = explode("\n", $student_response)[0];
    foreach ($words as $word){
      if (strstr($word, '(')){  // assume first word containing ( is the def
        $student_func_name = substr($word, 0, strpos($word, '('));
        break;
      }
    }
    $correct_func_name = strcmp($func_name, $student_func_name) == 0;
    $correct_colon = strcmp(substr($first_line, -1), ":") == 0;
    $correct_args = strstr($first_line, $args) ? true : false;
    $correct_constraint = strstr($student_response, $constraint) ? true : false;    
    $case_value = $constraint_exists ? ($max_points-10)/$test_c : ($max_points-5)/$test_c;    
    
    
    //determine points
    if ($correct_func_name){
      $comment .= "You named your function correctly. ";
      $item_points["f_name"] = 2;
      $points += 2;
    }
    else{
      $comment .= "You were supposed to name your function \"".$func_name."\", but you named yours \"".$student_func_name."\". ";
      $item_points["f_name"] = 0;
    }
    if ($correct_colon){
      $comment .= "You remembered to put the colon at the end of the first line. ";
      $item_points["colon"] = 1;
      $points += 1;
    }
    else{
      $comment .= "You were supposed to include a colon at the end of the first line, but yours was missing. ";
      $item_points["colon"] = 0;
    }
    if ($correct_args){
      $comment .= "You named the arguments to your function correctly. ";
      $item_points["args"] = 2;
      $points += 2;
    }
    else{
      $comment .= "You were supposed to give the arguments \"".$input.".\", but instead you provided \"".substr($first_line, strpos($first_line, "(")+1, strpos($first_line, ")")-strpos($first_line, "(")-1)."\". ";
      $item_points["args"] = 0;
    }
    if ($constraint_exists){
      $item_max_points["constraint"] = 5;
      if ($correct_constraint){
        $comment .= "You remembered to use the required constraint, \"".$constraint."\". ";
        $item_points["constraint"] = 5;
        $points += 5;
      }
      else{
        $comment .= "You were supposed to use the required constraint, \"".$constraint."\", but it was not found in your answer. ";
        $item_points["constraint"] = 0;
      }     
    }
    else{
      $item_max_points["constraint"] = 0;
      $item_points["constraint"] = 0;
    }
    
    $comment .= "\n";
    
    
    // run through each test case
    for ($j = 1; $j <= $test_c; $j++){
      $case = $test_v[$j];
      $input = urldecode( $case['input'] );
      $output = urldecode( $case['output'] );
      if (strcmp($constraint, "print") != 0){
        $file_contents = "#!/usr/bin/env python\n".$student_response."\nprint(".$student_func_name."(".$input."))";
      }
      else{
        $file_contents = "#!/usr/bin/env python\n".$student_response."\n".$student_func_name."(".$input.")";
      }
      if (!$correct_colon){
        $file_contents .= ":";
      }
      $file = file_put_contents("grade.py", $file_contents);
      $result = substr(shell_exec("python grade.py 2>&1"), 0, -1);
      $item_max_points[$j] = $case_value;
      if (strcmp($result, $output) == 0){
        $comment .= "You passed the case where the function call was \"".$student_func_name."(".$input.")"."\" and the output was \"".$output."\". ";
        $item_points[$j] = $case_value;
        $points += $case_value;
      }
      else{
        $comment .= "You failed the case where the function call was \"".$student_func_name."(".$input.")\". You were supposed to give \"".$output."\", but instead you gave \"".$result."\". ";
        if (strstr($output, "File \"grade.py\", line") == 0){
          $comment .= "got the following error: \"".$output."\". ";
        }
        else{
          $comment .= "gave ".$output.". ";
        }
        
        $item_points[$j] = 0;
      }
    }    
    
    
    $score += $points;
    $question_arr["comment"] = $comment;
    $question_arr["item_points"] = $item_points;
    $question_arr["item_max_points"] = $item_max_points;
    $question_arr["score"] = $points;
    $question_arr["max"] = $max_points;
    $question_arr["test_c"] = $test_c;
    
    $questions_arr[$i] = $question_arr;
    
  }
  
  $send_to_back["questions"] = $questions_arr;
  $send_to_back["score"] = $score;
  $send_to_back["max"] = $max_score;
  $send_to_back["q_count"] = $q_count;
  
  $send_to_back = json_encode( $send_to_back );
  
  $curl = curl_init("https://web.njit.edu/~co77/cs490/beta/upload_graded_test.php");
  curl_setopt($curl, CURLOPT_POST, 1); 
  curl_setopt($curl, CURLOPT_POSTFIELDS, "req=".urlencode($send_to_back));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  
  $result = curl_exec($curl);
  curl_close($curl);
  
  echo $result;
  
?>