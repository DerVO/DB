<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * The PEAR DB driver for PHP's sqlite extension
 * for interacting with SQLite databases
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Database
 * @package    DB
 * @author     Urs Gehrig <urs@circle.ch>
 * @author     Mika Tuupola <tuupola@appelsiini.net>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/DB
 */

/**
 * Obtain the DB_common class so it can be extended from
 */
require_once 'DB/common.php';

/**
 * The methods PEAR DB uses to interact with PHP's sqlite extension
 * for interacting with SQLite databases
 *
 * These methods overload the ones declared in DB_common.
 *
 * @category   Database
 * @package    DB
 * @author     Urs Gehrig <urs@circle.ch>
 * @author     Mika Tuupola <tuupola@appelsiini.net>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/DB
 */
class DB_sqlite extends DB_common
{
    // {{{ properties

    /**
     * The DB driver type (mysql, oci8, odbc, etc.)
     * @var string
     */
    var $phptype = 'sqlite';

    /**
     * The database syntax variant to be used (db2, access, etc.), if any
     * @var string
     */
    var $dbsyntax = 'sqlite';

    /**
     * The capabilities of this DB implementation
     *
     * Meaning of the 'limit' element:
     *   + 'emulate' = emulate with fetch row by number
     *   + 'alter'   = alter the query
     *   + false     = skip rows
     *
     * @var array
     */
    var $features = array(
        'limit'         => 'alter',
        'pconnect'      => true,
        'prepare'       => false,
        'ssl'           => false,
        'transactions'  => false,
    );

    /**
     * A mapping of native error codes to DB error codes
     * @var array
     */
    var $errorcode_map = array(
    );

    /**
     * SQLite data types
     *
     * @link http://www.sqlite.org/datatypes.html
     *
     * @var array
     */
    var $keywords = array (
        'BLOB'      => '',
        'BOOLEAN'   => '',
        'CHARACTER' => '',
        'CLOB'      => '',
        'FLOAT'     => '',
        'INTEGER'   => '',
        'KEY'       => '',
        'NATIONAL'  => '',
        'NUMERIC'   => '',
        'NVARCHAR'  => '',
        'PRIMARY'   => '',
        'TEXT'      => '',
        'TIMESTAMP' => '',
        'UNIQUE'    => '',
        'VARCHAR'   => '',
        'VARYING'   => '',
    );

    /**
     * The most recent error message from $php_errormsg
     * @var string
     */
    var $_lasterror = '';


    // }}}
    // {{{ constructor

    /**
     * Constructor for this class.
     *
     * Error codes according to sqlite_exec.  Error Codes specification is
     * in the {@link http://sqlite.org/c_interface.html online manual}.
     *
     * This errorhandling based on sqlite_exec is not yet implemented.
     *
     * @access public
     */
    function DB_sqlite()
    {
        $this->DB_common();
    }

    // }}}
    // {{{ connect()

    /**
     * Connect to a database represented by a file.
     *
     * @param $dsn the data source name; the file is taken as
     *        database; "sqlite://root:@host/test.db?mode=0644"
     * @param $persistent (optional) whether the connection should
     *        be persistent
     * @access public
     * @return int DB_OK on success, a DB error on failure
     */
    function connect($dsninfo, $persistent = false)
    {
        if (!DB::assertExtension('sqlite')) {
            return $this->raiseError(DB_ERROR_EXTENSION_NOT_FOUND);
        }

        $this->dsn = $dsninfo;
        if ($dsninfo['dbsyntax']) {
            $this->dbsyntax = $dsninfo['dbsyntax'];
        }

        if ($dsninfo['database']) {
            if (!file_exists($dsninfo['database'])) {
                if (!touch($dsninfo['database'])) {
                    return $this->sqliteRaiseError(DB_ERROR_NOT_FOUND);
                }
                if (!isset($dsninfo['mode']) ||
                    !is_numeric($dsninfo['mode']))
                {
                    $mode = 0644;
                } else {
                    $mode = octdec($dsninfo['mode']);
                }
                if (!chmod($dsninfo['database'], $mode)) {
                    return $this->sqliteRaiseError(DB_ERROR_NOT_FOUND);
                }
                if (!file_exists($dsninfo['database'])) {
                    return $this->sqliteRaiseError(DB_ERROR_NOT_FOUND);
                }
            }
            if (!is_file($dsninfo['database'])) {
                return $this->sqliteRaiseError(DB_ERROR_INVALID);
            }
            if (!is_readable($dsninfo['database'])) {
                return $this->sqliteRaiseError(DB_ERROR_ACCESS_VIOLATION);
            }
        } else {
            return $this->sqliteRaiseError(DB_ERROR_ACCESS_VIOLATION);
        }

        $connect_function = $persistent ? 'sqlite_popen' : 'sqlite_open';

        // track_errors must remain on for simpleQuery()
        ini_set('track_errors', 1);
        $php_errormsg = '';

        if (!($conn = @$connect_function($dsninfo['database']))) {
            if (empty($php_errormsg)) {
                return $this->sqliteRaiseError(DB_ERROR_NODBSELECTED);
            } else {
                return $this->raiseError(DB_ERROR_NODBSELECTED, null,
                                         null, null, $php_errormsg);
            }
        }

        $this->connection = $conn;
        return DB_OK;
    }

    // }}}
    // {{{ disconnect()

    /**
     * Log out and disconnect from the database.
     *
     * @access public
     * @return bool true on success, false if not connected.
     * @todo fix return values
     */
    function disconnect()
    {
        $ret = @sqlite_close($this->connection);
        $this->connection = null;
        return $ret;
    }

    // }}}
    // {{{ simpleQuery()

    /**
     * Send a query to SQLite and returns the results as a SQLite resource
     * identifier.
     *
     * @param the SQL query
     * @access public
     * @return mixed returns a valid SQLite result for successful SELECT
     * queries, DB_OK for other successful queries. A DB error is
     * returned on failure.
     */
    function simpleQuery($query)
    {
        $ismanip = DB::isManip($query);
        $this->last_query = $query;
        $query = $this->_modifyQuery($query);

        if (!ini_get('track_errors')) {
            // leave it on, since will need it on every time.
            ini_set('track_errors', 1);
        }
        $php_errormsg = '';

        $result = @sqlite_query($query, $this->connection);
        $this->_lasterror = $php_errormsg ? $php_errormsg : '';

        $this->result = $result;
        if (!$this->result) {
            return $this->sqliteRaiseError(null);
        }

        /* sqlite_query() seems to allways return a resource */
        /* so cant use that. Using $ismanip instead          */
        if (!$ismanip) {
            $numRows = $this->numRows($result);

            /* if numRows() returned PEAR_Error */
            if (is_object($numRows)) {
                return $numRows;
            }
            return $result;
        }
        return DB_OK;
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal sqlite result pointer to the next available result.
     *
     * @param a valid sqlite result resource
     * @access public
     * @return true if a result is available otherwise return false
     */
    function nextResult($result)
    {
        return false;
    }

    // }}}
    // {{{ fetchInto()

    /**
     * Fetch a row and insert the data into an existing array.
     *
     * Formating of the array and the data therein are configurable.
     * See DB_result::fetchInto() for more information.
     *
     * @param resource $result    query result identifier
     * @param array    $arr       (reference) array where data from the row
     *                            should be placed
     * @param int      $fetchmode how the resulting array should be indexed
     * @param int      $rownum    the row number to fetch
     *
     * @return mixed DB_OK on success, null when end of result set is
     *               reached or on failure
     *
     * @see DB_result::fetchInto()
     * @access private
     */
    function fetchInto($result, &$arr, $fetchmode, $rownum=null)
    {
        if ($rownum !== null) {
            if (!@sqlite_seek($this->result, $rownum)) {
                return null;
            }
        }
        if ($fetchmode & DB_FETCHMODE_ASSOC) {
            $arr = @sqlite_fetch_array($result, SQLITE_ASSOC);
            if ($this->options['portability'] & DB_PORTABILITY_LOWERCASE && $arr) {
                $arr = array_change_key_case($arr, CASE_LOWER);
            }
        } else {
            $arr = @sqlite_fetch_array($result, SQLITE_NUM);
        }
        if (!$arr) {
            /* See: http://bugs.php.net/bug.php?id=22328 */
            return null;
        }
        if ($this->options['portability'] & DB_PORTABILITY_RTRIM) {
            /*
             * Even though this DBMS already trims output, we do this because
             * a field might have intentional whitespace at the end that
             * gets removed by DB_PORTABILITY_RTRIM under another driver.
             */
            $this->_rtrimArrayValues($arr);
        }
        if ($this->options['portability'] & DB_PORTABILITY_NULL_TO_EMPTY) {
            $this->_convertNullArrayValuesToEmpty($arr);
        }
        return DB_OK;
    }

    // }}}
    // {{{ freeResult()

    /**
     * Free the internal resources associated with $result.
     *
     * @param $result SQLite result identifier
     * @access public
     * @return bool true on success, false if $result is invalid
     */
    function freeResult(&$result)
    {
        // XXX No native free?
        if (!is_resource($result)) {
            return false;
        }
        $result = null;
        return true;
    }

    // }}}
    // {{{ numCols()

    /**
     * Gets the number of columns in a result set.
     *
     * @return number of columns in a result set
     */
    function numCols($result)
    {
        $cols = @sqlite_num_fields($result);
        if (!$cols) {
            return $this->sqliteRaiseError();
        }
        return $cols;
    }

    // }}}
    // {{{ numRows()

    /**
     * Gets the number of rows affected by a query.
     *
     * @return number of rows affected by the last query
     */
    function numRows($result)
    {
        $rows = @sqlite_num_rows($result);
        if (!is_integer($rows)) {
            return $this->raiseError();
        }
        return $rows;
    }

    // }}}
    // {{{ affected()

    /**
     * Gets the number of rows affected by a query.
     *
     * @return number of rows affected by the last query
     */
    function affectedRows()
    {
        return @sqlite_changes($this->connection);
    }

    // }}}
    // {{{ errorNative()

    /**
     * Get the native error string of the last error (if any) that
     * occured on the current connection.
     *
     * This is used to retrieve more meaningfull error messages DB_pgsql
     * way since sqlite_last_error() does not provide adequate info.
     *
     * @return string native SQLite error message
     */
    function errorNative()
    {
        return($this->_lasterror);
    }

    // }}}
    // {{{ errorCode()

    /**
     * Determine PEAR::DB error code from the database's text error message.
     *
     * @param  string  $errormsg  error message returned from the database
     * @return integer  an error number from a DB error constant
     */
    function errorCode($errormsg)
    {
        static $error_regexps;
        if (!isset($error_regexps)) {
            $error_regexps = array(
                '/^no such table:/' => DB_ERROR_NOSUCHTABLE,
                '/^no such index:/' => DB_ERROR_NOT_FOUND,
                '/^(table|index) .* already exists$/' => DB_ERROR_ALREADY_EXISTS,
                '/PRIMARY KEY must be unique/i' => DB_ERROR_CONSTRAINT,
                '/is not unique/' => DB_ERROR_CONSTRAINT,
                '/uniqueness constraint failed/' => DB_ERROR_CONSTRAINT,
                '/may not be NULL/' => DB_ERROR_CONSTRAINT_NOT_NULL,
                '/^no such column:/' => DB_ERROR_NOSUCHFIELD,
                '/^near ".*": syntax error$/' => DB_ERROR_SYNTAX
            );
        }
        foreach ($error_regexps as $regexp => $code) {
            if (preg_match($regexp, $errormsg)) {
                return $code;
            }
        }
        // Fall back to DB_ERROR if there was no mapping.
        return DB_ERROR;
    }

    // }}}
    // {{{ dropSequence()

    /**
     * Deletes a sequence
     *
     * @param string $seq_name  name of the sequence to be deleted
     *
     * @return int  DB_OK on success.  DB_Error if problems.
     *
     * @internal
     * @see DB_common::dropSequence()
     * @access public
     */
    function dropSequence($seq_name)
    {
        $seqname = $this->getSequenceName($seq_name);
        return $this->query("DROP TABLE $seqname");
    }

    /**
     * Creates a new sequence
     *
     * @param string $seq_name  name of the new sequence
     *
     * @return int  DB_OK on success.  A DB_Error object is returned if
     *              problems arise.
     *
     * @internal
     * @see DB_common::createSequence()
     * @access public
     */
    function createSequence($seq_name)
    {
        $seqname = $this->getSequenceName($seq_name);
        $query   = 'CREATE TABLE ' . $seqname .
                   ' (id INTEGER UNSIGNED PRIMARY KEY) ';
        $result  = $this->query($query);
        if (DB::isError($result)) {
            return($result);
        }
        $query   = "CREATE TRIGGER ${seqname}_cleanup AFTER INSERT ON $seqname
                    BEGIN
                        DELETE FROM $seqname WHERE id<LAST_INSERT_ROWID();
                    END ";
        $result  = $this->query($query);
        if (DB::isError($result)) {
            return($result);
        }
    }

    // }}}
    // {{{ nextId()

    /**
     * Returns the next free id in a sequence
     *
     * @param string  $seq_name  name of the sequence
     * @param boolean $ondemand  when true, the seqence is automatically
     *                           created if it does not exist
     *
     * @return int  the next id number in the sequence.  DB_Error if problem.
     *
     * @internal
     * @see DB_common::nextID()
     * @access public
     */
    function nextId($seq_name, $ondemand = true)
    {
        $seqname = $this->getSequenceName($seq_name);

        do {
            $repeat = 0;
            $this->pushErrorHandling(PEAR_ERROR_RETURN);
            $result = $this->query("INSERT INTO $seqname (id) VALUES (NULL)");
            $this->popErrorHandling();
            if ($result === DB_OK) {
                $id = @sqlite_last_insert_rowid($this->connection);
                if ($id != 0) {
                    return $id;
                }
            } elseif ($ondemand && DB::isError($result) &&
                      $result->getCode() == DB_ERROR_NOSUCHTABLE)
            {
                $result = $this->createSequence($seq_name);
                if (DB::isError($result)) {
                    return $this->raiseError($result);
                } else {
                    $repeat = 1;
                }
            }
        } while ($repeat);

        return $this->raiseError($result);
    }

    // }}}
    // {{{ tableInfo()

    /**
     * Returns information about a table.
     *
     * @param string         $result  a string containing the name of a table
     * @param int            $mode    a valid tableInfo mode
     * @return array  an associative array with the information requested
     *                or an error object if something is wrong
     * @access public
     * @internal
     * @see DB_common::tableInfo()
     * @since Method available since Release 1.7.0
     */
    function tableInfo($result, $mode = null)
    {
        if (isset($result->result)) {
            /*
             * Probably received a result object.
             * Extract the result resource identifier.
             */
            $this->last_query = '';
            return $this->raiseError(DB_ERROR_NOT_CAPABLE, null, null, null,
                                     'This DBMS can not obtain tableInfo' .
                                     ' from result sets');
        } elseif (is_string($result)) {
            /*
             * Probably received a table name.
             * Create a result resource identifier.
             */
            $id = @sqlite_array_query($this->connection,
                                      "PRAGMA table_info('$result');",
                                      SQLITE_ASSOC);
            $got_string = true;
        } else {
            /*
             * Probably received a result resource identifier.
             * Copy it.
             * Deprecated.  Here for compatibility only.
             */
            $this->last_query = '';
            return $this->raiseError(DB_ERROR_NOT_CAPABLE, null, null, null,
                                     'This DBMS can not obtain tableInfo' .
                                     ' from result sets');
        }

        if ($this->options['portability'] & DB_PORTABILITY_LOWERCASE) {
            $case_func = 'strtolower';
        } else {
            $case_func = 'strval';
        }

        $count = count($id);

        // made this IF due to performance (one if is faster than $count if's)
        if (!$mode) {
            // partial
            for ($i=0; $i<$count; $i++) {
                $res[$i]['table'] = $case_func($result);
                $res[$i]['name']  = $case_func($id[$i]['name']);
                if (strpos($id[$i]['type'], '(') !== false) {
                    $bits = explode('(', $id[$i]['type']);
                    $res[$i]['type'] = $bits[0];
                    $res[$i]['len'] = rtrim($bits[1],')');
                } else {
                    $res[$i]['type'] = $id[$i]['type'];
                    $res[$i]['len'] = 0;
                }

                $res[$i]['flags'] = '';
                if ($id[$i]['pk']) {
                    $res[$i]['flags'] .= 'primary_key ';
                }
                if ($id[$i]['notnull']) {
                    $res[$i]['flags'] .= 'not_null ';
                }
                if ($id[$i]['dflt_value'] !== null) {
                    $res[$i]['flags'] .= 'default_'
                                      . rawurlencode($id[$i]['dflt_value']);
                }
                $res[$i]['flags'] = trim($res[$i]['flags']);
            }

        } else {
            // full
            $res['num_fields'] = $count;

            for ($i=0; $i<$count; $i++) {
                $res[$i]['table'] = $case_func($result);
                $res[$i]['name']  = $case_func($id[$i]['name']);
                if (strpos($id[$i]['type'], '(') !== false) {
                    $bits = explode('(', $id[$i]['type']);
                    $res[$i]['type'] = $bits[0];
                    $res[$i]['len'] = rtrim($bits[1],')');
                } else {
                    $res[$i]['type'] = $id[$i]['type'];
                    $res[$i]['len'] = 0;
                }

                $res[$i]['flags'] = '';
                if ($id[$i]['pk']) {
                    $res[$i]['flags'] .= 'primary_key ';
                }
                if ($id[$i]['notnull']) {
                    $res[$i]['flags'] .= 'not_null ';
                }
                if ($id[$i]['dflt_value'] !== null) {
                    $res[$i]['flags'] .= 'default_'
                                      . rawurlencode($id[$i]['dflt_value']);
                }
                $res[$i]['flags'] = trim($res[$i]['flags']);

                if ($mode & DB_TABLEINFO_ORDER) {
                    $res['order'][$res[$i]['name']] = $i;
                }
                if ($mode & DB_TABLEINFO_ORDERTABLE) {
                    $res['ordertable'][$res[$i]['table']][$res[$i]['name']] = $i;
                }
            }
        }

        return $res;
    }

    // }}}
    // {{{ getSpecialQuery()

    /**
     * Returns the query needed to get some backend info.
     *
     * Refer to the online manual at http://sqlite.org/sqlite.html.
     *
     * @param string $type What kind of info you want to retrieve
     * @return string The SQL query string
     */
    function getSpecialQuery($type, $args=array())
    {
        if (!is_array($args)) {
            return $this->raiseError('no key specified', null, null, null,
                                     'Argument has to be an array.');
        }

        switch (strtolower($type)) {
            case 'master':
                return 'SELECT * FROM sqlite_master;';
            case 'tables':
                return "SELECT name FROM sqlite_master WHERE type='table' "
                       . 'UNION ALL SELECT name FROM sqlite_temp_master '
                       . "WHERE type='table' ORDER BY name;";
            case 'schema':
                return 'SELECT sql FROM (SELECT * FROM sqlite_master UNION ALL '
                       . 'SELECT * FROM sqlite_temp_master) '
                       . "WHERE type!='meta' ORDER BY tbl_name, type DESC, name;";
            case 'schemax':
            case 'schema_x':
                /*
                 * Use like:
                 * $res = $db->query($db->getSpecialQuery('schema_x', array('table' => 'table3')));
                 */
                return 'SELECT sql FROM (SELECT * FROM sqlite_master UNION ALL '
                       . 'SELECT * FROM sqlite_temp_master) '
                       . "WHERE tbl_name LIKE '{$args['table']}' AND type!='meta' "
                       . 'ORDER BY type DESC, name;';
            case 'alter':
                /*
                 * SQLite does not support ALTER TABLE; this is a helper query
                 * to handle this. 'table' represents the table name, 'rows'
                 * the news rows to create, 'save' the row(s) to keep _with_
                 * the data.
                 *
                 * Use like:
                 * $args = array(
                 *     'table' => $table,
                 *     'rows'  => "id INTEGER PRIMARY KEY, firstname TEXT, surname TEXT, datetime TEXT",
                 *     'save'  => "NULL, titel, content, datetime"
                 * );
                 * $res = $db->query( $db->getSpecialQuery('alter', $args));
                 */
                $rows = strtr($args['rows'], $this->keywords);

                $q = array(
                    'BEGIN TRANSACTION',
                    "CREATE TEMPORARY TABLE {$args['table']}_backup ({$args['rows']})",
                    "INSERT INTO {$args['table']}_backup SELECT {$args['save']} FROM {$args['table']}",
                    "DROP TABLE {$args['table']}",
                    "CREATE TABLE {$args['table']} ({$args['rows']})",
                    "INSERT INTO {$args['table']} SELECT {$rows} FROM {$args['table']}_backup",
                    "DROP TABLE {$args['table']}_backup",
                    'COMMIT',
                );

                // This is a dirty hack, since the above query will no get executed with a single
                // query call; so here the query method will be called directly and return a select instead.
                foreach ($q as $query) {
                    $this->query($query);
                }
                return "SELECT * FROM {$args['table']};";
            default:
                return null;
        }
    }

    // }}}
    // {{{ getDbFileStats()

    /**
     * Get the file stats for the current database.
     *
     * Possible arguments are dev, ino, mode, nlink, uid, gid, rdev, size,
     * atime, mtime, ctime, blksize, blocks or a numeric key between
     * 0 and 12.
     *
     * @param string $arg Array key for stats()
     * @return mixed array on an unspecified key, integer on a passed arg and
     * false at a stats error.
     */
    function getDbFileStats($arg = '')
    {
        $stats = stat($this->dsn['database']);
        if ($stats == false) {
            return false;
        }
        if (is_array($stats)) {
            if (is_numeric($arg)) {
                if (((int)$arg <= 12) & ((int)$arg >= 0)) {
                    return false;
                }
                return $stats[$arg ];
            }
            if (array_key_exists(trim($arg), $stats)) {
                return $stats[$arg ];
            }
        }
        return $stats;
    }

    // }}}
    // {{{ escapeSimple()

    /**
     * Escape a string according to the current DBMS's standards
     *
     * In SQLite, this makes things safe for inserts/updates, but may
     * cause problems when performing text comparisons against columns
     * containing binary data. See the
     * {@link http://php.net/sqlite_escape_string PHP manual} for more info.
     *
     * @param string $str  the string to be escaped
     *
     * @return string  the escaped string
     *
     * @since 1.6.1
     * @see DB_common::escapeSimple()
     * @internal
     */
    function escapeSimple($str) {
        return @sqlite_escape_string($str);
    }

    // }}}
    // {{{ modifyLimitQuery()

    function modifyLimitQuery($query, $from, $count, $params = array())
    {
        $query = $query . " LIMIT $count OFFSET $from";
        return $query;
    }

    // }}}
    // {{{ modifyQuery()

    /**
     * "DELETE FROM table" gives 0 affected rows in SQLite.
     *
     * This little hack lets you know how many rows were deleted.
     *
     * @param string $query The SQL query string
     * @return string The SQL query string
     */
    function _modifyQuery($query)
    {
        if ($this->options['portability'] & DB_PORTABILITY_DELETE_COUNT) {
            if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $query)) {
                $query = preg_replace('/^\s*DELETE\s+FROM\s+(\S+)\s*$/',
                                      'DELETE FROM \1 WHERE 1=1', $query);
            }
        }
        return $query;
    }

    // }}}
    // {{{ sqliteRaiseError()

    /**
     * Gather information about an error, then use that info to create a
     * DB error object and finally return that object.
     *
     * @param  integer  $errno  PEAR error number (usually a DB constant) if
     *                          manually raising an error
     * @return object  DB error object
     * @see errorNative()
     * @see errorCode()
     * @see DB_common::raiseError()
     */
    function sqliteRaiseError($errno = null)
    {
        $native = $this->errorNative();
        if ($errno === null) {
            $errno = $this->errorCode($native);
        }

        $errorcode = @sqlite_last_error($this->connection);
        $userinfo = "$errorcode ** $this->last_query";

        return $this->raiseError($errno, null, null, $userinfo, $native);
    }

    // }}}
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 */

?>
