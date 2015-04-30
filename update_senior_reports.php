<?php
/**
 * @file
 * Update ir:seniorReportCModel MODS records with useAndReproduction statement.
 *
 * Takes input from a plaintext file, each line with a pid to update.
 *
 * Use:
 *   drush -u 1 scr update_senior_reports.php
 *
 * Resource index query used to generate list:
 *   select $object from <#ri>
 *   where $object <fedora-model:hasModel> <info:fedora/ir:seniorReportCModel>
 */

$fedora_url = '';
$fedora_user = '';
$fedora_password = '';
$input_file = 'senior_reports_to_update.txt';
$pause_time_ns = 300000000;

$dsid_to_update = 'MODS';
$insert_point = '<mods:note type="statement of responsibility"';
$xml_to_insert = <<<EOT
  <mods:accessCondition type="useAndReproduction">Not available for use outside of the University of New Brunswick</mods:accessCondition>
EOT;
$cmodel_check = 'ir:seniorReportCModel';
$duplicate_insert_string_check = '<mods:accessCondition type="useAndReproduction"';

$excluded_pids = array(
  'unbscholar:4712',
);

_islandora_bootstrap_drupal();
$connection = new RepositoryConnection($fedora_url, $fedora_user, $fedora_password);
$api = new FedoraApi($connection);
$cache = new SimpleCache();
$repository = new FedoraRepository($api, $cache);

$file = fopen($input_file, 'r');

while (!feof($file)) {
  $line = fgets($file);
  $pid_to_load = trim($line);
  print $pid_to_load;
  $fedora_object = islandora_object_load($pid_to_load);

  if (in_array($cmodel_check, $fedora_object->models) && !in_array($fedora_object->id, $excluded_pids)) {
    if ($fedora_object[$dsid_to_update]) {
      $mods_datastream = $fedora_object[$dsid_to_update];
      $mods_datastream_content = $mods_datastream->content;
      if (strpos($mods_datastream_content, $duplicate_insert_string_check) === FALSE) {
        $insert_position = strpos($mods_datastream_content, $insert_point);
        if ($insert_position) {
          $new_datastream_content = substr_replace($mods_datastream_content, $xml_to_insert, $insert_position, 0);
          print $new_datastream_content;
        }
        $mods_datastream->setContentFromString($new_datastream_content);
        $fedora_object->ingestDatastream($mods_datastream);
      }
    }
  }
  time_nanosleep(0, $pause_time_ns);
}

/**
 * Helper : Bootstrap Drupal and Islandora.
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
