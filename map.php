<?php

namespace Map;
use \DB;

require_once( "DB.php" );


class map
{
    static protected $db;
    protected $map;

    public function __construct()
    {
        $this->db = new DB\DB();
        $this->map = $this->db->select( "map" );
    }

    public function get_map_array( $arr_key, $arr_value )
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
            if( !is_null( $temp_key ) && !is_null( $temp_val ) )
            {
                $ret[ $temp_key ] = $temp_val;
            }
        }
        return $ret;
    }
}