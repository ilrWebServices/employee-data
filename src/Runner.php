<?php

namespace IlrProfilesDataFeed;

class Runner {

  public static function build(string $output_dir): void {
    $logger = new SlackLogger(getenv('PROFILE_DATA_SLACK_WEBHOOK_URL'), 'Employee feed generator', getenv('PROFILE_DATA_SLACK_IDENTIFIER'));
    $start = hrtime(true);
    $employee_writer = new EmployeeFeed();
    $employee_position_writer = new EmployeePositionFeed();

    // Fetch workday data
    $workday_fetcher = new WorkdayFetcher(outputDir: $output_dir);
    $workday_data = $workday_fetcher->getData();

    if (!$workday_data) {
      $logger->emergency(message: 'Feed generation failed.');
      return;
    }

    // Loop over workday people.
    foreach ($workday_data as $workday_record) {
      // This will add a person to the employee feed, but it will skip
      // duplicates (since the workday feed is a list of positions) and people
      // whose role cannot be determined.
      $employee_writer->addRecord($workday_record);

      // This will add the position to the positions feed, so there should be
      // more records than the employee feed. It will also skip employees whose
      // role cannot be determined.
      $employee_position_writer->addRecord($workday_record);
    }

    // Save the new feeds to their final locations.
    $employee_writer->saveFile($output_dir . 'employee-feed.csv');
    $employee_position_writer->saveFile($output_dir . 'employee-position-feed.csv');

    $time = round((hrtime(true) - $start) / 1000000000, 2);

    $logger->info(message: 'Feed generated.', context: [
      $employee_writer->getRecordCount() . ' employee record(s)',
      $employee_position_writer->getRecordCount() . ' position record(s)',
      $time . ' seconds'
    ]);
  }


}
