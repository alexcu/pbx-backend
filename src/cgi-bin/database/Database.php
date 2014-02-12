<?php
/**
 * @class   Database
 *
 * Allows methods for interaction with the PBX Database
 *
 * @author  Alex Cummaudo
 * @date    2014-01-18
 */
class Database
{
    /***************************************
     * MySQL Database Location
     ***************************************/
    const CONNECTION_URL = "localhost";
    
    /**
     * Constructor for a new database connection
     * 
     * @access public
     * @param mixed $user
     * @param mixed $password = false
     * @param mixed $db
     * @return void
     */
    function __construct($user, $password = false, $db)
    {
        // If password is null, lookup secure pwd from username
        // else provide the pasword
        $password = $password === false ? pwd($user) : $password;
        
        $ctn = @new mysqli(self::CONNECTION_URL, $user, $password, $db);
        
        // 501 -- Database Connection Issue
        if ($ctn->connect_error) { throw new Exception("PBX501:".$ctn->connect_error); }
        else
        {
            $this->ctn = $ctn;
        }
    }
    
     /**
      * Creates a new db connection we can insert into with special key
      * 
      * @access public
      * @static
      * @return void
      */
     public static function new_sec_ctn($username, $password = false, $database)
     {
        // If password is null, lookup secure pwd from username
        // else provide the pasword
        $password = $password === false ? pwd($username) : $password;
        
        $passwordKey = Key::generate_pwd($password);
        return new Database($username, $passwordKey, $database);
     }
    
    /**
     * Destructor kills all database stuff.
     * 
     * @access public
     * @return void
     */
    function __destruct()
    {
        if ($this->ctn) { $this->ctn->close();    }
    }
    
    /**
     * Cleanup essentially closes the result set once we're done
     * using it
     *
     * @access public
     * @return void
     */
    private function cleanup()
    {
        // If there was a result, then kill it
        if ($this->result) { $this->result->close(); }
    }
    
    /**
     * Executes an $sql query, returning results given under the
     * format described $resultFormat -- {'xml', 'array', 'array-printable', 'json', 'raw'}
     * Don't specify a $resultFormat for SQL statements with no result
     * sets given
     * 
     * @access public
     * @param mixed $sql
     * @param mixed $resultFormat (default: NULL)
     * @return mixed format defined by $resultFormat OR
     *         true  given all queries were push queries
     */
    public function execute_query($sql, $resultFormat = NULL)
    {
        try
        {
            switch ($resultFormat)
            {
                case "xml":
                {
                    $result = $this->xml_query($sql);
                    break;
                }
                case "array":
                {
                    $result = $this->array_query($sql);
                    break;
                }
                case "array-printable":
                {
                    $result = print_r($this->array_query($sql), true);
                    break;
                }
                case "json":
                {
                    $result = $this->json_query($sql);
                    break;
                }
                case "raw":
                {
                    $result = $this->raw_query($sql);
                    break;
                }
                case NULL:
                {
                    $result = $this->raw_query($sql);
                    break;
                }
                default:
                {   
                    throw new Exception("PBX401:$resultFormat is not a valid result format");
                    break;
                }
            }
        }
        catch (Exception $e)
        {
            // This exception signals that all results were true
            if ($e->getMessage() == "all-true") { $result = true; }
            // Otherwise, keep throwing the exception upwards
            else throw $e;
        }       
        // Cleanup result (actual query result set)
        $this->cleanup();
        
        return $result;
    }
    
    /**
     * Returns a XML result set for an SQL query 
     * (useful for XPath/XSLT)
     * 
     * @access public
     * @param string $sql
     * @return xml document result set
     */
    private function xml_query($sql)
    {
        // Get the actual results from the queries
        $queriesResults = $this->raw_query($sql);
        
        // Setup XML Document
        $xmlDoc = new DOMDocument("1.0");
        
        // Setup Root Element
        $xmlRoot = $xmlDoc->createElement("results");
        $xmlDoc->appendChild($xmlRoot);
                
        // For every result
        foreach ($queriesResults as $result)
        {          
            // Only if we have executed SQL with result set
            if (get_class($result) == "mysqli_result")
            {   
                // Setup Query Element
                $xmlQry = $xmlDoc->createElement("query");
                $xmlRoot->appendChild($xmlQry);

                // Get all columns
                $columns    = $result->fetch_fields();
                
                // For every result in column set
                while ($row = $result->fetch_assoc())
                {
                    // Setup Record Element
                    $xmlRow = $xmlDoc->createElement("record");
                    $xmlQry->appendChild($xmlRow);
                    
                    // For every column in this record
                    foreach ($columns as $column)
                    {
                        // Setup this Column's Element (name and it's value)
                        $xmlData = $xmlDoc->createElement($column->name, $row[$column->name]);
                        $xmlRow->appendChild($xmlData);
                    }
                } 
            }
        }
        return $xmlDoc->saveXML();
    }
    
    /**
     * Returns an array set for an SQL query 
     * 
     * @access public
     * @param string $sql
     * @return string result set
     */
    private function array_query($sql)
    {
        // Get the actual results from the queries
        $queriesResults = $this->raw_query($sql);
        
        // Empty query array
        $queryArray = array();
        $i          = 0;
        
        // For every result
        foreach ($queriesResults as $result)
        {
            // Only if we have executed SQL with result set
            if (get_class($result) == "mysqli_result")
            {
                // Setup Root Array Document
                $resultArray      = array();
                
                // Get all columns
                $columns    = $result->fetch_fields();
                
                // Row number setup
                $j          = 0;
                
                // For every result in column set
                while ($row = $result->fetch_assoc())
                {
                    // Setup Record Element
                    $recordArray = array();
                    
                    // For every column in this record
                    foreach ($columns as $column)
                    {
                        // Setup this Column's assoc. array (name and it's value)
                        $recordArray[$column->name] = $row[$column->name];
                    }
                    
                    // Pop the j'th record in
                    $resultArray[$j] = $recordArray;
                    $j++;
                }   
            }
            
            
            // Pop the i'th result query in
            $queryArray[$i] = $resultArray;
            $i++;
        }
        
        // If only one query, return the first result
        if (count($queryArray) == 1) return $queryArray[0];
        else
        // Return multi-line
        return $queryArray;
    }
    
    /**
     * Returns a XML JSON set for an SQL query 
     * (useful for GameMaker returns)
     * 
     * @access public
     * @param string $sql
     * @return string result set
     */
    private function json_query($sql)
    {
        // Get an array query to encode
        $result = $this->array_query($sql);
        
        // Return the encoded JSON        
        return json_encode($result);
    }
    
    /**
     * Send a query to the connection
     * 
     * @access public
     * @param mixed $sql
     * @return mysqli_result of raw results
     */
    private function raw_query($sql)
    {
        // Multiline Query from input
        $allQueries = explode(";", $sql);
        
        // Truth array
        $truthArray = array();
        
        // Execute all  queries
        // (except last one---last one is always blank due to explode)
        for ($i = 0; $i < count($allQueries) - 1; $i++ )
        {
            if ($result = $this->ctn->query($allQueries[$i].";"))
            {
                // Add to result set (or true on a non-result query)
                $this->results[$i] = $result;
                
                // Check if this result is true (push qry)
                if ($this->results[$i] === true) { $truthArray[$i] = true; }
            }
            else
            {
                // 502 -- Query Failed
                throw new Exception("PBX502:".$this->ctn->error);
            }
        }
        
        // If all queries were push results (i.e. they
        // return true) then ensure we just return a 
        // signular true
        for ($i = 0; $i < count($truthArray); $i++)
        {
            // This was a true-push query?
            if ($truthArray[$i] === true) 
            {
                // This is the last result? (sub 1 since 0 based)
                // Well then, we haven't break'ed once (see below)
                // so then all queries must be true... return true
                // as opposed to an array of trues!
                if ($i == count($truthArray) - 1) 
                { throw new Exception("all-true"); }
                // Othewise, it isn't the last result... keep checking
                else continue;
            }
            // Otherwise, this is a result set? Stop checking...
            else break;
        }
        
        // Return all query results from these queries
        return $this->results;
    }
     
    
    private $ctn;
    private $results;
}

?>