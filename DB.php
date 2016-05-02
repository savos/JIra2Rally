<?php

namespace DB; 
use \PDO;

require_once( "DB_config.php" );

class DB {

    // db connection handler
    static protected $conn;

    /**
     * set conn connection for this object
     * @return PDO conn connection
     */
    public function __construct()
    {
        if( !isset( self::$conn ) )
        {
            self::$conn = new \PDO( 'mysql:host='. DB_HOST .';dbname='.DB_BASE.';charset=utf8', DB_USER, DB_PASS );
        }        
    }

    /**
     * [prepare description]
     * @param  [type] $sql [description]
     * @return [type]      [description]
     */
    public function prepare( $sql )
    {
        return self::$conn->prepare( $sql );
    }


    /**
     * insert record in table
     * @param  string $table_name
     * @param  json   $json       records to be added
     * @return bool               true if ok, false if not
     */
    public function insert( $table_name, $json )
    {
        $keys           = implode( ", ", array_keys( $json ) );
        $key_parameters = ":" . implode( ", :", array_keys( $json ) );
        
        $query = "INSERT INTO ".$table_name." (" . $keys . ") VALUES (" . $key_parameters . ")";

        try
        {
            $stmt = self::$conn->prepare( $query );

            foreach( $json as $key => $value )
            {
                if( is_array( $value ) )
                {
                    $value = json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                } 
                $stmt->bindValue( ':'.$key, $value );
            }

            return $stmt->execute();
        }
        catch( PDOException $e )
        {
            throw new MyDatabaseException( $Exception->getMessage( ) , $Exception->getCode( ) );
            echo "Error: " . $e->getMessage();
        }
 
    }


    /**
     * [update_issue_in_DB description]
     * @param  [type] $issue_json [description]
     * @return [type]             [description]
     */
    public function update( $table_name, $json, $where = "" )
    {
        $query = "UPDATE " . $table_name . " SET ";

        foreach( $json as $key => $value )
        {
            $query .= $key . "= :" . $key . ", ";
        }
        $query = substr( $query, 0, -2 );

        if( $where != "" ) $query .= " WHERE ". $where;

        try
        {
            $stmt = self::$conn->prepare( $query );

            foreach( $json as $key => $value )
            {
                if( is_array( $value ) )
                {
                    $value = json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                } 
                $stmt->bindValue( ':'.$key, $value );
            }
            $stmt->execute();
        }
        catch( PDOException $e )
        {
            echo "Error: " . $e->getMessage();
        }
        return self::$conn->lastInsertId();
    }


    /**
     *  Select data from table
     * 
     *  return array
     */
    public function select( $table_name, $key = " * ", $where = "" )
    {
        $query  = "SELECT ";
        $query .= ( is_array( $key ) ) ? implode( ",", array_keys( $json ) ) : $key ;
        $query .= " FROM ". $table_name;
        if( $where != "" ) $query .= " WHERE ". $where; 

        try
        {
            $stmt = self::$conn->prepare($query);
            $stmt->execute(); 
            $stmt->setFetchMode( PDO::FETCH_ASSOC );

            return $stmt->fetchAll();
        }
        catch( PDOException $e )
        {
            echo "Error: " . $e->getMessage();
        }
    }

}

?>
