<?php

namespace Rcalicdan\SmsApi\Tests;

use SmsApi;

class SmsApiTest extends AbstractTestCase
{

  /**
   * SendMessage Test
   *
   * @return void
   */
  public function testSendMessageResponse()
  {
      $response = SmsApi::sendMessage("9999999999", "Hi")->response();
      $this->assertNotEmpty($response,"Response is empty.");
  }

  //TODO: Add more tests (https://github.com/Rcalicdan/laravel-sms-api/issues/3)
}
