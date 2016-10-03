<?php

namespace Netglue\MoneyTest;

use Netglue\Money\BoeRateService as Service;
use Netglue\Money\BoeRateClient as Client;
use DateTime;

class BoeRateServiceTest extends \PHPUnit_Framework_TestCase
{

    private $service;

    public function setUp()
    {
        $rates = [
            [
                'date' => DateTime::createFromFormat('YmdHi', 201601010000),
                'rate' => 1.0,
            ],
            [
                'date' => DateTime::createFromFormat('YmdHi', 201601020000),
                'rate' => 2.0,
            ],
            [
                'date' => DateTime::createFromFormat('YmdHi', 201601030000),
                'rate' => 3.0,
            ],
            [
                'date' => DateTime::createFromFormat('YmdHi', 201601040000),
                'rate' => 4.0,
            ],
            [
                'date' => DateTime::createFromFormat('YmdHi', 201601050000),
                'rate' => 5.0,
            ],
        ];
        $this->service = new Service($rates);
    }

    public function testFirstDate()
    {
        $d = $this->service->firstDate();
        $this->assertInstanceOf('DateTime', $d);
        $this->assertSame('20160101', $d->format('Ymd'));
    }

    public function testLastDate()
    {
        $d = $this->service->lastDate();
        $this->assertInstanceOf('DateTime', $d);
        $this->assertSame('20160105', $d->format('Ymd'));
    }

    public function testGetRateReturnsLatestWithoutArgs()
    {
        $this->assertSame(5.0, $this->service->getRate());
    }

    public function testExpectedRateForDate()
    {
        $d = DateTime::createFromFormat('YmdHi', 201601030000);
        $this->assertSame(3.0, $this->service->getRate($d));
    }

    /**
     * @expectedException OutOfRangeException
     */
    public function testExceptionThrownForOutOfRangeDate()
    {
        $d = DateTime::createFromFormat('YmdHi', 201501010000);
        $this->service->getRate($d);
    }

    public function testToJson()
    {
        $data = $this->service->toJson('Ymd');
        $rates = json_decode($data, true);
        $this->assertCount(5, $rates);
        foreach ($rates as $rate) {
            $this->assertInternalType('string', $rate['date']);
            $this->assertTrue(is_numeric($rate['rate']));
        }
    }

    public function testToJsonAndJsonFactoryReturnExpectedData()
    {
        $json = $this->service->toJson();
        $service = $this->service::jsonFactory($json);
        $this->assertSame('20160101', $service->firstDate()->format('Ymd'));
        $this->assertSame('20160105', $service->lastDate()->format('Ymd'));
    }

    public function testJsonFactoryAcceptsCustomDateFormat()
    {
        $json = $this->service->toJson('d/m/Y');
        $service = $this->service::jsonFactory($json, 'd/m/Y');
        $this->assertSame('20160101', $service->firstDate()->format('Ymd'));
        $this->assertSame('20160105', $service->lastDate()->format('Ymd'));
    }

    public function testEmptyRateOrDateIsSkipped()
    {
        $json = json_encode([
            [
                'date' => null,
                'rate' => null,
            ],
            [
                'date' => null,
                'rate' => 5.1
            ],
            [
                'date' => '2016-01-01',
                'rate' => null
            ],
            [
                'date' => '2016-01-11',
                'rate' => 1.1
            ],
        ]);
        $service = $this->service::jsonFactory($json);
        $this->assertSame('20160111', $service->firstDate()->format('Ymd'));
        $this->assertSame('20160111', $service->lastDate()->format('Ymd'));
    }

    /**
     * @group slow
     */
    public function testFixtureDataIsSane()
    {
        $client = $this->getMockBuilder(Client::class)
            ->setMethods(['getRateFile'])
            ->getMock();

        $xml = file_get_contents(__DIR__ . '/../data/BOE-Base-Rates.xml');

        $client->method('getRateFile')
            ->will($this->returnValue($xml));

        $service = new Service($client->get());
        $first = $service->firstDate();
        $last = $service->lastDate();
        $this->assertSame('19750102', $first->format('Ymd'));
        $this->assertSame('20160804', $last->format('Ymd'));
        $this->assertSame(0.25, $service->getRate());

        $date = DateTime::createFromFormat('Ymd', 20160501);
        $this->assertSame(0.5, $service->getRate($date));


    }

}
