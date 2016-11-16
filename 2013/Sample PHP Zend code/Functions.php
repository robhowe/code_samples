<?php

/*
 * order_provisioner - Functions.php
 *
 * Library containing common functions
 * for Process*OrderRecords.php calls.
 */

require_once('Zend/Mail.php');

define('SID_ORDER', 183);  // Special ID for Order commands


/**
 * Validate an array of data for a order_* command.
 *
 * @param array $data  The associative array of data to validate.
 * @param string $command  The command type (order_add or order_senduserconf).
 * @return boolean  True if valid; false otherwise.
 */
function validateOrderData(&$data, $command) {
    $emailValidator = new Zend_Validate_EmailAddress();

    $data['userid']       = (array_key_exists('userid', $data) ? $data['userid'] : NULL);
    if (empty($data['userid'])) {
        syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (userid missing)');
        return false;
    }
    $data['domain']       = (array_key_exists('domain', $data) ? $data['domain'] : NULL);
    if (empty($data['domain'])) {
        syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (domain missing)');
        return false;
    }

    $data['full_userid'] = $data['userid'] . '@' . $data['domain'];
    $data['ship_name']   = (array_key_exists('ship_name', $data) ? $data['ship_name'] : '');
    $data['ship_addr1']  = (array_key_exists('ship_addr1', $data) ? $data['ship_addr1'] : '');
    $data['ship_addr2']  = (array_key_exists('ship_addr2', $data) ? $data['ship_addr2'] : '');
    $data['ship_addrs']  = $data['ship_addr1'];
    $data['ship_addrs'] .= !empty($data['ship_addr2']) ? ',    ' . $data['ship_addr2'] : '';
    $data['ship_city']   = (array_key_exists('ship_city', $data) ? $data['ship_city'] : '');
    $data['ship_state']  = (array_key_exists('ship_state', $data) ? $data['ship_state'] : '');
    $data['ship_zip']    = (array_key_exists('ship_zip', $data) ? $data['ship_zip'] : '');
    $data['ship_phone']  = (array_key_exists('ship_phone', $data) ? $data['ship_phone'] : '');
    $data['ship_email']  = (array_key_exists('ship_email', $data) ? $data['ship_email'] : '');
    if ($command == 'order_senduserconf') {
        if (!$emailValidator->isValid($data['ship_email'])) {
            syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (ship_email \'' . $data['ship_email'] . '\' invalid)');
            return false;
        }
    }

    $data['device']     = (array_key_exists('device', $data) ? strtolower($data['device']) : NULL);
    if ($data['device'] == NULL) {
        syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (device missing)');
        return false;
    }
    if ($data['device'] !== 'yes') {
        syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (device not "yes")');
        return false;
    }
    $data['device']       = 'standard';
    $data['peripheral']   = (array_key_exists('peripheral', $data) ? strtolower($data['peripheral']) : NULL);
    $data['vendor_email'] = (array_key_exists('vendor_email', $data) ? $data['vendor_email'] : NULL);
    if ($command == 'order_add') {
        if ($data['vendor_email'] == NULL) {
            syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (vendor_email missing)');
            return false;
        }
        if (!$emailValidator->isValid($data['vendor_email'])) {
            syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (vendor_email ' . $data['vendor_email'] . ' invalid)');
            return false;
        }
    }

    $data['affiliate']    = (array_key_exists('affiliate', $data) ? $data['affiliate'] : NULL);
    if (empty($data['affiliate'])) {
        syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (affiliate missing)');
        return false;
    }

    $data['order_conf']   = (array_key_exists('order_conf', $data) ? $data['order_conf'] : NULL);
    $data['order_date']   = (array_key_exists('order_date', $data) ? $data['order_date'] : NULL);
    $data['tracking_num'] = (array_key_exists('tracking_num', $data) ? $data['tracking_num'] : NULL);
    if ($command == 'order_senduserconf') {
        if (empty($data['order_conf']) &&
            empty($data['order_date']) &&
            empty($data['tracking_num']) ) {
            syslog(LOG_WARNING, 'WARNING: invalid ' . $command . ' command data (all 3 order fields missing)');
            return false;
        }
    }

    return true;
}


/**
 * Send an email to the vendor or end-user.
 *
 * @param string $method  The send method; either 'Zend' or 'PHP mail' (default).
 * @param string $to  The recipient's email address.
 * @param string $bcc  A valid email address to BCC to.
 * @param string $from  A valid email address to send from.
 * @param string $subject  The email's subject.
 * @param string $message  The email's body content.
 * @param string $attachment  The email's file attachment contents (optional).
 * @return boolean  True if the send-email succeeded; false otherwise.
 */
function sendOrderEmail($method, $to, $bcc, $from, $subject, $message, $attachment=NULL) {
    $sent = true;

    if ($method == 'Zend') {
        // Send via Zend mail

        $mail = new Zend_Mail();
        $mail->addTo($to);
        $mail->addBcc($bcc);
        $mail->setFrom($from);
        $mail->setSubject($subject);
        $mail->setBodyHtml($message);
        if (!empty($attachment)) {
            // do attachment:
            $at = $mail->createAttachment($attachment);
            $at->type        = 'application/csv';
            $at->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
            $at->encoding    = Zend_Mime::ENCODING_8BIT;
            $date            = date('YmdHis', time());
            $at->filename    = "orders_{$date}.csv";
        }

        try {
            $mail->send();
        } catch (Exception $e){
            $sent = false;
        }
    } else {

        if (!empty($attachment)) {
            syslog(LOG_ERR, 'ERROR: Order PHP mail attachments not implemented yet');
            return false;

            // To implement attachments, see:  
            // http://webcheatsheet.com/PHP/send_email_text_html_attachment.php
        }

        // Send via PHP mail()

        $headers = 'From: ' . $from . "\r\n" .
                   'X-Mailer: PHP/' . phpversion() .
                   'MIME-Version: 1.0' . "\r\n" .
                   'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
                   'Bcc: ' . $bcc . "\r\n";

        $sent = mail($to, $subject, $message, $headers);
    }

    return $sent;
}


/**
 * Connect to the database.
 *
 * @param object $db_cfg  The database configuration object.
 * @return object  The database adapter.
 */
function dbConnect($db_cfg) {
    return new Zend_Db_Adapter_Pdo_Mysql($db_cfg);
}


/**
 * Get a list of commands that need to be processed.
 *
 * @param object $DB  The database adapter.
 * @param string $action  The specific actions to retrieve.
 * @return array  An array of rows from the `commands` table.
 */
function getCommands($DB, $action=NULL) {
    $bindArray = array(':sid' => SID_ORDER);
    $sql = <<<__SQL__
SELECT c.cmdid, c.did, d.domain, c.action,
       c.userid, c.count, c.data_blob AS data
  FROM commands c
  JOIN domain d ON c.did = d.did
 WHERE c.sid = :sid
   AND c.count < 10

__SQL__;
    if (!empty($action)) {
        $sql .= <<<__SQL__
   AND c.action = :action

__SQL__;
        $bindArray[':action'] = $action;
    }
    $sql .= <<<__SQL__
ORDER BY c.created ASC
__SQL__;

    $statement = $DB->query($sql, $bindArray);
    $commands = array();

    while (($row = $statement->fetch())) {
        $commands []= $row;
    }

    return $commands;
}


/**
 * Convert the data in the "data" column into an associative array.
 *
 * @param string $data  The data as a string.
 * @return array  The data as an associative array.
 */
function decodeDataColumn($data) {
    $retval = array();
    $parts = explode(';', $data);

    foreach ($parts as $part) {
        $split = explode('=', $part);
        $key = $split[0];
        if (count($split) > 1) {
            $value = $split[1];
        } else {
            $value = NULL;
        }
        $key   = urldecode($key);
        $value = urldecode($value);

        if ($key) {  // ignore any extra ';' on end
            $retval[$key] = $value;
        }
    }

    return $retval;
}


/**
 * Erase a command entry from the `commands` table.
 *
 * @param object $DB  The database adapter.
 * @param integer $cmdid  The command ID.
 * @return boolean  True if the erase succeeded; false otherwise.
 */
function eraseCommand($DB, $cmdid) {
    $sql = 'DELETE FROM commands WHERE cmdid = :cmdid';

    $statement = $DB->prepare($sql);
    return $statement->execute(array(':cmdid' => $cmdid));
}


/**
 * Erase command entries from the `commands` table.
 *
 * @param object $DB  The database adapter.
 * @param string $cmdidList  A list of command IDs.
 * @return boolean  True if the erases succeed; false otherwise.
 */
function eraseCommands($DB, $cmdidList) {
    $sql = "DELETE FROM commands WHERE cmdid in ({$cmdidList})";

    $statement = $DB->prepare($sql);
    return $statement->execute();
}


/**
 * Increment the column "count" by one for a command entry.
 *
 * @param object $DB  The database adapter.
 * @param integer $cmdid  The command ID.
 * @param integer $lastcount  The last count.
 * @return boolean  True if the increment succeeded; false otherwise.
 */
function incrementCommandCount($DB, $cmdid, $lastcount=NULL) {
    $bindArray = array(':cmdid' => $cmdid);
    $sql = <<<__SQL__
UPDATE commands

__SQL__;
    if ($lastcount == NULL) {
        $sql .= <<<__SQL__
   SET `count` = `count` + 1

__SQL__;
    } else {
        $sql .= <<<__SQL__
   SET `count` = :lastcount

__SQL__;
        $bindArray[':lastcount'] = $lastcount + 1;
    }
    $sql .= <<<__SQL__
 WHERE cmdid = :cmdid
__SQL__;

    $statement = $DB->prepare($sql);
    return $statement->execute($bindArray);
}


/**
 * Increment the column "count" by one for a list of command entries.
 *
 * @param object $DB  The database adapter.
 * @param string $cmdidList  The command IDs.
 * @return boolean  True if the increments succeeded; false otherwise.
 */
function incrementCommandsCount($DB, $cmdidList) {
    $sql = "UPDATE commands SET `count` = `count` + 1 WHERE cmdid in ({$cmdidList})";

    $statement = $DB->prepare($sql);
    return $statement->execute();
}


/**
 *  outputCSV creates a line of CSV and outputs it
 */
function outputCSV($array) {
    $fp = fopen('php://output', 'w');  // this file actually writes to php output
    fputcsv($fp, $array);
    fclose($fp);
}

/**
 *  getCSV creates a line of CSV and returns it.
 */
function getCSV($array) {
    ob_start();  // buffer the output ...
    outputCSV($array);
    return ob_get_clean();  // ... then return it as a string
}
