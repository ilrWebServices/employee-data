<?php

namespace IlrProfilesDataFeed;

use League\Csv\Reader;

class EmployeeFeed extends FeedWriterBase {

  protected Reader $legacyData;
  protected array $addedEmployeeIds = [];
  protected \LDAP\Connection $ldap;

  public function __construct() {
    parent::__construct();

    // Add the header.
    $this->writer->insertOne([
      'Employee_ID',
      'Netid',
      'Preferred_Name',
      'employee_role',
      'First_Name',
      'Last_Name',
      'Email_Primary_Work',
      'Phone_Primary_Work',
      'Primary_Work_Address_1',
      'Primary_Work_Address_2',
      'Primary_Work_Address_City',
      'Primary_Work_Address_State',
      'Primary_Work_Address_Postal',
      'Primary_Work_Address_Visibility',
      'd7_image_uri',
      'd7_cv_uri',
      'd7_cv_description',
      'd7_overview',
      'd7_areas_of_expertise',
      'd7_other_expertise',
      'd7_links',
    ]);

    $data_dir = __DIR__ . '/../data/';
    $this->legacyData = Reader::createFromPath($data_dir . 'd7_profile_data.csv')->setHeaderOffset(0);
    $this->setLogger(new SlackLogger(getenv('PROFILE_DATA_SLACK_WEBHOOK_URL'), 'Employee feed generator (employees)'));
    $this->ldap = ldap_connect('ldaps://query.directory.cornell.edu:636');

    // Temporarily set the error handler to always throw an Exception.
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
      throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    // Try to bind to the ldap server. If it fails, it triggers a warning, which
    // `try` doesn't normally catch. But our error handler set above will throw
    // an error for the warning.
    try {
      ldap_bind($this->ldap, getenv('LDAP_USER'), getenv('LDAP_PASS'));
    }
    catch (\Exception $e) {
      $this->logger->error(message: $e->getMessage());
    }

    // Set the error handler back to what it was.
    restore_error_handler();
  }

  public function addRecord(array $data): int {
    if (in_array($data['Employee_ID'], $this->addedEmployeeIds)) {
      return 0;
    }

    $role = $this->getRole($data['Netid'], $data['Job_Family_Group'], $data['Job_Families'], $data['Job_Profile_Name']);

    if (!$role) {
      return 0;
    }

    // Initialize the feed record.
    // @todo Primary_Work_Address_Visibility must be 'Public'
    $feed_record = [
      $data['Employee_ID'],
      $data['Netid'],
      $data['Preferred_Name'],
      $role,
      $data['Preferred_Name_-_First_Name'],
      $data['Preferred_Name_-_Last_Name'],
      $this->getPreferredEmail($data['Netid'], $data['Email_Primary_Work']),
      $data['Phone_-_Primary_Work'],
      $data['Primary_Work_Address_1'],
      $data['Primary_Work_Address_2'],
      $data['Primary_Work_Address_City'],
      $data['Primary_Work_Address_State'],
      $data['Primary_Work_Address_Postal'],
      $data['Primary_Work_Address_Visibility'],
    ];

    // Load D7 data.
    $d7_records = $this->legacyData->filter(function (array $record) use ($data) {
      return $record['netid'] === $data['Netid'];
    });

    foreach ($d7_records as $d7_record) {
      $feed_record['d7_image_uri'] = $d7_record['image_uri'];
      $feed_record['d7_cv_uri'] = $d7_record['cv_uri'];
      $feed_record['d7_cv_description'] = $d7_record['vita_file_desc'];
      $feed_record['d7_overview'] = $d7_record['overview'];
      $feed_record['d7_areas_of_expertise'] = $d7_record['areas_of_expertise'];
      $feed_record['d7_other_expertise'] = $d7_record['other_expertise'];
      $feed_record['d7_links'] = $this->linksFromMarkup($d7_record['links_markup']);
    }

    $this->addedEmployeeIds[] = $data['Employee_ID'];
    return parent::addRecord($feed_record);
  }

  protected function linksFromMarkup(string $markup): string {
    if (empty($markup)) {
      return '';
    }

    $dom = new \DOMDocument();
    $dom->loadHTML($markup);
    $links_elements = $dom->getElementsByTagName('a');
    $links_delim = [];

    foreach ($links_elements as $links_element) {
      $url = $links_element->getAttribute('href');
      $text = $links_element->nodeValue;

      // Some URLs should be skipped.
      $skip = match (true) {
        strpos($url, 'www.ilr.cornell.edu/people') !== FALSE => true,
        strpos($url, 'works.bepress.com') !== FALSE => true,
        default => false
      };

      if ($skip) {
        continue;
      }

      // Strip leading scheme and hostname from www.ilr.cornell.edu links.
      $url = preg_replace('|https?://www.ilr.cornell.edu/|', '/', $url);
      $links_delim[] = $url . "\t" . $text;
    }

    return implode("\n", $links_delim);
  }

  protected function getPreferredEmail(string $netid, string $default_email): string {
    $email = $default_email;
    $search = ldap_search($this->ldap, 'ou=people,o=cornell university,c=us', 'uid=' . ldap_escape($netid), ['mail']);

    if (ldap_count_entries($this->ldap, $search) === 1) {
      $result = ldap_get_entries($this->ldap, $search);
      if (isset($result[0]['mail'][0])) {
        $email = $result[0]['mail'][0];
      }
    }

    return $email;
  }

}
