<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Subscriber\CasSubscriberTest.
 */

namespace Drupal\Tests\cas\Unit\Subscriber;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ServerBag;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSusbscriberInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Condition\ConditionManager;
use Drupal\cas\Service\CasHelper;
use Drupal\cas\Subscriber\CasSubscriber;
    
/**
 * CasSubscriber unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Subscriber\CasSubscriber
 */
class CasSubscriberTest extends UnitTestCase {
  
  /**
   * The mocked current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The mocked Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $requestStack;

  /**
   * The mocked condition manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager|PHPUnit_Framework_MockObject_MockObject
   */
  protected $conditionManager;

  /**
   * The mocked CasHelper.
   *
   * @var \Drupal\cas\Service\CasHelper|PHPUnit_Framework_MockObject_MockObject
   */
  protected $casHelper;

  /**
   * The mocked route matcher.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|PHPUnit_Framework_MockObject_MockObject
   */
  protected $routeMatcher;

  /**
   * The mocked GetResponseEvent
   *
   * @var \Symfony\Component\HttpKernel\Event\GetResponseEvent|PHPUnit_Framework_MockObject_MockObject
   */
  protected $event;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->requestStack = $this->getMock('\Symfony\Component\HttpFoundation\RequestStack');
    $this->casHelper = $this->getMockBuilder('\Drupal\cas\Service\CasHelper')
                            ->disableOriginalConstructor()
                            ->getMock();
    $this->currentUser = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $this->conditionManager = $this->getMockBuilder('\Drupal\Core\Condition\ConditionManager')
                                   ->disableOriginalConstructor()
                                   ->getMock();
    $this->routeMatcher = $this->getMock('\Drupal\Core\Routing\RouteMatchInterface');
    $this->event = $this->getMockBuilder('\Symfony\Component\HttpKernel\Event\GetResponseEvent')
                  ->disableOriginalConstructor()
                  ->getMock();
  }

  /**
   * Test our event subscription declaration.
   *
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents() {
    $this->assertThat(
      CasSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0],
      $this->contains('handle')
    );
  }

  /**
   * Test backing out when we get a sub request.
   *
   * @covers ::handle
   * @covers ::__construct
   */
  public function testHandleSubRequest() {
    $config_factory = $this->getConfigFactoryStub();
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::SUB_REQUEST));
    $cas_subscriber->expects($this->never())
      ->method('isIgnoreableRoute');
    $cas_subscriber->expects($this->never())
      ->method('isNotNormalRequest');
    $cas_subscriber->expects($this->never())
      ->method('handleForcedPath');
    $cas_subscriber->expects($this->never())
      ->method('handleGateway');
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test backing out when user is authenticated.
   *
   * @covers ::handle
   * @covers ::__construct
   */
  public function testHandleIsAuthenticated() {
    $config_factory = $this->getConfigFactoryStub();
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $this->currentUser->expects($this->once())
      ->method('isAuthenticated')
      ->will($this->returnValue(TRUE));
    $cas_subscriber->expects($this->never())
      ->method('isIgnoreableRoute');
    $cas_subscriber->expects($this->never())
      ->method('isNotNormalRequest');
    $cas_subscriber->expects($this->never())
      ->method('handleForcedPath');
    $cas_subscriber->expects($this->never())
      ->method('handleGateway');
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test backing out when the current route is the service route.
   *
   * @covers ::handle
   * @covers ::isIgnoreableRoute
   * @covers ::__construct
   */
  public function testHandleIsIgnoreableRoute() {
    $config_factory = $this->getConfigFactoryStub();
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $this->routeMatcher->expects($this->once())
      ->method('getRouteName')
      ->will($this->returnValue('cas.service'));
    $cas_subscriber->expects($this->never())
      ->method('isNotNormalRequest');
    $cas_subscriber->expects($this->never())
      ->method('handleForcedPath');
    $cas_subscriber->expects($this->never())
      ->method('handleGateway');
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test backing out when the request comes from specific automated sources.
   *
   * @covers ::handle
   * @covers ::isNotNormalRequest
   * @covers ::__construct
   * @covers ::isIgnoreableRoute
   *
   * @dataProvider handleIsNotNormalRequestDataProvider
   */
  public function testHandleIsNotNormalRequest($method_param, $method_value) {
    $config_factory = $this->getConfigFactoryStub();
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                             $this->requestStack,
                             $this->routeMatcher,
                             $config_factory,
                             $this->currentUser,
                             $this->conditionManager,
                             $this->casHelper,
                           ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $this->routeMatcher->expects($this->once())
      ->method('getRouteName')
      ->will($this->returnValue('not_cas.service'));
    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));

    $map = array(
      array($method_param, NULL, FALSE, $method_value),
    );
    $server->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));
    $cas_subscriber->expects($this->never())
      ->method('handleForcedPath');
    $cas_subscriber->expects($this->never())
      ->method('handleGateway');
    $cas_subscriber->handle($this->event);

  }

  /**
   * Provides parameters for testHandleIsNotNormalRequest.
   *
   * @return array
   *  Parameters.
   *
   * @see \Drupal\Tests\cas\Unit\Subscriber\CasSubscriber::testHandleIsNotNormalRequest
   */
  public function handleIsNotNormalRequestDataProvider() {
    // Request is from xmlrpc.php.
    $params[] = array('SCRIPT_FILENAME', 'xmlrpc.php');

    // Request is from cron.php.
    $params[] = array('SCRIPT_FILENAME', 'cron.php');

    // Request is from a known crawler.
    $params[] = array('HTTP_USER_AGENT', 'gsa-crawler');

    return $params;
  }

  /**
   * Test passing through isNotNormalRequest when user agent is not a bot.
   *
   * @covers ::handle
   * @covers ::isNotNormalRequest
   * @covers ::__construct
   * @covers ::isIgnoreableRoute
   */
  public function testHandleIsNotNormalRequestPassThrough() {
    $config_factory = $this->getConfigFactoryStub();
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                             $this->requestStack,
                             $this->routeMatcher,
                             $config_factory,
                             $this->currentUser,
                             $this->conditionManager,
                             $this->casHelper,
                           ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $this->routeMatcher->expects($this->once())
      ->method('getRouteName')
      ->will($this->returnValue('not_cas.service'));
    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));

    $server->expects($this->any())
      ->method('get')
      ->will($this->returnValue('NotAKnownBot'));
    
    // We want to check that we've gotten past this point.
    $_SESSION['cas_temp_disable'] = TRUE;
    $cas_subscriber->handle($this->event);

  }

  /**
   * Test backing out when we have cas_temp_disable.
   *
   * @covers ::handle
   * @covers ::__construct
   */
  public function testHandleTempDisable() {
    $config_factory = $this->getConfigFactoryStub();
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));
    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $attributes = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->attributes = $attributes;
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));
    // Set the session variable that should force backing out.
    $_SESSION['cas_temp_disable'] = TRUE;

    $cas_subscriber->expects($this->never())
      ->method('handleForcedPath');
    $cas_subscriber->expects($this->never())
      ->method('handleGateway');
    $cas_subscriber->handle($this->event);
    $this->assertEmpty($_SESSION);
  }
  /**
   * Test handling a forced login path.
   *
   * @covers ::handle
   * @covers ::handleForcedPath
   * @covers ::__construct
   */
  public function testHandleForcedPath() {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'forced_login.enabled' => TRUE,
        'forced_login.paths' => array('<front>'),
      ),
    ));
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $condition = $this->getMockBuilder('\Drupal\Core\Condition\ConditionPluginBase')
                      ->disableOriginalConstructor()
                      ->getMock();
    $this->conditionManager->expects($this->once())
      ->method('createInstance')
      ->with('request_path')
      ->will($this->returnValue($condition));
    $condition->expects($this->once())
      ->method('setConfiguration')
      ->with(array('<front>'));
    $this->conditionManager->expects($this->once())
      ->method('execute')
      ->with($condition)
      ->will($this->returnValue(TRUE));
    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $attributes = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->attributes = $attributes;
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));
    $this->casHelper->expects($this->any())
      ->method('getServerLoginUrl')
      ->will($this->returnValue('https://example.com/cas/login'));
    $this->event->expects($this->once())
      ->method('setResponse');
    $cas_subscriber->expects($this->never())
      ->method('handleGateway');
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test 'failing through' the forced login check due to config option.
   *
   * @covers ::handle
   * @covers ::handleForcedPath
   * @covers ::handleGateway
   */
  public function testHandleForcedPathWithConfigOff() {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'forced_login.enabled' => FALSE,
        'forced_login.paths' => array('<front>'),
      ),
    ));
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $attributes = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->attributes = $attributes;
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;

    // This assertion means we've made it through to gateway mode. Exit out.
    $request_object->expects($this->once())
      ->method('isMethod')
      ->with('GET')
      ->will($this->returnValue(FALSE));

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));
    
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test 'failing through' the forced login check due to no condition match.
   *
   * @covers ::handle
   * @covers ::handleForcedPath
   * @covers ::handleGateway
   */
  public function testHandleForcedPathNoConditionMatch() {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'forced_login.enabled' => TRUE,
        'forced_login.paths' => array('<front>'),
      ),
    ));
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $attributes = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->attributes = $attributes;
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $condition = $this->getMockBuilder('\Drupal\Core\Condition\ConditionPluginBase')
                      ->disableOriginalConstructor()
                      ->getMock();
    $this->conditionManager->expects($this->once())
      ->method('createInstance')
      ->with('request_path')
      ->will($this->returnValue($condition));
    $condition->expects($this->once())
      ->method('setConfiguration')
      ->with(array('<front>'));
    $this->conditionManager->expects($this->once())
      ->method('execute')
      ->with($condition)
      ->will($this->returnValue(FALSE));
    // This assertion means we've made it through to gateway mode. Exit out.
    $request_object->expects($this->once())
      ->method('isMethod')
      ->with('GET')
      ->will($this->returnValue(FALSE));

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));
    
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test exiting out of handleGateway if we're not configured to do it.
   *
   * @covers ::handle
   * @covers ::handleGateway
   */
  public function testHandleGatewayConfigOff() {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'forced_login.enabled' => TRUE,
        'forced_login.paths' => array('<front>'),
        'gateway.check_frequency' => CasHelper::CHECK_NEVER,
      ),
    ));
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $attributes = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->attributes = $attributes;
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $condition = $this->getMockBuilder('\Drupal\Core\Condition\ConditionPluginBase')
                      ->disableOriginalConstructor()
                      ->getMock();

    // Asserting that these methods are only called once means that we exited
    // out of handleGateway during configuration checking.
    $this->conditionManager->expects($this->once())
      ->method('createInstance')
      ->with('request_path')
      ->will($this->returnValue($condition));
    $condition->expects($this->once())
      ->method('setConfiguration')
      ->with(array('<front>'));
    $this->conditionManager->expects($this->once())
      ->method('execute')
      ->with($condition)
      ->will($this->returnValue(FALSE));

    $request_object->expects($this->once())
      ->method('isMethod')
      ->with('GET')
      ->will($this->returnValue(TRUE));

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));
    
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test exiting out of gateway if we're not on a configured path.
   *
   * @covers ::handle
   * @covers ::handleGateway
   */
  public function testHandleGatewayWithPathNotInConfig() {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'forced_login.enabled' => TRUE,
        'forced_login.paths' => array('<front>'),
        'gateway.check_frequency' => CasHelper::CHECK_ALWAYS,
        'gateway.paths' => array('<front>'),
      ),
    ));
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $attributes = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->attributes = $attributes;
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $condition = $this->getMockBuilder('\Drupal\Core\Condition\ConditionPluginBase')
                      ->disableOriginalConstructor()
                      ->getMock();

    $this->conditionManager->expects($this->any())
      ->method('createInstance')
      ->with('request_path')
      ->will($this->returnValue($condition));
    $condition->expects($this->any())
      ->method('setConfiguration')
      ->with(array('<front>'));
    $this->conditionManager->expects($this->any())
      ->method('execute')
      ->with($condition)
      ->will($this->returnValue(FALSE));

    $request_object->expects($this->once())
      ->method('isMethod')
      ->with('GET')
      ->will($this->returnValue(TRUE));

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));

    // Asserting that CasHelper never gets asked for the server login url
    // means that we failed out checking paths.
    $this->casHelper->expects($this->never())
      ->method('getServerLoginUrl');
    
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test exiting out of gateway if CHECK_ONCE and we already checked.
   *
   * @covers ::handle
   * @covers ::handleGateway
   */
  public function testHandleGatewayWithGatewayAlreadyChecked() {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'forced_login.enabled' => TRUE,
        'forced_login.paths' => array('<front>'),
        'gateway.check_frequency' => CasHelper::CHECK_ONCE,
        'gateway.paths' => array('<front>'),
      ),
    ));
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $attributes = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->attributes = $attributes;
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $condition = $this->getMockBuilder('\Drupal\Core\Condition\ConditionPluginBase')
                      ->disableOriginalConstructor()
                      ->getMock();

    $this->conditionManager->expects($this->any())
      ->method('createInstance')
      ->with('request_path')
      ->will($this->returnValue($condition));
    $condition->expects($this->any())
      ->method('setConfiguration')
      ->with(array('<front>'));
    $this->conditionManager->expects($this->any())
      ->method('execute')
      ->with($condition)
      ->will($this->onConsecutiveCalls(FALSE, TRUE));

    $request_object->expects($this->once())
      ->method('isMethod')
      ->with('GET')
      ->will($this->returnValue(TRUE));

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));

    $_SESSION['cas_gateway_checked'] = TRUE;

    // Asserting that CasHelper never gets asked for the server login url
    // means that we failed out checking paths.
    $this->casHelper->expects($this->never())
      ->method('getServerLoginUrl');
    
    $cas_subscriber->handle($this->event);
  }

  /**
   * Test processing gateway with CHECK_ONCE to make sure SESSION gets set.
   *
   * @covers ::handle
   * @covers ::handleGateway
   */
  public function testHandleGatewayWithCheckOnceSuccess() {
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'forced_login.enabled' => TRUE,
        'forced_login.paths' => array('<front>'),
        'gateway.check_frequency' => CasHelper::CHECK_ONCE,
        'gateway.paths' => array('<front>'),
      ),
    ));
    $cas_subscriber = $this->getMockBuilder('\Drupal\cas\Subscriber\CasSubscriber')
                           ->setConstructorArgs(array(
                              $this->requestStack,
                              $this->routeMatcher,
                              $config_factory,
                              $this->currentUser,
                              $this->conditionManager,
                              $this->casHelper,
                            ))
                           ->setMethods(NULL)
                           ->getMock();
    $this->event->expects($this->any())
      ->method('getRequestType')
      ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));

    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $attributes = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->attributes = $attributes;
    $server = $this->getMock('\Symfony\Component\HttpFoundation\ServerBag');
    $request_object->server = $server;
    $condition = $this->getMockBuilder('\Drupal\Core\Condition\ConditionPluginBase')
                      ->disableOriginalConstructor()
                      ->getMock();

    $this->conditionManager->expects($this->any())
      ->method('createInstance')
      ->with('request_path')
      ->will($this->returnValue($condition));
    $condition->expects($this->any())
      ->method('setConfiguration')
      ->with(array('<front>'));
    $this->conditionManager->expects($this->any())
      ->method('execute')
      ->with($condition)
      ->will($this->onConsecutiveCalls(FALSE, TRUE));

    $request_object->expects($this->once())
      ->method('isMethod')
      ->with('GET')
      ->will($this->returnValue(TRUE));

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));

    $this->casHelper->expects($this->once())
      ->method('getServerLoginUrl')
      ->will($this->returnValue('https://example.com'));

    $this->event->expects($this->once())
      ->method('setResponse');
    $cas_subscriber->handle($this->event);
    $this->assertArrayHasKey('cas_gateway_checked', $_SESSION);
  }
}
