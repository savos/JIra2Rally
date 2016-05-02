<?php

namespace Jira;
use \DB;
use \Map;

// TODO links for future work;
// uri with all statuses:   https://jira.carrierzone.com/jira/rest/api/latest/status
// uri with all priorities: https://jira.carrierzone.com/jira/rest/api/latest/priority

// include connection parameters for Jira
require_once( "jira_config.php" );
require_once( "DB.php" );
require_once( "map.php" );

/**
 *  PHP class for managing Jira issues
 *  (equiv. to RallyDev deffects)
 */
class Jira 
{

    protected $path;
    protected $map;

    static protected $db;


    /**
     * Initialize connection parametesr
     * @param String $username 
     * @param String $password 
     * @param String $path     Jira url
     */
    public function __construct( $path = DOMAIN . VERSION . SEARCH, $project = PROJECT  )
    {
        $this->db = new DB\DB();

        $this->path     = $path . $project;
        $this->map      = new Map\map();
    }

    /**
     * Check issues on Jira, insert new and update updated
     * @param  integer $pageSize [description]
     * @return none
     */
    public function sync_db_with_jira( $pageSize = 200 )
    {
        $total = $this->get_number_of_issues();
        $pages = 1 + floor( $total / $pageSize );

        $previous_update = $this->db->select( "jira", "max( updated ) as previous_update" );
        $previous_update = $previous_update[ 0 ][ 'previous_update' ];

        $last_update = $previous_update;

        for( $i=0; $i < $pages; $i++ )
        {
            $path = $this->path . STARTAT . $i * $pageSize . MAXRESULTS . $pageSize . "&fields=self,key,created,updated"; 
            $json = $this->get_json_from_url( $path );

            for( $j = 0; ( $j < $pageSize ) && ($j + $i * $pageSize < $total ); $j++ )
            {      
                $issue_path = $json[ 'issues' ][ $j ][ 'self' ];

                if( $json[ 'issues' ][ $j ][ 'fields' ][ 'created' ] > $previous_update )
                {
                    $issue_json = $this->get_json_from_url( $issue_path );
                    $result = $this->insert_issue_in_DB( $issue_json );
                }       
                elseif( $json[ 'issues' ][ $j ][ 'fields' ][ 'updated' ] > $previous_update )
                {
                    $issue_json = $this->get_json_from_url( $issue_path );
                    $result = $this->update_issue_in_DB( $issue_json );
                }
            }
        }
    }


    /**
     * Returns data prepared from json for db insert
     * @param  json   $issue_json data from Jira in son format
     * @return [type]             flat data in table format
     */
    public function get_record_from_json( $issue_json )
    {
        $map = $this->map->get_map_array( "jira_key", "jira_path" );
        $result = array();

        foreach( $map as $key => $value )
        {
            $path = explode( "-", $value );
            $json = $issue_json;

            foreach( $path as $part ) 
            {
                if( isset( $json[ $part ] ) ) 
                {
                    $json = $json[ $part ];
                }
            }

            if( is_array( $json ) ) 
                $json = json_encode( $json );

            $result[ $key ] = $json;
        }

        return $result;
    }


    /**
     * insert issue from jira json to database
     * @param json $issue_json json from jira
     */
    public function insert_issue_in_DB( $issue_json )
    {
        // get prepared array for database 
        $issue = $this->get_record_from_json( $issue_json );

        $issue[ 'exists_on_rally' ] = 'FALSE';
        $issue[ 'update_on_rally' ] = 'TRUE';

        $rows = $this->db->insert( "jira", $issue );

        return $rows;          
    }


    /**
     * Updates changed issue data in database
     * @param  json   $issue_json issue data in jira json format
     * @return [type]             [description]
     */
    function update_issue_in_DB( $issue_json )
    {
        // get prepared array for database 
        $issue = $this->get_record_from_json( $issue_json );
        $issue[ 'exists_on_rally' ] = 'TRUE';
        $issue[ 'update_on_rally' ] = 'TRUE';

        $where = "jira_object_ID ='".$issue[ "jira_object_ID" ]."'";

        $rows = $this->db->update( "jira", $issue, $where );

        return $rows;
    }


    /**
     * Add issue(id) attachments to database and marks them for rally
     * TODO functionality should be moved to Jira2Rally
     * @param int  $jira_ID  jira object id, e.g. "JAM-22"
     * @param json $jira_att attachment data
     */
    public function add_attachments_to_db( $jira_ID, $jira_att )
    {
        $attachment = array (
            "jira_object_id"                 => $jira_ID,
            "jira_attachment_content_url"    => $jira_att[ "content" ],
            "jira_attachment_mimeType"       => $jira_att[ "mimeType" ],
            "jira_attachment_size"           => $jira_att[ "size" ],
            "jira_attachment_created"        => $jira_att[ "created" ],
            "jira_attachment_filename"       => $jira_att[ "filename" ]
        );

        $where =  "jira_object_id ='".$jira_ID;
        $where .= "' AND jira_attachment_filename ='".$jira_att[ "filename" ]."'";
        $att = $this->db->select( 'attachments', " * ", $where );

        $result = "";

        if( sizeof( $att ) == 0 )
        {
            $attachment[ 'action' ] = "ADD";
            $attachment[ 'system' ] = "RALLY";
            $result = $this->db->insert( 'attachments', $attachment );
        }
        elseif( $attachment[ "jira_attachment_created" ] > $att[ 0 ][ "jira_attachment_created" ] )
        {
            $attachment[ 'action' ] = "UPDATE";
            $attachment[ 'system' ] = "RALLY";
            $result = $this->db->update( 'attachments', $attachment, $where );
        }
        return $result;
    }


    /**
     * Returns json from url
     * @param  url    $path    url that should return json
     * @param  string $context additional parameters (e.g. for authorisation)
     * @return json            returns collected json structure
     */
    protected function get_json_from_url( $path )
    {
        $context = $this->get_context();
        return json_decode( file_get_contents( $path, false, $context ), true );
    }


    /**
     * returns total number of issues for query (here is limited for project)
     * @return int   total number of issues returned by query
     */
    public function get_number_of_issues( $update_period = "")
    {
        $path = $this->path . STARTAT . "0" . MAXRESULTS . "1";
        $json = $this->get_json_from_url( $path );

        return $json["total"];
    }


    /**
     * Authorize context so Jira will return Json
     * @return json header with encoded credentials
     */
    public function get_context()
    {
        $context = stream_context_create( 
                        array( "http"=> 
                            array( "header" => "Authorization: Basic " . base64_encode( USERNAME.':'.PASSWORD ) )
        ));

        return $context;
    }


    /**
     * returns issue from url by order number.
     * @param  [type] $No integer
     * @return json   returns jira json
     */
    public function get_issue_data( $No )
    {
         $path = $this->path . STARTAT . $No . MAXRESULTS . "1";
         $json = $this->get_json_from_url( $path );

         return $this->get_json_from_url( $json['issues'][ 0 ]['self'] );
    }


    /**
     * Returns objects (issues) from DB changed on Jira from last checkup
     * @return json Jira db table format
     */
    public function get_created_updated_objects()
    {
        $rows = $this->db->select( 'jira', " * ", "update_on_rally = 'TRUE'");

        return $rows;
    }


    /**
     * If object is added to rally, marked as processed
     * TODO move method to JirA2Rally class
     * @param [type] $jira_object_ID [description]
     */
    public function set_object_as_added( $jira_object_ID )
    {
        $fields = array( 'exists_on_rally' => 'TRUE', 'update_on_rally' => 'FALSE' );
        $where  = "jira_object_ID ='".$jira_object_ID."'";

        $this->db->update( 'jira', $fields, $where );
    }
}

