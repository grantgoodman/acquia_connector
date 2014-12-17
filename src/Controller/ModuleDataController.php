<?php

/**
 * @file
 * Contains \Drupal\acquia_connector\Form\Controller\StatusController.
 */

namespace Drupal\acquia_connector\Controller;

use Drupal\acquia_connector\Subscription;
use Drupal\Core\Access\AccessInterface;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class StatusController.
 */
class ModuleDataController extends ControllerBase {

  /**
   * Send a file's contents to the requestor
   * D7: acquia_spi_send_module_data
   */
  public function sendModuleData($data = array()) {
    $request = \Drupal::request();
    if (empty($data)) {
      $data = json_decode($request->getContent(), TRUE);
    }

    $headers = [
      'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
      'Content-Type' => 'text/plain',
      'Cache-Control' => 'no-cache',
      'Pragma' => 'no-cache',
    ];

    // If our checks pass muster, then we'll provide this data.
    // If the file variable is set and if the user has allowed file diffing.
    $file = $data['body']['file'];
    $document_root = getcwd();
    $file_path = realpath($document_root . '/' . $file);
    // Be sure the file being requested is within the webroot and is not any
    // settings.php file.
    if (is_file($file_path) && strpos($file_path, $document_root) === 0 && strpos($file_path, 'settings.php') === FALSE) {
      $file_contents = file_get_contents($file_path);

      return new Response($file_contents, Response::HTTP_OK, $headers);
    }
    return new Response('', Response::HTTP_NOT_FOUND, $headers);
  }

  /**
   * @param $data
   * @param $message
   * @return bool
   * D7: acquia_spi_valid_request
   */
  public function isValidRequest($data, $message) {
    $key = $this->config('acquia_connector.settings')->get('key');
    \Drupal::logger('acquia module data')->notice('$data: '. print_r($data, TRUE));
    if (!isset($data['authenticator']) || !isset($data['authenticator']['time']) || !isset($data['authenticator']['nonce'])) {
      return FALSE;
    }
    $string = $data['authenticator']['time'] . ':' . $data['authenticator']['nonce'] . ':' . $message;
    $hash = sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) . pack("H*", sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) . $string)));
    \Drupal::logger('acquia $hash data')->notice('$hash: '. $hash);
    if ($hash == $data['authenticator']['hash']) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Access callback for sendModuleData() callback.
   */
  public function access() {
    $request = \Drupal::request();
    $data = json_decode($request->getContent(), TRUE);

    // We only do this if we are on SSL
    $via_ssl = isset($_SERVER['HTTPS']) ? TRUE : FALSE;
    $via_ssl = TRUE;

    if ($this->config('acquia_connector.settings')->get('spi.module_diff_data') && $via_ssl) {
      $subscription = new Subscription();
      if ($subscription->hasCredentials() && isset($data['body']['file']) && $this->isValidRequest($data, $data['body']['file'])) {
        \Drupal::logger('acquia module data')->notice('module_diff_data: OK '. $this->config('acquia_connector.settings')->get('spi.module_diff_data'));
        return AccessResultAllowed::allowed();
      }
      \Drupal::logger('acquia module data')->notice('module_diff_data: OPS!!! '. $this->config('acquia_connector.settings')->get('spi.module_diff_data'));

      // Log the request if validation failed and debug is enabled.
      if ($this->config('acquia_connector.settings')->get('debug')) {
        $info = array(
          'data' => $data,
          'get' => $request->query->all(),
          'server' => $request->server->all(),
          'request' => $request->request->all(),
        );

        \Drupal::logger('acquia module data')->notice('Site Module Data request: @data', array('@data' => var_export($info, TRUE)));
      }
    }

    return AccessResultForbidden::forbidden();
  }
}
