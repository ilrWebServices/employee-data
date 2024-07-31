<?php

/**
 * @file
 * Functions for pulling data from ldap and Activity Insight.
 * @todo Retrieve faculty leave from a Box file rather than storing it here which requires a redeployment when it changes
 *
 */
define('SETTINGS', [
  'ai_api_url' => 'https://webservices.digitalmeasures.com/login/service/v4',
  'ai_userid' => getenv('AI_USER'),
  'ai_pwd' => getenv('AI_PASS'),
  'ldap_start' => 'ou=people,o=cornell university,c=us',
  'ldap_filter' => '(|(uid=hkm38)(uid=lac24)(uid=dak275)(uid=jds13)(uid=pl82)(uid=ad838)(uid=cl672)(uid=mlc13)(uid=ldd3)(uid=mb2693)(uid=lrt4)(uid=sdm39)(uid=dca58)(uid=ajc22)(uid=kfh7)(uid=vmb2)(uid=hck2)(uid=cjm267)(uid=rss14)(uid=mfl55)(uid=bdd28)(cornelledudeptname1=LIBR - Catherwood*)(&(|(cornelledudeptname1=LIBR - Hospitality, Labor*)(cornelledudeptname1=LIBR - Management Library)(cornelledudeptname1=LIBR - ILR Catherwood Library))(cornelleducampusaddress=Ives Hall*))(cornelledudeptname1=IL-*)(cornelledudeptname1=E-*)(cornelledudeptname1=ILR*)(cornelledudeptname1=CAHRS))',
  'ldap_server' => 'directory.cornell.edu',
  'ldap_port' => '389',
  'ldap_user' => getenv('LDAP_USER'),
  'ldap_pwd' => getenv('LDAP_PASS'),
  'output_dir' => (getenv('OUTPUT_DIR') ?: 'output') . '/',
  'slack_webhook_url' => getenv('PROFILE_DATA_SLACK_WEBHOOK_URL') ?: FALSE,
]);

$ldap_attributes = array(
  'displayname',
  'cornelledupublishedemail',
  'cornelleducampusaddress',
  'cornelleducampusphone',
  'edupersonprincipalname',
  'cornelleduunivtitle1',
  'cornelleduwrkngtitle1',
  'cornelledutype',
  'cornelledudeptid1',
  'cornelledudeptname1',
  'uid',
  'sn',
  'givenname',
  'mailalternateaddress',
  'edupersonnickname',
  'cornelledulocaladdress',
);

define('LDAP_ATTRIBUTES', implode(',', $ldap_attributes));

function query_ai($uri) {
  $curl = curl_init();
  curl_setopt_array($curl, array( CURLOPT_URL => SETTINGS['ai_api_url'] . $uri
  , CURLOPT_USERPWD => SETTINGS['ai_userid'] . ':' . SETTINGS['ai_pwd']
  , CURLOPT_ENCODING => 'gzip'
  , CURLOPT_FOLLOWLOCATION => true
  , CURLOPT_POSTREDIR => true
  , CURLOPT_RETURNTRANSFER => true
  ));

  $responseData = curl_exec($curl);

  if (curl_errno($curl)) {
    $errorMessage = curl_error($curl);
    // TODO: Handle cURL error
  } else {
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  }
  curl_close($curl);
  return (object)array("responseData" => $responseData, "statusCode" => $statusCode);
}

function xslt_transform($xml, $xsl, $format='xml') {
  $inputdom =  new DomDocument();
  $inputdom->loadXML($xml);

  $proc = new XSLTProcessor();
  $proc->importStylesheet($xsl);
  $proc->setParameter(null, "", "");
  if ($format == 'xml') {
    return $proc->transformToXML($inputdom);
  } else if ($format == 'doc') {
    return $proc->transformToDoc($inputdom);
  }
}

function stripEmptyCDATA($xml) {
  return preg_replace('/<!\[CDATA\[(<ul class="[^"]+"><\/ul>)+\]\]>/i', '', $xml);
}

function doc_append(&$doc1, $doc2) {
  // iterate over 'item' elements of document 2
  $records = $doc2->getElementsByTagName('Record');
  for ($i = 0; $i < $records->length; $i ++) {
      $record = $records->item($i);

      // import/copy item from document 2 to document 1
      $temp = $doc1->importNode($record, true);

      // append imported item to document 1 'res' element
      $doc1->getElementsByTagName('Data')->item(0)->appendChild($temp);
  }
}

function get_ilr_profiles_transform_xsl($version='default') {
  $xsl = new DOMDocument();
  if ($version != 'default') {
    $xsl->load('alt-transform.xsl');
  } else {
    $xsl->load('digital-measures-faculty-public.xsl');
    $xpath = new DOMXPath($xsl);
    $ldap_file_element = $xpath->query("//xsl:variable[@name='ldap']");
    $ldap_file_element[0]->setAttribute('select', "document('" . SETTINGS['output_dir'] . "ldap.xml')");
  }
  return $xsl;
}

function get_ai_departments() {
  $URI = '/SchemaIndex/INDIVIDUAL-ACTIVITIES-IndustrialLaborRelations/DEPARTMENT';
  return query_ai($URI);
}

function get_ai_users() {
  $URI = '/User/INDIVIDUAL-ACTIVITIES-IndustrialLaborRelations';
  return query_ai($URI);
}

function get_ai_person($netid) {
  $entity_keys = '/PCI,NARRATIVE_INTERESTS,INTELLCONT,ADMIN,ADMIN_PERM,EDUCATION,OUTREACH_STATEMENT,PRESENT,AWARDHONOR';
  $URI = '/SchemaData/INDIVIDUAL-ACTIVITIES-IndustrialLaborRelations/USERNAME:' . strtolower($netid);
  $result = query_ai($URI . $entity_keys);
  // If not found, try with the netid in upper case. Some records in AI are in this state, and XPath is case-sensitive.
  if ( $result->statusCode != 200 ) {
    $URI = '/SchemaData/INDIVIDUAL-ACTIVITIES-IndustrialLaborRelations/USERNAME:' . strtoupper($netid);
    $result = query_ai($URI . $entity_keys);
  }
  return $result;
}

function write_all_people_to_file() {
  $users = simplexml_load_string(get_ai_users()->responseData);
  $first = true;
  foreach ($users->User as $user) {
    $person = get_ai_person($user->attributes()->username)->responseData;
    if ($first) {
      $person = preg_replace('/<\/Data>/', '', $person);
      file_put_contents(SETTINGS['output_dir'] . "all-people.xml", $person);
      $first = false;
    } else {
      $person = preg_replace('/<\/Data>/', '', $person);
      $person = preg_replace('/<Data [^>]+>/', '', $person);
      $person = preg_replace('/<\?xml [^>]+>/', '', $person);
      file_put_contents(SETTINGS['output_dir'] . "all-people.xml", $person, FILE_APPEND);
    }
  }
  file_put_contents(SETTINGS['output_dir'] . "all-people.xml", '</Data>', FILE_APPEND);
}

function get_ldap_info($filter, $attributes, $start) {
  $ds=ldap_connect(SETTINGS['ldap_server']);
  $ldapbind = ldap_bind($ds, SETTINGS['ldap_user'], SETTINGS['ldap_pwd']);

  if ($ds) {
    $sr=ldap_search($ds, $start, $filter, $attributes);
    $ret = ldap_get_entries($ds, $sr);
    ldap_close($ds);
      return $ret;
  } else {
    return array();
  }
}

function run_ldap_query($filter) {
  return get_ldap_info($filter, explode(',', LDAP_ATTRIBUTES), SETTINGS['ldap_start']);
}

function get_ilr_people_from_ldap() {
  return run_ldap_query(SETTINGS['ldap_filter']);
}

function get_faculty_leave() {
  $handle = fopen("faculty-leave.csv", "r");
  $first_line = true;
  $faculty_leave = Array();
  $leave = Array();
  if ($handle) {
      while (($line = fgets($handle)) !== false) {
        if (!$first_line) {
          array_push($faculty_leave, explode(',', $line));
        }
        $first_line = false;
      }
  }
  fclose($handle);

  foreach($faculty_leave as $faculty) {
    $leave[strtolower($faculty[0])] = Array("leave_start" => $faculty[6], "leave_end" => $faculty[7]);
  }
  return $leave;
}

function get_leave_for_one_faculty($faculty_leave_array, $netid) {
  if (array_key_exists($netid, $faculty_leave_array)) {
    $result = $faculty_leave_array[$netid];
  } else {
    $result = Array("leave_start" => '', "leave_end" => '');
  }
  return $result;
}

function ldap2xml($ldap) {
  $result = array();

  if (count($ldap)) {
    $whiteLabels = array();
    $whiteLabels['displayname'] = "ldap_display_name";
    $whiteLabels['cornelleducampusaddress'] = "ldap_campus_address";
    $whiteLabels['cornelleducampusphone'] = "ldap_campus_phone";
    $whiteLabels['cornelledupublishedemail'] = "ldap_email";
    $whiteLabels['edupersonprincipalname'] = "ldap_edupersonprincipalname";
    $whiteLabels['cornelleduunivtitle1'] = "ldap_working_title1";
    $whiteLabels['cornelleduwrkngtitle1'] = "ldap_working_title2";
    $whiteLabels['cornelledutype'] = "ldap_employee_type";
    $whiteLabels['cornelledudeptid1'] = "ldap_department";
    $whiteLabels['cornelledudeptname1'] = "ldap_department_name";
    $whiteLabels['uid'] = "ldap_uid";
    $whiteLabels['sn'] = "ldap_last_name";
    $whiteLabels['givenname'] = "ldap_first_name";
    $whiteLabels['mailalternateaddress'] = "ldap_mail_nickname";
    $whiteLabels['edupersonnickname'] = "ldap_nickname";
    $whiteLabels['cornelledulocaladdress'] = "ldap_local_address";

    $faculty_titles = array();
    $faculty_titles[] = 'Extension Associate Sr';
    $faculty_titles[] = 'Extension Associate';
    $faculty_titles[] = 'Lecturer Sr';
    $faculty_titles[] = 'Lecturer Visit';
    $faculty_titles[] = 'Lecturer';
    $faculty_titles[] = 'Prof Assoc';
    $faculty_titles[] = 'Prof Asst';
    $faculty_titles[] = 'Prof Emeritus';
    $faculty_titles[] = 'Prof Leading';
    $faculty_titles[] = 'Prof Visiting';
    $faculty_titles[] = 'Professor Visiting';
    $faculty_titles[] = 'Professor';
    $faculty_titles[] = 'Research Associate Sr';
    $faculty_titles[] = 'Research Associate';
    $faculty_titles[] = 'Scholar Visit';
    $faculty_titles[] = 'Research Professor';

    $temp_faculty = array('co283','igp2','lac24', 'lha1', 'gc32', 'ljf8', 'lsg3', 'vmb2', 'zen2', 'srt82', 'so44', 'jds13','lrs95', 'elg234', 'jn497', 'lhc62', 'mjb62', 'cjb39', 'nah36', 'ak839', 'kal238', 'ko259', 'mcs378', 'ws283', 'jt693', 'aaw43', 'mfl55','no232','kfh7');
    $deans = array('ajc22', 'ljb239', 'jeg68', 'rss14','mlc13','ldd3','lrt4');
    $faculty_leave = get_faculty_leave();

      $result[] = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    $result[] = "<Data dmd:date=\"2010-02-23\" xmlns=\"http://www.digitalmeasures.com/schema/data\" xmlns:dmd=\"http://www.digitalmeasures.com/schema/data-metadata\">";

    foreach($ldap AS $person) {
      if (is_array($person) && !empty($person['cornelledutype'][0]) && $person['cornelledutype'][0] != 'alumni') {
        $result[] = "\t<Record username=\"" . $person['uid'][0] . "\">";
        foreach (explode(',', LDAP_ATTRIBUTES) as $attr) {
          if (array_key_exists($attr, $person)) {
            for ($j=0; $j<count($person[$attr])-1; $j++) {
              $suffix = count($person[$attr]) > 2 ? $j + 1 : '';
              $thisVal = trim($person[$attr][$j]);
              if ($attr == 'edupersonprincipalname') {
                // Use of $person['edupersonprincipalname'] to honor preferred email is depricated
                // Instead, the value of the white-listed variable ldap_email is set to $person['cornelledupublishedemail']
                $thisVal = '';
              }
              if ($attr == 'cornelledudeptname1') {
                switch ($person['cornelledudeptname1'][$j]) {
                  case "Dean's Office":
                    $thisVal = "ILR Dean's Office";
                    break;

                  case 'ILR -  Human Resources':
                    $thisVal = "ILR - Human Resources";
                    break;

                  case 'ILR - Employment & Disability':
                    $thisVal = "ILR - Yang-Tan Institute on Employment and Disability";
                    break;

                  default:
                    break;
                }
              }
              if ($attr == 'cornelleduwrkngtitle1' && strpos($person['cornelleduwrkngtitle1'][0], 'Temp Serv') !== FALSE) {
                $thisVal = 'Staff';
              }
              if ($thisVal == '-') {
                $thisVal = '';
              }
              if (strlen($thisVal) > 0) {
                $result[] = "\t\t<$whiteLabels[$attr]" . "$suffix>" . htmlspecialchars($thisVal, ENT_QUOTES, "UTF-8") . "</$whiteLabels[$attr]" . "$suffix>";
              } else {
                $result[] = "\t\t<$whiteLabels[$attr]" . "$suffix/>";
              }
            }
          } else {
            $result[] = "\t\t<$whiteLabels[$attr]/>";
          }
        }
        if ($person['cornelledutype'][0] == 'faculty') {
          $profile_type = 'faculty';
        } elseif (in_array($person['uid'][0], $deans)) {
          $profile_type = 'faculty';
        } elseif (array_key_exists( 'cornelledudeptid1', $person) && $person['cornelledutype'][0] == 'academic' && strpos($person['cornelledudeptid1'][0], 'LIB')) {
          $profile_type = 'librarian';
        } elseif (array_key_exists( 'cornelleduunivtitle1', $person) && in_array($person['cornelleduunivtitle1'][0], $faculty_titles)) {
          $profile_type = 'faculty';
        } elseif (in_array($person['uid'][0], $temp_faculty)) {
          $profile_type = 'faculty';
        } else {
          $profile_type = 'staff';
        }
        $result[] = "\t\t<ldap_profile_type>{$profile_type}</ldap_profile_type>";

        $leave = get_leave_for_one_faculty($faculty_leave, $person['uid'][0]);
        $result[] = "\t\t<ldap_leave_start>{$leave['leave_start']}</ldap_leave_start>";
        $result[] = "\t\t<ldap_leave_end>{$leave['leave_end']}</ldap_leave_end>";

        $result[] = "\t</Record>";
      }
      }
      $result[] = "</Data>";
  }
  return join("\n", $result);
}

function new_empty_xml_file($filename) {
  return file_put_contents($filename
  , '<?xml version="1.0" encoding="UTF-8"?>
<Data xmlns="http://www.digitalmeasures.com/schema/data" xmlns:dmd="http://www.digitalmeasures.com/schema/data-metadata" dmd:date="2014-01-14">');
}

function add_log_event(&$log, $message) {
  $time = time();
  $elapsed_time = count($log) > 0 ? $time - $log[count($log) - 1]['time'] : 0;
  $log[] = array(
    'message' => $message,
    'time' => $time,
    'elapsed_time' => $elapsed_time,
  );
  return true;
}

function display_log($log) {
  $result = "";
  foreach ($log as $entry) {
    $result .= date('D j/n/Y', $entry['time']) . ' ' . date('H:i:s', $entry['time']) . ': ' .
      $entry['message'] .
      ($entry['elapsed_time'] > 0 ? " in ({$entry['elapsed_time']} seconds)\n" : "\n");
  }
  $total_time = $log[count($log)-1]['time'] - $log[0]['time'];
  $result .= "\nTotal execution time: {$total_time} seconds.\n";
  return $result;
}

// Write log results to file and also return as string
function log_results(&$job_log, $job_title) {
  $ip_tracking = !empty($_SERVER['REMOTE_ADDR']) ? "(requested from IP: {$_SERVER['REMOTE_ADDR']})" : '(from local CLI script execution)';
  $job_results = "Results of {$job_title} {$ip_tracking}:\n" . display_log($job_log);

  if (SETTINGS['slack_webhook_url']) {
    $data = [
      'blocks' => [
        [
          'type' => "header",
          'text' => [
            'type' => "plain_text",
            'text' => $job_title,
          ],
        ],
        [
          'type' => "section",
          'text' => [
            'type' => "mrkdwn",
            'text' => "```$job_results```",
          ],
        ],
      ],
    ];

    $context  = stream_context_create([
      'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode($data),
      ]
    ]);

    $result = file_get_contents(SETTINGS['slack_webhook_url'], false, $context);
  }

  return $job_results;
}

// Makes a file in an S3 bucket publicly readable.
function set_perms(&$aws_Client, $bucket, $file_name) {
  $aws_Client->putObjectAcl(array(
    'Bucket'     => $bucket,
    'Key'        => $file_name,
    'ACL'        => 'public-read'
  ));
}

// Deletes a file in an S3 bucket.
function delete_file($file_name) {
  if (file_exists($file_name)) {
    unlink($file_name);
  }
}

// Repalces a file in an S3 bucket with a new version.
function replace_file($file_name, $output) {
  delete_file($file_name);
  file_put_contents($file_name, $output);
  //set_perms($aws_Client, $aws_bucket, $file_name);
}

// Functions to write data files

// LDAP to file
function write_ldap_xml_to_file(&$job_log) {
  $ldap = get_ilr_people_from_ldap();
  replace_file(SETTINGS['output_dir'] . 'ldap.xml', ldap2xml($ldap));
  add_log_event($job_log, "LDAP file created");
  return $ldap;
}

// Raw AI data to file
function write_raw_ai_data_to_file($ldap, &$job_log) {
  $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
  <Data xmlns="http://www.digitalmeasures.com/schema/data" xmlns:dmd="http://www.digitalmeasures.com/schema/data-metadata" dmd:date="2014-01-14"/>');
  $dom_xml = dom_import_simplexml($xml);
  $count = 0;
  // For each person returned by the ldap query, Append appropriate xml to xml/ilr_people.xml
  foreach( $ldap as $person) {
    $count += 1;
    if (!empty($person['uid'][0])) {
      //   Try to get person info from Activity Insights
      $ai_data = get_ai_person($person['uid'][0]);

      if ( $ai_data->statusCode == 200 ) {  // Activity Insight returned data for this person
        // Add Activity Insight data to the main XML document
        $ai_person_xml = new SimpleXMLElement($ai_data->responseData);
        // Set netid/username to lowercase.
        $ai_person_xml->Record[0]['username'] = strtolower($ai_person_xml->Record[0]['username']);
        $dom_ai_person_xml = dom_import_simplexml($ai_person_xml->Record);
        $dom_ai_person_xml = $dom_xml->ownerDocument->importNode($dom_ai_person_xml, TRUE);
        $dom_xml->appendChild($dom_ai_person_xml);
      } else {
        // Add a placeholder Record to the main XML document with the userid
        $record = $xml->addChild('Record');
        $record->addAttribute('username', $person['uid'][0]);
        $record->addAttribute('noaidata', 'true');
      }
    }
  }

  // Note that count is off if the $person['uid'][0] check is empty.
  $record_count = $xml->addChild('recordcount', $count);
  $xml->asXML(SETTINGS['output_dir'] . 'ilr_profiles_raw_ai_data.xml');
  add_log_event($job_log, "Raw Activity Insight data collected for " . $count . " records");
}

// Aggregated and transformed data to file
function write_aggregated_ai_data_to_file(&$job_log, $version='default') {
  $output_file = SETTINGS['output_dir'] . 'ilr_profiles_feed.xml';

  // Retrieve to XML
  $raw_xml = file_get_contents(SETTINGS['output_dir'] . "ilr_profiles_raw_ai_data.xml");

  // Run the XSLT transform on the main xml file, which will fold in the fields from ldap.
  replace_file($output_file, stripEmptyCDATA(xslt_transform($raw_xml, get_ilr_profiles_transform_xsl($version), 'xml')));
  // Save a dated copy of today's file in case tomorrow's is a train wreck
  copy($output_file, str_replace('.xml', '-' . date('d', time()) . '.xml', $output_file ));
  add_log_event($job_log, "Final ILR Profiles data feed generated");
}

// main()

date_default_timezone_set('EST');

// Create a variable to hold all the log messages
$job_log = array();

add_log_event($job_log, "Job begun");
$ldap = write_ldap_xml_to_file($job_log);
write_raw_ai_data_to_file($ldap, $job_log);
write_aggregated_ai_data_to_file($job_log);
$job_results = log_results($job_log, "aggregation and XSL transformation of all ILR faculty and staff profile data");

print $job_results;
