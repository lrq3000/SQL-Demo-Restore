<?php
/* Copyright (C) 2012-2013   Stephen Larroque <lrq3000@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the Lesser GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the Lesser GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *              \brief      Class to automatically manage a demo sql database
 *		\author		Stephen Larroque
 */

class SQLDemoRestore {
    public $mysql_host = 'localhost:3306';
    public $mysql_username = 'root';
    public $mysql_password = '';
    public $mysql_database = 'test';
    public $parameters_filename = 'sqldemorestore.txt';
    public $parameters = array();
    public $parameters_delimiter = ':';
    public $errors = array();
    public $mysql_conn = null;

    public function __construct($mysql_host=null, $mysql_username=null, $mysql_password=null, $mysql_database=null, $parameters_filename=null) {
        if (isset($mysql_host)) $this->mysql_host = $mysql_host;
        if (isset($mysql_username)) $this->mysql_username = $mysql_username;
        if (isset($mysql_password)) $this->mysql_password = $mysql_password;
        if (isset($mysql_database)) $this->mysql_database = $mysql_database;
        if (isset($parameters_filename)) $this->parameters_filename = $parameters_filename;

        if (isset($mysql_host) or isset($mysql_username) or isset($mysql_password) or isset($mysql_database)) { // only connect if at least one database parameter was supplied
            $this->mysql_conn = $this->connect($this->mysql_host, $this->mysql_username, $this->mysql_password, $this->mysql_database);
        }

        return true;
    }

    /** Connect to a MySQL database
     *
     *  @return resource  database connection handler
     */
    public function connect($mysql_host='localhost:3306', $mysql_username='root', $mysql_password='', $mysql_database='test') {
        // Connect to MySQL server
        $conn = mysql_pconnect($mysql_host, $mysql_username, $mysql_password) or $this->errors[] = 'Error connecting to MySQL server: ' . mysql_error();
        if (is_resource($conn)) {
            // Select database
            mysql_select_db($mysql_database) or $this->errors[] = 'Error selecting MySQL database: ' . mysql_error();

            return $conn;
        } else {
            return false;
        }
    }

    /** Close a MySQL connection to database
     *
     *  @return     bool       mysql_close return code (true/false)
     */
    public function close($conn = null) {
        if (!isset($conn) or !is_resource($conn)) {
            return mysql_close($this->mysql_conn);
        } else {
            return mysql_close($conn);
        }
    }

    /** Load the parameters from file (like the reset lastDate)
     * Note: format = key:value (where : is $this->parameters_delimiter)
     *
     *  @return     bool    true/false  false if file could not be found, or true if read
     */
    public function loadParametersFile() {
        if (!file_exists($this->parameters_filename)) return false;
        $lines = file($this->parameters_filename);
        foreach($lines as $line) {
            list($key, $value) = explode($this->parameters_delimiter,$line);
            $this->parameters[$key] = $value;
        }
        if (empty($this->parameters)) {
            return false;
        } else {
            return true;
        }
    }

    /** Get database's reset last date/time (either from cache or from file)
     *
     *  @return     null/timestamp      last date timestamp or null if empty
     */
    public function getLastDate($recursed=false) {
        // if lastDate is defined, we return it
        if (isset($this->parameters['lastDate'])) {
            return $this->parameters['lastDate'];
        // if lastDate is not defined but parameters are defined (loaded successfully), then we return null
        } elseif (!empty($this->parameters) and !isset($this->parameters['lastDate'])) {
            return null;
        // else parameters is not defined and we read the file and retry
        } else {
            if (!$recursed and $this->loadParametersFile()) { // avoid infinite recursion by checking the recursed flag
                return $this->getLastDate(true);
            } else { // file could not be found
                return null; // return null
            }
        }
    }

    /** Get the difference between current time and database's reset last date/time
     *
     *  @return     null/int    time difference
     */
    public function getLastDateDiff() {
        $old = $this->getLastDate();
        if (!isset($old)) { // if old date is not set, then we return a null difference (special value meaning it's the first time)
            return null;
        } else {
            return time() - $old;
        }
    }

    /** Check reset last date/time with current time difference against a threshold, and return true or false whether the difference is greater than the threshold
     *
     * @param   int     $diff       threshold
     *
     *  @return     bool        true/false      true if diff is greater than threshold, false otherwise
     */
    public function isLastDateValid($diff=null) {
        if ($diff > 0) {
            $rdiff = $this->getLastDateDiff();
            if (!isset($rdiff) or $rdiff > $diff) { // null = first time reset, or if the diff is bigger then it's also ok
                return true;
            } else {
                $this->errors[] = 'Difference time necessary for reset is not met. Please wait and retry to reset in '.ceil(($diff-$rdiff)/60).' minutes.';
                return false;
            }
        } else {
            return true;
        }
    }

    /** Set last date in parameters as the current time (unless another time is specified)
     *
     *  @param  timestamp   timestamp to set as the reset last date (by default: current time (now))
     *
     */
    public function setLastDate($timestamp=null) {
        if (!isset($timestamp)) $timestamp = time();
        $this->parameters['lastDate'] = $timestamp;
    }

    /** Save the parameters into file (like reset lastDate)
    * Note: format = key:value (where : is $this->parameters_delimiter)
    *
    * @return   int/false   false if there is an error, or the number of bytes written if OK
    */
    public function saveParametersFile() {
        $temp = array();
        foreach ($this->parameters as $key=>$value) {
            $temp[] = $key.$this->parameters_delimiter.$value;
        }

        $fh =fopen($this->parameters_filename, 'w');
        $rtncode = fwrite($fh, implode("\n", $temp));
        fclose($fh);

        return $rtncode;
    }

    /**
     *  Reset the database using a sql dump, and optionally force to wait a certain time before accepting the reset (to avoid abusers and spammers)
     *
     *  @param  $filename   string      filename for the sql file to be restored into database
     *  @param  $diff   int     seconds to wait before next reset is accepted
     *
     *  @return  int     0 if OK, 1 if KO
     */
    public function reset($filename, $diff=null) {
        $dflag = $this->isLastDateValid($diff);

        if ($dflag) {
            $this->setLastDate();
            $this->saveParametersFile();
            return $this->restore($filename);
        } else {
            return 1;
        }
    }

    /** Force flushing (printing) php output buffer
    * function by brudinie at googlemail dot com on phpmanual.net
    */
    public function flush (){
        echo(str_repeat(' ',1024)."\n"); // Browser workaround: force the output of a minimal amount of character (else the client's browser may not want to refresh the page with the data, even if the data is flushed and sent to the client!)
        // check that buffer is actually set before flushing
        if (ob_get_length()){
            @ob_flush();
            @flush();
            @ob_end_flush();
        }
        @ob_start();
    }

    /** Proceed to a reset and prints the messages (also automatically redirect afterwards)
     *
     *  @param  $filename   string      filename for the sql file to be restored into database
     *  @param  $diff   int     seconds to wait before next reset is accepted
     *  @param  $redirection    null/string   redirection url for the automatic redirection and link (null for default: same page)
     *  @param  $redirectiontime    null/int    time delay for the automatic redirection in seconds (can also be null to disable automatic redirection)
     *
     */
    public function resetWithMessages($filename, $diff=null, $redirection=null, $redirectiontime=5) {
        if (!$redirection) {
            $rurl = $_SERVER['PHP_SELF'];
        } else {
            $rurl = $redirection;
        }

        if (isset($redirectiontime)) {
            if (!headers_sent()) { // use standard HTTP redirection if headers are not already sent (the sqldemorestore calling stnippet is above any other printing)
                header( 'refresh: '.$redirectiontime.'; url='.$rurl );
            } else { // else headers are already sent, use Javascript to redirect
                print '<script type="text/javascript">
                setTimeout(function () {
                        window.location.href = "'.$rurl.'"; //will redirect to your blog page (an ex: blog.html)
                }, '.($redirectiontime*1000).');
                </script>';
            }
        }
        print 'Please wait while the database is being reset...';
        $this->flush(); // Force page refresh
        $rtncode = $this->reset($filename, $diff);
        if (!empty($this->errors)) {
            print '<br />Errors were encountered: '.implode(', ', $this->errors);
        } else {
            print '<br />Done!';
        }
        if (isset($redirectiontime)) print '<br />You will be automatically redirected in '.$redirectiontime.' seconds, or you can';
        print '<br /><a href="'.$rurl.'">Click here to go back to the demo page</a>';

        return $rtncode;
    }

    /** Display a reset link and automatically manage the whole reset process
     * Note: this is mainly to be used as an active mean for users to reset the demo
     *
     *  @param  $filename   string      filename for the sql file to be restored into database
     *  @param  $interval   int     seconds to wait before next reset is accepted
     *  @param  $linklabel  string  link label
     *  @param  $redirection    null/string   redirection url for the automatic redirection and link (null for default: same page)
     *  @param  $redirectiontime    null/int    time delay for the automatic redirection in seconds (can also be null to disable automatic redirection)
     *
     *  @return bool/null   null if nothing was done (only printing link), true if the reset was successful, false if problem
     *
     */
    public function resetForm($filename, $interval=null, $linklabel='Reset the demo', $redirection=null, $redirectiontime=5) {
        if (!isset($_REQUEST['reset']) or $_REQUEST['reset'] != 'ok') {
            print('<a href="'.$_SERVER['PHP_SELF'].'?reset=ok">'.$linklabel.'</a>');
            return null;
        } else {
            return $this->resetWithMessages($filename, $interval, $redirection, $redirectiontime);
        }
    }

    /** Automatically reset the database at the given interval ($diff) silently, but print messages if reset is being processed and then redirects automatically
     *
     *  @param  $filename   string      filename for the sql file to be restored into database
     *  @param  $interval   int     seconds to wait before next reset is accepted
     *  @param  $redirection    null/string   redirection url for the automatic redirection and link (null for default: same page)
     *  @param  $redirectiontime    null/int    time delay for the automatic redirection in seconds (can also be null to disable automatic redirection)
     *
     */
    public function resetAuto($filename, $interval=null, $redirection=null, $redirectiontime=5) {
        if (!isset($_REQUEST['reset']) and $_REQUEST['reset'] !== 'ok') { // check that we didn't call for a manual reset on the same calling script
            if ($this->isLastDateValid($interval)) {
                return $this->resetWithMessages($filename, $interval, $redirection, $redirectiontime);
            }
        }
    }

    /** Show the date/time of the last reset in a human format
     *
     *  @param  string  $format     date() format to print the date/time (can be left null)
     */
    public function showLastDate($format="j F Y H:i (T)") {
        $last_date = $this->getLastDate();
        if (!empty($last_date)) {
            return date($format, $last_date);
        } else {
            return null;
        }
    }

    /** Restore MySQL dump using PHP
     * (c) 2006 Daniel15
     * Last Update: 9th December 2006
     * Version: 0.2
     * Edited: Cleaned up the code a bit.
     *
     * Please feel free to use any part of this, but please give me some credit :-)
     *
     * @param   string  $filename   filename of the sql dump to restore in database
     *
     * @return  int     0 if OK, 1 if KO
     */
    public function restore($filename) {

        // Connect to MySQL server
        //$conn = mysql_connect($mysql_host, $mysql_username, $mysql_password) or die('Error connecting to MySQL server: ' . mysql_error());
        // Select database
        //mysql_select_db($mysql_database) or die('Error selecting MySQL database: ' . mysql_error());

        // Temporary variable, used to store current query
        $templine = '';
        $DELIMITER = ';';
        $D_LEN = 1;

        // Read in entire file
        if (file_exists($filename) === FALSE) {
            $this->errors[] = "ErrorFailedToOpenInDir: Failed to open file ".$filename;
            return 0;
        }
        $lines = file($filename);
        if(!$lines) {
            $this->errors[] = "ErrorFailedToOpenInDir: Empty sql dump ".$filename;
            return 0;
        }

        // Loop through each line
        foreach ($lines as $line_num => $line) {

            $line = trim($line);

            if(substr($line, 0, 9) == 'DELIMITER') { // change delimiter automatically if specified inside the sql file
                $DELIMITER = str_replace('DELIMITER ', '', $line);
                $D_LEN = strlen($DELIMITER);
                continue;
            }

            // Only continue if it's not a comment
            if(substr($line, 0, 2) != '--' and $line != '') { // Add this line to the current segment
                $templine .= $line;

                // If it has a DELIMITER at the end, it's the end of the query
                if(substr($line, -$D_LEN, $D_LEN) == $DELIMITER) {
                    mysql_query(rtrim($templine, $DELIMITER))
                    or $this->errors[] = 'Error performing query '.$templine.': '.mysql_error();
                    $templine = ''; // Reset temp variable to empty
                } else {
                    $templine .= "\n";
                }
            }
        }

        //mysql_close($conn);

        if (empty($this->errors)) return 0; else return 1;
    }

    /** MySQL Database Saving (dumping)
     *  Description: Backup the db OR just a table in pure php without mysqldump binary (does not require any exec permission)
     *	Author: David Walsh (http://davidwalsh.name/backup-mysql-database-php)
     *	Updated and enhanced by Stephen Larroque (lrq3000) and by the many commentators from the blog
     *	Note about foreign keys constraints: for Dolibarr, since there are a lot of constraints and when imported the tables will be inserted in the dumped order, not in constraints order, then we ABSOLUTELY need to use SET FOREIGN_KEY_CHECKS=0; when importing the sql dump.
     *	Note2: db2SQL by Howard Yeend can be an alternative, by using SHOW FIELDS FROM and SHOW KEYS FROM we could generate a more precise dump (eg: by getting the type of the field and then precisely outputting the right formatting - in quotes, numeric or null - instead of trying to guess like we are doing now).
     *
     *	@param	string	$outputfile		Output file name
     *	@param	string	$tables			Table name or '*' for all
     *	@return	int						0 if KO, 1 if OK
     */
    public function dump($outputfile, $tables='*', $option_disable_fk=true, $option_use_transaction=false, $option_sql_ignore=false, $option_delayed=false, $option_drop=true, $option_nolocks=false) {

        // Set to UTF-8
        mysql_query('SET NAMES utf8');
        mysql_query('SET CHARACTER SET utf8');

        // Get version
        $query = mysql_query('SELECT version() as version;');
        $row = mysql_fetch_assoc($query);
        $sqlversion = $row['version'];

        //get all of the tables
        if ($tables == '*')
        {
            $tables = array();
            $result = mysql_query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
            while($row = mysql_fetch_row($result))
            {
                $tables[] = $row[0];
            }
        }
        else
        {
            $tables = is_array($tables) ? $tables : explode(',',$tables);
        }

        //cycle through
        $handle = fopen($outputfile, 'w+');
        if (fwrite($handle, '') === FALSE)
        {
            $this->errors[] = "ErrorFailedToWriteInDir: Failed to open file ".$outputfile;
            return 0;
        }

        // Print headers and global mysql config vars
        $sqlhead = '';
        $sqlhead .= "--  SQL dump via php
--
-- Host: ".$this->mysql_host."    Database: ".$this->mysql_database."
-- ------------------------------------------------------
-- Server version	".$sqlversion."

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

";

        if ($option_disable_fk) $sqlhead .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sqlhead .= "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n";
        if ($option_use_transaction) $sqlhead .= "SET AUTOCOMMIT=0;\nSTART TRANSACTION;\n";

        fwrite($handle, $sqlhead);

        $ignore = '';
        if ($option_sql_ignore) $ignore = 'IGNORE ';
        $delayed = '';
        if ($option_delayed) $delayed = 'DELAYED ';

        // Process each table and print their definition + their datas
        foreach($tables as $table)
        {
            // Saving the table structure
            fwrite($handle, "\n--\n-- Table structure for table `".$table."`\n--\n");

            if ($option_drop) fwrite($handle,"DROP TABLE IF EXISTS `".$table."`;\n"); // Dropping table if exists prior to re create it
            //fwrite($handle,"/*!40101 SET @saved_cs_client     = @@character_set_client */;\n");
            //fwrite($handle,"/*!40101 SET character_set_client = utf8 */;\n");
            $resqldrop=mysql_query('SHOW CREATE TABLE '.$table);
            $row2 = mysql_fetch_row($resqldrop);
            fwrite($handle,$row2[1].";\n");
            //fwrite($handle,"/*!40101 SET character_set_client = @saved_cs_client */;\n\n");


            // Dumping the data (locking the table and disabling the keys check while doing the process)
            fwrite($handle, "\n--\n-- Dumping data for table `".$table."`\n--\n");
            if (!$option_nolocks) fwrite($handle, "LOCK TABLES `".$table."` WRITE;\n"); // Lock the table before inserting data (when the data will be imported back)
            if ($option_disable_fk) fwrite($handle, "ALTER TABLE `".$table."` DISABLE KEYS;\n");

            $sql='SELECT * FROM '.$table;
            $result = mysql_query($sql);
            $num_fields = mysql_num_rows($result);
            while($row = mysql_fetch_row($result)) {
                // For each row of data we print a line of INSERT
                fwrite($handle,'INSERT '.$delayed.$ignore.'INTO `'.$table.'` VALUES (');
                $columns = count($row);
                for($j=0; $j<$columns; $j++) {
                    // Processing each columns of the row to ensure that we correctly save the value (eg: add quotes for string - in fact we add quotes for everything, it's easier)
                    if ($row[$j] == null and !is_string($row[$j])) {
                        // IMPORTANT: if the field is NULL we set it NULL
                        $row[$j] = 'NULL';
                    } elseif(is_string($row[$j]) and $row[$j] == '') {
                        // if it's an empty string, we set it as an empty string
                        $row[$j] = "''";
                    } elseif(is_numeric($row[$j]) and !strcmp($row[$j], $row[$j]+0) ) { // test if it's a numeric type and the numeric version ($nb+0) == string version (eg: if we have 01, it's probably not a number but rather a string, else it would not have any leading 0)
                        // if it's a number, we return it as-is
                        $row[$j] = $row[$j];
                    } else { // else for all other cases we escape the value and put quotes around
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = preg_replace("#\n#", "\\n", $row[$j]);
                        $row[$j] = "'".$row[$j]."'";
                    }
                }
                fwrite($handle,implode(',', $row).");\n");
            }
            if ($option_disable_fk) fwrite($handle, "ALTER TABLE `".$table."` ENABLE KEYS;\n"); // Enabling back the keys/index checking
            if (!$option_nolocks) fwrite($handle, "UNLOCK TABLES;\n"); // Unlocking the table
            fwrite($handle,"\n\n\n");
        }

        /* Backup Procedure structure*/
        /*
         $result = mysql_query('SHOW PROCEDURE STATUS');
        if (mysql_num_rows($result) > 0)
        {
        while ($row = mysql_fetch_row($result)) { $procedures[] = $row[1]; }
        foreach($procedures as $proc)
        {
        fwrite($handle,"DELIMITER $$\n\n");
        fwrite($handle,"DROP PROCEDURE IF EXISTS '$name'.'$proc'$$\n");
        $resqlcreateproc=mysql_query("SHOW CREATE PROCEDURE '$proc'");
        $row2 = mysql_fetch_row($resqlcreateproc);
        fwrite($handle,"\n".$row2[2]."$$\n\n");
        fwrite($handle,"DELIMITER ;\n\n");
        }
        }
        */
        /* Backup Procedure structure*/

        // Write the footer (restore the previous database settings)
        $sqlfooter="\n\n";
        if ($option_use_transaction) $sqlfooter .= "COMMIT;\n";
        if ($option_disable_fk) $sqlfooter .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $sqlfooter.="\n\n-- Dump completed on ".date('Y-m-d G-i-s');
        fwrite($handle, $sqlfooter);

        fclose($handle);

        return 1;
    }

    /** Extract a zip into the specified path
     *
     */
    public function extractzip($filename, $dir) {
        require_once('pclzip.lib.php');

        $archive = new PclZip($filename);

        if ($archive->extract(PCLZIP_OPT_PATH, $dir) == 0) {
            $this->errors[] = "Error while extracting zip file: ".$archive->errorInfo(true);
            return false;
        } else {
            return true;
        }
    }

    /** Recursively remove a directory and all files inside
     * by longears at BLERG dot gmail dot com on phpmanual.net
    *
    *   @param  string  directory path
    */
    public function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    /** Restore a files directory from a zip file
     *
     *  @param  string  $zipfile    zip file path
     *  @param  string  $path   target path where to restore the files from the zip (please make sure the directory is created first)
     *  @param  bool    $priordelete    force deletion of the folder prior to restoring (else new files won't be deleted, and a few files may not be overwritten correctly)
     *
     *  @return   bool     true/false      true if OK, false if KO
     *
     */
    public function restoreFiles($zipfile, $path, $priordelete=false) {
        if (file_exists($path) and file_exists($zipfile)) {
            if ($priordelete) $this->rrmdir($path);
            if (!file_exists($path)) mkdir($path, 0755); // recreate folder if it was deleted
            return $this->extractzip($zipfile, $path);
        } else {
            if (!file_exists($zipfile)) $this->errors[] = 'Zip file does not exist.';
            if (!file_exists($path)) $this->errors[] = 'Target path does not exist, please create it prior to restore files inside.';
            return false;
        }
    }
}

//SQLRestore(dirname(__FILE__).'/dolibarr33.sql');

?>