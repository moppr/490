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
    $arg_v = $q['arg_v'];
    $args = "";
    for ($j = 1; $j <= $arg_c; $j++){
      $arg = $arg_v[$j];
      $args .= $arg['name'] . ", ";
    }
    $args = substr($args, 0, -2);
    
    // find correct function name from question
    $words = explode(' ', $q_text);
    $func_name = "noFunctionNameProvided";
    foreach ($words as $word){
      if (substr($word, -2) == "()"){
        $func_name = substr($word, 0, -2);
      }
    }
    
    // build correct first line
    $first_line = "def ".$func_name."(".$args."):";
    
    
    $student_response = $q['code'];
    $student_first_line = explode("\n", $student_response)[0];
    
    // see if first line (function definition) matches
    $correct_func_def = $first_line == $student_first_line;
    
    // find student's function name and replace correct one with student one (does nothing if there's no mismatch)
    $words = explode(' ', $student_response);
    foreach ($words as $word){
      if (strstr($word, '(')){  // assume first word containing ( is the def
        $student_func_name = substr($word, 0, strpos($word, '(', 0));
        break;
      }
    }
    $func_name = isset($student_func_name) ? $student_func_name : $func_name;
    
    $max_points = intval($q['value']);  // points for the indiv. question, score for the whole test
    $max_score += $max_points;
    $test_c = intval($q['test_c']);
    $cases_passed = 0;
    $test_v = $q['test_v'];
    $comment = "";
    $points = $max_points;        
    $question_arr = [
      "q_num"=>$q_num,
      "answer"=>$student_response,
    ];
    
    // run through each test case
    for ($j = 1; $j <= $test_c; $j++){
      $case = $test_v[$j];
      $input = $case['input'];
      $output = $case['output'];
      // running and grading
      $file_contents = "#!/usr/bin/env python\n".$student_response."\nprint(".$func_name."(".$input."))";
      $file = file_put_contents("grade.py", $file_contents);
      $result = substr(shell_exec("python grade.py 2>&1"), 0, -1);
      if ($result == $output){
        $cases_passed++;
      }
    }
    
    //determine points
    $comment .= "Passed $cases_passed out of $test_c test cases";
    $points = intval($cases_passed/$test_c * $max_points);
    if ($correct_func_def && $points < $max_points){
      $comment .= "\n1 point awarded back for correct function definition";
      $points += 1;  // intval rounds down so this can't go over max
    }
    elseif (!$correct_func_def && $points > 0){
      $comment .= "\n1 point deducted for wrong function definition";
      $points -= 1;
    }
    
    $score += $points;
    $question_arr["comment"] = $comment;
    $question_arr["score"] = $points;
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