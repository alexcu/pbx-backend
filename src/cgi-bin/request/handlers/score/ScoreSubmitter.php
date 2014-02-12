<?php
/**
 * @class   ScoreSubmitter
 *
 * Allows games to submit high scores to the PBX database.
 * 
 * @implements iRequestHandler
 * @author  Alex Cummaudo
 * @date    2014-02-09
 */
class ScoreSubmitter implements iRequestHandler
{
    /***************************************
     * Database Authorisation Details
     ***************************************/
    const DB_USERNAME_INSERTER = "paperbox_gameins";
    const DB_PASSWORD_INSERTER = false;
    const DB_DATABASE = "paperbox_games";
    
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
         return "pushScore";
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
        
        $playerName = trim($data['playerName'    ]);
        $score      = trim($data['score'         ]);
        $dateTime   =      $data['dateTime'      ];
        $authKey    =      $data['scoreAuthKey'  ];
        
        // If we've got a level
        $level      = isset($data['level']) ? $data['level'] : false;
        
        // Validate this key (an exception is thrown otherwise)
        ScoreAuthoriser::validate_score_with_key($authKey, $playerName, $score, $dateTime);
          
        // $gameID
        // ~~~~~~~
        // Game Identifer Name == $gameID[0]
        // Game Version Major  == $gameID[1][0]
        // Game Version Minor  == $gameID[1][1]
        // Game Version Patch  == $gameID[1][2]
        // Game Version Dev    == $gameID[1][3]
        $gameID     = GameAuthoriser::get_game_details($data['gameID']);
        
        // $country
        // ~~~~~~~~
        $country    = $this->find_player_country(trim($data['playerIP']));
        
        // Check that the player name exists first
        if (!NameAuthoriser::check_player_exists($playerName))
            throw new Exception("PBX702");
        
        // Now submit to database
        $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);



        $sql   = "INSERT INTO HighScore
                    (playerID,
                     score,
                     level,
                     dateTime, 
                     gameId, 
                     gameVersionMajor,
                     gameVersionMinor,
                     gameVersionPatch,
                     gameVersionDev,
                     countryCode)
               VALUES
                    ( (SELECT Player_FindID('$playerName')),
                       $score,";
        $sql  .=       $level === false ? "NULL," : "'$level',".
                     "'$dateTime',
                      '{$gameID[0]}',
                      '{$gameID[1][0]}',
                      '{$gameID[1][1]}',
                      '{$gameID[1][2]}',
                      '{$gameID[1][3]}',
                      '$country');";
        // Return the state of the executed query we pushed
        // (should be true if there were no errors)
        return $dbCtn->execute_query($sql);
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
        if (! ( isset($data['playerName'    ]) &&
                isset($data['scoreAuthKey'  ]) &&
                isset($data['score'         ]) &&
                isset($data['dateTime'      ]) &&
                isset($data['playerIP'      ]) &&
                isset($data['gameID'        ])    )) 
                throw new Exception("PBX401");
     }
    /***************************************
     * END Implement iRequestHandler Methods
     ***************************************/
    
    
    /**
     * Finds the player's country based on their IP Address.
     * 
     * @access private
     * @param mixed $playerIP
     * @return void
     */
    private function find_player_country($playerIP)
    {
        // Get Country Code using cURL
        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_URL, "http://ipinfo.io/$playerIP/country");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $countryCode = curl_exec($curl);
        curl_close($curl);
        
        return $countryCode;
    }
}
?>