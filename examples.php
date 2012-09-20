<?php

// Init mysql vars
$mysql_host = "localhost:3306";
$mysql_username = "root";
$mysql_database = "dolibarr33";
$mysql_password = '';

// Include and load the class
include_once(dirname(__FILE__).'/sqldemorestore.class.php');
$sqldemo = new SQLDemoRestore($mysql_host, $mysql_username, $mysql_password, $mysql_database);

// Automatically reset the database every 10 minutes
$rtncode = $sqldemo->resetAuto(dirname(__FILE__).'/test.sql', 600);
if ($rtncode) { // If the reset was successfully done, ...
    /* Restore files from a zip archive
    print('<br /> Now restoring files...');
    $sqldemo->flush();
    $sqldemo->restoreFiles(dirname(__FILE__).'/../myfiles.zip', dirname(__FILE__).'/thisfolder/', true);
    print('<br /> Done.');
    */
    die(); // stop printing the rest of the page if the database is/was being reset (a message and an automatic redirection will take place)
}

// Print a form to manually reset the database, limited to one reset every 2 minutes
$sqldemo->resetForm(dirname(__FILE__).'/test.sql', 120);

// Print last reset date/time
print('<br />Last demo reset: '.$sqldemo->showLastDate());

/* Example to restore the database manually
if ($sqldemo->restore(dirname(__FILE__).'/test.sql')) {
    print('Database successfully imported!');
} else {
    print('There were errors: '.implode($sqldemo->errors));
}
*/

/* Example to dump (save) the database
if ($sqldemo->dump(dirname(__FILE__).'/test2.sql')) {
    print('Database successfully exported!');
} else {
    print('There were errors: '.implode($sqldemo->errors));
}
*/

/* Example to restore files from a zip file
// Include and load the class
include_once(dirname(__FILE__).'/sqldemorestore.class.php');
$sqldemo = new SQLDemoRestore(); // sql connection infos are optional

if ($sqldemo->restoreFiles(dirname(__FILE__).'/../myfiles.zip', dirname(__FILE__).'/demofolder/', true)) {
    print 'All done!';
} else {
    print 'Errors occured: <br />'.implode('<br />', $sqldemo->errors);
}
*/

?>