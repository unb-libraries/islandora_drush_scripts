<?php

/*
 * This file should be put in the islandora modules plugins directory
 * typical usage: drush -u 1 islandora_purge_pids path_to_query.txt TRUE
 * the above would give you an interactive purge
 * if the last parameter is not TRUE then all the pids in the list will be purged without prompting
 * purging an object cannot be undone so use wisely (you have been warned)
 *
 * This script was taken and modified from an original script by UPEI Robertson
 * Library / Paul Pound.
 *
 * https://github.com/roblib/scripts/blob/master/drush/drupal7/islandora_purge_pids.drush.inc
 */



function islandora_purge_collection_members_drush_command() {
  $items = array();

  $items['islandora_purge_collection_members'] = array(
    'description' => "deletes all objects in a collection.  Please use with caution as purged objects are unrecoverable!  
      You will have to use the drush -u switch or you may not have permission to purge some objects.",
    'arguments' => array(
      'collection_pid' => 'The pid of the collection object',
      'interactive' => 'if TRUE then you will be asked to confirm the purge action for each object'
    ),
    'examples' => array(
      'drush islandora_purge_collection_memberss parent:pid TRUE',
    ),
    'aliases' => array('islandorapc'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN, // we can pass in users id on the command line using drush -u.
  );

  return $items;
}

//drush hook
function drush_islandora_purge_collection_members($collection_pid, $interactive) {
  drush_print('Current working directory ' . getcwd());
  if (!isset($collection_pid)) {
    drush_print("No collection PID specified");
    return;
  }
  islandora_purge_collection_members_doAction($collection_pid, $interactive);
}

//just a function
function islandora_purge_collection_members_doAction($collection_pid, $interactive) {
  global $user;
  $tuque = islandora_get_tuque_connection($user);
  $repository = $tuque->repository;

  $query = <<<EOT
select \$object from <#ri>
where \$object <fedora-rels-ext:isMemberOfCollection> <info:fedora/$collection_pid>
EOT;

  $results = $repository->ri->itqlQuery($query);
  $objects = islandora_purge_collection_members_sparql_results_as_array($results);
  foreach ($objects as $object) {
    drush_print($object);
  }

  $num = count($objects);
  if (!drush_confirm(dt('are you sure you want to delete @num objects?', array('@num' => $num)))) {
    // was: return drush_set_error('CORE_SYNC_ABORT', 'Aborting.');
    drush_die('Aborting.');
  }
  if ($interactive == 'TRUE') {
    foreach ($objects as $pid) {
      if (drush_confirm(dt('purge @pid ?', array('@pid' => $pid)))) {
        $repository->purgeObject($pid);
      }
    }
  }
  else {
    foreach ($objects as $pid) {
      $repository->purgeObject($pid);
    }
  }
}

/**
 * returns an array of pids 
 * @todo pull this up into an api
 * @param SimpleXMLElement $content
 * @return array
 */
function islandora_purge_collection_members_sparql_results_as_array($results) {
  $resultsarray = array();
  foreach($results as $result){
    $resultsarray[] = $result['object']['value'];
  }
  return $resultsarray;
}
