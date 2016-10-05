<?php

namespace Netglue\Money;

use DateTime;
use Zend\Stdlib\SplPriorityQueue;

class BoeRateService extends SplPriorityQueue
{

    /**
     * Construct with rate data as retrieved by the BoeRateClient
     *
     * @param array $rates
     */
    public function __construct(array $rates)
    {
        foreach ($rates as $rate) {
            if ($rate['date'] instanceof DateTime) {
                $this->insert($rate, $rate['date']->getTimestamp());
            }
        }
    }

    /**
     * Return the date of the first rate available
     *
     * @return DateTime
     */
    public function firstDate()
    {
        $rates = $this->toArray();
        $d = end($rates)['date'];
        return $d;
    }

    /**
     * Return the most recent rate change date available
     *
     * @return DateTime
     */
    public function lastDate()
    {
        $q = (clone $this);
        $q->setExtractFlags(self::EXTR_DATA);
        return $q->current()['date'];
    }

    /**
     * Serialize the rate data using the given date format string
     *
     * @param  string $dateFormat
     * @return string
     */
    public function toJson(string $dateFormat = 'c')
    {
        $q = (clone $this);
        $q->setExtractFlags(self::EXTR_DATA);
        $out = $q->toArray();
        array_walk($out, function(&$data) use ($dateFormat) {
            $data['date'] = $data['date']->format($dateFormat);
        });
        return json_encode($out);
    }

    /**
     * Return a new service from a json data string
     *
     * @param string $json
     * @param string $dateFormat
     * @return BoeRateService
     */
    public static function jsonFactory(string $json, string $dateFormat = null)
    {
        $data = json_decode($json, true);
        $rates = [];
        foreach($data as $set) {
            $rate = isset($set['rate']) ? (float) $set['rate'] : null;
            $date = isset($set['date']) ? (string) $set['date']: null;
            if (!$rate || !$date) {
                continue;
            }
            $date = $dateFormat
                  ? DateTime::createFromFormat($dateFormat, $date)
                  : new DateTime($date);
            $rates[] = [
                'rate' => $rate,
                'date' => $date,
            ];
        }

        return new static($rates);
    }

    /**
     * Return the most recent rate from the data set, or the rate on the specified date
     *
     * @param  DateTime $date
     * @return float
     */
    public function getRate(DateTime $date = null) : float
    {
        if (!$date) {
            return $this->top()['rate'];
        }

        if ($date > $this->lastDate() || $date < $this->firstDate()) {
            throw new \OutOfRangeException(sprintf(
                'The given date %s is not within the range of the data set starting %s and ending %s',
                $date->format('Y-m-d'),
                $this->firstDate()->format('Y-m-d'),
                $this->lastDate()->format('Y-m-d')
            ));
        }

        $rates = array_filter($this->toArray(), function($rate) use ($date) {
            return $rate['date'] <= $date;
        });

        return current($rates)['rate'];
    }





}
