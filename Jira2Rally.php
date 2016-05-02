<?php

ini_set( 'max_execution_time', 3600 );

require_once( "RallyDev.php" );
require_once( "Jira.php" );
require_once( "DB.php" );


/**
 * Function to log actions
 * @param  string $value Text to be logged
 * @return none
 */
function log_actions( $value )
{
    file_put_contents( "log.log", PHP_EOL . date( 'm/d/Y h:i:s a', time() ). ", ". $value, FILE_APPEND );
}


class Jira2Rally
{
    // 'TEST' for test project or <>'TEST' real project
    const MODE = "TEST";

    protected $jira_object;
    protected $rally_object;
    // matrix for parameters mapping
    protected $map;

    static protected $db;


    /**
     * Initialize data
     */
    public function __construct()
    {
        $this->db           = new DB\DB();
        $this->map          = $this->get_map();
        $this->jira_object  = new Jira\Jira(); 
        $this->rally_object = new RallyDev\RallyDev();
    }


    /**
     * aggregate method for transfering data from Jira to RallyDev 
     * @return none   throws errors in used methods
     */
    public function transfer_from_jira_to_rally()
    {
        $this->jira_object->sync_db_with_jira();
        $jira = $this->jira_object->get_created_updated_objects();

        for( $i=0; $i < sizeof( $jira ); $i++ )
        {
            $rally_json = $this->map_jira_to_rally( $jira[ $i ] );
            $result = $this->rally_object->add_data_to_db( $rally_json );

            if( $result != "" )
            {
                $this->jira_object->set_object_as_added( $jira[ $i ][ "jira_object_ID" ] );
            }
            else
            {
                $error  = "<br/>ERROR: data can't be added to database - method rally->add_data_to_db()<br/>";
                $error .= "----- Possibly problem with database connection (check if db server is running)<br/>";
                throw new \Exception( $error );
            }

            if( sizeof( $jira[ $i ][ 'attachment' ] > 0 ) )
            {
                $jira_att = json_decode( $jira[ $i ][ 'attachment'], true );

                for( $j=0; $j < sizeof( $jira_att ); $j++ )
                {
                    $res = $this->jira_object->add_attachments_to_db( $jira[ $i ]["jira_object_ID"], $jira_att[ $j ] );
                }
            }
        }

        $this->rally_object->transfer_data_from_DB_to_rally();

        // call DB stored procedure to update attachment table with rally data
        $stmt = $this->db->prepare( "CALL update_attachments_with_rally_data" );
        $stmt->execute();

        $attachments = $this->db->select( "attachments", " * ", "action <> 'NONE' AND system = 'RALLY'");

        for( $i=0; $i < sizeof( $attachments ); $i++ )
        {
            $att = $attachments[ $i ];
            $att_url = $att[ 'rally_attachment_content_url' ];

            if( is_null( $att_url ) )
            {
                $content = file_get_contents( $att[ "jira_attachment_content_url" ], FILE_BINARY, $this->jira_object->get_context() );
                $att_url = $this->rally_object->create_attachment( $content );

                //update table with rally attachment content url
                $update = array( "rally_attachment_content_url" => $att_url );
                $where  = "jira_object_ID ='". $att[ "jira_object_ID" ] ."' AND ";
                $where .= "jira_attachment_filename ='".$att[ "jira_attachment_filename" ]."'";
                $this->db->update( "attachments", $update, $where );
            }

            // prepare json for assigning attachment to defect
            $jira_attachment_json = array();
            $jira_attachment_json["mimeType"] = $att[ "jira_attachment_mimeType" ];
            $jira_attachment_json["filename"] = $att[ "jira_attachment_filename" ];
            $jira_attachment_json["size"]     = $att[ "jira_attachment_size" ];

            $result = $this->rally_object->assign_attachment( $att[ "rally_object_url" ], $att_url, $jira_attachment_json );
            
            if( $result )
            {
                $update = array( "action" => "NONE", "system" => "NONE" );
                $this->db->update( "attachments", $update, $where );
            }
        }
    }


    /**
     * Get map matrix form mapping between Jira and Rally from db
     * @return 2Darray   array of mapping rows
     */
    protected function get_map()
    {
        return $this->db->select( "map" );
    }


    /**
     * get mapping array as attribute_key => attribute_value
     * @param  string $arr_key   key of assoc. array
     * @param  [type] $arr_value [description]
     * @return [type]            [description]
     */
    protected function get_map_array( $arr_key, $arr_value )
    {
        $ret = array();
        for( $i=0 ; $i < sizeof( $this->map ); $i++ )
        {
            $temp_key = "";
            $temp_val = "";
            foreach( $this->map[ $i ] as $key => $value )
            {
                if( $key == $arr_key   ) $temp_key = $value;
                if( $key == $arr_value ) $temp_val = $value;
            }
            $ret[ $temp_key ] = $temp_val;
        }
        return $ret;
    }


    /**
     * Map jira data to rally data
     * @param  [type] $jira [description]
     * @return [type]       [description]
     */
    protected function map_jira_to_rally( $jira )
    {
        $map =       $this->get_map_array( "jira_key", "rally_key" );
        $transform = $this->get_map_array( "jira_key", "transform" );

        $rally_json = array();

        foreach( $jira as $key => $value ) 
        {
            if( isset( $map[ $key ] ) )
            {
                if( $map[ $key ] == "notes" )
                {
                    switch( $key )
                    {
                        case "current_results"   : $rally_json[ $map[ $key ] ] = "<br/><br/>- CURRENT RESULTS -<br/><br/>";
                                                   break;

                        case "expected_results"  : $rally_json[ $map[ $key ] ] .= "<br/><br/>- EXPECTED RESULTS -<br/><br/>";
                                                   break;

                        case "tested_on_url"     : $rally_json[ $map[ $key ] ] .= "<br/><br/>- TESTED ON URL -<br/><br/>";
                                                   break;

                        case "login_information" : $rally_json[ $map[ $key ] ] .= "<br/><br/>- LOGIN INFORMATION -<br/><br/>";
                                                   break;
                        default: break;
                    }
                    $rally_json[ $map[ $key ] ] .= $jira[ $key ];
                }
                else
                {
                    switch( $transform[ $key ] )
                    {
                        case 'DIRECT'    :  $rally_json[ $map[ $key ] ] = $jira[ $key ]; 
                                            break;

                        case 'ATTRIBUTE' :  $rally_json[ $map[ $key ] ] = $this->get_rally_attribute( $key, $value );
                                            break;

                        case 'CODE'      :  $function = "jira_to_rally_".$key;
                                            $rally_json[ $map[ $key ] ] = $this->$function( $value );
                                            break;

                        default           : break;
                    }
                }
            }
        }

        // at this step, issue must be in Jira already
        $rally_json[ "exists_in_jira" ] = "TRUE";

        // it is not needed to update this field in Jira, so it is set to False
        $rally_json[ "update_in_jira" ] = "FALSE";

        // Define action for data; parameter will be unset before insert in Rally table
        $rally_json[ "action" ] = ( $jira[ "exists_on_rally" ] == "TRUE" ) ? "UPDATE" : "INSERT";


        // Manage specific mappings
        
        // at the moment, project is hardcoded to PromoJam; if mode='TEST', project is test; 
        if( self::MODE == "TEST" ) 
        {   // TEST project 
            $rally_json[ "project" ] = "https://rally1.rallydev.com/slm/webservice/v2.0/project/36671333232";
        }
        else
        {   // Promo Jam
            $rally_json[ "project" ] = "https://rally1.rallydev.com/slm/webservice/v2.0/project/18510434029";
        }

         // at the moment, Submited By is hardcoded to Tatiana A
        $rally_json[ "submitted_by" ] = "https://rally1.rallydev.com/slm/webservice/v2.0/user/32078757805";      

        return $rally_json;
    }


    /**
     * Prepare steps_to_reproduce string for html display
     * @param  string  $value string with ascii linebreaks
     * @return string         string with html linebreaks
     */
    protected function jira_to_rally_steps_to_reproduce( $value )
    {
        $value = str_replace( "\n\r", "<br/>", $value);
        $value = str_replace( "\r\n", "<br/>", $value);
        $value = str_replace( "\n",   "<br/>", $value);
        $value = str_replace( "\r",   "<br/>", $value);

        return $value;
    }


    /**
     * map data from jira 'browsers' array to rally found_in string
     * @param  [array]  $value string array e.g. ['FF36, 'Chrome']
     * @return [string]        string e.g. 'FF36, Chrome'
     */
    protected function jira_to_rally_browsers( $value )
    {
        $ret = "";
        $value = json_decode( $value, true );

        for( $i = 0; $i < sizeof( $value ); $i++ )
        {
            $ret .= $value[ $i ][ 'value' ].", ";
        }

        return substr( $ret, 0, -2 );
    }


    /**
     * map data from jira 'environment' array to rally environment string
     * @param  [array]  $environment string array e.g. ['QA, 'Staging']
     * @return [string]              string e.g. 'Test, Stage'
     */
    protected function jira_to_rally_environment( $environment )
    {
        $environment = json_decode( $environment, true );
        $ret = "";

        for( $i = 0; $i < sizeof( $environment ); $i++ )
        {
            $array = $this->get_rally_attribute( "environment", $environment[ $i ][ "value" ] );
            if( sizeof( $array ) > 1 )
            {
                $array = $array[ 0 ][ 'rally_attribute' ];
            }
            $ret .= $array;
            $ret .= ", ";
        }
        return substr( $ret, 0, -2 );
    }


    /**
     * returns mapped rally attribute from jira attr.
     * @param  string $jira_parameter e.g. 'status', 'severity' ...
     * @param  string $jira_attribute e.g. 'In Progress' for 'status'
     * @return string                 rally attr, e.g. 'Open'
     */
    protected function get_rally_attribute( $jira_parameter, $jira_attribute  )
    {
        $where = "jira_parameter = '".$jira_parameter."' AND jira_attribute ='".$jira_attribute."'";
        $result = $this->db->select( "attributes", "rally_attribute", $where );

        switch( sizeof( $result ) )
        {
            case 0 : $error  = "<br/>ERROR : Mapping for jira attribute '".$jira_parameter."'->'";
                     $error .= $jira_attribute."' doesn't exist in table 'attributes'<br/><br/>";
                     throw new Exception( $error );
                     break;

            case 1 : return $result[ 0 ][ "rally_attribute" ];
                     break;

            default : return $result;
                      break;
        }
    }
}


