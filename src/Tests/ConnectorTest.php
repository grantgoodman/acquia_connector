<?php

/**
 * @file
 * Definition of Drupal\acquia_connector\Tests\ConnectorTest.
 */

namespace Drupal\acquia_connector\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\acquia_connector\Subscription;

/**
 * Tests the functionality of the Acquia Connector module.
 */
class ConnectorTest extends WebTestBase{
  protected $strictConfigSchema = FALSE;
  /*protected $acqtest_email = 'stas.mihnovich@acquia.com';
  protected $acqtest_pass = '937wv1s45!web20';
  protected $acqtest_id =  'JQWX-22095';
  protected $acqtest_key = 'safe-19b923433d4f30dd2491421b5ed72389';*/

  protected $acqtest_email = 'TEST_networkuser@example.com';
  protected $acqtest_pass = 'TEST_password';
  protected $acqtest_id =  'TEST_AcquiaConnectorTestID';
  protected $acqtest_key = 'TEST_AcquiaConnectorTestKey';
  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('acquia_connector', 'toolbar', 'devel', 'acquia_connector_test', 'rest');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Acquia Connector UI & Connection',
      'description' => 'Test Acquia Connector UI and connecting to Acquia Insight.',
      'group' => 'Acquia',
    );
  }

  /**
   *{@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Create and log in our privileged user.
    $this->privileged_user = $this->drupalCreateUser(array(
      'administer site configuration',
      'access administration pages',
      'access toolbar',
    ));
    $this->drupalLogin($this->privileged_user);
    // Create a user that has a Network subscription.
    $this->network_user = $this->drupalCreateUser();
    $this->network_user->mail = $this->acqtest_email;
    $this->network_user->pass = $this->acqtest_pass;
    $this->network_user->save();
    //$this->drupalLogin($this->network_user);
    //Setup variables.
    $this->cloud_free_url = 'https://www.acquia.com/acquia-cloud-free';
    $this->setup_path = 'admin/config/system/acquia-connector/setup';
    $this->credentials_path = 'admin/config/system/acquia-connector/credentials';
    $this->settings_path = 'admin/config/system/acquia-connector';

    //nspi.dev
    /*\Drupal::config('acquia_connector.settings')->set('spi.server', 'http://nspi.acquia.dev')->save();
    \Drupal::config('acquia_connector.settings')->set('spi.ssl_verify', FALSE)->save();
    \Drupal::config('acquia_connector.settings')->set('spi.ssl_override', TRUE)->save();*/

    //local
    \Drupal::config('acquia_connector.settings')->set('network_address', 'http://drupal-alerts.local:8083/')->save();
    \Drupal::config('acquia_connector.settings')->set('spi.server', 'http://drupal-alerts.local:8083/')->save();
    \Drupal::config('acquia_connector.settings')->set('spi.ssl_verify', FALSE)->save();
    \Drupal::config('acquia_connector.settings')->set('spi.ssl_override', TRUE)->save();
  }

  /**
   * Helper function for storing UI strings.
   */
  private function acquiaConnectorStrings($id) {
    switch ($id) {
      case 'free':
        return 'Sign up for Acquia Cloud Free, a free Drupal sandbox to experiment with new features, test your code quality, and apply continuous integration best practices.';
      case 'get-connected':
        return 'If you have an Acquia Network subscription, connect now. Otherwise, you can turn this message off by disabling the Acquia Network modules.';
      case 'enter-email':
        return 'Enter the email address you use to login to the Acquia Network';
      case 'enter-password':
        return 'Enter your Acquia Network password';
      case 'account-not-found':
        return '1800 : Account not found.';
      case 'id-key':
        return 'Enter your identifier and key from your subscriptions overview or log in to connect your site to the Acquia Network.';
      case 'enter-key':
        return 'Network key';
      case 'subscription-not-found':
        //return 'Error: Subscription not found (1800)'; //@todo
        return 'Can\'t connect to the Acquia Network.';
      case 'saved':
        return 'The Acquia configuration options have been saved.';
      case 'subscription':
        //return 'Subscription: ' . $this->acqtest_id; // Assumes subscription name is same as id. @todo
        return 'Subscription: docs.acquia.com';
      case 'migrate':
        return 'Transfer a fully-functional copy of your site to Acquia Cloud.';
      case 'migrate-hosting-404':
        return 'Error: Hosting not available under your subscription. Upgrade your subscription to continue with import.';
      case 'migrate-select-environments':
        return 'Select environment for migration';
      case 'migrate-files-label':
        return 'Migrate files directory';
      case 'menu-active':
        return 'Subscription active (expires 2023/10/8)';
      case 'menu-inactive':
        return 'Subscription not active';
    }
  }


  public function testAcquiaConnectorGetConnected() {
    // Check for call to get connected.
    $this->drupalGet('admin');
    $this->assertText($this->acquiaConnectorStrings('free'), 'The explanation of services text exists');
    $this->assertLinkByHref($this->cloud_free_url, 0, 'Link to Acquia.com Cloud Services exists');
    $this->assertText($this->acquiaConnectorStrings('get-connected'), 'The call-to-action to connect text exists');
    $this->assertLink('connect now', 0, 'The "connect now" link exists');

    // Check connection setup page. --------------------------------------------------------------
    $this->drupalGet($this->setup_path);
    $this->assertText($this->acquiaConnectorStrings('enter-email'), 'The email address field label exists');
    $this->assertText($this->acquiaConnectorStrings('enter-password'), 'The password field label exists');
    $this->assertLinkByHref($this->cloud_free_url, 0, 'Link to Acquia.com free signup exists');

    // Check errors on automatic setup page.
    $edit_fields = array(
      'email' => $this->randomString(),
      'pass' => $this->randomString(),
    );
    $submit_button = 'Next';
    $this->drupalPostForm($this->setup_path, $edit_fields, $submit_button);
    $this->assertText($this->acquiaConnectorStrings('account-not-found'), 'Account not found for random automatic setup attempt');
    $this->assertText($this->acquiaConnectorStrings('menu-inactive'), 'Subscription not active menu message appears');

    // Check manual connection. ------------------------------------------------------------------
    $this->drupalGet($this->credentials_path);
    $this->assertText($this->acquiaConnectorStrings('id-key'), 'The network key and id description exists');
    $this->assertText($this->acquiaConnectorStrings('enter-key'), 'The network key field label exists');
    $this->assertLinkByHref($this->cloud_free_url, 0, 'Link to Acquia.com free signup exists');

    // Check errors on connection page.
    $edit_fields = array(
      'acquia_identifier' => 'JQWX-00000',//$this->randomString(),
      'acquia_key' => 'safe-19b923433d4f30dd2491421b5ed72389',//$this->randomString(),
    );
    $submit_button = 'Connect';
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);
    $this->assertText($this->acquiaConnectorStrings('subscription-not-found'), 'Subscription not found for random credentials');
    $this->assertText($this->acquiaConnectorStrings('menu-inactive'), 'Subscription not active menu message appears');

    // Connect site on key and id.
    $edit_fields = array(
      'acquia_identifier' => $this->acqtest_id,
      'acquia_key' => $this->acqtest_key,
    );
    $submit_button = 'Connect';
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);
    $this->drupalGet($this->settings_path);
    $this->assertText($this->acquiaConnectorStrings('subscription'), 'Subscription connected with key and identifier');
    $this->assertLinkByHref($this->setup_path, 0, 'Link to change subscription exists');
    $this->assertText($this->acquiaConnectorStrings('migrate'), 'Acquia Cloud Migrate description exists');

    // Connect via automatic setup.
    \Drupal::config('acquia_connector.settings')->clear('identifier')->save();
    \Drupal::config('acquia_connector.settings')->clear('key')->save();
    $edit_fields = array(
      'email' => $this->acqtest_email,
      'pass' => $this->acqtest_pass,
    );
    $submit_button = 'Next';
    $this->drupalPostForm($this->setup_path, $edit_fields, $submit_button);
    $this->drupalGet($this->setup_path);
    $this->drupalGet($this->settings_path);
    $this->assertText($this->acquiaConnectorStrings('subscription'), 'Subscription connected with credentials');
    // Confirm menu reports active subscription.
    $this->drupalGet('admin');
    $this->assertText($this->acquiaConnectorStrings('menu-active'), 'Subscription active menu message appears');
    // Test dynamic banner.
    $edit_fields = array(
      'acquia_dynamic_banner' => TRUE,
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);
    $this->assertFieldChecked('edit-acquia-dynamic-banner', '"Receive updates from Acquia" option stays saved');
  }

  /**
   * Test Agent subscription methods.
   */
  public function testAcquiaConnectorSubscription(){
    // Set a wrapper on drupal_http_request() to record connection request counts.
    //variable_set('drupal_http_request_function', '_acquia_connector_test_http_request_wrapper');

    // Starts as inactive.
    $this->subscription = new Subscription();
    $this->assertFalse($this->subscription->isActive(), 'Subscription is not currently active.');
    //$this->drupalPost('agent-api', array(), array());
    // Confirm HTTP request count is 0 because without credentials no request
    // should have been made.
    $this->assertIdentical(\Drupal::config('acquia_connector_test')->get('requests'), 0);
    //\Drupal::config('acquia_connector.settings')->set('spi.ssl_verify', FALSE)->save();
    $this->assertFalse($this->subscription->update(), 'Subscription is currently false.');
    // Confirm HTTP request count is still 0.
    $this->assertIdentical(\Drupal::config('acquia_connector_test')->get('requests'), 0);
    // Fail a connection.
    $random_id = $this->randomString();
    $edit_fields = array(
      'acquia_identifier' => $random_id,
      'acquia_key' => $this->randomString(),
    );
    $submit_button = 'Connect';
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);


    // Connect.
    $edit_fields = array(
      'acquia_identifier' => $this->acqtest_id,
      'acquia_key' => $this->acqtest_key,
    );
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);


  }

}