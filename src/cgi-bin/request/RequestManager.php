<?php
/**
 * @class   Request Manager
 *
 * Mediates all requests to the ./scripts protected folder; allows
 * access to that folder given reqID is good
 *
 * @author  Alex Cummaudo
 * @date    2013-11-30
 */
class RequestManager
{
    /**
     * Sets up all future request communication with the server.
     * If a reqID is inputted, it means that the player is not
     * initiating a connection, and therefore it is already using
     * a session ID
     * 
     * @access  public
     * @param   @deprecated mixed $reqID (default: NULL)
     * @return  void
     */
    function __construct()
    {   
        /******************** DEPRECATED ********************
            // Generator Sequence is the range the randomly generated
        // $reqID will be, with the range as X0000 to (X+1)0000
        $genSeq = 2;
        // Create a new request idenfication number that will
        // be used for all future communication with the request
        // manager if the $reqID is null
        if ($reqID === NULL)
            // Generator Sequence automatically hashed using sha256
            $this->reqID = hash("sha256", rand($genSeq*10000,$genSeq*20000));
        else
            // The $reqID is the inputted one
            $this->reqID = $reqID;
        ******************** DEPRECATED ********************/
        
        // Setup $reqHandlers;
        $this->requestHandlers = array();
        $this->add_request_handlers();
    }
    
    
    /**
     * Initiates all request handlers.
     * You will need to add any new handlers here
     * 
     * @access private
     * @return void
     */
    private function add_request_handlers()
    {
        array_push($this->requestHandlers, new GameAuthoriser());
        array_push($this->requestHandlers, new GameDownloadRecord());
        array_push($this->requestHandlers, new NameAuthoriser());
        array_push($this->requestHandlers, new ScoreAuthoriser());
        array_push($this->requestHandlers, new ScoreSubmitter());
        array_push($this->requestHandlers, new GetLeaderboards());
        array_push($this->requestHandlers, new GetAchievements());


        array_push($this->requestHandlers, new AchivAuthoriser());
        array_push($this->requestHandlers, new AchivUnlocker());

    }
    
    /**
     * Executes the request initiated in the constructor.
     * 
     * @access public
     * @param $requestType the type of request to be executed
     * @param $data the query string data that was passed to the server
     * @return void
     */
    public function execute($requestType, $data)
    {
        // Initialise response array
        $response = array();
        
        // Return the response from the request handler that matches
        foreach ($this->requestHandlers as $handler)
            if ($handler->get_id() == $requestType)
                return $handler->execute($data);
                
        // Outside? We haven't found a request handler of this type!
        throw new Exception("PBX401:A request type with this name does not exist");
    }
  
    private static $requestHandlers;
    
    /******************** DEPRECATED ********************
    private $reqID;
     ******************** DEPRECATED ********************/
}

?>