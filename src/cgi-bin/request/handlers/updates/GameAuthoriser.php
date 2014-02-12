<?php
/**
 * @class   GameAuthoriser
 *
 * Handles authorised game IDs (i.e. verifys if
 * the game is a PBX authorised game)
 *
 * @implements iRequestHandler
 * @author  Alex Cummaudo
 * @date    2014-01-09
 */
class GameAuthoriser implements iRequestHandler
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
     * Game versions file loc
     ***************************************/
    const GAME_VERSIONS_LOC = "ftp.games.paperboxstudios.net";
    const GAME_VERSIONS_USR = "games@paperboxstudios.net";
    const GAME_VERSIONS_PWD = false;
    
    
     /**
      * __construct function.
      * Load in $gameVersions
      *
      * @access public
      * @return void
      */
     public function __construct()
     {
        self::$liveGameList = $this->read_in_live_games(self::GAME_VERSIONS_LOC, self::GAME_VERSIONS_USR, self::GAME_VERSIONS_PWD);
        self::$dbGameList   = $this->read_in_database_games();
        
        $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);
        
        // Ensure everything in the liveGameList matches 
        // the dbGameList... if not insert into the db
        foreach (self::$liveGameList as $liveGame)
        {
            // This live game is *not* in the Database?
            if (! in_array($liveGame, self::$dbGameList))
            {
                // See if it has a name in the DB
                $liveGameID = substr($liveGame, 0, 4);
                $liveGameVersions = explode(".", substr($liveGame, 5));
                
                $liveGameTitle = $dbCtn->execute_query("SELECT Game_GetTitle('$liveGameID') AS \"title\";", "array");
                $liveGameTitle = $liveGameTitle[0]["title"];
                
                // This game doesn't have a name to match against! Email us immediately!
                if ($liveGameTitle == NULL)
                {
                    mail("Paperbox Studios <paperboxstudios@gmail.com>", "PBX Backend Alert",
                         "==============================================================================\n".
                         " MESSAGE:                                                                     \n".
                         " The game with the id $liveGameID was located at ".self::GAME_VERSIONS_LOC."  \n".
                         " but does not have a title to match against in the PBX Backend DB. Verify the \n".
                         " entry for this game inserted into the Database (paperbox_games.Game)         \n".
                         "==============================================================================\n".
                         "                                                                              \n".
                         "Paperbox Backend System (Version Dog)                                         \n".
                         "Alert Generated ".date("Y-m-d H:i:s"),
                         "Content-Type: text/plain"                                                 ."\r\n".
                         "From: Paperbox Backend <paperboxstudios@gmail.com>"                       ."\r\n".
                         "Reply-To: Paperbox Studios <paperboxstudios@gmail.com>"                   ."\r\n");
                }
                
                // Insert it into the database
                $sql = "CALL Game_NewVersion('$liveGameID', $liveGameVersions[0], $liveGameVersions[1], $liveGameVersions[2], $liveGameVersions[3]);";
                $dbCtn->execute_query($sql);
                
                // Update the game list to reflect the changes
                self::$dbGameList   = $this->read_in_database_games();
            }
        }
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
         return "authGame";
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
        
         $theGameID    = substr($data["gameID"], 0, 4);
         $theGameIDVer = $data["gameID"];
         
         // If we just want the ftp url
         $returnFTPURL = false;
         $returnTitle  = false;
         if (isset($_GET["getDownloadURL"])) { $returnFTPURL = true; } 
         if (isset($_GET["getTitle"]))       { $returnTitle = true;  }
         if ($returnFTPURL || $returnTitle)  { $theGameIDVer = $theGameID."-*.*.*.*"; }

         // Firstly, check if the game is an authorised game         
         if (!self::verify_game($theGameIDVer)) { throw new Exception("PBX801"); }
         
         // Secondly, check if there is an update for this game
         $latestVersion = $this->find_latest_version($theGameID);
         
         // Getting the FTP URL
         if ($returnFTPURL) { return self::GAME_VERSIONS_LOC."/$latestVersion.exe"; }
         // Getting the title
         if ($returnTitle)  { return $this->get_title($latestVersion); }
         // Otherwise check if update avaliabel
         if ($theGameIDVer != $this->find_latest_version($theGameID)) { return "Update available: latest version is v".substr($latestVersion, 5); }
         // Otherwise all good!
         else { return true; }
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
      * Returns the latest version of the game with the given id
      * in the database list
      * 
      * @access public
      * @static
      * @param mixed $gameIDWithVersions
      * @return the latest version id
      */
     public static function verify_game($gameIDWithVersions)
     {   
         // For every DB game
         foreach (self::$dbGameList as $aGame)
         {   
            // If *.*.*.* versions and matching XXXX-
            $wildcardCheck = substr($gameIDWithVersions, 5, strlen($gameIDWithVersions)) == "*.*.*.*";
            $idCheck       = substr($gameIDWithVersions, 0, 4                          ) == substr($aGame, 0, 4);
            
            // Does this game match the Game ID provided? If so, return true.
            if ($aGame == $gameIDWithVersions) { return true; }
            else if ($wildcardCheck && $idCheck) { return true; }
         }
         
         // Out of loop? Return false
         return false;
     }
     
     /**
      * Returns the latest version of the game with the given id
      * in the database list
      * 
      * @access private
      * @param mixed $gameID
      * @return the latest version id
      */
     private function find_latest_version($gameID)
     {
         $result = NULL; // defaults to NULL if game id is not found
         
         // For every DB game
         foreach (self::$dbGameList as $aGame)
         {   
            // If this game matches the Game ID provided?
            if (substr($aGame, 0, 4) == $gameID)
            {
                // If this game has a greater value than the current result?
                // Then the new result is this game
                if ($this->game_value($aGame) >= $this->game_value($result)) { $result = $aGame; }
            }
         }
         
         return $result;
     }
     
     /**
      * Returns the 'value' of a game (for comparing which
      * game is more 'up-to-date'
      * @access private
      * @param mixed $gameIDWithVersions
      * @return int the 'worth' of this game
      */
     private function game_value($gameIDWithVersions)
     {
        // if NULL is provided, return 0
        if ($gameIDWithVersions == NULL) { return 0; }
        
        $versions = explode(".", substr($gameIDWithVersions, 5));
        
        for ($i = 3; $i >= 0; $i--)
        {
            // Increase a result exponentially high by its index position
            $res += ($versions[3-$i] * pow(10,$i*$i+($i*3)));
        }
        return $res;
     }
     
     /**
      * Reads in an array of the directory where the games are stored.
      * 
      * @access private
      * @param mixed $theDirectory we read from
      * @return void
      */
     private function read_in_live_games($theDirectory, $usr = NULL, $pwd = NULL)
     {
        $ftpCtn = ftp_connect($theDirectory);
        $theList = array();
        $pwd = $pwd === false ? Key::generate_pwd(pwd($usr)) : $pwd;
        
        if (ftp_login($ftpCtn, $usr, $pwd))
        {
            // Scan through the directory to remove non-gameID directories (such as ., .. etc.)
            $contents = ftp_nlist($ftpCtn, "/");
            foreach ($contents as $i)
            {
                // Doesn't match XXXX-0.0.0.0.exe
                if (preg_match("/^[A-Z]{4}-\d+.\d+.\d+.\d+.exe$/", $i)) 
                { array_push($theList, substr($i, 0, -4)); } // remove the .exe
            }
        }
        else { throw new Exception("PBX503:Cannot open the game versions directory"); }
        
        ftp_close($ftpCtn);

        return $theList;
     }
     
     /**
      * Reads in an array of the database held games.
      * 
      * @access private
      * @param mixed $theDirectory
      * @return void
      */
     private function read_in_database_games()
     {
        $theList = array();
        
        $dbCtn = new Database(self::DB_USERNAME_VIEWER, self::DB_PASSWORD_VIEWER, self::DB_DATABASE);
        $theGames = $dbCtn->execute_query("CALL Game_ListAll_ID();", "array");

        foreach ($theGames as $i)
        {
            array_push($theList, $i["gameID"]);
        }
        return $theList;
     }
     
     /**
      * Returns the name of a game
      *
      * @access private
      * @param theGame
      * @return void
      */
     private function get_title($gameID)
     {
        $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);

        $liveGameID = substr($gameID, 0, 4);
        $liveGameTitle = $dbCtn->execute_query("SELECT Game_GetTitle('$liveGameID') AS \"title\";", "array");
        return $liveGameTitle[0]["title"];
     }
     
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
     
     private static $liveGameList;
     private static   $dbGameList;
     
}