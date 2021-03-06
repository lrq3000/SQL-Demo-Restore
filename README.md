﻿SQL DEMO RESTORE CLASS
======================

DESCRIPTION
-----------

SQL Demo Restore Class is a simple PHP >= 5.0 class to automatically reset a demo web application, by restoring a sql dump file into the database and by restoring files from a zip file.

It provides:

- Easy functions to restore and dump (save) a sql database into a dump file, in pure PHP and SQL, without the need to exec mysqldump binary or any third-party module.
- No dependencies.
- An interval to limit abuses of the reset functions.
- All-in-one functions to:
 * provide a link to users to reset the database, and the class will automatically manage the rest (checking interval limit, restoring the dump, printing messages to user to wait, redirecting him or provide a link to go back).
 * reset the database at regular interval in the background, without the need of a cronjob (the first user to load the page after the set interval will reset the database automatically).
 * restore files from a zip file (using the PclZip Library, pure php implementation and bundled with this software).

LICENSE
-------
This software is licensed under the Lesser GNU Public License version 3 or above (LGPLv3+).

This software uses the PclZip Library under the LGPLv2.1+

EXAMPLE
-------

### SQL reset example:
 
    // Init mysql vars
    $mysql_host = "localhost:3306";
    $mysql_username = "root";
    $mysql_database = "testdb";
    $mysql_password = '';

    // Include and load the class
    include_once(dirname(__FILE__).'/sqldemorestore.class.php');
    $sqldemo = new SQLDemoRestore($mysql_host, $mysql_username, $mysql_password, $mysql_database);

    // Automatically reset the database every 10 minutes
    $rtncode = $sqldemo->resetAuto(dirname(__FILE__).'/test.sql', 600);
    if ($rtncode) { // If the reset was successfully done, ...
        // Restore files from a zip archive
        // $sqldemo->restoreFiles(dirname(__FILE__).'/../myfiles.zip', dirname(__FILE__).'/demofolder/', true);
        die(); // stop printing the rest of the page if the database is/was being reset (a message and an automatic redirection will take place)
    }

    // Print a form to manually reset the database, limited to one reset every 2 minutes
    $sqldemo->resetForm(dirname(__FILE__).'/test.sql', 120);

    // Print last reset date/time
    print('<br />Last demo reset: '.$sqldemo->showLastDate());

### File restore example:
    // Include and load the class
    include_once(dirname(__FILE__).'/sqldemorestore.class.php');
    $sqldemo = new SQLDemoRestore(); // sql connection infos are optional

    if ($sqldemo->restoreFiles(dirname(__FILE__).'/../myfiles.zip', dirname(__FILE__).'/demofolder/', true)) {
        print 'All done!';
    } else {
        print 'Errors occured: <br />'.implode('<br />', $sqldemo->errors);
    }
    
You can find these examples and a few others inside the file examples.php

ADDITIONAL NOTES
----------------
This software uses the nice PclZip library by Vincent Blavet. You can easily update the zip library very simply by downloading a new release from the website:
http://www.phpconcept.net
