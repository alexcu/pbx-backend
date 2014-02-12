<?php
/**
 * @class   Key
 *
 * Allows for official PBX keys to be generated
 *
 * @author  Alex Cummaudo
 * @date    2013-11-27
 */
class Key
{
    
    /**
     * Generates a new key from given data.
     * 
     * @access public
     * @static
     * @param string $name of the key
     * @param array $data to encrypt
     * @return a new Key object
     */
    public static function new_key_from_data($name, $timestamp, array $data)
    {
        // New key to be returned
        $obj = new Key();
        
        // Initalise values
        $obj->name = strtoupper($name);
        
        // If timestamp was true then we override dateTime checks
        $timestamp = $timestamp === true ? "2013-01-01 01:01:01" : $timestamp;
        
        // Generate Salt based on a DateTime of timestamp
        $obj->salt = $obj->generate_salt(new DateTime("$timestamp"));
        if ($obj->salt === NULL) { throw new Exception("PBX601:Invalid timestamp"); }
        
        // Genertate a key
        $obj->key = $obj->generate_key($data);
        
        // Return the object
        return $obj;
    }


    /**
     * Generates a new Key object from a raw, already encrypted key.
     * 
     * @access public
     * @static
     * @param string $key that is already encrypted
     * @return a new Key object
     */
    public static function new_key_from_source($key)
    {
        // All SHA256 keys must be 64 characters long--if not, auth key couldn't be made
        if (strlen($key) != 64) { throw new Exception("PBX603"); }
        $obj = new Key();
        $obj->key = $key;       
        return $obj;
    }


    /**
     * Validates two keys together
     * 
     * @access public
     * @param array $data
     * @return true or false on a match/no match
     */
    public static function validate_keys(Key $key1, Key $key2)
    {    
        return $key1->key == $key2->key;
    }
    
    
    /**
     * Generates a new SHA-256 encrypted key based on the data
     * it recieves and salted with the timestamp in a special way
     * 
     * @access private
     * @param array $data
     * @return a new string representing the key
     */
    private function generate_key(array $data)
    {   
        // Add the Name
        $result = $this->name.":";

        // Add all the data
        foreach ($data as $key => $value)
        {
            // Key value pairs to be encrypted
            $result .= "$key=$value";
            // Append ampersand where not last key
            $result .= "&";
        }
        // Generate and append Salt
        $result.= ":".$this->salt;
    
        // Return hashed result
        return hash("sha256", $result);
    }
    
    
    /**
     * Generates salt based on the timestamp.
     * 
     * @access private
     * @param DateTime $timestamp The time to generate salt on
     * @return A random number
     */
    private function generate_salt(DateTime $timestamp)
    {   
        // Overriding timestamp
        if ($timestamp->format('Y-m-d H:i:s') != "2013-01-01 01:01:01")
        {
            // Timestamps must be at least older than
            // the earliest timezone (Pacific/Midway)
            // Too early if diff days > 0
            $tooEarly = $timestamp->diff(new DateTime("now", new DateTimeZone("Pacific/Midway"  )))->days > 0;
            
            // And it must be at least earlier than 
            // latest the timezone (Pacific/Tongatapu)
            // Too late if diff days > 0
            $tooLate = $timestamp->diff(new DateTime("now", new DateTimeZone("Pacific/Tongatapu")))->days > 0;
                        
            // Validate against those above
            if ( $tooEarly || $tooLate ) return NULL;
        }

        // Get values set for salt random value
        $years   = (int)$timestamp->format('Y');
        $months  = (int)$timestamp->format('m');
        $days    = (int)$timestamp->format('d');        
        $hours   = (int)$timestamp->format('H');
        $minutes = (int)$timestamp->format('i');
        $seconds = (int)$timestamp->format('s');
        
        // Simple salt random value algorithm based on time
        $v1 = ($years * $months) / $days * $seconds;
        $v2 = ($hours - $minutes) + $seconds;
        
        // Default to 100 on a 0 for either
        if ($v1 == 0) $v1 = 100;
        if ($v2 == 0) $v2 = 100;
        
        // Even second? Divide top from bottom
        if ($seconds % 2 == 0) return abs($v1/$v2);
        // Odd second? Divide bottom from top
        else                   return abs($v2/$v1 * 10000);
    }
    
    
    /**
     * Generates secure PBX passwords.
     * 
     * @access public
     * @param mixed $password
     * @return void
     */
    public static function generate_pwd($password)
    {
        $passwordKey = Key::new_key_from_data("PBXDB", true, array("pwd"=>"$password"));
        return substr($passwordKey->get_key(), 0, 25);
    }
    
    /**
     * Returns the key.
     * 
     * @access public
     * @return void
     */
    public function get_key()
    {
        return $this->key;
    }
    
    private $key;
    private $salt;
    private $name;
}

?>