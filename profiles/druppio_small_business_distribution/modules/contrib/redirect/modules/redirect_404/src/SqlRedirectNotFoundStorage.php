<?php

namespace Drupal\redirect_404;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Provides an SQL implementation for redirect not found storage.
 *
 * To keep a limited amount of relevant records, we implement an exponential
 * decay similar to the radioactivity process decay (see radioactivity module).
 *
 * The relevancy:
 * - represents the relevancy for timestamp of each record
 * - is recalculated for an individual record on each hit for current time
 *
 * To clean the records, we calculate the effective relevancy for NOW(),
 * determine the relevancy cutoff value and drop the less relevant rows.
 * The half life of relevancy is (float) 86400.00 = 1 day
 * Each hit adds 1.
 *
 * Relevancy formula: $relevancy = 1 + $relevancy * $decay, where
 * $decay = POW(2, - (NOW() - timestamp) / $halflife)
 */
class SqlRedirectNotFoundStorage implements RedirectNotFoundStorageInterface {

  /**
   * Maximum column length for invalid paths.
   */
  const MAX_PATH_LENGTH = 191;

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SqlRedirectNotFoundStorage.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A Database connection to use for reading and writing database data.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function logRequest($path, $langcode) {
    // If the request is not new, update its relevancy for the time interval
    // since the last hit.
    if (Unicode::strlen($path) > static::MAX_PATH_LENGTH) {
      // Don't attempt to log paths that would result in an exception. There is
      // no point in logging truncated paths, as they cannot be used to build a
      // new redirect.
      return;
    }
    // Ignore invalid UTF-8, which can't be logged.
    if (!Unicode::validateUtf8($path)) {
      return;
    }

    $this->database->merge('redirect_404')
      ->key('path', $path)
      ->key('langcode', $langcode)
      ->expression('count', 'count + 1')
      ->expression('relevancy', 'relevancy * pow(2, -( UNIX_TIMESTAMP(NOW()) - IFNULL( timestamp, UNIX_TIMESTAMP(NOW()) ) )/86400.00) + 1')
      ->fields([
        'timestamp' => REQUEST_TIME,
        'count' => 1,
        'resolved' => 0,
        'relevancy' => 1.00,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function resolveLogRequest($path, $langcode) {
    $this->database->update('redirect_404')
      ->fields(['resolved' => 1])
      ->condition('path', $path)
      ->condition('langcode', $langcode)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function purgeOldRequests() {
    $row_limit = $this->configFactory->get('redirect_404.settings')->get('row_limit');
    $cutoff_exp = '(relevancy * pow(2, -(UNIX_TIMESTAMP(NOW()) - timestamp)/86400.00))';

    // Determine cutoff level to get the current min relevancy we want to keep.
    $query = $this->database->select('redirect_404', 'r404');
    $query->addExpression($cutoff_exp, 'cutoff');
    $query->orderBy('cutoff', 'DESC');
    $cutoff = $query->range($row_limit, 1)->execute()->fetchField();

    // Delete records below cutoff value, if given. Otherwise skip the cleanup.
    if (!empty($cutoff)) {
      $this->database
        ->delete('redirect_404')
        ->where( $cutoff_exp . ' < :cutoff', [':cutoff' => $cutoff])
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listRequests(array $header = [], $search = NULL) {
    $query = $this->database
      ->select('redirect_404', 'r404')
      ->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header)
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(25)
      ->fields('r404');

    if ($search) {
      // Replace wildcards with PDO wildcards.
      // @todo Find a way to write a nicer pattern.
      $wildcard = '%' . trim(preg_replace('!\*+!', '%', $this->database->escapeLike($search)), '%') . '%';
      $query->condition('path', $wildcard, 'LIKE');
    }
    $results = $query->condition('resolved', 0, '=')->execute()->fetchAll();

    return $results;
  }

}
