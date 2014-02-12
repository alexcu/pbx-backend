<?php
/**
 * @class   ScoreAuthoriser
 *
 * Generates official PBX key to be saved under the game's score.txt and/or
 * passing a key to the ScoreSubmitter will ensure an official PBX score so
 * that it can authorised to be submitted to PBX database
 *
 * @implements iRequestHandler
 *
 * @author  Alex Cummaudo
 * @date    2013-12-12
 */
class ScoreAuthoriser implements iRequestHandler
{
    /***************************************
     * Implement iRequestHandler Methods
     ***************************************/
     /**
      * get_id function.
      * Returns the id of this handler
      *
      * @access public
      * @return void
      */
     public function get_id()
     {
         return "authScore";
     }
     
     /**
      * execute function.
      * Executes this EventHandler
      *
      * @access public
      * @param array $data
      * @return void
      */
     public function execute(array $data)
     {
        $this->validate_data($data);
        
        $playerName = $data['playerName'];
        $dateTime   = $data['dateTime'  ];
        $score      = $data['score'     ];
        
        $key = $this->generate_authorisation_key($playerName, $score, $dateTime);
        
        // If scoreAuthKey is not set, it means we want to send back
        // a generated new key
        if (! isset($data['scoreAuthKey'])) { return $key->get_key(); }
        // Otherwise, we want to compare the scoreAuthKey against it
        else 
        { return self::validate_score_with_key($data['scoreAuthKey'], $playerName, $score, $dateTime) ? true : false; }
     }
     
     /**
      * validate_data function.
      * Validates data given under $data when execute is called
      *
      * @access private
      * @param array $data
      * @return void
      */
     public function validate_data(array $data)
     {
        // If the data wasn't set, throw PBX401 Inappropriate Data Supplied
        if (! ( isset($data['playerName']) &&
                isset($data['dateTime'  ]) &&
                isset($data['score'     ])    )) 
                throw new Exception("PBX401");
     }
    /***************************************
     * END Implement iRequestHandler Methods
     ***************************************/
     
    /**
     * generate_authorisation_key function.
     * Generates an authorisation key for the authorised score
     *
     * @access public
     * @param mixed $playerName
     * @param mixed $score
     * @param mixed $dateTime
     * @return void
     */
    private function generate_authorisation_key($playerName, $score, $dateTime)
    {
        // Generate new key
        $keyData    = array("playerName" => $playerName, "score" => $score);

        return Key::new_key_from_data("SCORE_AUTHORISATION_KEY", "$dateTime", $keyData);
    }
        
    /**
     * validate_score_with_key function.
     * Validates a given key with the data provided
     * @deprecated
     * @access public
     * @param mixed $key
     * @param mixed $playerName
     * @param mixed $score
     * @param mixed $dateTime
     * @return void
     */
    public function validate_score_with_key($key, $playerName, $score, $dateTime)
    {
        if  ( 
                Key::validate_keys
                (
                    Key::new_key_from_source($key),
                    self::generate_authorisation_key($playerName, $score, $dateTime)
                )
            )
        return true;
        else throw new Exception("PBX602:Score that was submitted was not authorised by PBX"); 
    }
}
?>