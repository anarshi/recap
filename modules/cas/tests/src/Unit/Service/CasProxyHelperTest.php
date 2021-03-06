<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Service\CasProxyHelperTest.
 */

namespace Drupal\Tests\cas\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\cas\Service\CasProxyHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * CasHelper unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Service\CasProxyHelper
 */
class CasProxyHelperTest extends UnitTestCase {

  /**
   * Test proxy authentication to a service.
   *
   * @covers ::proxyAuthenticate
   * @covers ::getServerProxyURL
   * @covers ::parseProxyTicket
   *
   * @dataProvider proxyAuthenticateDataProvider
   */
  public function testProxyAuthenticate($target_service, $cookie_domain, $already_proxied) {
    // Set up the fake pgt in the session.
    $_SESSION['cas_pgt'] = $this->randomMachineName(24);

    // Set up properties so the http client callback knows about them.
    $cookie_value = $this->randomMachineName(24);

    if ($already_proxied) {
      // Set up the fake session data.
      $_SESSION['cas_proxy_helper'][$target_service][] = array(
        'Name' => 'SESSION',
        'Value' => $cookie_value,
        'Domain' => $cookie_domain,
      );

      $httpClient = new Client();
      $casHelper = $this->getMockBuilder('\Drupal\cas\Service\CasHelper')
                        ->disableOriginalConstructor()
                        ->getMock();
      $casProxyHelper = new CasProxyHelper($httpClient, $casHelper);

      $jar = $casProxyHelper->proxyAuthenticate($target_service);
      $cookie_array = $jar->toArray();
      $this->assertEquals('SESSION', $cookie_array[0]['Name']);
      $this->assertEquals($cookie_value, $cookie_array[0]['Value']);
      $this->assertEquals($cookie_domain, $cookie_array[0]['Domain']);
    }
    else {

      $proxy_ticket = $this->randomMachineName(24);
      $xml_response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
           <cas:proxySuccess>
             <cas:proxyTicket>PT-$proxy_ticket</cas:proxyTicket>
            </cas:proxySuccess>
         </cas:serviceResponse>";
      $mock = new MockHandler([
        new Response(200, [], $xml_response),
        new Response(200, ['Content-type' =>  'text/html', 'Set-Cookie' => 'SESSION=' . $cookie_value]),
      ]);
      $handler = HandlerStack::create($mock);
      $httpClient = new Client(['handler' => $handler]);

      $casHelper = $this->getMockBuilder('\Drupal\cas\Service\CasHelper')
                        ->disableOriginalConstructor()
                        ->getMock();
      $casProxyHelper = new CasProxyHelper($httpClient, $casHelper);

      // The casHelper expects to be called for a few things.
      $casHelper->expects($this->once())
                ->method('getServerBaseUrl')
                ->will($this->returnValue('https://example.com/cas/'));
      $casHelper->expects($this->once())
                ->method('isProxy')
                ->will($this->returnValue(TRUE));


      $jar = $casProxyHelper->proxyAuthenticate($target_service);
      $this->assertEquals('SESSION', $_SESSION['cas_proxy_helper'][$target_service][0]['Name']);
      $this->assertEquals($cookie_value, $_SESSION['cas_proxy_helper'][$target_service][0]['Value']);
      $this->assertEquals($cookie_domain, $_SESSION['cas_proxy_helper'][$target_service][0]['Domain']);
      $cookie_array = $jar->toArray();
      $this->assertEquals('SESSION', $cookie_array[0]['Name']);
      $this->assertEquals($cookie_value, $cookie_array[0]['Value']);
      $this->assertEquals($cookie_domain, $cookie_array[0]['Domain']);
    }
  }

  /**
   * Provides parameters and return value for testProxyAuthenticate.
   *
   * @return array
   *   Parameters and return values.
   *
   * @see \Drupal\Tests\cas\Unit\Service\CasProxyHelperTest::testProxyAuthenticate
   */
  public function proxyAuthenticateDataProvider() {
    /* There are two scenarios that return successfully that we test here.
     * First, proxying a new service that was not previously proxied. Second,
     * a second request for a service that has already been proxied.
     */
    return array(
      array('https://example.com', 'example.com', FALSE),
      array('https://example.com', 'example.com', TRUE),
    );
  }

  /**
   * Test the possible exceptions from proxy authentication.
   *
   * @covers ::proxyAuthenticate
   * @covers ::getServerProxyURL
   * @covers ::parseProxyTicket
   *
   * @dataProvider proxyAuthenticateExceptionDataProvider
   */
  public function testProxyAuthenticateException($is_proxy, $pgt_set, $target_service, $response, $client_exception, $exception_type, $exception_message) {
    if ($pgt_set) {
      // Set up the fake pgt in the session.
      $_SESSION['cas_pgt'] = $this->randomMachineName(24);
    }
    // Set up properties so the http client callback knows about them.
    $cookie_value = $this->randomMachineName(24);

    $casHelper = $this->getMockBuilder('\Drupal\cas\Service\CasHelper')
                      ->disableOriginalConstructor()
                      ->getMock();

    $casHelper->expects($this->any())
              ->method('getServerBaseUrl')
              ->will($this->returnValue('https://example.com/cas/'));
    $casHelper->expects($this->any())
              ->method('isProxy')
              ->will($this->returnValue($is_proxy));

    if ($client_exception == 'server') {
      $code = 404;
    }
    else {
      $code = 200;
    }
    if ($client_exception == 'client') {
      $secondResponse = new Response(404);
    }
    else {
      $secondResponse = new Response(200, ['Content-type' => 'text/html', 'Set-Cookie' => 'SESSION=' . $cookie_value]);
    }
    $mock = new MockHandler([new Response($code, [], $response), $secondResponse]);
    $handler = HandlerStack::create($mock);
    $httpClient = new Client(['handler' => $handler]);

    $casProxyHelper = new CasProxyHelper($httpClient, $casHelper);
    $this->setExpectedException($exception_type, $exception_message);
    $jar = $casProxyHelper->proxyAuthenticate($target_service);

  }

  /**
   * Provides parameters and exceptions for testProxyAuthenticateException.
   *
   * @return array
   *   Parameters and exceptions.
   *
   * @see \Drupal\Tests\cas\Unit\Service\CasProxyHelperTest::testProxyAuthenticateException
   */
  public function proxyAuthenticateExceptionDataProvider() {
    $target_service = 'https://example.com';
    $exception_type = '\Drupal\cas\Exception\CasProxyException';
    // Exception case 1: not configured as proxy.
    $params[] = array(FALSE, TRUE, $target_service, '', FALSE, $exception_type,
      'Session state not sufficient for proxying.');

    // Exception case 2: session pgt not set.
    $params[] = array(TRUE, FALSE, $target_service, '',  FALSE, $exception_type,
      'Session state not sufficient for proxying.');

    // Exception case 3: http client exception from proxy app.
    $proxy_ticket = $this->randomMachineName(24);
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:proxySuccess>
          <cas:proxyTicket>PT-$proxy_ticket</cas:proxyTicket>
        </cas:proxySuccess>
      </cas:serviceResponse>";

    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      'client',
      $exception_type,
      '',
    );

    // Exception case 4: http client exception from CAS Server.
    $proxy_ticket = $this->randomMachineName(24);
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:proxySuccess>
          <cas:proxyTicket>PT-$proxy_ticket</cas:proxyTicket>
        </cas:proxySuccess>
      </cas:serviceResponse>";

    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      'server',
      $exception_type,
      '',
    );

    // Exception case 5: non-XML response from CAS server.
    $response = "<> </> </ <..";
    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      FALSE,
      $exception_type,
      'CAS Server returned non-XML response.',
    );

    // Exception case 6: CAS Server rejected ticket.
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:proxyFailure code=\"INVALID_REQUEST\">
           'pgt' and 'targetService' parameters are both required
         </cas:proxyFailure>
       </cas:serviceResponse>";
    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      FALSE,
      $exception_type,
      'CAS Server rejected proxy request.',
    );

    // Exception case 7: Neither proxyFailure nor proxySuccess specified.
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
         <cas:proxy code=\"INVALID_REQUEST\">
         </cas:proxy>
       </cas:serviceResponse>";
    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      FALSE,
      $exception_type,
      'CAS Server returned malformed response.',
    );

    // Exception case 8: Malformed ticket.
    $response = "<cas:serviceResponse xmlns:cas='http://example.com/cas'>
        <cas:proxySuccess>
        </cas:proxySuccess>
       </cas:serviceResponse>";
    $params[] = array(
      TRUE,
      TRUE,
      $target_service,
      $response,
      FALSE,
      $exception_type,
      'CAS Server provided invalid or malformed ticket.',
    );

    return $params;
  }

}
