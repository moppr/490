<?php
  // grab whatever was relayed to myself
  $json = json_decode( $_POST["req"], true );
  $contents = $json['contents'];
  $q_count = intval($contents['q_count']);
  $student = $contents['student'];
  $test_no = $contents['t_num'];
  
  $send_to_back = [
    "student"=>$student,
    "t_num"=>$test_no
  ];
  
  $score = 0;
  $max_score = 0;
  $questions_arr = [];
  
  // analyze each question from the test
  for ($i = 1; $i <= $q_count; $i++){
    $q = $contents[$i];
    $q_num = $q['q_num'];
    $q_text = $q['question'];
    
    // build arg list
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
    $constraint_exists = $constraint != "default";
    $student_response = $q['code'];    
    $max_points = intval($q['value']);  // points for the indiv. question, score for the whole test
    $points = 0;
    $max_score += $max_points;
    $test_c = intval($q['test_c']);
    $cases_passed = 0;
    $test_v = json_decode( $q['test_v'], true );
    $item_comment = [];
    $item_points = [];
    $item_max_points = ["f_name"=>5, "colon"=>"2", "args"=>"3"]; 
    $question_arr = [
      "q_num"=>$q_num,
      "answer"=>$student_response,
    ];
    
    $student_func_name = "";
    $words = explode(' ', $student_response);
    $first_line = explode("\n", $student_response);
    foreach ($words as $word){
      if (strstr($word, '(')){  // assume first word containing ( is the def
        $student_func_name = substr($word, 0, strpos($word, '(', 0));
        break;
      }
    }
    $correct_func_name = $func_name == $student_func_name;
    $correct_colon = $first_line[0][-1] == ":";
    $correct_args = strstr($first_line, $args) ? true : false;
    $correct_constraint = strstr($student_response, $constraint) ? true : false;
    
    $case_value = $constraint_exists ? ($max_points-15)/$test_c : ($max_points-10)/$test_c;    
    
    // run through each test case
    for ($j = 1; $j <= $test_c; $j++){
      $case = $test_v[$j];
      $input = $case['input'];
      $output = $case['output'];
      // running and grading
      $file_contents = "#!/usr/bin/env python\n".$student_response."\nprint(".$student_func_name."(".$input."))";
      $file = file_put_contents("grade.py", $file_contents);
      $result = substr(shell_exec("python grade.py 2>&1"), 0, -1);
      $item_max_points[$j] = $case_value;
      if ($result == $output){
        $item_comment[$j] = "Test case ".$j." passed";
        $item_points[$j] = $case_value;
      }
      else{
        $item_comment[$j] = "Test case ".$j." failed";
        $item_points[$j] = 0;
      }
    }
    
    //determine points
    if ($correct_func_name){
      $item_comment["f_name"] = "Correct function name";
      $item_points["f_name"] = 5;
      $points += 5;
    }
    else{
      $item_comment["f_name"] = "Incorrect function name";
      $item_points["f_name"] = 0;
    }
    if ($correct_colon){
      $item_comment["colon"] = "Correct colon placement";
      $item_points["colon"] = 2;
      $points += 2;
    }
    else{
      $item_comment["colon"] = "Proper colon missing on first line";
      $item_points["colon"] = 0;
    }
    if ($correct_args){
      $item_comment["args"] = "Correct function arguments";
      $item_points["args"] = 3;
      $points += 3;
    }
    else{
      $item_comment["args"] = "Incorrect function arguments provided";
      $item_points["args"] = 0;
    }
    if ($constraint_exists){
      $item_max_points["constraint"] = 5;
      if ($correct_constraint){
        $item_comment["constraint"] = "Constraint found";
        $item_points["constraint"] = 5;
        $points += 5;
      }
      else{
        $item_comment["constraint"] = "Constraint missing";
        $item_points["constraint"] = 0;
      }     
    }
    else{
      $item_max_points["constraint"] = 0;
      $item_comment["constraint"] = "N/A";
      $item_points["constraint"] = 0;
    }
    
    
    $score += $points;
    $question_arr["item_comment"] = $item_comment;
    $question_arr["item_points"] = $item_points;
    $question_arr["item_max_points"] = $item_max_points;
    $question_arr["score"] = $points
    $question_arr["max"] = $max_points;
    
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