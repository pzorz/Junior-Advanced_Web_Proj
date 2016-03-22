<?php

/**                                                                                
 * Prints out an error message in JSON format. It has the fields:                  
 *  status: "error"                                                                
 *  info:  the given message                                                       
 *                                                                                 
 * @param message The error message to print.                                      
 */                                                                                
function dieWithError($message){                                                   
    echo json_encode(array(                                                        
        "status" => "error",                                                       
        "info" => $message                                                         
    ));                                                                            
    exit;                                                                          
}

/**                                                                                
 * Generate a random string. Taken from http://stackoverflow.com/a/13212994.       
 *                                                                                 
 * @param length The length of the string (default: 10).                           
 * @return A random alphanumeric string.                                           
 */                                                                                
function generateRandomString($length = 10) {                                      
    return substr(str_shuffle(                                                     
        "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0,   
         $length);                                                                 
} 

?>
