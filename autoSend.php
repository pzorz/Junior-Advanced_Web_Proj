<?php

/*
* Function to conect with the DB
*
* @param $dbName        the name of the DB
* @return $db		the connection to the db
*/
function connectToDB($dbName){

        try{
                $db = new PDO("sqlite:$dbName");
        }catch(PDOException $exception){
                echo"$exception";
        }
 
        return $db;
}

/*
* Function that loops through entire task table.
* Checks the date things are due. If the date is one
* day away from the current date then verify the email is
* valid and email the user. If the time is past due, email
* the user and remove it from the DB.
*
* @param $dbName        the name of the DB
*/

function emailUsers($dbName){
        $db = connectToDB($dbName);
	
	//gets all tasks from the table
        $result = $db->query("select * from tasks");
        
	//loops throug tasks 
        while($row=$result->fetch(PDO::FETCH_ASSOC)){
               	//is the email ok
		if($row["email"]!=""){
                        //is email preference set
			if($row["preference"] == "email"){

                                //$set up timestamps
                                $timestamp =date('m/d/Y', strtotime($row["dueDate"]));
					print_r("the timeStamp:  $timestamp");
                                $yesterday = date('m/d/Y', strtotime('yesterday'));
                                $tomorrow = date('m/d/Y', strtotime('tomorrow'));
					print_r("yesterday: $yesterday");
					print_r("tomorrow: $tomorrow");
                                
				//check the dates
				if($timestamp == $tomorrow) {
                                        mail($row["email"],"Task Reminder","You have a task, \"". $row["taskName"] ."\", due tomorrow.");
                                }
                                else if($timestamp == $yesterday){
                                        mail($row["email"], "Task Overdue", "You did not compleat a task, \"". $row["taskName"] ."\", on time.".
					" It has been removed for you.");
					
					$statement = "DELETE from tasks where taskID=". $db->quote($row["taskID"]);

        				$db->exec($statement);
					print_r($db->errorInfo());
                                }
                        }
                       
                }
                //mail($row,"test","message");
                print_r($row);
        }
  
}

emailUsers("db/fp.db");

?>

