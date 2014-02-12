<?php
/**
 * @class   Achievement Authoriser class.
 *
 * Generates official PBX key for achievements -- consider storing
 * under a achievements.txt if player is offline and submitting
 * once they're online and/or pass a achievement auth key to the 
 * AchivSubmitter to ensure official PBX score will be submitted 
 * to PBX database
 *
 * @implements iRequestHandler
 * @author  Alex Cummaudo
 * @date    2014-01-18
 */
class AchivAuthoriser implements iRequestHandler
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
         return "authAchiv";
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
        
        $playerName = trim($data['playerName']);
        $dateTime   = $data['dateTime'  ];
        $achivID    = $data['achivID'   ];
        $gameID     = $data['gameID'    ];
        
        // Check if player has already unlocked this entry before key stuff...
        if (AchivUnlocker::check_if_unlocked($achivID, substr($gameID, 0, 4), $playerName)) { return "false"; }

        // To generate key, override date time to true if tsk_auth checks out
        $dateTime   = self::tsk_auth($data) ? true : $dateTime;
        $key = $this->generate_authorisation_key($playerName, $achivID, $gameID, $dateTime);
        
        // If achivAuthKey is not set, it means we want to send back
        // a generated new key
        if (! isset($data['achivAuthKey'])) { return $key->get_key(); }
        // Otherwise, we want to compare the scoreAuthKey against it
        else 
        { return self::validate_achiv_with_key($data['achivAuthKey'], $playerName, $achivID, $gameID, $dateTime) ? true : false; }
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
                isset($data['gameID'    ]) &&
                isset($data['achivID'   ])    )) 
                throw new Exception("PBX401");
     }
    /***************************************
     * END Implement iRequestHandler Methods
     ***************************************/
     
    /**
     * generate_authorisation_key function.
     * Generates an authorisation key for the authorised achivement
     * 
     * @access private
     * @param mixed $playerName
     * @param mixed $achivID
     * @param mixed $gameID
     * @param mixed $dateTime
     * @return void
     */
    private function generate_authorisation_key($playerName, $achivID, $gameID, $dateTime)
    {
        // Generate new key
        $keyData    = array("playerName" => $playerName, "achivID" => $achivID, "gameID" => $gameID);

        return Key::new_key_from_data("ACHIEVEMENT_AUTHORISATION_KEY", $dateTime , $keyData);
    }
             
    /**
     * validate_achiv_with_key function.
     * Validates a given key with the data provided
     *
     * @access public
     * @param mixed $key
     * @param mixed $playerName
     * @param mixed $achivID
     * @param mixed $gameID
     * @param mixed $dateTime
     * @return void
     */
    public static function validate_achiv_with_key($key, $playerName, $achivID, $gameID, $dateTime)
    {
        if  ( 
                Key::validate_keys
                (
                    Key::new_key_from_source($key),
                    self::generate_authorisation_key($playerName, $achivID, $gameID, $dateTime)
                )
            )
        return true;
        else throw new Exception("PBX602:Achievement that was submitted was not authorised by PBX"); 
    }
    
    /**
     * verifys tsk auth key and returns true or false if verified
     * @param data Primary execution data
     * @return true/false if authorised or not
     */
    public static function tsk_auth($data)
    {
        // Temporary Storage Key Auth Key
        if (isset($data['tskAuthKey']))
        {
            $validTskAuthKey = sha1(base64_encode("TSK_AUTH_KEY:dateTime={$data['dateTime']}&:{$data['playerName']}"));
            // Authorise TSK
            if ($validTskAuthKey === $data['tskAuthKey'])
            {
                // If authorised, override dateTime to true
                return true;
            }
        }
        return false;
    }
}
?>