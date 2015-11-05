<?php
namespace PromisePay\Tests;

use Promisepay\PromisePay;
use Promisepay\Exception;

class PromisePayTest extends \PHPUnit_Framework_TestCase {
    
    public function testConstantsDefined() {
        $this->assertTrue(defined('PromisePay\API_LOGIN'));
        $this->assertTrue(defined('PromisePay\API_PASSWORD'));
        $this->assertTrue(defined('PromisePay\API_URL'));
        $this->assertTrue(defined('PromisePay\API_KEY'));
    }
    
    public function testGetRequestMethod() {
        $request = PromisePay::RestClient('get', 'items/');
        
        $this->assertEquals($request->content_type, 'application/json');
        $this->assertNotNull(json_decode($request->raw_body)); // json_decode() returns null on invalid JSON, not false
        $this->assertEquals($request->request->username, constant('PromisePay\API_LOGIN'));
        $this->assertEquals($request->request->password, constant('PromisePay\API_PASSWORD'));
    }
    
    /**
     * @expectedException PromisePay\Exception\ApiUnsupportedRequestMethod
     */
    public function testInvalidRequestMethod() {
        PromisePay::RestClient('commit', 'items/10');
    }
    
}
