<?php

namespace IlrProfilesDataFeed;

class EmployeePositionFeed extends FeedWriterBase {

  use DataReplacementsTrait;

  public function __construct() {
    parent::__construct();

    // Add the header.
    $this->writer->insertOne([
      'Position_ID',
      'Employee_ID',
      'KFS_Org_Name',
      'Business_Title',
      'Primary_Job',
    ]);

    $this->setLogger(new SlackLogger(getenv('PROFILE_DATA_SLACK_WEBHOOK_URL'), 'Employee feed generator (employee positions)'));
  }

  public function addRecord(array $data): int {
    $role = $this->getRole($data['Netid'], $data['Job_Family_Group'], $data['Job_Families'], $data['Job_Profile_Name']);

    if (!$role) {
      return 0;
    }

    $department_name = preg_replace('/\d{4}\-\d{4}\ /', '', $data['KFS_Org_Name']);
    $title = $this->replaceTitle($data['Business_Title']);

    try {
      $department = $this->replaceDepartment($department_name);
    } catch (\UnhandledMatchError $e) {
      $this->logger->error(message: strtr('Skipped %netid because of missing department.', [
        '%netid' => $data['Netid'],
      ]), context: [
        'Department: ' . $department_name,
        'Title: ' . $title,
      ]);

      return 0;
    }

    $feed_record = [
      $data['Position_ID'],
      $data['Employee_ID'],
      $department,
      $title,
      $data['Primary_Job'],
    ];

    return parent::addRecord($feed_record);
  }

}
