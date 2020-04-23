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
    $item_test_cases = [];
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
      $item_points["f_name"] = 2;
      $points += 2;
    }
    else{
      $item_points["f_name"] = 0;
    }
    $item_test_cases["f_name"] = "Expected: ".$func_name."\nOutput: ".$student_func_name;
    
    if ($correct_colon){
      $item_test_cases["colon"] = "Colon found";
      $item_points["colon"] = 1;
      $points += 1;
    }
    else{
      $item_test_cases["colon"] = "Colon missing";
      $item_points["colon"] = 0;
    }
    
    if ($correct_args){
      $comment .= "You named the arguments to your function correctly.\n";
      $item_points["args"] = 2;
      $points += 2;
    }
    else{
      $comment .= "You were supposed to give the arguments \"".$args."\", but instead you provided \"".substr($first_line, strpos($first_line, "(")+1, strpos($first_line, ")")-strpos($first_line, "(")-1)."\".\n";
      $item_points["args"] = 0;
    }
    $item_test_cases["args"] = "Expected: ".$args."\nOutput: ".substr($first_line, strpos($first_line, "(")+1, strpos($first_line, ")")-strpos($first_line, "(")-1);
    
    
    if ($constraint_exists){
      $item_max_points["constraint"] = 5;
      if ($correct_constraint){
        $item_test_cases["constraint"] = "Constraint ".$constraint." found";
        $item_points["constraint"] = 5;
        $points += 5;
      }
      else{
        $item_test_cases["constraint"] = "Constraint ".$constraint." missing";
        $item_points["constraint"] = 0;
      }     
    }
    else{
      $item_max_points["constraint"] = 0;
      $item_points["constraint"] = 0;
      $item_test_cases["constraint"] = "N/A";
    }
    
    // fix missing colon
    if (!$correct_colon){
      $colon_spot = strpos($student_response, ")");
      $student_response = substr($student_response, 0, $colon_spot+1) . ":" . substr($student_response, $colon_spot+1);
    }
    
    
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
      $file = file_put_contents("grade.py", $file_contents);
      $result = substr(shell_exec("python grade.py 2>&1"), 0, -1);
      $item_max_points[$j] = round($case_value, 2);
      if (strcmp($result, $output) == 0){
        $item_points[$j] = round($case_value, 2);
        $points += $case_value;
      }
      else{        
        $item_points[$j] = 0;
      }
      $item_test_cases[$j] = "Input: ".$func_name."(".$input.")\nExpected: ".$output."\nOutput: ".$result;
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