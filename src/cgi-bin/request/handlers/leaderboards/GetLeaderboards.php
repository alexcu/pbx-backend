<?php
/**
 * @class   Leaderboards retrieval class
 *
 * Retrieves the leaderboards in the specified format
 *
 * @implements iRequestHandler
 * @author  Alex Cummaudo
 * @date    2014-01-11
 */
class GetLeaderboards implements iRequestHandler
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
         return "getLeaderboards";
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

        // Optionals set to NULL initially
        $game        = NULL;
        $playerName  = NULL;
        $scope       = NULL;
        $limit       = NULL;
        
        if (isset($data['scope'     ])) { $scope       = $data['scope' ];     } // { bestAllTime , bestToday , bestWeek , lastEntry <requires playerName set> }
        if (isset($data['gameID'    ])) { $game        = substr($data['gameID'], 0, 4);     } // the 4 digit game identifier limits scope
        if (isset($data['playerName'])) { $playerName  = $data['playerName']; } // playerName limits scope
        if (isset($data['limit'     ])) { $limit       = $data['limit'];      } // limit limits the number of results returned
    
        // Not using playerName lookup database functions
        if ($playerName == NULL)
        {   
            // Begin searching Leaderboards_<view>
            $dbView = "Leaderboards_";
            
            // Switch for Scopes
            switch ($scope)
            {
                case ("bestAllTime")    : $dbView .= "BestAllTime";     break;
                case ("bestToday")      : $dbView .= "BestToday";       break;
                case ("bestWeek")       : $dbView .= "BestWeek";        break;
                case ("summaryPlayers") : $dbView .= "SummaryPlayers";  break;
                // Where no scope is provided, use Ranked
                default                 : $dbView .= "Ranked";
            }
            
            // We want to get gameID/title only?
            if ($scope == "gamesThatExist") 
            { 
                $sql = "SELECT DISTINCT gameName, gameID FROM $dbView ";
            }
            else
            {
                // If our scope asks for the top X then limit to the top X
                if (preg_match("/^top\d$/", $scope)) { $limit = intval(substr($scope, 3)); }

                // Compile final sql
                $sql = "SELECT * FROM $dbView ";
                
                // Add gameID/limit if set
                if ($game  != NULL) { $sql .= "WHERE gameID = '$game'"; }
                if ($limit != NULL) { $sql .= "LIMIT 0, $limit ";      }
            }            
            
            // Final terminator for statement
            $sql .= ";";
        }
        // Using playerName lookup database procedure
        else
        {
            // Begin using Leaderboards_<procedure>
            $dbLookupProc = "Leaderboards_LookupName";
            
            // Switch for Scopes
            switch ($scope)
            {
                case ("lastEntry")     : $dbLookupProc .= "_LastEntry";    break;
                case ("bestScore")     : $dbLookupProc .= "_BestScores";   break;
                // Where no scope is provided, use Ranked
                default                 : $dbLookupProc .= "_Ranked";
            }
            
            // Compile final sql
            $sql = "CALL $dbLookupProc('$playerName');";
        }
        
        // Setup Database Connection
        $dbCtn  = new Database(self::DB_USERNAME, self::DB_PASSWORD, self::DB_DATABASE);
        
        $result = $dbCtn->execute_query($sql, $format);
        
        // Returns a true if all okay
        return $result;
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
        if (!isset($data['format'])) { throw new Exception("PBX401"); }
     }
     
    /***************************************
     * END Implement iRequestHandler Methods
     ***************************************/
     
     private $dbCtn;
     private $scope;
}