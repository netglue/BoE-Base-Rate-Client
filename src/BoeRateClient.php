<?php
/**
 * A client that retrieves the bank of england base rate from http://www.bankofengland.co.uk/
 */

// All available dates
// ?Travel=NIxRPxSUx&FromSeries=1&ToSeries=50&DAT=ALL&VFD=Y&CSVF=TT&C=13T&Filter=N&xml.x=1&xml.y=1
// Specific Range of dates
// ?Travel=NIxRPxSUx&FromSeries=1&ToSeries=50&DAT=RNG&FD=1&FM=Jan&FY=1998&TD=1&TM=Sep&TY=2013&VFD=N&xml.x=22&xml.y=26&CSVF=TT&C=13T&Filter=N

/**
 * Rates are delivered daily except bank holidays and weekends
 * First and last observations are always included at the same depth as rate data:
 * <Cube FIRST_OBS="1975-01-02" LAST_OBS="2013-09-04"/>
 */

namespace Netglue\Money;

use Zend\Http\Client as HttpClient;
use Zend\Http\Request as HttpRequest;
use DateTime;
use DateTimezone;
use XMLReader;

class BoeRateClient
{

    /**
     * The end point of the BoE Website where we can get rate information
     *
     * @var string
     */
    private $endpoint = 'http://www.bankofengland.co.uk/boeapps/iadb/fromshowcolumns.asp';

    /**
     * Zend Http Client
     *
     * @var HttpClient
     */
    private $client;

    /**
     * Timezone for Rates
     *
     * @var DateTimezone
     */
    private $tz;

    /**
     * URL to retrieve rates
     *
     * @var string|null
     */
    private $url;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(HttpClient $client = null)
    {
        if ($client) {
            $this->setHttpClient($client);
        }
        $this->tz = new DateTimezone('Europe/London');
    }

    /**
     * Set Http Client
     *
     * @param  HttpClient $client
     * @return void
     */
    public function setHttpClient(HttpClient $client)
    {
        $this->client = $client;
    }

    /**
     * Return Http Client
     *
     * @return HttpClient
     */
    public function getHttpClient()
    {
        if (!$this->client) {
            $this->client = new HttpClient;
        }
        return $this->client;
    }

    /**
     * Set/Override the url used to retrieve the rates
     *
     * @param  string $url
     * @return void
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
    }

    /**
     * Return URL used to get rates. Only populated if a request has been sent, or the url has been overriden
     *
     * @return string|null
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Return rates as an array
     *
     * @param  DateTime $from Optional from date
     * @param  DateTime $to   Optional to date
     * @return array
     */
    public function get(DateTime $from = null, DateTime $to = null)
    {
        return $this->ratesToArray($this->getRateFile($from, $to));
    }

    /**
     * Traverses the given XML string in the format expected from the BoE Website and returns an array
     *
     * @param  string $xml
     * @return array
     */
    private function ratesToArray(string $xml)
    {
        $reader = new XMLReader;
        $reader->XML($xml);
        $rates = array();
        $last  = null;
        while ($reader->read()) {
            if ($reader->nodeType === XmlReader::ELEMENT) {
                if ($reader->name === 'Cube' && $reader->getAttribute('TIME')) {
                    $rate = (float) $reader->getAttribute('OBS_VALUE');
                    // Skip days where the rate did not change
                    if ($rate === $last) {
                        continue;
                    }
                    $last = $rate;
                    $date = new DateTime($reader->getAttribute('TIME'), $this->tz);
                    $key  = (int) $date->format("Ymd");
                    $rates[$key] = [
                        'date' => $date,
                        'rate' => $rate
                    ];
                }
            }
        }

        return $rates;
    }

    /**
     * Return XML from the remote optionally between two specific dates
     *
     * @param  DateTime $from
     * @param  DateTime $to
     * @return string
     */
    private function getRateFile(DateTime $from = null, DateTime $to = null)
    {
        $client = $this->getHttpClient();
        $client->reset();

        $url    = $this->url;
        $params = [];

        if (!$url) {
            $url = $this->endpoint;
            $params = $this->getUrlParams($from, $to);
        }

        $client->setUri($url);

        $client->setParameterGet($params);
        $client->setMethod(HttpRequest::METHOD_GET);
        $response = $client->send();

        // Update the requested url
        $this->url = (string) $client->getUri();

        // The remote returns text/html with status 200 on error
        $contentType = $response->getHeaders()->get('contenttype');
        $contentType = $contentType->toString();
        if (strpos(strtolower($contentType), 'xml') === false) {
            throw new \RuntimeException("The remote website did not return XML");
        }

        return $response->getBody();
    }

    /**
     * Return an array of parameters used in the GET request to the BOE site based on the given dates
     *
     * @param  DateTime $from
     * @param  DateTime $to
     * @return array
     */
    private function getUrlParams(DateTime $from = null, DateTime $to = null)
    {
        $params = [
            'Travel' => 'NIxRPxSUx',
            'FromSeries' => '1',
            'ToSeries' => '50',
            'VFD' => 'N',
            'xml.x' => 1,
            'xml.y' => 1,
            'CSVF' => 'TT',
            'C' => '13T',
            'Filter' => 'N',
        ];
        // No $to? Assume $from -> now()
        if (null !== $from && null === $to) {
            $to = new DateTime;
        }
        // No $from? Return all available data
        if (null === $from) {
            $params = array_merge($params, ['DAT' => 'ALL']);
        } else {
            // Otherwise populate date parameters accordingly after checking date validity
            if ($from > $to) {
                throw new \InvalidArgumentException("The from date cannot be after the to date");
            }
            $params = array_merge(
                $params,
                [
                    'DAT' => 'RNG',
                    'FD' => $from->format("j"),
                    'FM' => $from->format("M"),
                    'FY' => $from->format("Y"),
                    'TD' => $to->format("j"),
                    'TM' => $to->format("M"),
                    'TY' => $to->format("Y"),
                ]
            );
        }
        return $params;
    }


}
