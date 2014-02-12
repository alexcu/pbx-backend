<?php
/**
 * @class   Achievement Unlocker class.
 *
 * Unlocks achievements by submitting the unlock to the server
 *
 * @implements iRequestHandler
 * @author  Alex Cummaudo
 * @date    2014-01-18
 */
class AchivUnlocker implements iRequestHandler
{
    /***************************************
     * Database Authorisation Details
     ***************************************/
    const DB_USERNAME_VIEWER = "paperbox_viewers";
    const DB_PASSWORD_VIEWER = false;
    
    const DB_USERNAME_INSERTER = "paperbox_gameins";
    const DB_PASSWORD_INSERTER = false;

    const DB_DATABASE = "paperbox_games";

    /***************************************
     * Achievements Database File Location
     * Must be relative to root
     ***************************************/
    const ACHIEVEMENTS_XML_LOC = "./resources/achievements.xml";
    
    /**
      * __construct function.
      * Load in achivements from XML into database if changed
      *
      * @access public
      * @return void
      */
    public function __construct()
    {
        $this->liveAchivList = $this->read_in_live_achievements();
        $this->dbAchivList   = $this->read_in_db_achievements();
        
        $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);

        // UPDATE THE DATABASE with live info!
        $liveGamesWithAchivs = array_keys($this->liveAchivList);
        foreach ($liveGamesWithAchivs as $liveGameWithAchiv => $gameName)
        {
            $achivsForThisGame = $this->liveAchivList[$gameName];
            
            // If this game does not exist in the DB list?
            if (! array_key_exists($gameName, $this->dbAchivList))
            {
                // Add the array for this game (empty)
                $this->dbAchivList[$gameName] = array();
            }    
            
            // For every achievement in this game
            for ($id = 0; $id < count($achivsForThisGame); $id++)
            {   
                // Split up the title and desc accordingly from raw desc
                $rawDesc = $achivsForThisGame[$id];
                
                // Replace any ' with '' so as to not confuse/muddle up SQL
                $rawDesc = str_replace("'", "''", $rawDesc);
                
                $titleDelimiterPos = strpos($rawDesc, "=");
                
                // Split up the = for title and desc
                $theTitle = substr($rawDesc, 0, $titleDelimiterPos);
                $theDesc  = substr($rawDesc, $titleDelimiterPos+1, strlen($rawDesc));
                
                // If this RAW achievement in this game does not exist in the DB list
                if (! in_array($rawDesc, $this->dbAchivList[$gameName]))
                {
                    
                    // If the id (index) does exist, it means we want to UPDATE the title for that achievement
                    if (array_key_exists($id, $this->dbAchivList[$gameName]))
                    {
                        $dbCtn->execute_query("CALL Achievement_UpdateAchievement('$theTitle', '$theDesc', $id, '$gameName');");
                    }
                    // Else it doesnt exist in the DB--we want to INSERT it
                    else
                    {
                        $dbCtn->execute_query("CALL Achievement_NewAchievement('$theTitle', '$theDesc', $id, '$gameName');");   
                    }
                }
            }
        }
        
        // Read in any change
        $this->dbAchivList   = $this->read_in_db_achievements();
    }

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
         return "unlockAchiv";
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
        $achivID    = trim($data['achivID'       ]);
        $dateTime   =      $data['dateTime'      ];
        $authKey    =      $data['achivAuthKey'  ];
          
        // $gameID
        // ~~~~~~~
        // Game Identifer Name == $gameID[0]
        // Game Version Major  == $gameID[1][0]
        // Game Version Minor  == $gameID[1][1]
        // Game Version Patch  == $gameID[1][2]
        // Game Version Dev    == $gameID[1][3]
        $gameID     = GameAuthoriser::get_game_details($data['gameID']);
        
        // Authorise key (with raw game id)
        // To generate key, override date time to true if tsk_auth checks out with correct datetime
        AchivAuthoriser::validate_achiv_with_key($authKey, $playerName, $achivID, $data["gameID"], AchivAuthoriser::tsk_auth($data) ? true : $dateTime);
        
        
        // Check that the player name exists first
        if (!NameAuthoriser::check_player_exists($playerName))
            throw new Exception("PBX702");
        
        // Now submit to database
        $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);
        
        $sql   = "INSERT INTO UnlockedAchievement
                    (playerID,
                     achievementID,
                     dateTime, 
                     gameId, 
                     gameVersionMajor,
                     gameVersionMinor,
                     gameVersionPatch,
                     gameVersionDev)
               VALUES
                    ( (SELECT Player_FindID('$playerName')),
                       $achivID,
                      '$dateTime',
                      '{$gameID[0]}',
                      '{$gameID[1][0]}',
                      '{$gameID[1][1]}',
                      '{$gameID[1][2]}',
                      '{$gameID[1][3]}');";
        
        // Unlock the achievement
        $dbCtn->execute_query($sql);
        
        // Return the achievement desc
        $dbCtn  = new Database(self::DB_USERNAME_VIEWER, self::DB_PASSWORD_VIEWER, self::DB_DATABASE);
        $achivDesc = $dbCtn->execute_query("CALL Achievement_LookupInfo($achivID, '{$gameID[0]}');", "array");
        return $achivDesc[0]["title"];
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
        if (! ( isset($data['gameID']) )) 
                throw new Exception("PBX401");
     }

    /***************************************
     * END Implement iRequestHandler Methods
     ***************************************/
 
     /**
      * Reads in the live achievements (XML doc).
      * 
      * @access private
      * @return void
      */
     private function read_in_live_achievements()
     {
        $theList = array();
        
        // Load in XML
        $achivXML = DOMDocument::load(self::ACHIEVEMENTS_XML_LOC);

        // $responseDatabase returns false where not loaded properly
        if ($achivXML === false)
            throw new Exception("PBX503:Achievements missing");
            
        // Get each game
        foreach ($achivXML->getElementsByTagName("game") as $game)
        {
            $gameIDName = $game->getAttribute("id");
            
            // For each achivement in this game
            foreach ($game->getElementsByTagName("achievement") as $achievement)
            {
                // Set the ID
                $id = (int)$achievement->getAttribute("id");
                
                // Get the title
                $title = $achievement->getAttribute("title");

                // Set the description
                $theList[$gameIDName][$id] = $title."=".$achievement->textContent;
            }
        }
        
        return $theList;
     }
     
     /**
      * Reads in the stored achievements (DB).
      * 
      * @access private
      * @return void
      */
     private function read_in_db_achievements()
     {
         $theList = array();
         
         // Load in the db
         $dbCtn = new Database(self::DB_USERNAME_VIEWER, self::DB_PASSWORD_VIEWER, self::DB_DATABASE);
         $dbResults = $dbCtn->execute_query("SELECT * FROM Achievement;", "array");
         
         // Get each game and its title=description
         foreach ($dbResults as $achievement)
            $theList[$achievement["gameID"]][(int)$achievement["id"]] = $achievement["title"]."=".$achievement["description"];
            
        return $theList;   
     }
     
     /**
      * Checks for prexesiting unlock with the given game id, achiv id, and player name
      *
      * @access public
      * @return boolean
      * @static
      */
     public static function check_if_unlocked($achivID, $gameID, $playerName)
     {
         $dbCtn = new Database(self::DB_USERNAME_VIEWER, self::DB_PASSWORD_VIEWER, self::DB_DATABASE);
         $results = $dbCtn->execute_query("CALL UnlockedAchievements_LookupPlayer_ExistingUnlockCount($achivID, '$gameID', (SELECT Player_FindID('$playerName')));", "array");   
         return $results[0]["count"] > 0;
     }
     
     
     private $liveAchivList;
     private $dbAchivList;
}

?>