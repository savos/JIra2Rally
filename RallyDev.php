<?php
namespace RallyDev;

use \DB;

// include connection parameters for RallyDev
require_once( "RallyDev_config.php" );
require_once( "DB.php" );

/**
 *  PHP class for managing RallyDev defects
 *  (equiv. to Jira issues)
 */
class RallyDev {

	private $username;
	private $password;
	private $security_token;

	protected $path;


    /**
     * Initialize connection parametesr
     * @param String $username 
     * @param String $password 
     * @param String $path     Rally url
     */
	public function __construct( $username = USERNAME, $password = PASSWORD, $path = DOMAIN . VERSION )
	{
        $this->db           = new DB\DB();

		$this->username = $username;
		$this->password = $password;
		$this->path     = $path;
		$this->security_token = $this->generate_security_token(); 
	}


    /**
     * get defect from db into rally's defect json format
     * @param  string $where where clause that have to return just one row
     * @return json          json in rally's defect format
     */
    public function get_defect_from_DB( $where )
    {
         $db = new DB\DB();
         $data = $db->select( "rally", "*", $where );

        if( sizeof( $data ) != 1 )
        {
            $error  = "ERROR: Returned [".sizeof( $data )."] rows from 'rally' table instead 1 row";
            throw new \Exception( $error );
        }

        $defect_json = json_decode( file_get_contents( "rally_defect_template.json" ), true );

        $defect_json[ "Defect" ][ "Name" ]                  = $data[ 0 ][ "name" ];
        $defect_json[ "Defect" ][ "Project" ][ "_ref" ]     = $data[ 0 ][ "project" ];
        $defect_json[ "Defect" ][ "State" ]                 = $data[ 0 ][ "state" ];
        $defect_json[ "Defect" ][ "Environment" ]           = $data[ 0 ][ "environment" ];
        $defect_json[ "Defect" ][ "Priority" ]              = $data[ 0 ][ "priority" ];
        $defect_json[ "Defect" ][ "Severity" ]              = $data[ 0 ][ "severity" ];
        $defect_json[ "Defect" ][ "SubmittedBy" ][ "_ref" ] = $data[ 0 ][ "submitted_by" ];
        $defect_json[ "Defect" ][ "FoundInBuild" ]          = $data[ 0 ][ "found_in" ];
        $defect_json[ "Defect" ][ "Description" ]           = $data[ 0 ][ "description" ];
        $defect_json[ "Defect" ][ "Notes" ]                 = $data[ 0 ][ "notes" ];

        return $defect_json;
    }


    /**
     * Set defect as processed in rally table
     * @param string $jira_object_ID
     * @param json   $defect
     */
    protected function set_defect_as_processed( $jira_object_ID, $defect )
    {
        $defect[ "action" ]            = "NONE";
        $defect[ "transfer_to_rally" ] = "FALSE";
        $where = "jira_object_ID ='".$jira_object_ID."'";

        return $this->db->update( "rally", $defect, $where );
    }


    /**
     * Transfers data from db to Rally
     */
    public function transfer_data_from_DB_to_rally()
    {

        $defects = $this->db->select( "rally", " * ", "action <> 'NONE' ");

        for( $i=0; $i < sizeof( $defects ); $i++ )
        {
            $defect = $defects[ $i ];

            if( $defect[ "action" ] == "INSERT" )
            {
                $json = $this->get_defect_from_DB( "jira_object_ID ='".$defect[ "jira_object_ID" ]."'" );
                $result = $this->create_defect( $json );

                $this->set_defect_as_processed( $defect[ "jira_object_ID" ], $result );

            }
            elseif( $defect[ "action" ] == "UPDATE" )
            {
                $json   = $this->get_defect_from_DB( "jira_object_ID ='".$defect[ "jira_object_ID" ]."'" );
                $result = $this->update_defect( $defect[ "rally_object_url" ], $json );

                $this->set_defect_as_processed( $defect[ "jira_object_ID" ], $result );
            }

            if( isset( $result ) )
            {
                $this->update_to_DB_on_success( $result, $defect[ 'jira_object_ID' ] );
            }
        }
    }


    /**
     * Method that creates defect in Rally from prepared and mapped json
     * @param  json $defect_json defect data json
     * @return               [description]
     */
    public function create_defect( $defect_json )
    {
        $path  = DOMAIN . VERSION . _DEFECT;
        $path .= _ACTION_CREATE;

        $result = $this->post_json_2rally( $path, $defect_json );

        $rally_object_ID  = $result[ "CreateResult" ][ "Object" ][ "ObjectID" ];
        $rally_object_url = $result[ "CreateResult" ][ "Object" ][ "_ref" ];

        return array( "rally_object_ID" => $rally_object_ID, "rally_object_url" => $rally_object_url );
    }


    /**
     * Updates defect on Rally
     * @param  string $rally_path url of Rally defect that should be updated
     * @param  json   $json       defect's data in Rally defect json format
     * @return array              returns defect id and defect's url
     */
    protected function update_defect( $rally_path, $json )
    {
//        $path  = DOMAIN . VERSION . _DEFECT;
        $path = $rally_path;

        $result = $this->post_json_2rally( $path, $json, "UPDATE" );

        $rally_object_ID  = $result[ "OperationResult" ][ "Object" ][ "ObjectID" ];
        $rally_object_url = $result[ "OperationResult" ][ "Object" ][ "_ref" ];

        return array( "rally_object_ID" => $rally_object_ID, "rally_object_url" => $rally_object_url );
    }


    /**
     * Add attachments from db to Rally
     * @return json
     */
    protected function add_attachments_from_DB_to_rally()
    {
        $attachments = $this->db->select( "attachments", " * ", $where = "action ='ADD' AND system ='RALLY'" );

        for( $i=0; $i < sizeof( $attachments ); $i++ )
        {
            $att = $attachments[ $i ];

            $content = file_get_contents( $att[ "jira_attachment_content_url" ] );

            $att_url = $this->rally_object->create_attachment( $content );

            $jira_attachment_json = array();
            $jira_attachment_json["mimeType"] = $att[ "jira_attachment_mimeType" ];
            $jira_attachment_json["filename"] = $att[ "jira_attachment_filename" ];
            $jira_attachment_json["size"]     = $att[ "jira_attachment_size" ];

            $result = $this->rally_object->assign_attachment( $att[ "rally_object_url" ], $att_url, $jira_attachment_json );

            return $result;
        }
    }


    /**
     * Update rally table in db after defect succsessfully added to Rally
     * @param  [type] $rally_object_ID 
     * @param  [type] $jira_object_ID  
     * @return    
     */
    protected function update_to_DB_on_success( $rally_object_ID, $jira_object_ID )          
    {
        if( isset( $result ) )
        {
            $update = array( 
                "rally_object_url"  => DOMAIN . VERSION . _DEFECT . "/" . $rally_object_ID,
                "rally_object_ID"   => $rally_object_ID,
                "action"            => "NONE",
                "exists_in_jira"    => "TRUE",
                "update_in_jira"    => "FALSE",
                "transfer_to_rally" => "FALSE"  
            );   

            $where = "jira_object_ID = '". $jira_object_ID ."'";
            return $this->db->update( "rally", $update, $where );
        }
    }


    /**
     * Create attachment (attachment content) at RallyDev.com 
     * @param  text   $content base64 encoded content
     * @return json            attachment data in json
     */
    public function create_attachment( &$content )
    {
        $content = base64_encode( $content );

        $json = array( "AttachmentContent" => array( "Content" => $content) );
        $path = DOMAIN . VERSION . _ATTACHMENTCONTENT . _ACTION_CREATE;// . "?key=".$this->get_security_token();

        $res = json_encode( $this->post_json_2rally( $path, $json ), JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE );
        $res = json_decode( $res, true );

        return $res["CreateResult"]["Object"]["_ref"];
    }


    /**
     * Assign already created attachment to related defect
     * @param  url    $defect_url           Rally defect url
     * @param  url    $rally_attachment_url url of attachment in Rally
     * @param  json   $jira_attachment_json json with parameters collected from Jira
     * @return json   returns json of created attachment                
     */
    public function assign_attachment( $defect_url, $rally_attachment_content_url, $jira_attachment_json )
    {
        $json = json_decode( file_get_contents( "rally_attachment_template.json" ), true );
        $json["Attachment"]["Content"]     = $rally_attachment_content_url;
        $json["Attachment"]["Artifact"]    = $defect_url;
        $json["Attachment"]["ContentType"] = $jira_attachment_json["mimeType"];
        $json["Attachment"]["Name"]        = $jira_attachment_json["filename"];
        $json["Attachment"]["Size"]        = $jira_attachment_json["size"];

        $path   = DOMAIN . VERSION . _ATTACHMENT . _ACTION_CREATE;
        $result = $this->post_json_2rally( $path, $json );

        return $result;
    }


    /**
     * Generate security token from www.RallyDev.com on object initialization
     * @return String like 'ZfIUAjrnRj24LPxBVZ2gABnS2ehkUQ4irCTGfirWyI'
     */
    protected function generate_security_token()
    {
        $context = stream_context_create( 
                        array( "http"=> 
                            array( "header" => "Authorization: Basic " . base64_encode( $this->username.':'.$this->password) )
        ));
        $security_token_json  = $this->get_json_from_url( $this->path . _AUTHORIZE, $context );

        return $security_token_json['OperationResult']['SecurityToken'];
    }


    /**
     * Returns json from url
     * @param  url    $path    url that should return json
     * @param  string $context additional parameters (e.g. for authorisation)
     * @return json            returns collected json structure
     */
	protected function get_json_from_url( $path, $context = "" )
	{
        $context = stream_context_create( 
            array( "http"=> 
                array( "header" => "Authorization: Basic " . base64_encode( $this->username.':'.$this->password) )
        ));
		return json_decode( file_get_contents( $path, false, $context ), true );
	}


    /**
     * Returns security token from RellyDev.com
     * @return string security token value
     */
	public function get_security_token()
	{
		return $this->security_token;
	}


    /**
     * Sends json formated data to RallyDev to create some Rally object
     * @param  url    $path Rally path
     * @param  json   $json data to be transfered
     * @return json   response result from url
     */
    protected function post_json_2rally( $path, $json, $method = "POST" )
    {
        $json_string = json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        switch( $method )
        {
            case "POST"   : $index = "CreateResult";    $custom_request = "POST"; break;
            case "UPDATE" : $index = "OperationResult"; $custom_request = "POST"; break;
            case "DELETE" : $index = "OperationResult"; $custom_request = "DELETE"; break;
            default       : break;
        }

        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, $path);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_USERPWD, $this->username.':'.$this->password );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $custom_request );                                                                     
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_string );                                                                  
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: charset=ANSI',
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen( $json_string ),
            'zsessionid:'. API_KEY                                                                      
        ) );        
        $result = json_decode( $this->curl_check_errors( $ch ), true );
        curl_close($ch);

        if( sizeof( $result[ $index ]["Errors"] ) )
        {
            throw new \Exception( "<br/><br/>Create object on RallyDev ERROR: ".json_encode( $result[ $index ]["Errors"] ) ); die();
        }

        if( sizeof( $result[ $index ]["Warnings"] ) )
        {
            echo( "<br/><br/>Create object on RallyDev warrning: ".json_encode( $result[ $index ]["Warnings"] ) );
        }

        return $result;
    }


    /**
     * Check if curl was performed ok
     * @param  curl header $ch 
     * @return      returns json if ok, error if not
     */
    protected function curl_check_errors( $ch )
    {
        $result = curl_exec($ch);

        if ( curl_errno( $ch ) ) 
        {
            $result = '<br/>cURL ERROR -> ' . curl_errno( $ch ) . ': ' . curl_error($ch);
            echo "ERROR! " . $result;
        } 
        else 
        {
            $returnCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            switch($returnCode)
            {
                case 200: break;
                default :  $result = 'HTTP ERROR -> ' . $returnCode;
                           break;
            }
        }
        return $result;
    }


    /**
     * Add defect data to rally table
     * @param [type] $rally_json
     */
    public function add_data_to_db( $rally_json )
    {
        $action = $rally_json[ 'action' ];
        $rally_json[ "transfer_to_rally" ] = "TRUE";

        if( $action == "INSERT" )
        {
            $result = $this->insert_data_to_db( $rally_json );
        }
        elseif( $action == "UPDATE" )
        {
            $result = $this->update_data_in_db( $rally_json );
        }

        return $result;
    }


    /**
     * Insert data from prepared json to db; jira_id must have value
     * @param  array  $json assoc. array
     * @return status       insert status
     */
    protected function insert_data_to_db( $json )
    {
        $defect = $this->get_defect_by_jira_ID( $json['jira_object_ID'] );
        if( !$defect )
        {
            $json[ 'action' ]            = "INSERT";
            $json[ 'transfer_to_rally' ] = "TRUE";

            return $this->db->insert( "rally", $json );
        }
        else
        {
            $json[ 'action' ]            = "UPDATE";
            $json[ 'transfer_to_rally' ] = "TRUE";

            return $this->db->update( "rally", $json, "jira_object_ID ='".$json['jira_object_ID']."'" );
        }
    }


    /**
     * update data in db from prepared json
     * @param  arrav  $defect 
     * @return status         update status
     */
    public function update_data_in_db( $defect ) 
    {
        $where = "";
        if( isset( $defect[ 'rally_object_ID' ] ) ) $where .= "rally_object_ID='".$defect[ 'rally_object_ID' ]."'";
        if( $where != "" ) $where .= " OR ";
        if( isset( $defect[ 'jira_object_ID' ] ) )  $where .= "jira_object_ID='".$defect[ 'jira_object_ID' ]."'";

        $rows = $this->db->select( "rally", " * ", $where );

        if( sizeof( $rows ) == 1 )
        {
            if( isset( $defect[ 'rally_object_ID' ] ) )
            {
                $where = "rally_object_ID ='".$defect[ 'rally_object_ID' ]."'";
            }
            elseif( isset( $defect[ 'jira_object_ID' ] ) )
            {
                $where = "jira_object_ID ='".$defect[ 'jira_object_ID' ]."'";
            }
            else
            {
                $error  = "<br/>ERROR updating data in database:<br/>";
                $error .= "----- There is no jira_id nor rally_id set in this defect<br/>";
                throw new \Exception ( $error );
            }

            $defect[ 'action' ]            = "UPDATE";
            $defect[ 'transfer_to_rally' ] = "TRUE";

            return $this->db->update( 'rally', $defect, $where );
        }
        else
        {
            $error  = "<br/>ERROR updating data in database:<br/>";
            $error .= "----- There is > ".sizeof( $rows )." records with ".$where."<br/>";
            throw new \Exception ( $error );
        }
    }


    /**
     * returns defect data from DB by jira_ID
     * @param  string  $jira_ID jira ID like 'JAM-42'
     * @return json             prepared json with defect data
     */
    public function get_defect_by_jira_ID( $jira_object_ID )
    {
        $where = "jira_object_ID='".$jira_object_ID."'";
        
        return $this->db->select( 'rally', " * ", $where );
    }



    /**
     * returns defect data from DB by rally_ID
     * @param  string $rally_ID rally id like '17641764164'
     * @return json           prepared json with defect data
     */
    public function get_defect_by_rally_ID( $rally_ID )
    {
        $where = "rally_object_ID='".$rally_ID."'";
        
        return $this->db->select( 'rally', " * ", $where );
    }


    /**
     * Method deletes defect by defect_ID
     * @param  int    $defect_ID rally defect ID
     * @return [type]            [description]
     */
    public function delete_defect( $defect_ID )
    {
        $path = DOMAIN . VERSION . _DEFECT . "/" . $defect_ID;

        return $this->post_json_2rally( $path, "", "DELETE" );
    }


    /**
     * Delete all defects from test project
     * @param  integer $pageSize
     */
    public function delete_all_test_defects( $pageSize = 200 )
    {
        $total = $this->get_number_of_defects();
        $pages = 1 + floor( $total / $pageSize );

        $k=0;

        for( $i=0; $i < $pages; $i++ )
        {
            $path = DOMAIN . VERSION. _DEFECT . "?start=" . $i * $pageSize . "&pagesize=" . $pageSize;

            $json = $this->get_json_from_url( $path );

            for( $j = 0; ( $j < $pageSize ) && ($j + $i * $pageSize < $total ); $j++ )
            {      
                $defect_path = $json[ 'QueryResult' ][ 'Results' ][ $j ][ '_ref' ];

                $defect = $this->get_json_from_url( $defect_path );
                if( $defect[ 'Defect' ][ 'Project' ][ '_ref' ] == "https://rally1.rallydev.com/slm/webservice/v2.0/project/YourRallyProjectID" )
                {
                    $this->delete_defect( $defect[ 'Defect' ][ 'ObjectID' ] );
                }
            }
        }
    }


    /**
     * Returns number of defects from rally
     * @param  string $update_period
     * @return int                   
     */
    public function get_number_of_defects( $update_period = "")
    {
        $path = DOMAIN . VERSION . _DEFECT . "?pagesize=200";
        $json = $this->get_json_from_url( $path );

        return $json[ 'QueryResult' ][ 'TotalResultCount' ];
    }
}



