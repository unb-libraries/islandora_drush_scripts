<?php
/**
 * @file
 * Update ir:seniorReportCModel MODS records with useAndReproduction statement.
 *
 * Use:
 *   drush -u 1 scr update_senior_reports.php
 */

/**
 * MAIN
 *
 * Takes input from a plain text file, each line with a pid to update.
 */

$fedora_url='';
$fedora_user='';
$fedora_password='';

_islandora_bootstrap_drupal();
$connection = new RepositoryConnection($fedora_url, $fedora_user, $fedora_password);
$api = new FedoraApi($connection);
$cache = new SimpleCache();
$repository = new FedoraRepository($api, $cache);

$file = fopen("senior_reports_to_update.txt", "r");

while(!feof($file)){
  $line = fgets($file);
  $pid_to_load = trim($line);
  print $pid_to_load;

  $dsid_to_update = 'MODS';
  $fedora_object = islandora_object_load($pid_to_load);

  $XML_to_insert=<<<EOT
  <mods:accessCondition type="useAndReproduction">Not available for use outside of the University of New Brunswick</mods:accessCondition>
EOT;

  if ( in_array('ir:seniorReportCModel', $fedora_object->models) && !in_array($fedora_object->id, array('unbscholar:4712')) ) {
    if ($fedora_object[$dsid_to_update]) {
      $mods_datastream = $fedora_object[$dsid_to_update];
      $mods_datastream_content = $mods_datastream->content;
      if (strpos($mods_datastream_content, '<mods:accessCondition type="useAndReproduction"') === FALSE) {
        $sor_position = strpos($mods_datastream_content, '<mods:note type="statement of responsibility"');
        if ($sor_position) {
          $new_datastream_content = substr_replace($mods_datastream_content, $XML_to_insert, $sor_position, 0);
          print $new_datastream_content;
        }
        $mods_datastream->setContentFromString($new_datastream_content);
        $fedora_object->ingestDatastream($mods_datastream);
      }
      // $repository->ingestObject($fedora_object);
    }
  }

  time_nanosleep(0, 300000000);
}


/**
 * Boostraps Drupal and Islandora.
 */
function _islandora_bootstrap_drupal() {
  $drupal_core_path = DRUSH_DRUPAL_CORE;
  require_once $drupal_core_path . '/includes/bootstrap.inc';
  require_once $drupal_core_path . '/includes/common.inc';
  include_once $drupal_core_path . '/includes/unicode.inc';

  drupal_bootstrap(DRUPAL_BOOTSTRAP_SESSION);
  require_once $drupal_core_path . '/includes/install.inc';
  require_once $drupal_core_path . '/modules/system/system.install';

  include_once $drupal_core_path . '/includes/module.inc';
  drupal_load('module', 'islandora');
  include_once './sites/all/modules/islandora/includes/tuque.inc';
}
