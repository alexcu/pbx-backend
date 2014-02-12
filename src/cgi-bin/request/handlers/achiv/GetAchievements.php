<?php
/**
 * @class   Achievements retrieval class
 *
 * Retrieves achievements in the specified format and scope
 *
 * @implements iRequestHandler
 * @author  Alex Cummaudo
 * @date    2014-01-18
 */
class GetAchievements implements iRequestHandler
{
    /***************************************
     * Database Authorisation Details
     ***************************************/
    const DB_USERNAME = "paperbox_viewers";
    const DB_PASSWORD = false;
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
         return "getAchievements";
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
        $format      = $data['format']; // { json, array, xml, raw }
        $gameID      = $data['gameID']; // first 4 chars only
        $playerName  = $data['playerName'];
        $scope       = $data['scope']; // { unlocked, locked, stats (json only) }
        
        // Setup Database Connection
        $dbCtn  = new Database(self::DB_USERNAME, self::DB_PASSWORD, self::DB_DATABASE);
        
        switch ($scope)
        {
            case ("unlocked"): $dbLookupProc = "UnlockedAchievements_LookupPlayer_Unlocked"; break;
            case ("locked"  ): $dbLookupProc = "UnlockedAchievements_LookupPlayer_Locked"; break;
            case ("stats"   ):
            {
                // Get total unlocked achivs
                $unlockedAchivs = $dbCtn->execute_query("CALL UnlockedAchievements_LookupPlayer_Unlocked_Count('$gameID', '$playerName');", "array");
                $unlockedAchivs = (int)$unlockedAchivs[0]["unlockedAchievementCount"];
                
                unset($dbCtn);
                // Setup Database Connection (free result... bad I know...)
                $dbCtn  = new Database(self::DB_USERNAME, self::DB_PASSWORD, self::DB_DATABASE);
                
                // Get total achivs
                $totalAchivs    = $dbCtn->execute_query("CALL Achievement_LookupInfo(NULL, '$gameID');", "array");
                $totalAchivs    = (int)$totalAchivs[0]["totalAchievementCount"];
                
                // Work out and return stats for this player
                $result["unlocked"] = $unlockedAchivs;
                $result["locked"]   = $totalAchivs - $unlockedAchivs;
                $result["total"]    = $totalAchivs;
                $result["percentUnlocked"] = number_format((float)$unlockedAchivs / $totalAchivs, 2, '.', '');
                $result["percentLocked"]   = number_format((float)$result["locked"] / $totalAchivs, 2, '.', '');
                
                return json_encode($result);
            }
            default: throw new Exception("PBX401:Invalid scope"); break;
        }
        
        $sql = "CALL $dbLookupProc('$gameID', '$playerName');";
        return $dbCtn->execute_query($sql, $format);

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
                isset($data['format'    ]) &&
                isset($data['gameID'    ]) &&
                isset($data['scope'     ])    )) 
        { throw new Exception("PBX401"); }
     }
     
    /***************************************
     * END Implement iRequestHandler Methods
     ***************************************/
     
     private $dbCtn;
     private $scope;
}