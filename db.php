<?php

require_once("constants.php");
require_once("utils.php");

/**
 * Makes a connection to the specified SQLite database.
 *
 * @param dbName The name of the SQLite database.
 * @return A PDO instance for the database.
 */
function connectToDB($dbName){
    // Try connecting; if there's a problem, bail!
    try{
        $db = new PDO("sqlite:$dbName");
    } catch(PDOException $exception) {
        dieWithError($exception->getMessage);
    }   

    // Allows us to access PDO errors.
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);

    return $db;
}

/**
 * Checks if the given table exists in the specified database.
 *
 * @param dbName The name of the database.
 * @param table The name of the table.
 * @return True if the table is found.
 */
function tableExists($dbName, $table){
    $db = connectToDB($dbName);

    // Find all the tables named $table.
    $query = "select name from sqlite_master where type='table' and ".
        "name='$table'";
    $result = $db->query($query);

    // Make sure the result is valid.
    if($result===false) dieWithError($db->errorInfo());

    // Check if there is more than one row.
    return count($result->fetchAll()) > 0;
}

/**
 * Creates a table called users in the given database. This table has
 * the following columns:
 *  
 *  email   		-- string (primary key)
 *  name     		-- string
 *  password_hash	-- string (not null)
 *
 * @param dbName The name of the database in which to create the table.
 */
function createUsersTable($dbName){
    $db = connectToDB($dbName);

    // Create the table if it doesn't already exist.
    $statement = "create table if not exists users(".
        "email text primary key, name text, passwordHash text not null)";
    $result = $db->exec($statement);

    // Make sure the statement was executed okay.
    if($result===false) dieWithError($db->errorInfo());
}

/**
 * Adds a new entry to the database.
 *
 * @param dbName The name of the database.
 * @param email The user's email (should be unique).
 * @param name The user's name.
 * @param password The user's authorization plain text password.
 */
function addUserEntry($dbName, $email, $name, $password){
    $db = connectToDB($dbName);

    // Create the statement to insert the data.
    $statement = "insert into users values (". $db->quote($email) .",".
        $db->quote($name) .",'". password_hash($password, PASSWORD_DEFAULT)."'" .")";

    $rowsEffected = $db->exec($statement);

    // The exec method either returns a number if things were succesful, or
    // false if they weren't. 0 == false, so we need to use three equals
    // here to test whether the returned value is actually the boolean
    // false.
    if($rowsEffected === false) dieWithError($db->errorInfo());
}

/**
 * Gets the user's information based on their email if it exists
 * (in an associative array of values), or false otherwise.
 *
 * @param dbName The name of the database.
 * @param email The email to look up.
 * @return An associative array of user info or false if the email is not 
 *         found.
 */
function userExists($dbName, $email){
    $db = connectToDB($dbName);

    // Create the query that looks for the user with the given email.
    $query = "select * from users where email=". $db->quote($email);

    $result = $db->query($query);

    // Verify things ran okay.
    if(!$result) dieWithError($db->errorInfo());

    // Count the number of rows returned: if no rows, then the user
    // doesn't exist, so return false. Otherwise, return the first (and what
    // should be the only) row.
    $result = $result->fetchAll();
    if(count($result) == 0)
        return false;
    else
        return $result[0];
}

/*
* Function to authinticate a user. Called when a user logs in.
* 
* @param $dbName        The name of the DB the user is in
* @param $email         The users email
* @param $password      The user's clear text password
* @return a boolean value. True if the user is authinticated.
* False if not.
*/
function authUser($dbName, $email, $password){
       //conect to the database
	if(!userExists($dbName, $email)) dieWithError("User does not exist");
	$userData = userExists($dbName, $email);

    	if($userData){
        	return password_verify($password, $userData["passwordHash"]);
    	}
    	return false;

}

/*
* Function to add a task to the task table. 
* @param $dbName        The name of the DB the table is in
* @param $email         The user
* @param $taskName      The name of the task to add
* @param $dueDate       The day the task is due
* @param $preference	Option to have app email user before task is due
*/
function addTask($dbName, $email, $taskName, $dueDate, $preference){
        //conect to the DB
	$db = connectToDB($dbName);
        
	//generate a random task ID
	$id;
		
        //query the DB for task ID's
        //if the task ID already exists, generate a new ID
	do{
		$id = rand();
	}while(taskExists($dbName, $id));
        
	//go through the associative array of taskDiscription
        //add the email, taskName, the discriptons, and the task ID to the 
                //task info table
	$statement = "insert into tasks values(".$db->quote($id).",".$db->quote($taskName).",".$db->quote($email).",".$db->quote($dueDate).",".$db->quote($preference).")";

	$rowsEffected = $db->exec($statement);

    	// The exec method either returns a number if things were succesful, or
    	// false if they weren't. 0 == false, so we need to use three equals
    	// here to test whether the returned value is actually the boolean
    	// false.
    	if($rowsEffected === false) dieWithError($db->errorInfo());
}

/*
* Function to make the Task table in the database.
*
* @param $dbName        The name of the DB the table should be created in
*/
function createTaskTable($dbName){
	//connect to DB
	$db = connectToDB($dbName);

    	// Create the table if it doesn't already exist.
   	$statement = "create table if not exists tasks(taskID text, taskName text, email text, dueDate text, preference text)";
    	$result = $db->exec($statement);

    	// Make sure the statement was executed okay.
    	if($result===false) dieWithError($db->errorInfo());
}

/*
* Function to determin if a task is in the DB
* Used by mutiple functions to make sure a task 
* exists before modification of the table.
*
* @param $dbName        The DB the table is
* @param $taskID        The task's unique ID
*
* @return an asociative arry of info about the task or false if no task
*/
function taskExists($dbName, $taskID){
	$db = connectToDB($dbName);

	// Create the query that looks for the user with the given email.
    	$query = "select * from tasks where taskID=". $db->quote($taskID);

    	$result = $db->query($query);

    	// Verify things ran okay.
    	if(!$result) dieWithError($db->errorInfo());

    	// Count the number of rows returned: if no rows, then the user
    	// doesn't exist, so return false. Otherwise, return the first (and what
    	// should be the only) row.
    	$result = $result->fetchAll();
    	if(count($result) == 0)
        	return false;
    	else
        	return $result[0];

}

/*
* Function to remove a task from the tasks table.
* Used when a task is considered complete, or when 
* a task is past due and the preference is set to email.
*
* @param $dbName	The DB the table is in
* @param $taskID	The ID of the task being removed
*/
function removeTask($dbName, $taskID){
        //conect to DB
	$db = connectToDB($dbName);
        //check to see if the task exists
	if(!taskExists($dbName, $taskID)) dieWithError("Task does not exist");
        //if it exists
	//remove the task
	$statement = "DELETE from tasks where taskID=". $db->quote($taskID);
      
	$rowsEffected = $db->exec($statement);
	if($rowsEffected === false) dieWithError($db->errorInfo());
}

/*
* Function that takes a task and changes to the task.
* Makes nescessary changes to the task.
* 
* @param $dbName        The DB the table is in
* @param $taskID        The task to change
* @param $edits         Asociative array of changes to be made
*/
function editTask($dbName, $taskID, $edits){

     //conect to DB
	 $db = connectToDB($dbName);

    	// An anonymous function to quote database values. This will create
    	// pairs like: email = 'blah@gmail.com'.
    	$quote = function($key, $val) use ($db) {
            return "$key = ". $db->quote($val);
    	};

    	// Create the statement to update all the values to change.
    	$statement = "update tasks set ".
        	join(",", array_map($quote, array_keys($edits), $edits)) .
        	" where taskID=". $db->quote($taskID);

    	$result = $db->exec($statement);

    	// Verify things ran okay.
    	if($result===false) dieWithError($db->errorInfo()); 
}
/*
* Function that returns all the tasks associated with an email address.
* 
* @param dbName	The name of the DB
* @param email	The user email
* @return results the results of the query
*/
function getUsersTasks($dbName, $email){
	$db = connectToDB($dbName);

	$query = "select * from tasks where email=". $db->quote($email);
	$results = $db->query($query);

	if(!$results) dieWithError($db->errorInfo());
	
	$results = $results->fetchAll();
	
	return $results;
}

?>
