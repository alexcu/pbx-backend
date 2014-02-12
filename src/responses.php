<?php
/**
 * @class   ResponseHandler
 *
 * Handles all exception errors (as well as success responses)
 * and spits out a JSON string that can be returned
 *
 * @author  Alex Cummaudo
 * @date    2013-12-16
 */
class ResponseHandler
{   
    /***************************************
     * Response Database File Location
     * Must be relative to root
     ***************************************/
    const RESPONSE_XML_LOC = "./resources/responses.xml";
    
    /**
     * Generates a JSON response.
     * 
     * @access public
     * @static
     * @param mixed $data
     * @return void
     */
    public static function generate_response($data) 
    {   
        // NOTE: Path must be relative to /auth/index.php!
        self::$responseDatabase = DOMDocument::load(self::RESPONSE_XML_LOC);

        // $responseDatabase returns false where not loaded properly
        if (self::$responseDatabase === false)
        { 
            // HARD CODED FAILURE TO GET RESPONSES DATABASE!
            $responseCode   = "PBX503";
            $responseDesc   = "Missing Internal File";
            $responseMsg    = "Failed to load error descriptions! Check responses.xml immediately!";
        }
        // Otherwise we have a response database
        else
        {
            // Explode data into required pieces (i.e. PBX\d{3}:OptionalMessage)
            $dataContents       = explode(":", $data, 2);
            
            // If not a PBX\d{3} formatted exception message (i.e. say PHP one)
            if (!preg_match("/^PBX\d{3}/", $dataContents[0]))
            { 
                $responseCode   = "PBX500";
                $responseMsg    = $data;    // The data that we recieved will serve as the message 
            }
            // Otherwise the error code is the data[0] component
            // and its (optional) message is the data[1] component
            else
            {
                $responseCode   = $dataContents[0];
                // Optional message was set? (and it isn't just ":")
                if (isset($dataContents[1]))
                { $responseMsg  = $dataContents[1]; }
            }
            
            // Get the reponse description
            $responseDesc       = self::get_response_description($responseCode);
        }
        
        // Return the encoded JSON (with or without optional message)
        $response = array
        (
            "responseCode"  => $responseCode,
            "responseDesc"  => $responseDesc,
        );
        
        if (isset($responseMsg))
        { $response["responseMsg"] = $responseMsg; }
        
        return json_encode($response);
    }
    
    /**
     * Looks up errors in the XML database RequestErrors.xml
     * 
     * @access private
     * @return void
     */
    public function get_response_description($code)
    {       
        // Return the element whose ID is this code
        foreach (self::$responseDatabase->getElementsByTagName("response") as $el)
        {
            // This element doesn't match the id? Move onto next
            if ($el->getAttribute("id") != $code) continue;
            // Otherwise it does!
            else
            { return $el->textContent; }
        }
        
        // If element was not found (i.e. out of loop), then this error is unknown
        return "Unknown error!";
    }
    
    private static $responseDatabase;
}
?>