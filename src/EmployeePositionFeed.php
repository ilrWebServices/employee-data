<?php

namespace IlrProfilesDataFeed;

class EmployeePositionFeed extends FeedWriterBase {

  use DataReplacementsTrait;

  public function __construct(
    protected string $file
  ) {
    parent::__construct($file);

    // Add the header.
    $this->writer->insertOne([
      'Position_ID',
      'Employee_ID',
      'KFS_Org_Name',
      'Business_Title',
    ]);

    $this->setLogger(new SlackLogger(getenv('PROFILE_DATA_SLACK_WEBHOOK_URL'), 'Employee feed generator (employee positions)'));
  }

  public function addRecord(array $data): int {
    $role = $this->getRole($data['Netid'], $data['Job_Family_Group'], $data['Job_Families'], $data['Job_Profile_Name']);

    if (!$role) {
      return 0;
    }

    $department = preg_replace('/\d{4}\-\d{4}\ /', '', $data['KFS_Org_Name']);

    if (!array_key_exists($department, self::DEPARTMENTS)) {
      $this->logger->error(message: 'Missing department ' . $department);
      return 0;
    }

    $feed_record = [
      $data['Position_ID'],
      $data['Employee_ID'],
      self::DEPARTMENTS[$department],
      strtr($data['Business_Title'], self::TITLE_REPLACEMENTS),
    ];

    return parent::addRecord($feed_record);
  }

}
