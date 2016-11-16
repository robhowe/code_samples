<?php
/**
 * fdvegan_batch_process.php
 *
 * Batch process implementation for module fdvegan.
 * Handles large data imports that would otherwise timeout on a webpage.
 *
 * PHP version 5.6
 *
 * @category   Install
 * @package    fdvegan
 * @author     Rob Howe <rob@robhowe.com>
 * @copyright  2015-2016 Rob Howe
 * @license    This file is proprietary and subject to the terms defined in file LICENSE.txt
 * @version    Bitbucket via git: $Id$
 * @link       http://fivedegreevegan.aprojects.org
 * @since      version 0.5
 */

/**
 * The $batch can include the following values. Only 'operations'
 * and 'finished' are required, all others will be set to default values.
 *
 * @param operations
 *   An array of callbacks and arguments for the callbacks.
 *   There can be one callback called one time, one callback
 *   called repeatedly with different arguments, different
 *   callbacks with the same arguments, one callback with no
 *   arguments, etc. (Use an empty array if you want to pass
 *   no arguments.)
 *
 * @param finished
 *   A callback to be used when the batch finishes.
 *
 * @param title
 *   A title to be displayed to the end user when the batch starts.
 *
 * @param init_message
 *   An initial message to be displayed to the end user when the batch starts.
 *
 * @param progress_message
 *   A progress message for the end user. Placeholders are available.
 *   Placeholders note the progression by operation, i.e. if there are
 *   2 operations, the message will look like:
 *    'Processed 1 out of 2.'
 *    'Processed 2 out of 2.'
 *   Placeholders include:
 *     @current, @remaining, @total and @percentage
 *
 * @param error_message
 *   The error message that will be displayed to the end user if the batch
 *   fails.
 *
 * @param file
 *   Path to file containing the callbacks declared above. Always needed when
 *   the callbacks are not in a .module file.
 *
 */
function fdvegan_init_load_batch() {

    $options = array('HavingTmdbId' => TRUE);  // no sense in trying to load any actors not in TMDb
    $persons_collection = new fdvegan_PersonCollection($options);
    $persons_collection->loadPersonsArray();  // load every actor in our DB
    //  To test this batch load code without slamming the TMDb API,
    //  comment out the line above and uncomment the line below:
//    $persons_collection->loadPersonsArray(0,3);  // load only the first 3 actors, using (start, limit)


    $max_num = $persons_collection->count();
    fdvegan_Content::syslog('LOG_INFO', "Starting init-load batch for ({$max_num}) persons.");
    $operations = array();
    $loop = 1;
    foreach ($persons_collection as $person) {
        $operations[] = array('fdvegan_init_load_batch_process', array($loop++, $max_num, $person->personId));
    }

    $batch = array(
                   'operations' => $operations,
                   'finished' => 'fdvegan_init_load_batch_finished',
                   'title' => t('Executing initial database-load'),
                   'init_message' => t('Initial database-load batch is starting.'),
                   'progress_message' => t('Processed @current out of @total actors.'),
                   'error_message' => t('Initial database load process has encountered an error.'),
                   'file' => drupal_get_path('module', 'fdvegan') . '/fdvegan_batch_process.php',
    );
    batch_set($batch);

    // If this function was called from a form submit handler, stop here,
    // FAPI will handle calling batch_process().
    // If not called from a submit handler, add the following,
    // noting the url the user should be sent to once the batch
    // is finished.
    // IMPORTANT:
    // If you set a blank parameter, the batch_process() will cause an infinite loop
//  batch_process('node/1');
}


/**
 * Batch Operation Callback
 *
 * Each batch operation callback will iterate over and over until
 * $context['finished'] is set to 1. After each pass, batch.inc will
 * check its timer and see if it is time for a new http request,
 * i.e. when more than 1 minute has elapsed since the last request.
 * Note that $context['finished'] is set to 1 on entry - a single pass
 * operation is assumed by default.
 *
 * An entire batch that processes very quickly might only need a single
 * http request even if it iterates through the callback several times,
 * while slower processes might initiate a new http request on every
 * iteration of the callback.
 *
 * This means you should set your processing up to do in each iteration
 * only as much as you can do without a php timeout, then let batch.inc
 * decide if it needs to make a fresh http request.
 *
 * @param options1, options2
 *   If any arguments were sent to the operations callback, they
 *   will be the first arguments available to the callback.
 *
 * @param context
 *   $context is an array that will contain information about the
 *   status of the batch. The values in $context will retain their
 *   values as the batch progresses.
 *
 * @param $context['sandbox']
 *   Use the $context['sandbox'] rather than $_SESSION to store the
 *   information needed to track information between successive calls to
 *   the current operation. If you need to pass values to the next operation
 *   use $context['results'].
 *
 *   The values in the sandbox will be stored and updated in the database
 *   between http requests until the batch finishes processing. This will
 *   avoid problems if the user navigates away from the page before the
 *   batch finishes.
 *
 * @param $context['results']
 *   The array of results gathered so far by the batch processing. This
 *   array is highly useful for passing data between operations. After all
 *   operations have finished, these results may be referenced to display
 *   information to the end-user, such as how many total items were
 *   processed.
 *
 * @param $context['message']
 *   A text message displayed in the progress page.
 *
 * @param $context['finished']
 *   A float number between 0 and 1 informing the processing engine
 *   of the completion level for the operation.
 *
 *   1 (or no value explicitly set) means the operation is finished
 *   and the batch processing can continue to the next operation.
 *
 *   Batch API resets this to 1 each time the operation callback is called.
 */
function fdvegan_init_load_batch_process($current_num, $max, $person_id, &$context) {
    // Note - if this function outputs anything to stdout, it will mess up the
    // JSON data response expected, so ensure that fdvegan_Content::syslog() is
    // only set to log to a file (not stdout) if you want to enable debugging
    // in this batch module.
    fdvegan_Content::syslog('LOG_DEBUG', "BEGIN fdvegan_init_load_batch_process({$current_num},{$max},{$person_id}).");

    $options = array('PersonId' => $person_id);
    if (!isset($context['sandbox']['progress'])) {  // First time through the loop for this person
        $context['sandbox']['progress'] = 0;
        unset($context['sandbox']['credits']); // Drupal does not automatically reset 'sandbox' variables
        $options['RefreshFromTmdb'] = TRUE;
    }
    $progress = $context['sandbox']['progress']++;
    $person = new fdvegan_Person($options);
    if (empty($person)) {
        $context['success'] = false;
        $context['message'] = t("Error occurred while processing actor # {$current_num}, person_id={$person_id}");
        $context['results'][] = check_plain("Error processing actor # {$current_num}, person_id={$person_id}");
    } else {
        // Next, loop through all credits and load any movies from TMDb that are missing from our DB, and update our DB.


        // other crucial code snipped...


        $message = "Now processing actor # {$current_num} : {$person->getFullName()}";
        if ($progress) {
            $message .= ", movie # {$progress}";
        }
        $context['message'] = t($message);
        $context['results'][] = check_plain("{$current_num} vegan actors processed");
        $context['finished'] = $credits->count() ? $context['sandbox']['progress'] / ($credits->count() + 1) : 1;
        if ($context['finished'] === 1) {
            unset($context['sandbox']['progress']); // Drupal does not automatically reset 'sandbox' variables
        }
    }
    fdvegan_Content::syslog('LOG_DEBUG', "END fdvegan_init_load_batch_process({$current_num},{$max},{$person_id}) progress={$progress}.");
}


/**
 * Batch 'finished' callback
 */
function fdvegan_init_load_batch_finished($success, $results, $operations) {
    $message = '';
    if ($success) {
        // The last $results[] element contains our special last message to the user.
        $message = end($results);
        drupal_set_message($message);
    } else {
        // An error occurred.
        // $operations contains the operations that remained unprocessed.
        $error_operation = reset($operations);
        $message = t('An error occurred while processing %error_operation with arguments: @arguments', array('%error_operation' => $error_operation[0], '@arguments' => print_r($error_operation[1], TRUE)));
        drupal_set_message($message, 'error');
    }
    fdvegan_Content::syslog('LOG_INFO', "fdvegan_init_load_batch_finished() success={$success}, msg={$message}.");
}


// other batch-process code snipped...


