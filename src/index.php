<?php

// Set the header to respond TXT
header('Content-Type: text/plain');

// Secure
require("../secure/pwd.php");

// Base Classes (referenced by any of the below)
require("./cgi-bin/request/iRequestHandler.php");
require("./cgi-bin/database/Database.php");
require("./cgi-bin/auth/Key.php");

// Request Handlers
require("./cgi-bin/request/handlers/leaderboards/GetLeaderboards.php");
require("./cgi-bin/request/handlers/name/NameAuthoriser.php");
require("./cgi-bin/request/handlers/updates/GameAuthoriser.php");
require("./cgi-bin/request/handlers/updates/GameDownload.php");
require("./cgi-bin/request/handlers/score/ScoreAuthoriser.php");
require("./cgi-bin/request/handlers/score/ScoreSubmitter.php");
require("./cgi-bin/request/handlers/achiv/AchivAuthoriser.php");
require("./cgi-bin/request/handlers/achiv/AchivUnlocker.php");
require("./cgi-bin/request/handlers/achiv/GetAchievements.php");

// The Request Handler
require("./cgi-bin/request/RequestManager.php");

// Response Handler
require("./responses.php");

/**
 * Main function to run when index is reached
 * 
 * @access  public
 * @return  string The PBX Backend string in json format
 * @author  Alex Cummaudo
 * @date    2013-12-17
 */
function main()
{   
    // Try/Catch to catch any PBX errors
    try
    {
        // Setup Request Manager
        $requestManager = new RequestManager();
        
        // Ensure the request type was given
        if (!(isset($_GET["requestType"]))) 
        { throw new Exception("PBX401:Missing a request type"); }
        
        // Push the requestType
        $response = $requestManager->execute($_GET["requestType"], $_GET);
    }
    catch (Exception $e)
    {
        // Return the generated error response based on the exception
        return ResponseHandler::generate_response($e->getMessage());
    }
    
    // Given that this request was a push response ($response = true)
    // then give them a confirmation response
    if ($response === true)
    { return ResponseHandler::generate_response("PBX200"); }
    // Otherwise return whatever response was desired wrapped as
    // a message in a PBX200
    else
    { return ResponseHandler::generate_response("PBX200:$response"); }
}

// Decode every _GET
foreach ($_GET as $datum)
{
    urldecode($datum);
}

// If error has occured
/* error_reporting(0); */
$err = error_get_last();
if ($err != NULL) { echo ResponseHandler::generate_response("PBX100:".$err["message"]); }
else echo main();

?>