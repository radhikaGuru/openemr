<?php

/**
 * QueryUtils.php  Is a helper class for commonly used database functions.  Eventually everything in the sql.inc file
 * could be migrated to this file or at least contained in this namespace.
 * @package openemr
 * @link      http://www.open-emr.org
 * @author    Stephen Nielson <stephen@nielson.org>
 * @copyright Copyright (c) 2021 Stephen Nielson <stephen@nielson.org>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Common\Database;

class QueryUtils
{
    /**
     * Executes the SQL statement passed in and returns a list of all of the values contained in the column
     * @param $sqlStatement
     * @param $column The column you want returned
     * @param array $binds
     * @throws SqlQueryException Thrown if there is an error in the database executing the statement
     * @return array
     */
    public static function fetchTableColumn($sqlStatement, $column, $binds = array())
    {
        $recordSet = self::sqlStatementThrowException($sqlStatement, $binds);
        $list = [];
        while ($record = sqlFetchArray($recordSet)) {
            $list[] = $record[$column] ?? null;
        }
        return $list;
    }

    public static function fetchSingleValue($sqlStatement, $column, $binds = array())
    {
        $records = self::fetchTableColumn($sqlStatement, $column, $binds);
        if (!empty($records[0])) {
            return $records[0];
        }
        return null;
    }

    public static function fetchRecords($sqlStatement, $binds = array())
    {
        $result = self::sqlStatementThrowException($sqlStatement, $binds);
        $list = [];
        while ($record = sqlFetchArray($result)) {
            $list[] = $record;
        }
        return $list;
    }

    /**
     * Executes the sql statement and returns an associative array for a single column of a table
     * @param $sqlStatement The statement to run
     * @param $column The column you want returned
     * @param array $binds
     * @throws SqlQueryException Thrown if there is an error in the database executing the statement
     * @return array
     */
    public static function fetchTableColumnAssoc($sqlStatement, $column, $binds = array())
    {
        $recordSet = self::sqlStatementThrowException($sqlStatement, $binds);
        $list = [];
        while ($record = sqlFetchArray($recordSet)) {
            $list[$column] = $record[$column] ?? null;
        }
        return $list;
    }

    /**
     * Standard sql query in OpenEMR.
     *
     * Function that will allow use of the adodb binding
     * feature to prevent sql-injection. Will continue to
     * be compatible with previous function calls that do
     * not use binding.
     * It will return a recordset object.
     * The sqlFetchArray() function should be used to
     * utilize the return object.
     *
     * @param  string  $statement  query
     * @param  array   $binds      binded variables array (optional)
     * @throws SqlQueryException Thrown if there is an error in the database executing the statement
     * @return recordset
     */
    public static function sqlStatementThrowException($statement, $binds)
    {
        return sqlStatementThrowException($statement, $binds);
    }

    /**
     * Sql insert query in OpenEMR.
     *  Only use this function if you need to have the
     *  id returned. If doing an insert query and do
     *  not need the id returned, then use the
     *  sqlStatement function instead.
     *
     * Function that will allow use of the adodb binding
     * feature to prevent sql-injection. This function
     * is specialized for insert function and will return
     * the last id generated from the insert.
     *
     * @param  string   $statement  query
     * @param  array    $binds      binded variables array (optional)
     * @throws SqlQueryException Thrown if there is an error in the database executing the statement
     * @return integer  Last id generated from the sql insert command
     */
    public static function sqlInsert($statement, $binds = array())
    {
        // Below line is to avoid a nasty bug in windows.
        if (empty($binds)) {
            $binds = false;
        }

        //Run a adodb execute
        // Note the auditSQLEvent function is embedded in the
        //   Execute function.
        $recordset = $GLOBALS['adodb']['db']->Execute($statement, $binds, true);
        if ($recordset === false) {
            throw new SqlQueryException($statement, "Insert failed. SQL error " . getSqlLastError());
        }

        // Return the correct last id generated using function
        //   that is safe with the audit engine.
        return $GLOBALS['lastidado'] > 0 ? $GLOBALS['lastidado'] : $GLOBALS['adodb']['db']->Insert_ID();
    }

    /**
     * Shared getter for SQL selects.
     *
     * @param $sqlUpToFromStatement - The sql string up to (and including) the FROM line.
     * @param $map - Query information (where clause(s), join clause(s), order, data, etc).
     * @throws SqlQueryException If the query is invalid
     * @return array of associative arrays | one associative array.
     */
    public static function selectHelper($sqlUpToFromStatement, $map)
    {
        $where = isset($map["where"]) ? $map["where"] : null;
        $data  = isset($map["data"]) && is_array($map['data']) ? $map["data"]  : [];
        $join  = isset($map["join"])  ? $map["join"]  : null;
        $order = isset($map["order"]) ? $map["order"] : null;
        $limit = isset($map["limit"]) ? intval($map["limit"]) : null;

        $sql = $sqlUpToFromStatement;

        $sql .= !empty($join)  ? " " . $join        : "";
        $sql .= !empty($where) ? " " . $where       : "";
        $sql .= !empty($order) ? " " . $order       : "";
        $sql .= !empty($limit) ? " LIMIT " . $limit : "";

        $multipleResults = sqlStatementThrowException($sql, $data);

        $results = array();

        while ($row = sqlFetchArray($multipleResults)) {
            array_push($results, $row);
        }

        if ($limit === 1) {
            return $results[0];
        }

        return $results;
    }
}
