<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-type: application/json');

// Include the helper files.
// Constants, like BASE_URI.
require_once("constants.php");
// This gives us several functions, including dieWithError.
require_once("utils.php");
// For database operations. 
require_once("db.php"); 
// For emailing.
require_once("email.php");

// This will parse simple parameters from PUT data. It populates the
// $_PUT global.
if($_SERVER['REQUEST_METHOD'] == 'PUT') {
    parse_str(file_get_contents("php://input"), $_PUT);
}else if($_SERVER['REQUEST_METHOD'] == 'DELETE'){
    parse_str(file_get_contents("php://input"), $_DELETE);
}

// Start everything off.
session_start();
setupDatabase();
handleRequest();

/**
 * Creates the tables and database if not already created.
 */
function setupDatabase(){
    global $DB_NAME;

    if(!tableExists($DB_NAME, "users")) 
        createUsersTable($DB_NAME);
    if(!tableExists($DB_NAME, "tasks"))
	createTaskTable($DB_NAME);
}

/**
 * Handles all requests made to the API.
 */
function handleRequest(){
    global $BASE_URI;
    global $_PUT;
    global $_DELETE;

    $matches = array();
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    // Strip out the base URI from the request URI.
    $uri = str_replace($BASE_URI, "",  $_SERVER['REQUEST_URI']);

    // Based on the request method and uri, we'll know where to send the
    // request.
    if($requestMethod == "POST" && $uri == "api/signup"){
        $status = signup($_POST);
    // Add in more of these for your other API calls.
    // } else if(...) {
    } else if($requestMethod == "GET" && preg_match("/^api\/logIn\/([^\/]*)\/([^\/]*)$/",$uri, $matches)){
	$status = login($_GET, $matches);
    
    } else if($requestMethod == "POST" && $uri == "api/addTask"){
	$status = taskAdd($_POST);
    } else if($requestMethod == "DELETE" && $uri == "api/remove"){
	$status = removeATask($_DELETE); 
    } else if($requestMethod == "PUT" && $uri == "api/updateTask"){ 
	$status = editATask($_PUT);
    } else if($requestMethod == "GET" && $uri == "api/check"){
	$status = checkLogin($_GET);
    } else if($uri == "api/logout"){
	logout();
    } else {
        dieWithError("Unrecognized request: $requestMethod to  $uri.");
    }

    echo json_encode($status);
    exit;
}

/**
* When a user wants to log out then distroy the session so the app will know the 
* user is loged out when the page refreshes
*/
function logout(){
	session_destroy();
}

/**
* When the page reloads it calls this function to decide if a user is loged in.
* If the user is loged in then it gets all the taks for a user and displays them.
* It checks if a user is loged in based off of sessions.
*/
function checkLogin($data){
	global $DB_NAME;
	
	
		
	if(isset($_SESSION["loged_In"]) && $_SESSION["loged_In"]){
		 $results = getUsersTasks($DB_NAME, $_SESSION["email"]); 
		return array(
			"status" => "success",
			"info" => "logedIn",
			"results" => $results
		);
	}else{
		return array(
			"status" => "success",
			"info" => "notLogedIn"
		);
	}
}

/**
 * Attempts to signs a user up and sends an email to them with their 
 * authorization key. If the email already exists, or if another error is
 * encountered, an error is printed.
 *
 * @param data A map containing the email, name, and password of the user.
 */
function signup($data) {
    global $DB_NAME;

    // Make sure we have the email and name params.
    if(!isset($data["email"]) || !isset($data["name"]) || !isset($data["password"]) ) 
        dieWithError("Missing email, name, or password for signup.");
    if($data["email"]=="" || $data["name"]=="" || $data["password"]=="")
	dieWithError("Missing email, name, or password for signup");

    // Get the email and verify that it doesn't already exist.
    $email = $data["email"];
    $name  = $data["name"];
    $password = $data["password"];

    //each user must have a unique email address
    if(userExists($DB_NAME, $email)) dieWithError("Email already exists");
  
    // Email was unique. Add user to the database.
    addUserEntry($DB_NAME, $email, $name, $password);

    // Return the status.
    return array(
        "status"    => "success",
        "info"      => "User signed up",
        "user-data" => array(
            "email"   => $email,
            "name"    => $name,
            "password_hash" => $password
        )
    );
}

/**
* A function that attempts to log a user into the app. It makes sure
* that all the required paramiters are given and then authienticates a user.
* If the user is authinticated then get all the tasks that the user has and send them 
* to the HTML to be displayed.
*
* @param $data		a map of data sent from $_GET
* @param $matches	an array of data taken from the URI containg the user email
* @return $results	an array containg all the tasks that belong to a user
*/
function login($data, $matches){
	global $DB_NAME;
	
	if($matches[1]==""){
		dieWithError("Missing email");
	}	
	$email = $matches[1];
	
	// Make sure we have the email and name params.
    	if(!isset($data["password"]) )
        	dieWithError("Missing email or password for login");
    	if($data["password"]=="")
        	dieWithError("Missing email or  password for signup");

    	// Get the email and verify that it doesn't already exist.
    	$password = $data["password"];

    	//each user must have a unique email address
    	//if(!userExists($DB_NAME, $email)) dieWithError("No such account $email");
	
	if(!authUser($DB_NAME, $email, $password)) dieWithError("Incorrect password or email");
	$_SESSION["loged_In"] = true;
	$_SESSION["email"] = $email;
	
	$results = getUsersTasks($DB_NAME, $email);
		
	return array(
		"status" => "success",
		"info" => "User loged in",
		"email" => $email,
		"results" => $results
	);
	

}

/**
* A function that adds a new task to the task table in the DB. This function checks to make 
* sure all the required information is given. If it is all given then it adds the taks and returns
* all the taks the user has so the HTML can display an updated version of the tasks.
*
* @param $data		a map of data contaning the taskName, dueDate, and preference
* @return $results	an array of tasks that the user has
*/
function taskAdd($data) {
    global $DB_NAME;

    // Make sure we have the params.
    if(!isset($data["taskName"]) || !isset($data["dueDate"]) || !isset($data["preferenceDropDown"]) )
        dieWithError("Missing task information.");

    if($data["taskName"]=="" || $data["dueDate"]=="" || $data["preferenceDropDown"]=="")
        dieWithError("Missing task information");

    // Get the email and verify that it doesn't already exist.
    $taskName = $data["taskName"];
    $dueDate  = $data["dueDate"];
    $preference = $data["preferenceDropDown"];
    $email = $data["email"];
    
    addTask($DB_NAME, $email, $taskName, $dueDate, $preference);
    
    
    $results = getUsersTasks($DB_NAME, $email);
   

    // Return the status.
    return array(
        "status"    => "success",
        "info"      => "Task Added",
	"results" => $results
    );
}

/**
* A functon that removes a task from the tasks table in the DB. It first removes the
* task using a function from db.php and then it gets all of the user's tasks. 
* This way the tasks being displayed are an accurate reperesentation of the user's tasks.
* 
* @param data 	a map of data contaning the taskID and the email of a user 
*/
function removeATask($data) {
    global $DB_NAME;    
    
    removeTask($DB_NAME, $data["id"]);
    $results = getUsersTasks($DB_NAME, $data["email"]);

    // Return the status.
    return array(
        "status"    => "success",
        "info"      => "Task removed",
	"results" => $results
    );
}

/**
* A function that allows the user to edit an existing task. It first checks to make sure all
* the required paramiters were sent over. The data sent over is then added to an associative array 
* and sent to update the DB using a function from db.php. Then the tasks associated with a user are retreved
* and sent to the HTML to display the tasks related to a user.
*
* @param $data 	a map of data contaning the TaskName, dueDate, preference, and email
*/
function editATask($data){
	global $DB_NAME;
	// Make sure we have the params.
    	if(!isset($data["taskName"]) || !isset($data["dueDate"]) || !isset($data["preferenceDropDown"]) )
        	dieWithError("Missing task information.");

    	if($data["taskName"]=="" || $data["dueDate"]=="" || $data["preferenceDropDown"]=="")
        	dieWithError("Missing task information");
	
	$changes = array(
		"taskName" => $data["taskName"],
		"dueDate" => $data["dueDate"],
		"preference" => $data["preferenceDropDown"],
		"email" => $data["email"]
	);	
	
	
	editTask($DB_NAME, $data["id"], $changes);
	$results = getUsersTasks($DB_NAME, $data["email"]);
    	
	// Return the status.
    	return array(
        	"status"    => "success",
        	"info"      => "Task Edited",
        	"results" => $results
    	);


}

?>
