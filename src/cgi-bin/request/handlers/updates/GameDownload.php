<?php
/**
 * @class   GameDownloadRecord
 *
 * Writes a record to game download database
 *
 * @implements iRequestHandler
 * @author  Alex Cummaudo
 * @date    2014-02-04
 */
class GameDownloadRecord implements iRequestHandler
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
         return "recordGameDownload";
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
        
         $gameIDWithVersions = $data['gameID'];
         $downloadKey        = $data['downloadKey'];
         $platform           = $data['platform'];
         
         $k1 = Key::new_key_from_data("GAME_DOWNLOAD_RECORD", true, array("gameID" => "$gameIDWithVersions"));
         $k2 = Key::new_key_from_source($downloadKey);
         
         // If invalid download key? (we want to make sure people cannot spam this request!)
         if (!
                Key::validate_keys
                (
                    Key::new_key_from_data("GAME_DOWNLOAD_RECORD", true, array("gameID" => "$gameIDWithVersions")),
                    Key::new_key_from_source($downloadKey)
                )
            )
        { throw new Exception("PBX602:Invalid download key-".$k1->get_key()." != ".$k2->get_key()); }
        
        // Check if the game is an authorised game         
        if (!GameAuthoriser::verify_game($gameIDWithVersions)) { throw new Exception("PBX801"); }
                 
        // $gameID
        // ~~~~~~~
        // Game Identifer Name == $gameID[0]
        // Game Version Major  == $gameID[1][0]
        // Game Version Minor  == $gameID[1][1]
        // Game Version Patch  == $gameID[1][2]
        // Game Version Dev    == $gameID[1][3]
        $gameID     = GameAuthoriser::get_game_details($data['gameID']);
        
        // Now submit to database
        $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);
        
        $sql   = "INSERT INTO Download
                    (gameId, 
                     gameVersionMajor,
                     gameVersionMinor,
                     gameVersionPatch,
                     gameVersionDev,
                     platform)
               VALUES
                    ( '{$gameID[0]}',
                      '{$gameID[1][0]}',
                      '{$gameID[1][1]}',
                      '{$gameID[1][2]}',
                      '{$gameID[1][3]}',
                      UPPER('$platform'));";
        
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
        if (! ( isset($data['gameID'])      && 
                isset($data['downloadKey']) &&
                isset($data['platform'])      )) 
                throw new Exception("PBX401");
     }
    /***************************************
     * END Implement iRequestHandler Methods
     ***************************************/
     
    /**
     * get_game_details function.
     * Generates game details from the $gameInfo passed
     * 
     * @access private
     * @param mixed $gameInfo
     * @return array (see below)
     */
    public static function get_game_details($gameID)
    {
        // Generate gameID contents
        $gameDetails      = explode("-", $gameID);
        $gameID           = $gameDetails[0];
        $gameVersionFull  = explode(".", $gameDetails[1]);
        $gameVersionMajor = $gameVersionFull[0];
        $gameVersionMinor = $gameVersionFull[1];
        $gameVersionPatch = $gameVersionFull[2];
        $gameVersionDev   = $gameVersionFull[3];
        
        return array(   
                        $gameID,            // return[0] = TEST
                        $gameVersionFull    // return[0] = major, [1] = minor, [2] = patch, [3] = dev 
                    );
    }
     
     private $liveGameList;
     private   $dbGameList;
     
}