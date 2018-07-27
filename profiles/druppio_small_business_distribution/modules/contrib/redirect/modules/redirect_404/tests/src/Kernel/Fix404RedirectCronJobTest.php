<?php

namespace Drupal\Tests\redirect_404\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the clean up cron job for redirect_404.
 *
 * @group redirect_404
 */
class Fix404RedirectCronJobTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['redirect_404'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installSchema('redirect_404', 'redirect_404');

    // Set the limit to 5 just for the test.
    \Drupal::configFactory()
      ->getEditable('redirect_404.settings')
      ->set('row_limit', 5)
      ->save();
  }

  /**
   * Tests adding and deleting rows form redirect_404 table.
   */
  function testRedirect404CronJob() {
    // Insert some records in the redirect test table with hardcoded relevancy
    // for each row to not delete all rows after running the cronjob.
    $this->insert404Row('/test5', 'en', 91.97781);
    $this->insert404Row('/test6', 'en', 82.63037);
    $this->insert404Row('/test2', 'en', 71.79651);
    $this->insert404Row('/test7', 'en', 67.85126);
    $this->insert404Row('/test4', 'en', 53.98305);
    $this->insert404Row('/test3', 'en', 42.81542);
    // The following rows have smaller relevancy than the cutoff value defined
    // by the row_limit and will be dropped.
    $this->insert404Row('/test9', 'en', 37.99983);
    $this->insert404Row('/test1', 'en', 28.99964);
    $this->insert404Row('/test0', 'en', 15.79511);
    $this->insert404Row('/test8', 'en', 3.929704);

    // Check that there are 10 rows in the redirect_404 table.
    $result = db_query("SELECT COUNT(*) as rows FROM {redirect_404}")->fetchField();
    $this->assertEquals(10, $result);

    // Run cron to drop 4 rows from the redirect_404 test table.
    redirect_404_cron();

    // Check that there are only the first 6 rows in the redirect_404 table.
    $this->assertNo404Row('/test0');
    $this->assertNo404Row('/test1');
    $this->assert404Row('/test2');
    $this->assert404Row('/test3');
    $this->assert404Row('/test4');
    $this->assert404Row('/test5');
    $this->assert404Row('/test6');
    $this->assert404Row('/test7');
    $this->assertNo404Row('/test8');
    $this->assertNo404Row('/test9');
  }

  /**
   * Inserts a 404 request log in the redirect_404 test table.
   *
   * @param string $path
   *   The path of the request.
   * @param string $langcode
   *   (optional) The langcode of the request.
   * @param float $relevancy
   *   (optional) The relevancy of this path.
   */
  protected function insert404Row($path, $langcode = 'en', $relevancy = 1.00) {
    db_insert('redirect_404')
    ->fields([
      'path' => $path,
      'langcode' => $langcode,
      'count' => 1,
      'timestamp' => 1465082407,
      'resolved' => 0,
      'relevancy' => $relevancy,
    ])
    ->execute();
  }

  /**
   * Passes if the row with the given parameters is in the redirect_404 table.
   *
   * @param string $path
   *   The path of the request.
   * @param string $langcode
   *   (optional) The langcode of the request.
   * @param int $resolved
   *   (optional) Boolean indicating if this path is already resolved or not.
   */
  protected function assert404Row($path, $langcode = 'en', $resolved = 0) {
    $this->assert404RowHelper($path, $langcode, $resolved, FALSE);
  }

  /**
   * Passes if the row with the given parameters is NOT in the redirect_404 table.
   *
   * @param string $path
   *   The path of the request.
   * @param string $langcode
   *   (optional) The langcode of the request.
   * @param int $resolved
   *   (optional) Integer indicating if this path is already resolved or not.
   */
  protected function assertNo404Row($path, $langcode = 'en', $resolved = 0) {
    $this->assert404RowHelper($path, $langcode, $resolved, TRUE);
  }

  /**
   * Passes if the row with the given parameters is in the redirect_404 table.
   *
   * @param string $path
   *   The path of the request.
   * @param string $langcode
   *   (optional) The langcode of the request.
   * @param int $resolved
   *   (optional) Integer indicating if this path is already resolved or not.
   * @param bool $not_exists
   *   (optional) TRUE if this 404 row should not exist in the redirect_404
   *   table, FALSE if it should. Defaults to TRUE.
   */
  protected function assert404RowHelper($path, $langcode = 'en', $resolved = 0, $not_exists = TRUE) {
    $result = db_select('redirect_404', 'r404')
      ->fields('r404', ['path'])
      ->condition('path', $path)
      ->condition('langcode', $langcode)
      ->condition('resolved', $resolved)
      ->execute()
      ->fetchField();

    if ($not_exists) {
      $this->assertNotEquals($path, $result);
    }
    else {
      $this->assertEquals($path, $result);
    }
  }
}
