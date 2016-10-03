<?php

namespace Netglue\MoneyTest;

use Netglue\Money\BoeRateClient as Client;
use Netglue\Money\BoeRateService as Service;
use Zend\Http\Client as HttpClient;
use DateTime;
use Zend\Stdlib\SplPriorityQueue;

class BoeRateClientTest extends \PHPUnit_Framework_TestCase
{

    public function testOverrideHttpClientInConstruct()
    {
        $client = new Client;
        $this->assertInstanceOf(HttpClient::class, $client->getHttpClient());

        $http = new HttpClient;
        $client = new Client($http);
        $this->assertSame($http, $client->getHttpClient());
    }

    public function testSetGetUrl()
    {
        $client = new Client;
        $this->assertNull($client->getUrl());

        $client->setUrl('foo');
        $this->assertSame('foo', $client->getUrl());
    }

    /**
     * @group slow
     */
    public function testDefaultRetrievalReturnsExpectedData()
    {
        $client = new Client;
        $rates = $client->get();
        $this->assertInternalType('array', $rates);
        $this->assertTrue(count($rates) >= 1);
        foreach($rates as $rate) {
            $this->assertInstanceOf('DateTime', $rate['date']);
            $this->assertInternalType('float', $rate['rate']);
        }
    }

    /**
     * @group slow
     * @expectedException RuntimeException
     * @expectedExceptionMessage The remote website did not return XML
     */
    public function testNonXmlResponseTriggersException()
    {
        $client = new Client;
        $client->setUrl('http://example.com');
        $rates = $client->get();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The from date cannot be after the to date
     */
    public function testInvalidDateRangeCausesException()
    {
        $client = new Client;
        $client->get(new DateTime, new DateTime('-2 days'));
    }

    /**
     * @group slow
     */
    public function testDateRangeRetrievesExpectedData()
    {
        $client = new Client;
        $from   = DateTime::createFromFormat('Ymd', '19750102');
        $to     = DateTime::createFromFormat('Ymd', '19750116');
        $data   = $client->get($from, $to);
        $this->assertInternalType('array', $data);
        $service = new Service($data);

        $first = $service->firstDate();
        $this->assertSame(11.5, $service->getRate());
        $this->assertSame('19750102', $first->format('Ymd'));
    }



}
