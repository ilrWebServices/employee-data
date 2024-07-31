<?php

namespace IlrProfilesDataFeed;

use Exception;
use League\Csv\Reader;
use League\Csv\Writer;

class Runner {

  const TITLE_REPLACEMENTS = [
    'Ctr' => 'Center',
    'Prof ' => 'Professor ',
    'Spec C06' => 'Specialist C06 (whatever that means)',
    'Sr Ext Assoc' => 'Senior Extension Associate',
  ];

  const DEPARTMENTS = [
    'Admissions Office' => 'Admissions Office',
    'Alumni Affairs & Development' => 'Alumni Affairs & Development',
    'Career Services' => 'Career Services',
    'Center for Advanced Research on Work' => 'Center for Advanced Research on Work',
    'Climate Jobs Institute' => 'Climate Jobs Institute',
    'Cornell Forensics Society' => 'Cornell Forensics Society',
    'Ctr for Advanced Human Resource Studies' => 'Center for Advanced Human Resource Studies',
    'Dean\'s Office' => 'Dean\'s Office',
    'Department of Global Labor and Work' => 'Department of Global Labor and Work',
    'EMHRM' => 'EMHRM',
    'Extension Administration' => 'Extension Administration',
    'Facilities' => 'Facilities',
    'Field Studies / Study Abroad' => 'Field Studies / Study Abroad',
    'Financial Operations & Budget Planning' => 'Financial Operations & Budget Planning',
    'Global Labor Institute' => 'Global Labor Institute',
    'Graduate Office' => 'Graduate Office',
    'Human Capital Development' => 'Human Capital Development',
    'Human Resource Studies' => 'Human Resource Studies',
    'Human Resources Office' => 'Human Resources Office',
    'ILR Buffalos Co-Lab' => 'Buffalo Co-Lab',
    'ILR Review' => 'ILR Review',
    'Institute for Compensation Studies' => 'Institute for Compensation Studies',
    'International & Comparative Labor' => 'International & Comparative Labor',
    'International Programs' => 'International Programs',
    'Ithaca Conference Center' => 'Ithaca Conference Center',
    'Labor & Employment Law' => 'Labor & Employment Law',
    'Labor Dynamics Institute' => 'Labor Dynamics Institute',
    'Labor Economics' => 'Labor Economics',
    'Marketing & Communications' => 'Marketing & Communications',
    'New York City Conference Center' => 'New York City Conference Center',
    'Office of Student Services' => 'Office of Student Services',
    'Organizational Behavior' => 'Organizational Behavior',
    'Resident Outreach Department' => 'Resident Outreach Department',
    'Scheinman Institute' => 'Scheinman Institute',
    'Smithers Institute' => 'Smithers Institute',
    'Social Statistics' => 'Social Statistics',
    'Technology Services' => 'Technology Services',
    'Worker Institute' => 'Worker Institute',
    'Yang Tan Institute' => 'Yang Tan Institute',
  ];

  public static function build(): void {
    $logger = new SlackLogger(getenv('PROFILE_DATA_SLACK_WEBHOOK_URL'), 'Employee feed generator');
    $start = hrtime(true);
    $feed_row_count = 0;
    $data_dir = __DIR__ . '/../data/';
    $output_dir = __DIR__ . '/../' . getenv('OUTPUT_DIR') . '/';
    $output_file = $output_dir . 'employee-feed.csv';

    if (file_exists($output_file)) {
      file_put_contents($output_file, '');
    }

    touch($output_file);
    $feed = Writer::createFromPath($output_file);
    $feed->insertOne([
      'Position_ID',
      'Employee_ID',
      'Netid',
      'First_Name',
      'Last_Name',
      'Email_Primary_Work',
      'Primary_Work_Address_1',
      'Primary_Work_Address_City',
      'Primary_Work_Address_State',
      'Primary_Work_Address_Postal',
      'Primary_Work_Address_City',
      'KFS_Org_Name',
      'Business_Title',
      'd7_image_uri',
      'd7_cv_uri',
      'd7_cv_description',
      'd7_overview',
      'd7_overview_format',
      'employee_role',
    ]);
    $d7_data = Reader::createFromPath($data_dir . 'd7_profile_data.csv')->setHeaderOffset(0);

    // Fetch workday data
    $workday_fetcher = new WorkdayFetcher(outputDir: $output_dir);
    $workday_data = $workday_fetcher->getData();

    if (!$workday_data) {
      $logger->emergency(message: 'Feed generation failed.');
      return;
    }

    // Loop over workday people.
    foreach ($workday_data as $workday_record) {
      $department = preg_replace('/\d{4}\-\d{4}\ /', '', $workday_record['KFS_Org_Name']);

      if (!array_key_exists($department, self::DEPARTMENTS)) {
        $logger->error(message: 'Missing department ' . $department);
        continue;
      }

      $role = self::getRole($workday_record['Job_Family_Group'], $workday_record['Job_Families'], $workday_record['Job_Profile_Name']);

      if (!$role) {
        // Log the missing person and skip this record, but only if the family
        // group is not 'Student' or 'Grad Students Group'.
        switch ($workday_record['Job_Family_Group']) {
          case 'Student':
          case 'Grad Students Group':
            break;
          default:
            $message = 'Skipped ' . $workday_record['Netid'] . ' because of missing role.';
            $logger->error(message: $message, context: [
              'Job_Family_Group: ' . $workday_record['Job_Family_Group'],
              'Job_Families: ' . $workday_record['Job_Families'],
              'Job_Profile_Name: ' . $workday_record['Job_Profile_Name'],
            ]);
        }

        continue;
      }

      // Initialize the feed record.
      $feed_record = [
        $workday_record['Position_ID'],
        $workday_record['Employee_ID'],
        $workday_record['Netid'],
        $workday_record['Preferred_Name_-_First_Name'],
        $workday_record['Preferred_Name_-_Last_Name'],
        $workday_record['Email_Primary_Work'],
        $workday_record['Primary_Work_Address_1'],
        $workday_record['Primary_Work_Address_City'],
        $workday_record['Primary_Work_Address_State'],
        $workday_record['Primary_Work_Address_Postal'],
        $workday_record['Primary_Work_Address_City'],
        self::DEPARTMENTS[$department],
        strtr($workday_record['Business_Title'], self::TITLE_REPLACEMENTS),
        $role,
      ];

      // Load activity insight data for faculty (mainly).

      // Load D7 data.
      $d7_records = $d7_data->filter(function (array $record) use ($workday_record) {
        return $record['netid'] === $workday_record['Netid'];
      });

      foreach ($d7_records as $d7_record) {
        $feed_record['d7_image_uri'] = $d7_record['image_uri'];
        $feed_record['d7_cv_uri'] = $d7_record['cv_uri'];
        $feed_record['d7_cv_description'] = $d7_record['vita_file_desc'];
        $feed_record['d7_overview'] = $d7_record['overview'];
        $feed_record['d7_overview_format'] = $d7_record['overview_format'];
      }

      // Add person to new csv output, merging workday and activity insight data.
      // Primary_Work_Address_Visibility must be 'Public'
      $feed->insertOne($feed_record);
      $feed_row_count++;
    }

    $time = round((hrtime(true) - $start) / 1000000000, 2);
    $logger->info(message: 'Feed generated.', context: [$feed_row_count . ' record(s)', $time . ' seconds']);
  }

  /**
   * Return an employee roll for some given workday values.
   */
  protected static function getRole(string $job_family_group, string $job_families, string $job_profile_name): string|false {
    if ($job_family_group === 'Faculty') {
      return 'faculty';
    }
    elseif ($job_family_group === 'Faculty Modifier') {
      return 'faculty';
    }
    elseif ($job_family_group === 'Post Graduate') {
      return 'academic';
    }
    elseif ($job_family_group === 'RTE Faculty') {
      return 'academic';
    }
    elseif ($job_family_group === 'RTE Faculty Modifier') {
      return 'academic';
    }
    elseif ($job_family_group === 'Staff') {
      return 'staff';
    }
    elseif ($job_family_group === 'Temporary') {
      return 'staff';
    }
    elseif ($job_family_group === 'Union') {
      $academic_profiles = [
        'Extension Supp Spec C06',
        'Research Support Spec C06',
        'Research Support Spec C07'
      ];

      if ($job_families === 'Communication Wkrs of America' && in_array($job_profile_name, $academic_profiles)) {
        return 'academic';
      }

      return 'staff';
    }

    return false;
  }

}
