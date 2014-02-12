<?php
/**
 * @class   NameAuthoriser
 *
 * Authorises a name against `naughty' words and ensures
 * that names are within correct boundaries of size, chars etc.
 *
 * @implements iRequestHandler
 * @author  Alex Cummaudo
 * @date    2014-01-04
 */
class NameAuthoriser implements iRequestHandler
{
    /***************************************
     * Database Authorisation Details
     ***************************************/
    const DB_USERNAME_INSERTER = "paperbox_gameins";
    const DB_PASSWORD_INSERTER = false;
        
    const DB_USERNAME_VIEWER = "paperbox_viewers";
    const DB_PASSWORD_VIEWER = false;
        
    const DB_DATABASE = "paperbox_games";

    /***************************************
     * Naughty Words File Location
     * Must be relative to root
     ***************************************/
    const NAUGHTY_WORDS_LOC = "./resources/badwords.txt";    

     /**
      * __construct function.
      * Load in $naughtyWords
      *
      * @access public
      * @return void
      */
     public function __construct()
     {        
        // Allow construction only where the naughty words exist
        if (!(file_exists(self::NAUGHTY_WORDS_LOC))) throw new Exception("PBX503:Naughty Words File");
        
        $this->naughtyWords = file(self::NAUGHTY_WORDS_LOC);
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
         return "authName";
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
        $override   = false;
        $updateName = false;
        // Set override params
        if (isset($data['o'])) { if ($data['o'] == "300594") { $override = true; } }
        // Set update params
        if (isset($data["update"])) { $updateName = trim($data["update"]); }
        
        if ($override != false && $updateName != false) { throw new Exception("PBX401:Cannot update and override a name at the same time"); }
    
        // Test for naughty word matches
        foreach ($this->naughtyWords as $word)
        {
            // If a name entered matches the playerName given
            if (preg_match("/^.*$word*.*$/i", $playerName) || 
                preg_match("/^.*$word*.*$/i", $updateName)   )
            { throw new Exception("PBX703:A match in the name was found to be unacceptable"); }
        }

        // If a name is blank entirely OR it's too large
        if (strlen($playerName) < 3 || strlen($playerName) > 30) 
        { throw new Exception("PBX703:Name not long enough or too long"); }

        // Updating existing name?
        if ($updateName != false)
        {
            // If a name is blank entirely OR it's too large
            if (strlen($updateName) < 3 || strlen($updateName) > 30) 
            { throw new Exception("PBX703:Name not long enough or too long"); }
            return $this->update_player_name($playerName, $updateName);
        }
        
        // Inserting new name?
        else
        {
            // No override key set?
            if ($override == false)
            {
                if ($this->check_player_exists($playerName)) { throw new Exception("PBX701"); }

                // Create a new player with that name in DB
                $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);
                $sql   = "INSERT INTO Player (name) VALUES ('$playerName');";
                return $dbCtn->execute_query($sql);
            }
            // Overriding?
            else
            {
                if ($this->check_player_exists($playerName))
                {
                    // Return true since we're overriding
                    return true;
                }
                // Cannot override a non-existant player
                else { throw new Exception("PBX702:You cannot override a player that doesn't exist"); }   
            }
        }
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
        if (! ( isset($data['playerName']) )) 
                throw new Exception("PBX401");
     }
    /***************************************
     * END Implement iRequestHandler Methods
     ***************************************/
     
     
     /**
      * Updates a player's name in the database to something new without
      * losing all their records.
      * 
      * @access private
      * @param mixed $existingPlayerName is the existing name
      * @param mixed $newPlayerName is the new name to replace
      * @return void
      */
     private function update_player_name($existingPlayerName, $newPlayerName)
     {
         if ($existingPlayerName == $newPlayerName) { throw new Exception("PBX703:Cannot update same name"); }
         
         // First, check if existing player name exists already (can't update
         // something that doesn't exist!)
         if (!$this->check_player_exists($existingPlayerName)) { throw new Exception("PBX702:Cannot update a player that doesn't exist"); }
         if ( $this->check_player_exists($newPlayerName)     ) { throw new Exception("PBX701:Cannot update a name that is currently used by another player"); }
         
         // Okay, so the player with that name exists. Now we can update it
         $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);
         $sql = "UPDATE Player SET name = '$newPlayerName' WHERE name = '$existingPlayerName';";
         
         return $dbCtn->execute_query($sql);
     }
     
     
     /**
      * Checks if a playerName exists in the database.
      * 
      * @access private
      * @param mixed $playerName to check if it exists
      * @return true or false depending on existance
      */
     public function check_player_exists($playerName)
     {
         // Lookup the name (given no override token) in the Database
        $dbCtn = Database::new_sec_ctn(self::DB_USERNAME_INSERTER, self::DB_PASSWORD_INSERTER, self::DB_DATABASE);
         
         $sql = "SELECT COUNT(name) AS \"count\" FROM Player WHERE name = '$playerName';";
         $playerExistsAlready = (array)$dbCtn->execute_query($sql, "array");
         // Player exists if count > 0 (i.e. is 1)
         return $playerExistsAlready[0]["count"] > 0;
     }
     
     
     private $naughtyWords;
}