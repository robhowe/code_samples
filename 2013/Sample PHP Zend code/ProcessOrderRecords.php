#!/usr/local/zend/bin/php
<?php

/*
 * order_provisioner - ProcessOrderRecords.php
 *
 * This script is run from a crontab once per day (at 3pm).
 * It reads all the current "order_add" commands from the
 * DB and processes them.
 * An aggregated data file is then sent to each vendor.
 *
 * NOTE! - When testing in development, you must setup
 *         your application.ini file to contain a
 *         email.vendor.debug_override & email.vendor.bcc
 *         to avoid sending live emails!
 */

require_once('Zend/Validate/EmailAddress.php');
require_once('Setup.php');
require_once('Functions.php');
require_once('LockFile.php');

define('APPNAME', 'order_add_provisioner');

// Define the timeout threshold for how long the script should run before
// triggering a fatal error, in minutes.
define('TIMEOUT_MINS_THRESHOLD', 60);


// Make sure that the script isn't already running.
// If not, signal that the script is running.
$lockFilename = sprintf(LockFile::DEFAULT_LOCKFILENAME_FORMAT, APPNAME);
if (!LockFile::lock($lockFilename)) {
    // Figure out how long the other instance of the script has been running
    // for.
    $instanceAge = LockFile::getAge('minutes');

    // Does the instance age meet or exceed our set threshold?
    if ($instanceAge >= TIMEOUT_MINS_THRESHOLD) {
        exit(1);
    } else {
        #echo "script is already running.\n";
        exit(0);
    }
}

// Open up the logging facility.
openlog(APPNAME, LOG_PID | LOG_ODELAY, LOG_LOCAL5);
syslog(LOG_DEBUG, '--start [' . APPNAME . ']--');


// Get the configuration.
$config = new Zend_Config_Ini(ROOT_DIR . '/application/configs/application.ini', APPLICATION_ENV);


// Connect to the database.
$DB = dbConnect($config->resources->db->params);

// Get a list of commands.
$commandList = getCommands($DB, 'order_add');

/* Iterate through the list of commands,
 * building an array of orders for each different
 * vendor (by vendor_email).
 */
$orders = array();
$emailValidator = new Zend_Validate_EmailAddress();
foreach ($commandList as $commandRow) {
    $command       = $commandRow['action'];
    $data          = decodeDataColumn($commandRow['data']);
    $data['cmdid'] = $commandRow['cmdid'];

    # Do validation
    if (!validateOrderData($data, $command)) {
        incrementCommandCount($DB, $data['cmdid']);
        continue;
    }

    // Command looks valid, so add it to the $orders array
    $orders[$data['vendor_email']][] = $data;
}


/* Now iterate through the list of vendors' orders,
 * build the .csv file, and email it out to the vendor(s).
 */
$fromEmail = '<some email addy>';
$toBcc = $config->email->vendor->bcc;

while ($vendorRecs = current($orders)) {
    $vendorEmail = key($orders);
    syslog(LOG_DEBUG, 'DEBUG: processing orders for vendor email: ' . $vendorEmail);

    // .csv file to be attached to the email
    $msgAttachment = 'Affiliate Name,Subscriber Name,Address,City,State,Zip,Phone,Email,Device,Peripheral';
    $msgAttachment .= "\r\n";
    $cmdidList = '';  // track cmdid's to delete if email is successful
    $cmdListPrefix = '';

    foreach ($vendorRecs as $userRow) {

        $msgAttachment .= getCSV(array(
                                       $userRow['affiliate'],
                                       $userRow['ship_name'],
                                       $userRow['ship_addrs'],
                                       $userRow['ship_city'],
                                       $userRow['ship_state'],
                                       $userRow['ship_zip'],
                                       $userRow['ship_phone'],
                                       $userRow['ship_email'],
                                       $userRow['device'],
                                       $userRow['peripheral']
        ));
        $cmdidList .= $cmdListPrefix . $userRow['cmdid'];
        $cmdListPrefix = ',';
    }


    syslog(LOG_DEBUG, 'DEBUG: processing orders for cmdids: ' . $cmdidList);
    $toEmail = $config->email->vendor->debug_override ? $config->email->vendor->debug_override : $vendorEmail;
    if ($emailValidator->isValid($toEmail)) {

        // Send email to vendor

        $msgSubject = "Our orders for you";

        $msgBody  = "<html><head><title>Orders</title>\r\n";

        $msgBody .= "<!-- trkids={$cmdidList} -->\r\n";
        $msgBody .= "</head><body>\r\n";
        if ($config->email->vendor->debug_override) {
            $msgBody .= "Dear Developer, rather than sending this email to {$vendorEmail}, it is being redirected to you:<br /><br />\r\n";
        }
        $msgBody .= "Dear device vendor,<br />\r\n";
        $msgBody .= "We are forwarding the following orders to you.<br /><br />\r\n";
        $msgBody .= "Please see attached .csv file.<br /><br />\r\n";

        $msgBody .= "<br />For any questions or concerns, please contact us at: {$fromEmail}<br />\r\n";
        $msgBody .= "Thank you.<br />\r\n";
        $msgBody .= "</body></html>\r\n";

        $sent = sendOrderEmail($config->email->method, $toEmail, $toBcc, $fromEmail, $msgSubject, $msgBody, $msgAttachment);
        if ($sent){
            // Mail was sent successfully
            syslog(LOG_INFO, 'OK: Orders sent to ' . $toEmail);
            eraseCommands($DB, $cmdidList);
        } else {
            // Mail failed to send
            syslog(LOG_ERR, 'ERROR: failed to send order email to ' . $toEmail);
            incrementCommandsCount($DB, $cmdidList);
        }

    } else {
        syslog(LOG_ERR, 'ERROR: Order vendor_email (' . $toEmail . ') invalid');
        incrementCommandsCount($DB, $cmdidList);
    }

    next($orders);
}


// Signal that the script has run successfully.
LockFile::unlock();
syslog(LOG_DEBUG, '--mark [' . APPNAME . ']--');
exit(0);
