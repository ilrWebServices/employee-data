<?php

namespace IlrProfilesDataFeed;

use League\Csv\Reader;

class EmployeeFeed extends FeedWriterBase {

  protected Reader $legacyData;
  protected array $addedEmployeeIds = [];

  public function __construct(
    protected string $file
  ) {
    parent::__construct($file);

    // Add the header.
    $this->writer->insertOne([
      'Employee_ID',
      'Netid',
      'employee_role',
      'First_Name',
      'Last_Name',
      'Email_Primary_Work',
      'Primary_Work_Address_1',
      'Primary_Work_Address_City',
      'Primary_Work_Address_State',
      'Primary_Work_Address_Postal',
      'd7_image_uri',
      'd7_cv_uri',
      'd7_cv_description',
      'd7_overview',
      'd7_overview_format',
    ]);

    $data_dir = __DIR__ . '/../data/';
    $this->legacyData = Reader::createFromPath($data_dir . 'd7_profile_data.csv')->setHeaderOffset(0);
    $this->setLogger(new SlackLogger(getenv('PROFILE_DATA_SLACK_WEBHOOK_URL'), 'Employee feed generator (employees)'));
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
      $role,
      $data['Preferred_Name_-_First_Name'],
      $data['Preferred_Name_-_Last_Name'],
      $data['Email_Primary_Work'],
      $data['Primary_Work_Address_1'],
      $data['Primary_Work_Address_City'],
      $data['Primary_Work_Address_State'],
      $data['Primary_Work_Address_Postal'],
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
      $feed_record['d7_overview_format'] = $d7_record['overview_format'];
    }

    $this->addedEmployeeIds[] = $data['Employee_ID'];
    return parent::addRecord($feed_record);
  }

}
